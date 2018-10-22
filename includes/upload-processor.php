<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Upload image processor
 * 
 * On file upload, before the file system processes the file to WP-CONTENT,
 * if file is an image:
 * - renames the file to absolutly url friendly name (like page slug)
 * - resizes to defined max sizes the original image
 * 
 * 
 * @param File_ressource $file
 * @uses ELD_IMAGE_MAX_WIDTH, ELD_IMAGE_MAX_HEIGHT, ELD_IMAGE_QUALITY
 * @see wp_handle_upload_prefilter Filter
 * @return File_ressource
 */
function eld_upload_processor($file) {
    
    $image_filetypes = array('image/gif', 'image/png', 'image/jpeg', 'image/jpg');

    // detect if image type
    if (in_array($file['type'], $image_filetypes)) {
        // sanitize image file name
        $file_name_parts = pathinfo($file['name']);
        $file['name'] = sanitize_title($file_name_parts['filename']) . '.' . $file_name_parts['extension'];
        // resize image
        $image_editor = wp_get_image_editor($file['tmp_name']);
        $size = $image_editor->get_size();
        if (
                (isset($size['width']) && $size['width'] > ELD_IMAGE_MAX_WIDTH )
                ||
                (isset($size['height']) && $size['height'] > ELD_IMAGE_MAX_HEIGHT )
            ){
            $image_editor->set_quality(ELD_IMAGE_QUALITY);
            $image_editor->resize(ELD_IMAGE_MAX_WIDTH, ELD_IMAGE_MAX_HEIGHT,false);
            $saved_image = $image_editor->save( $file['tmp_name'] );
            
            // rename file to remove extension
            rename( $saved_image['path'], $file['tmp_name'] );
            // update file size
            $file['size'] = filesize( $file['tmp_name'] );
        }
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'eld_upload_processor');
