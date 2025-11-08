# Magnetoid Image Optimizer

**Advanced WordPress plugin for optimizing images and converting to WebP format with powerful admin features.**

## Features

- Converts JPEG, PNG, and GIF images to `.webp` on upload.
- Adjustable WebP quality (0-100).
- Choose to keep or delete originals.
- Exclude folders using regex.
- Optimize thumbnails and generated image sizes.
- Notification log for conversions and errors (viewable in admin).
- Manual conversion: turn any media library image into WebP; revert back if needed.
- Bulk convert all images in the media library, with a progress bar.
- Export/import plugin settings to/from JSON.

## How It Works

### Conversion Per Upload
When you upload a supported image, it’s immediately converted to WebP using the PHP GD extension. You can configure the quality and whether to keep or delete the original file.

### Thumbnails and Sizes
If enabled, all generated image sizes are also optimized to WebP.

### Exclusion
Any files matching an exclusion regex will be skipped.

### Log and Notifications
Conversion events and errors are logged and can be viewed from the plugin admin page.

### Manual Conversion
From the media library (‘list’ view), you can manually convert any single image or revert a `.webp` image back to its original.

### Bulk Conversion
On the plugin settings/admin page, you can convert all supported media library files to WebP. Progress and results are shown live.

### Export/Import Settings
You can export your plugin settings to a `.json` file, or import them on a new site or setup.

---

## Requirements

- WordPress 5.0+
- PHP GD extension **with WebP support** (check with your hosting provider).

## Installation

1. Upload `magnetoid-image-optimizer.php` to `/wp-content/plugins/`.
2. Activate the plugin in the admin panel.
3. Go to **"Image Optimizer"** in the left menu to configure settings and manage images.

## FAQ

**How do I bulk convert all images?**  
Go to the plugin admin page and click **"Bulk Convert All Images"**.

**How are conversions logged?**  
All activity is shown in the "Conversion Log" table on the plugin admin page.

**Can I revert a WebP image?**  
Yes, use the "Revert" button for any WebP image in the Media Library.

**How do I export/import settings?**  
Use the "Export/Import Settings" section in the plugin admin.

**Will my uploads break if WebP is not supported?**  
If the server’s PHP GD does not have WebP support, conversion will be skipped and logged as an error.

## Advanced Usage

- Regex exclude allows for skipping folders or filenames (e.g. `/private-folder/`).
- Manual conversions or reverts from the media library are safe and instant.
- Thumbnail optimization covers all standard WordPress image sizes.

---

## Changelog

### 3.0.0
- Admin log, manual convert/revert, bulk conversion UI, export/import settings.

### 2.0.0
- Exclusions, notifications, thumbnail optimization, supported types toggle.

### 1.0.0
- Initial release.

---

**MIT License.**
