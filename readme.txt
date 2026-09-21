=== CRS Code Block ===
Contributors: crswebb
Tags: classic editor, tinymce, html, blocks
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Add, edit, and insert named HTML blocks in the WordPress classic editor.

== Description ==

CRS Code Block lets editors create reusable, named HTML blocks and insert
them into posts and pages from the classic (TinyMCE) editor toolbar.

* Manage blocks as a dedicated "CRS Blocks" post type, each with a title and an HTML body.
* Insert any saved block into the classic editor with a single toolbar button.
* Block HTML is sanitized on save with `wp_kses_post`; users with the
  `unfiltered_html` capability may store arbitrary markup.

== Installation ==

1. Upload the `crs-code-block` folder to `/wp-content/plugins/`, or install
   the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Create blocks under the "CRS Blocks" menu.
4. In the classic editor, use the "CRS Block" toolbar button to insert a block.

== Frequently Asked Questions ==

= Which editor does this support? =

The classic (TinyMCE) editor. The toolbar button is added only when the
classic editor is active for the current user.

= Why was my HTML changed when I saved a block? =

Block HTML is sanitized with `wp_kses_post` for users who do not have the
`unfiltered_html` capability. Users who do (typically administrators on a
single-site install) can store arbitrary markup, including `<script>`.

== Changelog ==

= 1.1.0 =
* Security: add a capability check to the block-list AJAX endpoint.
* Fix: load the TinyMCE plugin only on editor screens instead of every admin
  page, preventing a JavaScript error where `tinymce` was undefined.
* Fix: stop loading the editor script twice.
* Fix: preserve full markup on save for users with `unfiltered_html` and
  unslash input before sanitizing.
* Fix: escape textarea output with `esc_textarea`.
* Fix: guard against inserting an empty/undefined block selection.
* Remove an empty admin menu page.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Security and reliability fixes. Upgrade recommended.
