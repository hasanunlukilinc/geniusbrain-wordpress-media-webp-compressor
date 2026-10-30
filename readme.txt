=== GeniusBrain - Media WebP Compressor ===
Contributors: geniusbrain
Tags: webp, image optimization, compress images, convert to webp, media library
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert JPEG, PNG and GIF images to WebP on upload and in bulk. Free, no account, no external service.

== Description ==

GeniusBrain - Media WebP Compressor converts the images in your Media Library to WebP so your pages load faster.

* **Automatic on upload:** every new JPEG, PNG and GIF is converted to WebP, including all thumbnail sizes.
* **Bulk conversion:** convert your existing library with one click. It runs in small batches and continues where it stopped if you leave the page.
* **Keep or delete originals:** keep the original files (default) and restore any image from the Media Library list, or delete them to save disk space.
* **Your old posts keep working:** images already in your posts and pages are served as WebP. When originals are deleted, links inside posts and pages are updated during bulk conversion.
* **Safe by default:** an image stays in its original format when the WebP copy would be larger, and animated GIFs are never converted.
* **Runs on your server:** uses the image library your host already has (GD or Imagick). No API key, no account, no external requests, no paid limits.

* **Adjustable quality:** choose a WebP quality from 1 to 100 in Settings → GeniusBrain - Media WebP Compressor. The default, 80, is a good balance between size and sharpness.

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the zip file.
2. Activate it.
3. Open Settings → GeniusBrain - Media WebP Compressor to set the quality and choose whether originals are kept, and click "Convert all images" to convert your existing library.

== Frequently Asked Questions ==

= My server cannot create WebP images. What happens? =

The settings page shows a warning and nothing is converted. Ask your host to enable WebP support in the GD or Imagick PHP extension.

= Can I go back to the original image? =

Yes, if "Keep the original file" was on when the image was converted. Use "Restore original" in the Media Library list view.

= What happens when I deactivate or delete the plugin? =

Converted images stay WebP and keep working. Deleting the plugin removes only its settings.

= Why was an image not converted? =

The WebP column in the Media Library list view shows the reason: the WebP copy was larger than the original, the GIF is animated, or the file is missing.

== Screenshots ==

1. Settings → GeniusBrain - Media WebP Compressor: quality, original file option and bulk conversion with progress.
2. Media Library list view with the WebP column and the Restore original action.

== Changelog ==

= 1.0.2 =
* The Settings menu item shows the full plugin name.

= 1.0.1 =
* Fix: palette PNG and GIF images on servers using GD produced empty WebP files. Every WebP file is now checked before it is used.
* Images affected in 1.0.0 are restored to their kept originals on update so they can be converted again.

= 1.0.0 =
* First release: WebP conversion on upload and in bulk, adjustable quality, keep or delete originals, restore originals, WebP column in the Media Library.
