
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function($, window, document, _undefined)
{

    XenForo.XenGalleryEditor = function($textarea) { this.__construct($textarea); };
    XenForo.XenGalleryEditor.prototype =
    {
        __construct: function($textarea)
        {
            this.redactor = $textarea.data('redactor');
            this.redactorOptions = $textarea.data('options');

            this.dialogAlbumUrl =  'gallery/editor?type=album';
            this.titleAlbum = '';

            this.dialogContentUrl =  'gallery/editor?type=content';
            this.titleContent = '';

            if (typeof this.redactor != 'undefined')
            {
                this.redactor.opts.buttonsCustom.insertAlbum.callback = $.context(this, 'getAlbumModal');
                this.dialogAlbumUrl = this.redactorOptions.buttons.insertAlbum.dialogUrl;
                this.titleAlbum = this.redactorOptions.buttons.insertAlbum.title;

                this.redactor.opts.buttonsCustom.insertContent.callback = $.context(this, 'getContentModal');
                this.dialogContentUrl = this.redactorOptions.buttons.insertContent.dialogUrl;
                this.titleContent = this.redactorOptions.buttons.insertContent.title;
            }
        },

        getAlbumModal: function(ed)
        {
            var self = this;

            ed.saveSelection();
            ed.modalInit(this.titleAlbum, { url: this.dialogAlbumUrl}, 600, $.proxy(function()
            {
                $('#redactor_insert_album_btn').click(function(e) {
                    e.preventDefault();
                    self.insertAlbumBbcode(e, ed);
                });

                setTimeout(function() {
                    $('#redactor_album_url').focus();
                }, 100);

            }, ed));
        },

        insertAlbumBbcode: function(e, ed)
        {
            XenForo.ajax(
                this.dialogAlbumUrl,
                { url: $('#redactor_album_url').val() , size: $('input[name="redactor_cover_size"]:checked').val()},
                function(ajaxData) {
                    if (XenForo.hasResponseError(ajaxData))
                    {
                        return;
                    }

                    if (ajaxData.bbcode)
                    {
                        ed.restoreSelection();
                        ed.execCommand('inserthtml', ajaxData.bbcode);
                        ed.modalClose();
                    }
                    else if (ajaxData.message)
                    {
                        alert(ajaxData.message);
                    }
                }
            );
        },

        getContentModal: function(ed)
        {
            var self = this;

            ed.saveSelection();
            ed.modalInit(this.titleContent, { url: this.dialogContentUrl}, 600, $.proxy(function()
            {
                $('#redactor_insert_content_btn').click(function(e) {
                    e.preventDefault();
                    self.insertContentBbcode(e, ed);
                });

                setTimeout(function() {
                    $('#redactor_content_url').focus();
                }, 100);

            }, ed));
        },

        insertContentBbcode: function(e, ed)
        {
            XenForo.ajax(
                this.dialogContentUrl,
                { url: $('#redactor_content_url').val(), size: $('input[name="redactor_content_size"]:checked').val()},
                function(ajaxData) {
                    if (XenForo.hasResponseError(ajaxData))
                    {
                        return;
                    }

                    if (ajaxData.bbcode)
                    {
                        ed.restoreSelection();
                        ed.execCommand('inserthtml', ajaxData.bbcode);
                        ed.modalClose();
                    }
                    else if (ajaxData.message)
                    {
                        alert(ajaxData.message);
                    }
                }
            );
        }
    };

    XenForo.register('textarea.BbCodeWysiwygEditor', 'XenForo.XenGalleryEditor');

}(jQuery, this, document);