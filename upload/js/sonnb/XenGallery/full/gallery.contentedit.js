
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
    XenForo.XenGalleryContentEditOverlay = function($form)
    {
        if ($form.data('AutoValidator') != true)
        {
            new XenForo.AutoValidator($form);

            $form.data('AutoValidator', true);
            $form.bind(
                {
                    AutoValidationBeforeSubmit: function(e)
                    {
                        if ($(e.clickedSubmitButton).is('input[name="more_options"]'))
                        {
                            e.preventDefault();
                            e.returnValue = true;
                        }
                    },

                    AutoValidationComplete: function(e)
                    {
                        if (e.textStatus == 'success')
                        {
                            var contentId = e.ajaxData.contentId,
                                description = e.ajaxData.description,
                                fields = e.ajaxData.fields;

                            var $desContainer = $('#content-' + contentId).find('.messageContent blockquote'),
                                $desContainerOverlay = $('#content-overlay-' + contentId).find('.messageContent blockquote'),
                                $captionContainer = $('#caption-content-overlay-' + contentId),
                                $fieldContainerOverlay = $('#fieldList-content-overlay-' + contentId),
                                $fieldContainer = $('#fieldList-content-' + contentId);

                            $desContainer.html(description);
                            $desContainerOverlay.html(description);
                            $captionContainer.html(description);
                            $fieldContainer.html(fields);
                            $fieldContainerOverlay.html(fields);
                        }
                    }
                });
        }
    };

    XenForo.register('form.ContentEditOverlay', 'XenForo.XenGalleryContentEditOverlay');

}(jQuery, this, document);