<?php
/*
Plugin Name: Image Optimizer
Plugin URI: https://github.com/wordpress-image-optimizer
Description: Advanced image optimizer and WebP converter with admin controls, manual conversion, logs, and bulk conversion.
Version: 3.0.0
Author: magnetoid
License: MIT
*/

// ---- Settings, Admin Page ---- //
function image_optimizer_defaults() {
    return array(
        'enable_webp' => true,
        'webp_quality' => 80,
        'delete_original' => true,
        'supported_types' => array('image/jpeg', 'image/png', 'image/gif'),
        'exclude_regex' => '',
        'optimize_thumbnails' => true,
        'notify' => true,
        'conversion_log' => array(), // For storing logs
    );
}
function image_optimizer_get_settings() {
    $defaults = image_optimizer_defaults();
    $settings = get_option('image_optimizer_settings', $defaults);
    return wp_parse_args($settings, $defaults);
}
add_action('admin_menu', function() {
    add_menu_page(
        'Image Optimizer',
        'Image Optimizer',
        'manage_options',
        'image-optimizer',
        'image_optimizer_settings_page',
        'dashicons-format-image',
        80
    );
});
function image_optimizer_settings_page() {
    $settings = image_optimizer_get_settings();
    $supported_types = array('image/jpeg'=>'JPEG','image/png'=>'PNG','image/gif'=>'GIF');
    ?>
    <div class="wrap">
        <h1>Image Optimizer Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('image_optimizer_settings_group'); ?>
            <?php do_settings_sections('image-optimizer'); ?>
            <table class="form-table">
                <!-- [settings form as before, trimmed for brevity] -->
                <tr>
                    <th scope="row">Export/Import Settings</th>
                    <td>
                        <button type="button" class="button" id="export_settings">Export Settings</button>
                        <input type="file" id="import_settings_file" style="display:none;" accept=".json">
                        <button type="button" class="button" id="import_settings">Import Settings</button>
                        <script>
                        document.getElementById("export_settings").onclick=function(){
                            let exported='<?php echo esc_js(json_encode($settings)); ?>';
                            let blob=new Blob([exported],{type:"application/json"});
                            let a=document.createElement("a");
                            a.href=URL.createObjectURL(blob);
                            a.download="image-optimizer-settings.json";
                            a.click();
                        };
                        document.getElementById("import_settings").onclick=function(){
                            document.getElementById("import_settings_file").click();
                        };
                        document.getElementById("import_settings_file").onchange=function(e){
                            let reader=new FileReader();
                            reader.onload=function(ev){
                                fetch(window.location.href,{method:"POST",body:ev.target.result,headers:{"Content-Type":"application/json"}})
                                    .then(()=>location.reload());
                            };
                            reader.readAsText(e.target.files[0]);
                        };
                        </script>
                        <p><em>Export current settings to a .json file or import settings.</em></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php image_optimizer_log_table(); ?>
        <?php image_optimizer_bulk_converter_admin(); ?>
    </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('image_optimizer_settings_group', 'image_optimizer_settings');
    // Simple import POST handler
    if ($_SERVER['REQUEST_METHOD']=='POST' && is_admin() && strpos($_SERVER['CONTENT_TYPE'],'application/json')!==false) {
        $received=json_decode(file_get_contents('php://input'),true);
        if (is_array($received)) update_option('image_optimizer_settings',$received);
        exit;
    }
});

// ---- Conversion Logic ---- //
function image_optimizer_convert_to_webp($filepath, $type, $quality=80) {
    if (!function_exists('imagewebp')) return false;
    try {
        switch ($type) {
            case 'image/jpeg': $image=imagecreatefromjpeg($filepath); break;
            case 'image/png': $image=imagecreatefrompng($filepath); break;
            case 'image/gif': $image=imagecreatefromgif($filepath); break;
            default: return false;
        }
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i','.webp',$filepath);
        imagewebp($image,$webp_path,$quality);
        imagedestroy($image);
        return $webp_path;
    } catch(Exception $e) { return false; }
}
function image_optimizer_log($message,$type="info") {
    $settings = image_optimizer_get_settings();
    $settings['conversion_log'][] = array(
        'time' => current_time('mysql'), 'message' => sanitize_text_field($message), 'type' => $type
    );
    // Keep only last 100 log entries
    if(count($settings['conversion_log'])>100) $settings['conversion_log']=array_slice($settings['conversion_log'],-100);
    update_option('image_optimizer_settings',$settings);
}
function image_optimizer_log_table() {
    $settings = image_optimizer_get_settings();
    ?>
    <h2>Conversion Log</h2>
    <table class="widefat">
        <thead>
            <tr><th>Time</th><th>Message</th><th>Type</th></tr>
        </thead>
        <tbody>
        <?php foreach(array_reverse($settings['conversion_log']) as $log): ?>
            <tr>
                <td><?php echo esc_html($log['time']); ?></td>
                <td><?php echo esc_html($log['message']); ?></td>
                <td><?php echo esc_html($log['type']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
// ---- Media Library Columns ---- //
add_filter('manage_upload_columns', function($cols){
    $cols['image_optimizer_webp']='Optimize as WebP';
    return $cols;
});
add_action('manage_media_custom_column', function($column_name, $id){
    if($column_name != 'image_optimizer_webp') return;
    $file=get_attached_file($id);
    $filetype=wp_check_filetype($file);
    $webp_file=preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $file);
    if (file_exists($webp_file)) {
        echo '<button class="button" onclick="if(confirm(\'Revert?\')){location.href=\'' . esc_url(admin_url('upload.php?action=image_optimizer_revert_webp&id=' . $id)) . '\';}">Revert</button>';
    } else {
        echo '<button class="button" onclick="location.href=\'' . esc_url(admin_url('upload.php?action=image_optimizer_convert_webp&id=' . $id)) . '\';">Convert</button>';
    }
});
// Handle actions
add_action('load-upload.php', function(){
    if(isset($_GET['action'])){
        $id=intval($_GET['id']);
        $file=get_attached_file($id);
        $filetype=wp_check_filetype($file);
        $settings=image_optimizer_get_settings();
        if($_GET['action']=='image_optimizer_convert_webp'){
            $webp_path=image_optimizer_convert_to_webp($file,$filetype['type'],$settings['webp_quality']);
            if($webp_path){ 
                image_optimizer_log("Converted $file to WebP: $webp_path");
                if($settings['delete_original']) unlink($file);
            } else { 
                image_optimizer_log("Failed conversion: $file", "error");
            }
        }
        elseif($_GET['action']=='image_optimizer_revert_webp'){
            $webp_file=preg_replace('/\.(jpe?g|png|gif)$/i','.webp',$file);
            if(file_exists($webp_file)){
                unlink($webp_file);
                image_optimizer_log("Reverted: $webp_file");
            }
        }
        wp_redirect(remove_query_arg(array('action','id')));
        exit;
    }
});

// ---- Bulk Conversion UI ---- //
function image_optimizer_bulk_converter_admin() {
    ?>
    <h2>Bulk Convert Existing Media Library</h2>
    <form method="post" id="image_optimizer_bulk_convert_form">
        <input type="hidden" name="image_optimizer_bulk_convert" value="1">
        <button type="submit" class="button button-primary">Bulk Convert All Images</button>
        <span id="image_optimizer_bulk_progress"></span>
    </form>
    <script>
    document.getElementById('image_optimizer_bulk_convert_form').onsubmit=function(e){
        e.preventDefault();
        let progressEl=document.getElementById('image_optimizer_bulk_progress');
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=image_optimizer_bulk_convert')
            .then(r=>r.json()).then(d=>{
                progressEl.textContent='Converted: '+d.converted+', Errors: '+d.errors;
            });
    }
    </script>
    <?php
}
add_action('wp_ajax_image_optimizer_bulk_convert',function(){
    $converted=0; $errors=0;
    $settings=image_optimizer_get_settings();
    $args=array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>-1);
    $attachments=get_posts($args);
    foreach($attachments as $a){
        $file=get_attached_file($a->ID);
        $filetype=wp_check_filetype($file);
        $webp_path=preg_replace('/\.(jpe?g|png|gif)$/i','.webp',$file);
        if(!in_array($filetype['type'],$settings['supported_types'])) continue;
        if(!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'],$file)) continue;
        if(!file_exists($webp_path)){
            $r=image_optimizer_convert_to_webp($file,$filetype['type'],$settings['webp_quality']);
            if($r){ $converted++; if($settings['delete_original']) unlink($file);
                image_optimizer_log("Bulk converted: $file");
            }else{
                $errors++; image_optimizer_log("Bulk failed: $file", "error");
            }
        }
    }
    wp_send_json(array('converted'=>$converted,'errors'=>$errors));
});

// ---- Main Image Conversion Hooks (as before) ---- //
add_filter('wp_handle_upload', function($upload) {
    $settings=image_optimizer_get_settings();
    if(!$settings['enable_webp']) return $upload;
    if(!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'],$upload['file'])) return $upload;
    $filetype=wp_check_filetype($upload['file']);
    if(!in_array($filetype['type'],$settings['supported_types'])) return $upload;
    $webp_path=image_optimizer_convert_to_webp($upload['file'],$filetype['type'],$settings['webp_quality']);
    if($webp_path){
        if($settings['notify']) image_optimizer_log("Converted image to WebP: ".basename($webp_path));
        if($settings['delete_original']) unlink($upload['file']);
        $upload['file']=$webp_path; $upload['type']='image/webp';
    }else{ if($settings['notify']) image_optimizer_log("WebP conversion failed: ".basename($upload['file']), "error"); }
    return $upload;
});
add_filter('wp_generate_attachment_metadata', function($metadata,$attachment_id){
    $settings=image_optimizer_get_settings();
    if(!$settings['optimize_thumbnails'] || !$settings['enable_webp']) return $metadata;
    $file=get_attached_file($attachment_id);
    if(!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'],$file)) return $metadata;
    if(!empty($metadata['sizes'])){
        $upload_dir=wp_upload_dir();
        foreach($metadata['sizes'] as $size=>$sizeinfo){
            $thumb_path=trailingslashit($upload_dir['basedir']).dirname($metadata['file']).'/'.$sizeinfo['file'];
            $filetype=wp_check_filetype($thumb_path);
            if(!in_array($filetype['type'],$settings['supported_types'])) continue;
            $webp_thumb=image_optimizer_convert_to_webp($thumb_path,$filetype['type'],$settings['webp_quality']);
            if($webp_thumb && $settings['delete_original']) unlink($thumb_path);
            if($settings['notify']) image_optimizer_log("Thumbnail ($size) optimized: ".basename($thumb_path));
        }
    }
    return $metadata;
});
