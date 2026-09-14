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
                    if (!blocks || !blocks.length) {
                        editor.windowManager.alert('No CRS blocks found.');
                        return;
                    }
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
                            if (e.data.block) {
                                editor.insertContent(e.data.block);
                            }
                        }
                    });
                });
            }
        });
    });
})(jQuery);