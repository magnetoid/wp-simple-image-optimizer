<?php
/*
Plugin Name: Magnetoid Image Optimizer
Plugin URI: https://github.com/magnetoid/wordpress-image-optimizer
Description: Optimizes images uploaded to WordPress by converting them to WebP format. Includes advanced settings and bulk conversion.
Version: 2.0.0
Author: magnetoid
License: MIT
*/

// Default settings
function magnetoid_image_optimizer_defaults() {
    return array(
        'enable_webp' => true,
        'webp_quality' => 80,
        'delete_original' => true,
        'supported_types' => array('image/jpeg', 'image/png', 'image/gif'),
        'bulk_conversion' => false,
        'exclude_regex' => '',
        'optimize_thumbnails' => true,
        'notify' => true,
    );
}

function magnetoid_image_optimizer_get_settings() {
    $defaults = magnetoid_image_optimizer_defaults();
    $settings = get_option('magnetoid_image_optimizer_settings', $defaults);
    return wp_parse_args($settings, $defaults);
}

// Settings page
add_action('admin_menu', function() {
    add_options_page(
        'Magnetoid Image Optimizer',
        'Image Optimizer',
        'manage_options',
        'magnetoid-image-optimizer',
        'magnetoid_image_optimizer_settings_page'
    );
});

function magnetoid_image_optimizer_settings_page() {
    $settings = magnetoid_image_optimizer_get_settings();
    $supported_types = array('image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF');
    ?>
    <div class="wrap">
        <h1>Magnetoid Image Optimizer Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('magnetoid_image_optimizer_settings_group'); ?>
            <?php do_settings_sections('magnetoid-image-optimizer'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable WebP Conversion</th>
                    <td><input type="checkbox" name="magnetoid_image_optimizer_settings[enable_webp]" value="1" <?php checked($settings['enable_webp'], true); ?> /></td>
                </tr>
                <tr>
                    <th scope="row">WebP Quality</th>
                    <td><input type="number" name="magnetoid_image_optimizer_settings[webp_quality]" min="0" max="100" value="<?php echo esc_attr($settings['webp_quality']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Delete Original File After Conversion</th>
                    <td><input type="checkbox" name="magnetoid_image_optimizer_settings[delete_original]" value="1" <?php checked($settings['delete_original'], true); ?> /></td>
                </tr>
                <tr>
                    <th scope="row">Supported Image Types</th>
                    <td>
                        <?php foreach ($supported_types as $mime => $label): ?>
                        <label><input type="checkbox" name="magnetoid_image_optimizer_settings[supported_types][]" value="<?php echo $mime; ?>" <?php checked(in_array($mime, $settings['supported_types']), true); ?> /> <?php echo $label; ?></label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Optimize Resized Images (Thumbnails)</th>
                    <td><input type="checkbox" name="magnetoid_image_optimizer_settings[optimize_thumbnails]" value="1" <?php checked($settings['optimize_thumbnails'], true); ?> /></td>
                </tr>
                <tr>
                    <th scope="row">Regex to Exclude Folders (e.g. /exclude-this-folder/)</th>
                    <td><input type="text" name="magnetoid_image_optimizer_settings[exclude_regex]" value="<?php echo esc_attr($settings['exclude_regex']); ?>" size="40" /></td>
                </tr>
                <tr>
                    <th scope="row">Show Notifications on Conversion</th>
                    <td><input type="checkbox" name="magnetoid_image_optimizer_settings[notify]" value="1" <?php checked($settings['notify'], true); ?> /></td>
                </tr>
                <tr>
                    <th scope="row">Bulk Convert Existing Media Library (once)</th>
                    <td>
                        <input type="checkbox" name="magnetoid_image_optimizer_settings[bulk_conversion]" value="1" <?php checked($settings['bulk_conversion'], true); ?> />
                        <span style="color: #a00; font-size: small;">This runs ONCE when saved. Uncheck to disable.</span>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('magnetoid_image_optimizer_settings_group', 'magnetoid_image_optimizer_settings');
});

// Hook into uploads and metadata generation
add_filter('wp_handle_upload', 'magnetoid_image_optimizer_handle_upload');
add_filter('wp_generate_attachment_metadata', 'magnetoid_image_optimizer_optimize_thumbnails', 10, 2);

function magnetoid_image_optimizer_handle_upload($upload) {
    $settings = magnetoid_image_optimizer_get_settings();

    if (!$settings['enable_webp']) return $upload;

    if (!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'], $upload['file'])) return $upload;

    $filetype = wp_check_filetype($upload['file']);
    if (!in_array($filetype['type'], $settings['supported_types'])) return $upload;

    $webp_path = magnetoid_image_optimizer_convert_to_webp($upload['file'], $filetype['type'], $settings['webp_quality']);
    if ($webp_path) {
        // Notifications
        if ($settings['notify']) magnetoid_image_optimizer_notify('Converted image to WebP: ' . basename($webp_path));
        if ($settings['delete_original']) unlink($upload['file']);
        $upload['file'] = $webp_path;
        $upload['type'] = 'image/webp';
    } else {
        if ($settings['notify']) magnetoid_image_optimizer_notify('WebP conversion failed: ' . basename($upload['file']), true);
    }
    return $upload;
}

// Optimize resized images (thumbnails)
function magnetoid_image_optimizer_optimize_thumbnails($metadata, $attachment_id) {
    $settings = magnetoid_image_optimizer_get_settings();
    if (!$settings['optimize_thumbnails'] || !$settings['enable_webp']) return $metadata;
    $file = get_attached_file($attachment_id);

    if (!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'], $file)) return $metadata;

    if (!empty($metadata['sizes'])) {
        $upload_dir = wp_upload_dir();
        foreach ($metadata['sizes'] as $size => $sizeinfo) {
            $thumb_path = trailingslashit($upload_dir['basedir']) . dirname($metadata['file']) . '/' . $sizeinfo['file'];
            $filetype = wp_check_filetype($thumb_path);
            if (!in_array($filetype['type'], $settings['supported_types'])) continue;
            $webp_thumb = magnetoid_image_optimizer_convert_to_webp($thumb_path, $filetype['type'], $settings['webp_quality']);
            if ($webp_thumb && $settings['delete_original']) {
                unlink($thumb_path);
            }
            // Notification per thumbnail
            if ($settings['notify']) magnetoid_image_optimizer_notify('Thumbnail (' . $size . ') optimized: ' . basename($thumb_path));
        }
    }
    return $metadata;
}

// Conversion logic
function magnetoid_image_optimizer_convert_to_webp($filepath, $type, $quality=80) {
    if (!function_exists('imagewebp')) return false;
    try {
        switch ($type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filepath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filepath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filepath);
                break;
            default:
                return false;
        }
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $filepath);
        imagewebp($image, $webp_path, $quality);
        imagedestroy($image);
        return $webp_path;
    } catch (Exception $e) {
        return false;
    }
}

// Notification, displayed once per request in admin
function magnetoid_image_optimizer_notify($message, $error = false) {
    if (!is_admin()) return;
    add_action('admin_notices', function() use ($message, $error) {
        echo '<div class="' . ($error ? 'notice notice-error':'notice notice-success') . '"><p>' . esc_html($message) . '</p></div>';
    });
}

// Bulk conversion logic
add_action('admin_init', function() {
    $settings = magnetoid_image_optimizer_get_settings();
    if (!empty($settings['bulk_conversion'])) {
        magnetoid_image_optimizer_bulk_convert();
        // Disable after running once
        $settings['bulk_conversion'] = false;
        update_option('magnetoid_image_optimizer_settings', $settings);
    }
});

function magnetoid_image_optimizer_bulk_convert() {
    $settings = magnetoid_image_optimizer_get_settings();
    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
    );
    $attachments = get_posts($args);
    foreach ($attachments as $attachment) {
        $file = get_attached_file($attachment->ID);
        if (!$file || !file_exists($file)) continue;
        $filetype = wp_check_filetype($file);
        if (!in_array($filetype['type'], $settings['supported_types'])) continue;
        if (!empty($settings['exclude_regex']) && preg_match($settings['exclude_regex'], $file)) continue;
        $webp_path = magnetoid_image_optimizer_convert_to_webp($file, $filetype['type'], $settings['webp_quality']);
        if ($webp_path && $settings['delete_original']) unlink($file);
        if ($settings['notify']) magnetoid_image_optimizer_notify('Bulk-converted: ' . basename($file));
    }
}
