(function()
{
    var saveCmd =
    {
        modes : { wysiwyg:1, source:1 },
        exec : function( editor )
        {
            var $form = editor.element.$.form;
            if ( $form )
            {
                try
                {
                    editor.updateElement();
                    var postArr = $($form).serializeArray();
                    $.post($($form).attr('action'), postArr,  function(data)
                    {

                        try
                        {
                            var post_res=jQuery.parseJSON(data);
                            if(post_res.success)
                                $('#informer').fadeIn().delay(650).fadeOut();
                            else if (!post_res.success)
                                alert('Error: '+post_res.info);
                            else if (typeof editor.config.autoUpdateElement != 'undefined' && editor.config.close_on_save)
                                window.close();
                        }
                        catch (e)
                        {
                            alert('Form save error');
                        }
                    });

                }
                catch ( e ) {
                    //alert(e);
                }
            }
        }
    };
    var pluginName = 'ajaxsave';
    CKEDITOR.plugins.add( pluginName,
        {
            init : function( editor )
            {
                var command = editor.addCommand( pluginName, saveCmd );
                command.modes = { wysiwyg : !!( editor.element.$.form ) };
                editor.ui.addButton( 'AjaxSave',
                    {
                        label : editor.lang.save.toolbar,
                        command : pluginName,
                        icon: this.path + "icons/save.png"
                    });
            }
        });
})();