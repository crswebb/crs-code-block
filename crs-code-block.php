<?php
/*
Plugin Name: CRS Code Blocks
Plugin URI:  https://github.com/crswebb/crs-code-block
Description: A plugin to add/edit/remove named HTML blocks, for wordpress clasic editor.
Version:     1.0
Author:      CRS Webbproduktion AB
Author URI:  https://crswebb.se
*/


// Add admin menu
add_action('admin_menu', 'crs_blocks_menu');

function crs_blocks_menu(){
    add_menu_page('CRS Blocks Page', 'CRS Blocks', 'manage_options', 'crs-blocks', 'crs_blocks_page' );
}

// Display the admin page
function crs_blocks_page(){
}

add_action('init', 'crs_create_block_post_type');

function crs_create_block_post_type() {
    register_post_type('crs_block',
        array(
            'labels' => array(
                'name' => __('CRS Blocks'),
                'singular_name' => __('CRS Block'),
                'add_new' => __('Lägg till Block'), // Change the "Add New" label here
                'add_new_item' => __('Lägg till Block'), // And here
                'edit_item' => __('Redigera Block'), // And here
                'new_item' => __('Nytt Block'), // And here
                'view_item' => __('Visa Block'), // And here
                'view_items' => __('Visa Block'), // And here
                'search_items' => __('Sök Block'), // And here
                'not_found' => __('Inga block hittades.'), // And here
                'not_found_in_trash' => __('Inga block hittades i papperskorgen.'), // And here
                'all_items' => __('Alla Blocks'), // And here
            ),
            'public' => true,
            'has_archive' => false,
            'supports' => array('title'),
        )
    );
}

// Add meta box
add_action('add_meta_boxes', 'crs_add_html_meta_box');

function crs_add_html_meta_box() {
    add_meta_box(
        'crs_html_meta_box', // id
        'Block HTML', // title
        'crs_html_meta_box_callback', // callback
        'crs_block' // post type
    );
}

// Meta box callback
function crs_html_meta_box_callback($post) {
    // Add a nonce field
    wp_nonce_field('crs_save_html_meta', 'crs_html_meta_nonce');

    $value = get_post_meta($post->ID, '_crs_block_html', true);

    echo '<textarea id="crs_block_html" name="crs_block_html" rows="5" style="width:100%">' . esc_attr($value) . '</textarea>';
}

// Save meta box content
add_action('save_post', 'crs_save_html_meta_box_data');

function crs_save_html_meta_box_data($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['crs_html_meta_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['crs_html_meta_nonce'], 'crs_save_html_meta')) {
        return;
    }

    // Check if not an autosave.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Check for input data
    if (!isset($_POST['crs_block_html'])) {
        return;
    }

    // Sanitize user input.
    $my_data = wp_kses_post($_POST['crs_block_html']);

    // Update the meta field in the database.
    update_post_meta($post_id, '_crs_block_html', $my_data);
}

// Add TinyMCE button
add_action('admin_head', 'crs_add_tinymce_button');

function crs_add_tinymce_button() {
    // Check user permissions
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }

    // Check if WYSIWYG is enabled
    if ('true' == get_user_option('rich_editing')) {
        add_filter('mce_external_plugins', 'crs_add_tinymce_plugin');
        add_filter('mce_buttons', 'crs_register_tinymce_button');
    }
}

// Register TinyMCE button
function crs_register_tinymce_button($buttons) {
    array_push($buttons, "crs_button");
    return $buttons;
}

// Add TinyMCE plugin
function crs_add_tinymce_plugin($plugin_array) {
    $plugin_array['crs_button'] = plugin_dir_url(__FILE__) . 'button.js';
    return $plugin_array;
}

add_action('admin_enqueue_scripts', 'crs_enqueue_scripts');

function crs_enqueue_scripts() {
    wp_enqueue_script('crs-button', plugin_dir_url(__FILE__) . 'button.js', ['jquery'], false, true);
    wp_localize_script('crs-button', 'crs_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('crs_get_blocks'),
    ]);
}

add_action('wp_ajax_crs_get_blocks', 'crs_get_blocks');

function crs_get_blocks() {
    // Check nonce
    check_ajax_referer('crs_get_blocks');

    // Get blocks
    $blocks = get_posts([
        'post_type' => 'crs_block',
        'numberposts' => -1,
    ]);

    // Prepare blocks for JavaScript
    $blocks_js = [];
    foreach ($blocks as $block) {
        $blocks_js[] = [
            'text' => $block->post_title,
            'value' => get_post_meta($block->ID, '_crs_block_html', true),
        ];
    }

    // Send blocks to JavaScript
    wp_send_json($blocks_js);
}
?>