(function($) {
    tinymce.PluginManager.add('crs_button', function(editor, url) {
        editor.addButton('crs_button', {
            text: 'CRS Block',
            icon: false,
            onclick: function() {
                $.post(crs_ajax.url, {
                    action: 'crs_get_blocks',
                    _ajax_nonce: crs_ajax.nonce,
                }).done(function(blocks) {
                    editor.windowManager.open({
                        title: 'CRS Block',
                        body: [
                            {
                                type: 'listbox',
                                name: 'block',
                                label: 'Block',
                                values: blocks,
                            }
                        ],
                        onsubmit: function(e) {
                            editor.insertContent(e.data.block);
                        }
                    });
                });
            }
        });
    });
})(jQuery);