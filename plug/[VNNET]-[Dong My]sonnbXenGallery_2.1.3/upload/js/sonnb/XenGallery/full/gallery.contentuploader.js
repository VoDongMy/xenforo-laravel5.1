
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function($, window, document, _undefined) {

    XenForo.XenGalleryContentEditor = function ($container)
    {
        this.setVisibility = function(instant)
        {
            var $hideElement = $container.closest('.ctrlUnit'),
                $files = $container.find('.AttachedContent:not(#AttachedContentTemplate)');

            if ($hideElement.length == 0)
            {
                $hideElement = $container;
            }

            if (instant === true)
            {
                if ($files.length)
                {
                    $hideElement.show();
                }
                else
                {
                    $hideElement.hide();
                }
            }
            else
            {
                if ($files.length)
                {
                    $hideElement.xfFadeDown(XenForo.speed.normal);
                }
                else
                {
                    $hideElement.xfFadeUp(XenForo.speed.normal, false, false, 'swing');
                }
            }
        };

        this.setVisibility(true);

        var thisVis = $.context(this, 'setVisibility');

        $('#PhotoUploader').bind('AttachmentsChanged', thisVis);
        $('#VideoUploader').bind('AttachmentsChanged', thisVis);

    }

    XenForo.XenGalleryContentUploader = function($container)
    {
        var contentUploader = XenForo.AttachmentUploader($container),
            insertSpeed = XenForo.speed.normal,
            removeSpeed = XenForo.speed.fast;

        var attachmentErrorHandler = function(e)
        {
            var error = '';

            if (e.ajaxData)
            {
                $.each(e.ajaxData.error, function(i, errorText) { error += errorText + "\n"; });
            }
            else
            {
                error += e.message + "\n";
            }

            XenForo.alert(error + '<br /><br />' + e.file.name);

            $('#' + e.file.id).xfRemove(
                'xfFadeUp',
                function(){
                    if ($('.ContentEditor .AttachedContent').length < 2)
                    {
                        $('#noContent').fadeIn();
                    }
                },
                removeSpeed
            );
            $container.closest('form').find('input[type="submit"]').removeClass('disabled').removeAttr('disabled');

            console.warn('AttachmentUploadError: %o', e);
        };

        var isValidVideo = function (e)
        {
            var isVideo;

            switch (e.file.name.substr(e.file.name.lastIndexOf('.')).toLowerCase())
            {
                case '.flv':
                case '.wmv':
                case '.avi':
                case '.mpeg':
                case '.mkv':
                case '.mov':
                case '.mp4':
                case '.3gp':
                {
                    isVideo = true;
                    break;
                }

                default:
                {
                    isVideo = false;
                }
            }

            return isVideo;
        }

        $container.bind({
            AttachmentQueueValidation : function(e)
            {
                var $activeContents = $('.ContentEditor').find('.AttachedContent:visible');
                if ($activeContents.length > $container.data('maxfileupload'))
                {
                    XenForo.alert($container.data('error-maxfileupload'));
                    e.swfUpload.cancelUpload(e.file.id, false);
                    $('#' + e.file.id).remove();
                }

                if (!isValidVideo(e) && !e.isImage)
                {
                    e.swfUpload.cancelUpload(e.file.id, false);
                    console.log('Invalid File Type Selected: %s (%d bytes)', e.file.name, e.file.size);
                    return;
                }
            },

            AttachmentQueued : function(e)
            {
                if (!isValidVideo(e) && !e.isImage)
                {
                    e.swfUpload.cancelUpload(e.file.id, false);
                    console.log('Invalid File Type Selected: %s (%d bytes)', e.file.name, e.file.size);
                    return;
                }

                $container.closest('form').find('input[type="submit"]').addClass('disabled').attr('disabled', 'disabled');
                console.log('Queued: %s (%d bytes)', e.file.name, e.file.size);

                var $template = $('#AttachedContentTemplate').clone().attr('id', e.file.id);

                $template.find('.progress .gauge .meter').css('width', '0%');
                $template.find('.controls').hide();

                $template.xfInsert('appendTo', '.ContentList.Existing', null, insertSpeed);

                $container.trigger('AttachmentsChanged');

                $('.ContentEditor').closest('dl').show();
                $('#noContent').fadeOut();

                $('#' + e.file.id).find('.progress').show();
            },

            AttachmentUploadProgress : function(e) {
                var $result = $('#' + e.file.id),
                    $progress = $result.find('.progress'),
                    $meter = $progress.find('.meter'),
                    $text = $progress.find('.text'),
                    percentNum = Math.min(90, Math.ceil(e.bytes * 100 / e.file.size)),
                    percentage = percentNum + '%';

                $text.text(percentage);
                $meter.css('width', percentage);
            },

            AttachmentQueueError : attachmentErrorHandler,
            AttachmentUploadError : attachmentErrorHandler,

            AttachmentUploaded : function(e) {
                if (e.file) // SWFupload method
                {
                    var $result = $('#' + e.file.id),
                        $progress = $result.find('.progress'),
                        $meter = $progress.find('.meter'),
                        $text = $progress.find('.text'),

                        $attachment = $('#' + e.file.id),
                        $attachmentProcess = $attachment.find('.progress'),
                        $templateHtml = $(e.ajaxData.templateHtml),
                        $thumbnail;

                    $text.text('100%').hide();
                    $meter.css('width', '100%');

                    $attachmentProcess.fadeOut(XenForo.speed.fast, function()
                    {
                        $templateHtml.find('.description').xfInsert('insertBefore', $attachmentProcess, 'fadeIn', XenForo.speed.fast);

                        $thumbnail = $attachment.find('.Thumbnail');
                        $thumbnail.replaceWith($templateHtml.find('.Thumbnail'));
                        XenForo.activate($thumbnail);

                        var $controls = $attachment.find('.controls');
                        $controls.replaceWith($templateHtml.find('.controls')).show();
                        XenForo.activate($controls);

                        $attachmentProcess.xfRemove();

                        $attachment.attr('id', 'content' + e.ajaxData.content_data_id);

                        var $location = $attachment.find('input.location');
                        $location.each(function(){
                            var $self = $(this);
                            $self.geocomplete().bind("geocode:result", function(event, results){
                                if (results.geometry.location.kb)
                                {
                                    $self.parents('li.AttachedContent').find('input.location_lat').val(results.geometry.location.kb);
                                }
                                if (results.geometry.location.lb)
                                {
                                    $self.parents('li.AttachedContent').find('input.location_lng').val(results.geometry.location.lb);
                                }

                                return false;
                                event.preventDefault();
                            });
                        });

                        var $people = $attachment.find('.controls .peopleIcon'),
                            $place = $attachment.find('.controls .placeIcon'),
                            $cover = $attachment.find('.controls .coverIcon'),
                            $delete = $attachment.find('.controls .delete');

                        XenForo.XenGalleryContentPeople($people);
                        XenForo.XenGalleryContentPlace($place);
                        XenForo.XenGalleryContentCover($cover);
                        XenForo.XenGalleryContentDelete($delete);
                    });
                }
                else // regular javascript method
                {
                    var $attachment = $('#content' + e.ajaxData.content_data_id);

                    if (!$attachment.length)
                    {
                        $attachment = $(e.ajaxData.templateHtml).xfInsert('appendTo', $('.ContentEditor').find('.ContentList.Existing'), null, insertSpeed);

                        var $location = $('.ContentEditor').find('#content' + e.ajaxData.content_data_id).find('input.location');
                        $location.each(function(){
                            var $self = $(this);
                            $self.geocomplete().bind("geocode:result", function(event, results){
                                event.preventDefault();

                                if (results.geometry.location.kb)
                                {
                                    $self.parents('li.AttachedContent').find('input.location_lat').val(results.geometry.location.kb);
                                }
                                if (results.geometry.location.lb)
                                {
                                    $self.parents('li.AttachedContent').find('input.location_lng').val(results.geometry.location.lb);
                                }

                                return false;
                            });
                        });

                        var $people = $attachment.find('.controls .peopleIcon'),
                            $place = $attachment.find('.controls .placeIcon'),
                            $cover = $attachment.find('.controls .coverIcon'),
                            $delete = $attachment.find('.controls .delete');

                        XenForo.XenGalleryContentPeople($people);
                        XenForo.XenGalleryContentPlace($place);
                        XenForo.XenGalleryContentCover($cover);
                        XenForo.XenGalleryContentDelete($delete);

                        $('.ContentEditor').closest('dl').show();
                        $('#noContent').fadeOut();
                    }
                }

                $container.closest('form').find('input[type="submit"]').removeClass('disabled').removeAttr('disabled');

                $container.trigger('AttachmentsChanged');
            }
        });
    };

    XenForo.XenGalleryContentPeople = function($container)
    {
        $container.click(function (e)
        {
            var $contentContainer = $container.parents('.AttachedContent'),
                $peopleInput = $contentContainer.find('.description input.people');

            if ($peopleInput.is(':visible'))
            {
                $container.removeClass('active');
                $peopleInput.hide();
            }
            else
            {
                $container.addClass('active');
                $peopleInput.show();
            }
        });
    }

    XenForo.XenGalleryContentPlace = function($container)
    {
        $container.click(function (e)
        {
            var $contentContainer = $container.parents('.AttachedContent'),
                $locationInput = $contentContainer.find('.description input.location');

            if ($locationInput.is(':visible'))
            {
                $container.removeClass('active');
                $locationInput.hide();
            }
            else
            {
                $container.addClass('active');
                $locationInput.show();
            }
        });
    }

    XenForo.XenGalleryContentCover = function($container)
    {
        $container.click(function (e)
        {
            var $contentContainer = $container.parents('.AttachedContent'),
                $coverInput = $contentContainer.find('input.cover'),
                $coverTypeInput = $contentContainer.find('input.coverType'),
                $anotherCoverToggle = $('.controls .coverIcon');

            if ($container.hasClass('active'))
            {
                $container.removeClass('active');
                $coverInput.attr('checked', false);
                $coverTypeInput.attr('checked', false);
            }
            else
            {
                $anotherCoverToggle.removeClass('active');
                $container.addClass('active');
                $coverInput.attr('checked', true);
                $coverTypeInput.attr('checked', true);
            }
        });
    }

    XenForo.XenGalleryContentDelete = function($container)
    {
        $container.click(function (e)
        {
            var $contentContainer = $container.closest('.AttachedContent'),
                contentId = $container.data('content'),
                $input = $('#delete_'+contentId);

            $input.val(contentId);
            $contentContainer.fadeOut();
        });
    }

    XenForo.XenGalleryEmbedVideo = function($form)
    {
        var $return = new XenForo.AutoValidator($form);

        $form.bind('AutoValidationBeforeSubmit', function(e)
        {
            var $activeContents = $('.ContentEditor').find('.AttachedContent:visible');
            if ($activeContents.length > $('#ctrl_video_embed').data('maxfileupload'))
            {
                XenForo.alert($('#ctrl_video_embed').data('error-maxfileupload'));
                e.preventSubmit = true;
            }
        });

        $form.bind('AutoValidationComplete', function(e){
            var $attachment = $('#content' + e.ajaxData.content_data_id),
                $container = $('.ContentUploader');

            if (!$attachment.length)
            {
                $attachment = $(e.ajaxData.templateHtml).xfInsert('appendTo', $('.ContentEditor').find('.ContentList.Existing'), null, XenForo.speed.normal);

                var $location = $attachment.find('input.location');
                $location.each(function(){
                    var $self = $(this);
                    $self.geocomplete().bind("geocode:result", function(event, results){
                        event.preventDefault();

                        if (results.geometry.location.kb)
                        {
                            $self.parents('li.AttachedContent').find('input.location_lat').val(results.geometry.location.kb);
                        }
                        if (results.geometry.location.lb)
                        {
                            $self.parents('li.AttachedContent').find('input.location_lng').val(results.geometry.location.lb);
                        }

                        return false;
                    });
                });

                $('.ContentEditor').closest('dl').show();
                $('#noContent').fadeOut();

                var $people = $attachment.find('.controls .peopleIcon'),
                    $place = $attachment.find('.controls .placeIcon'),
                    $cover = $attachment.find('.controls .coverIcon'),
                    $delete = $attachment.find('.controls .delete');

                $container.closest('form').find('input[type="submit"]').removeClass('disabled').removeAttr('disabled');
                XenForo.XenGalleryContentPeople($people);
                XenForo.XenGalleryContentPlace($place);
                XenForo.XenGalleryContentCover($cover);
                XenForo.XenGalleryContentDelete($delete);

                $container.trigger('AttachmentsChanged');
            }
        });
    }

    // *********************************************************************

    if (typeof XenForo.AttachmentUploader == 'function')
    {
        XenForo.register('.ContentUploader', 'XenForo.XenGalleryContentUploader');
        XenForo.register('.ContentEditor', 'XenForo.XenGalleryContentEditor');
        XenForo.register('.formEmbedVideo', 'XenForo.XenGalleryEmbedVideo');
    }

    XenForo.register('.AttachedContent .controls .peopleIcon', 'XenForo.XenGalleryContentPeople');
    XenForo.register('.AttachedContent .controls .placeIcon', 'XenForo.XenGalleryContentPlace');
    XenForo.register('.AttachedContent .controls .coverIcon', 'XenForo.XenGalleryContentCover');
    XenForo.register('.AttachedContent .controls .delete', 'XenForo.XenGalleryContentDelete');

}(jQuery, this, document);