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

if (!defined('CRSCB_VERSION')) {
    define('CRSCB_VERSION', '1.1.0');
}

// Translations are managed via translate.wordpress.org for hosted plugins, so
// no bundled translation files or load_plugin_textdomain() call are needed.

add_action('init', 'crscb_create_block_post_type');

function crscb_create_block_post_type() {
    register_post_type('crscb_block',
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

/**
 * One-time, idempotent migration of the legacy post type and meta key to their
 * uniquely prefixed names, so existing blocks survive the rename.
 */
add_action('admin_init', 'crscb_maybe_migrate');

function crscb_maybe_migrate() {
    $target_version = '1';

    if (get_option('crscb_db_version') === $target_version) {
        return;
    }

    global $wpdb;

    // Rename the post type on existing posts: 'crs_block' -> 'crscb_block'.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time schema migration; a direct UPDATE is required to rename legacy rows and caching does not apply.
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            'crscb_block',
            'crs_block'
        )
    );

    // Rename the meta key: '_crs_block_html' -> '_crscb_block_html'.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time schema migration; a direct UPDATE is required to rename legacy meta keys and caching does not apply.
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            '_crscb_block_html',
            '_crs_block_html'
        )
    );

    update_option('crscb_db_version', $target_version);
}

// Add meta box
add_action('add_meta_boxes', 'crscb_add_html_meta_box');

function crscb_add_html_meta_box() {
    add_meta_box(
        'crscb_html_meta_box', // id
        'Block HTML', // title
        'crscb_html_meta_box_callback', // callback
        'crscb_block' // post type
    );
}

// Meta box callback
function crscb_html_meta_box_callback($post) {
    // Add a nonce field
    wp_nonce_field('crscb_save_html_meta', 'crscb_html_meta_nonce');

    $value = get_post_meta($post->ID, '_crscb_block_html', true);

    echo '<textarea id="crscb_block_html" name="crscb_block_html" rows="5" style="width:100%">' . esc_textarea($value) . '</textarea>';
}

// Save meta box content
add_action('save_post', 'crscb_save_html_meta_box_data');

function crscb_save_html_meta_box_data($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['crscb_html_meta_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crscb_html_meta_nonce'] ) ), 'crscb_save_html_meta')) {
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
    if (!isset($_POST['crscb_block_html'])) {
        return;
    }

    // Unslash, then sanitize user input. Users with the unfiltered_html
    // capability (typically admins) may store arbitrary markup — including
    // <script>/<style> — which this plugin is meant to support; everyone
    // else is restricted to post-safe HTML via wp_kses_post below.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here and sanitized below with wp_kses_post for users without unfiltered_html; raw markup intentionally allowed for those with it.
    $raw = wp_unslash($_POST['crscb_block_html']);
    if (current_user_can('unfiltered_html')) {
        $my_data = $raw;
    } else {
        $my_data = wp_kses_post($raw);
    }

    // Update the meta field in the database. update_post_meta() unslashes its
    // value internally, so re-slash here to preserve literal backslashes in the
    // stored markup (e.g. in JavaScript or CSS escapes).
    update_post_meta($post_id, '_crscb_block_html', wp_slash($my_data));
}

// Register the TinyMCE button and provide its AJAX config via the script queue.
add_action('admin_enqueue_scripts', 'crscb_enqueue_editor_assets');

function crscb_enqueue_editor_assets() {
    // Only for users who can edit content with the visual (TinyMCE) editor.
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }

    if ('true' !== get_user_option('rich_editing')) {
        return;
    }

    // button.js is loaded by TinyMCE via mce_external_plugins, so it has no
    // file handle of its own. Register a data-only handle and localize the AJAX
    // config onto it (the standard script queue, not a raw <script> tag).
    wp_register_script('crscb-editor', false, array(), CRSCB_VERSION, false);
    wp_enqueue_script('crscb-editor');
    wp_localize_script('crscb-editor', 'crscb_ajax', array(
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('crscb_get_blocks'),
    ));

    add_filter('mce_external_plugins', 'crscb_add_tinymce_plugin');
    add_filter('mce_buttons', 'crscb_register_tinymce_button');
}

// Register TinyMCE button
function crscb_register_tinymce_button($buttons) {
    array_push($buttons, 'crscb_button');
    return $buttons;
}

// Add TinyMCE plugin
function crscb_add_tinymce_plugin($plugin_array) {
    $plugin_array['crscb_button'] = plugin_dir_url(__FILE__) . 'button.js?ver=' . CRSCB_VERSION;
    return $plugin_array;
}

add_action('wp_ajax_crscb_get_blocks', 'crscb_get_blocks');

function crscb_get_blocks() {
    // Check nonce
    check_ajax_referer('crscb_get_blocks');

    // Check the user's permissions. A valid nonce proves request origin,
    // not authorization, so verify the capability explicitly.
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        wp_send_json_error('Insufficient permissions', 403);
    }

    // Get blocks
    $blocks = get_posts([
        'post_type' => 'crscb_block',
        'numberposts' => -1,
    ]);

    // Prepare blocks for JavaScript
    $blocks_js = [];
    foreach ($blocks as $block) {
        $blocks_js[] = [
            'text' => $block->post_title,
            'value' => get_post_meta($block->ID, '_crscb_block_html', true),
        ];
    }

    // Send blocks to JavaScript
    wp_send_json($blocks_js);
}
