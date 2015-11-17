
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

    XenForo.XenGalleryMyPlaylist = function($container) { this.__construct($container); };
    XenForo.XenGalleryMyPlaylist.prototype =
    {
        __construct: function($container)
        {
            this.phrase = $container.data('phrase');
            this.removeurl = $container.data('removeurl');
            this.$container = $container;

            this.$container.find('div.itemGallery').each($.context(this, 'addDeleteButton'));
        },

        addDeleteButton: function(index, element)
        {
            var $element = $(element),
                $delete = $('<span title="' + this.phrase + '" class="button delete"><i></i></span>');

            $element.prepend($delete);
            $delete.click($.context(this, 'deleteClick'));
        },

        deleteClick: function(e)
        {
            var $element = $(e.target),
                $content = $element.closest('.itemGallery'),
                $contentId = $content.attr('id').match(/\d+/g);

            XenForo.ajax(
                this.removeurl,
                {
                    content_id: $contentId[0],
                    content_type: $content.hasClass('video') ? 'video' : ''
                },
                $.context(this, 'deleteClickResponse')
            );
        },

        deleteClickResponse: function(ajaxData)
        {
            if (XenForo.hasResponseError(ajaxData))
            {
                return false;
            }

            if (ajaxData.message)
            {
                XenForo.alert(ajaxData.message, '', 2000);
            }

            if (ajaxData.content_id)
            {
                $('#content_' + ajaxData.content_id).remove();

                this.$container.masonry({
                    itemSelector: '.itemGallery',
                    isAnimated: !Modernizr.csstransitions,
                    easing: 'linear',
                    gutterWidth: 10
                });
            }
        }
    };

    XenForo.register('div.masonryContainer.myPlaylist', 'XenForo.XenGalleryMyPlaylist');

}
    (jQuery, this, document);