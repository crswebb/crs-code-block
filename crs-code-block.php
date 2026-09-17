<?php
/*
Plugin Name: CRS Code Block
Plugin URI:  https://github.com/crswebb/crs-code-block
Description: Add, edit, and insert named HTML blocks in the WordPress classic editor.
Version:     1.1.0
Requires at least: 5.0
Requires PHP: 7.2
Author:      CRS Webbproduktion AB
Author URI:  https://crswebb.se
License:     MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: crs-code-block
*/

if (!defined('ABSPATH')) {
    exit; // Prevent direct access.
}

if (!defined('CRS_CODE_BLOCK_VERSION')) {
    define('CRS_CODE_BLOCK_VERSION', '1.1.0');
}


// Translations load automatically for WordPress.org-hosted plugins (and from
// the bundled /languages folder via the Domain Path header on WordPress 6.7+),
// so no load_plugin_textdomain() call is needed.

add_action('init', 'crs_create_block_post_type');

function crs_create_block_post_type() {
    register_post_type('crs_block',
        array(
            'labels' => array(
                'name' => __('CRS Blocks', 'crs-code-block'),
                'singular_name' => __('CRS Block', 'crs-code-block'),
                'add_new' => __('Add Block', 'crs-code-block'),
                'add_new_item' => __('Add Block', 'crs-code-block'),
                'edit_item' => __('Edit Block', 'crs-code-block'),
                'new_item' => __('New Block', 'crs-code-block'),
                'view_item' => __('View Block', 'crs-code-block'),
                'view_items' => __('View Blocks', 'crs-code-block'),
                'search_items' => __('Search Blocks', 'crs-code-block'),
                'not_found' => __('No blocks found.', 'crs-code-block'),
                'not_found_in_trash' => __('No blocks found in the trash.', 'crs-code-block'),
                'all_items' => __('All Blocks', 'crs-code-block'),
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

    echo '<textarea id="crs_block_html" name="crs_block_html" rows="5" style="width:100%">' . esc_textarea($value) . '</textarea>';
}

// Save meta box content
add_action('save_post', 'crs_save_html_meta_box_data');

function crs_save_html_meta_box_data($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['crs_html_meta_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crs_html_meta_nonce'] ) ), 'crs_save_html_meta')) {
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

    // Unslash, then sanitize user input. Users with the unfiltered_html
    // capability (typically admins) may store arbitrary markup — including
    // <script>/<style> — which this plugin is meant to support; everyone
    // else is restricted to post-safe HTML via wp_kses_post below.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here and sanitized below with wp_kses_post for users without unfiltered_html; raw markup intentionally allowed for those with it.
    $raw = wp_unslash($_POST['crs_block_html']);
    if (current_user_can('unfiltered_html')) {
        $my_data = $raw;
    } else {
        $my_data = wp_kses_post($raw);
    }

    // Update the meta field in the database. update_post_meta() unslashes its
    // value internally, so re-slash here to preserve literal backslashes in the
    // stored markup (e.g. in JavaScript or CSS escapes).
    update_post_meta($post_id, '_crs_block_html', wp_slash($my_data));
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
        // button.js is loaded by TinyMCE via mce_external_plugins (correct
        // timing and lifecycle), so it has no wp_enqueue_script handle to
        // localize onto. Print its AJAX config inline in this already
        // capability-/editor-guarded context instead.
        $crs_ajax = array(
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('crs_get_blocks'),
        );
        printf(
            "<script type=\"text/javascript\">var crs_ajax = %s;</script>\n",
            wp_json_encode($crs_ajax)
        );

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
    $plugin_array['crs_button'] = plugin_dir_url(__FILE__) . 'button.js?ver=' . CRS_CODE_BLOCK_VERSION;
    return $plugin_array;
}

add_action('wp_ajax_crs_get_blocks', 'crs_get_blocks');

function crs_get_blocks() {
    // Check nonce
    check_ajax_referer('crs_get_blocks');

    // Check the user's permissions. A valid nonce proves request origin,
    // not authorization, so verify the capability explicitly.
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        wp_send_json_error('Insufficient permissions', 403);
    }

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