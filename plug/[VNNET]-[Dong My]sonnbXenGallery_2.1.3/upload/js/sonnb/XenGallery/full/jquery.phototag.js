/*
 * jQuery PhotoTag plugin 1.3
 *
 * Copyright (c) 2012 Karl Mendes
 * http://karlmendes.com
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Revision:
 * Modified by sonnb to fit XenGallery requirements.
 */

!function($, window, document, _undefined) {

    $.fn.photoTag = function( options ){
        var enableTag = false;

        var defaultOptions = {
            requestTagsUrl: 'photo-tags.php',
            deleteTagsUrl: 'delete.php',
            addTagUrl: 'add-tag.php',
            parametersForNewTag: {
                username: {
                    parameterKey: 'username',
                    label: 'Username',
                    _xfToken: ''
                }
            },
            parametersForRequest : ['image-id','album-id'],
            literals:{
                communicationProblem: 'Communication problem, your changes could not be saved',
                saveTag: 'Ok',
                cancelTag: 'Cancel',
                addNewTag: 'Add new tag',
                removeTag: 'Remove tag'
            },
            tag: {
                tagIdParameter: 'tag_id',
                defaultWidth: 100, //Without border and margin
                defaultHeight: 100, //Without border and margin
                cssClass: 'photoTag-tag secondaryContent',
                idPrefix: 'photoTag-tag_',
                showDeleteLinkOnTag: true,
                deleteLinkCssClass: 'photoTag-delete',
                deleteLinkIdPrefix: 'photoTag-delete_',
                flashAfterCreation: false,
                newTagFormWidth: 106,
                newTagFormClass: 'photoTag-newTagForm'
            },
            imageWrapper: '.pvContentWrapper',
            imageWrapBox: {
                cssClass: 'photoTag-wrap',
                idPrefix: 'photoTag-wrap_',
                addNewLinkIdPrefix: 'photoTag-add_',
                controlPaneIdPrefix: 'photoTag-cpanel_',
                controlPaneCssClass: 'photoTag-cpanel',
                tagListCssClass: 'photoTag-list',
                canvasIdPrefix: 'photoTag-canvas_'
            },
            externalAddTagLinks: {
                selector: ".addTag"
            },
            isEnabledToEditTags: true,
            manageError: 'internal function, user can bind a new one. function(response)',
            beforeTagRequest: 'bind by user, function( parameters )'
        };

        var cache = {
            tags: {}
        };

        var options = $.extend(true,defaultOptions,options);

        var getParametersForImage = function( imageElement )
        {
            var parameters = {};
            $.each(options.parametersForRequest,function( i, key)
            {
                var parameterValue = imageElement.attr('data-'+key);
                if(parameterValue)
                {
                    parameters[key] = parameterValue;
                }
            });

            return parameters;
        };

        var registerEventsForTagBox = function(tagBox, tagJSON, image)
        {
            tagBox.mouseover(function(){
                if(!$.browser.msie)
                {
                    $(this).stop().animate({ opacity: 1.0 }, 500);
                }
                else
                {
                    $(this).css({ opacity: 1.0 });
                }
            }).mouseout(function(){
                    if(!$.browser.msie)
                    {
                        $(this).stop().animate({ opacity: 0.0 }, 500);
                    }
                    else
                    {
                        $(this).css({ opacity: 0.0 });
                    }
                });

            $(window).resize(function(){
                var tempTagBox = $('#'+options.tag.idPrefix+'temp', $(options.imageWrapper)),
                    realHeight = image.data('height'),
                    realWidth = image.data('width'),
                    actualHeight = image.height(),
                    actualWidth = image.width(),
                    imageTop = image.position().top,
                    imageleft = image.position().left,
                    css = {
                        top: ((actualHeight/realHeight)*tagJSON.top + imageTop) + 'px',
                        left: ((actualWidth/realWidth)*tagJSON.left + imageleft)+ 'px'
                        //height: (actualWidth/realWidth)*tagJSON.width + 'px',
                        //width: (actualHeight/realHeight)*tagJSON.height + 'px'
                    };

                tagBox.css(css).data('width', (actualWidth/realWidth)*tagJSON.width).data('height', (actualHeight/realHeight)*tagJSON.height);
                tempTagBox.data('width', (actualWidth/realWidth)*tagJSON.width).data('height', (actualHeight/realHeight)*tagJSON.height);
            });
        };

        var manageError = function( response )
        {
            if( $.isFunction(options.manageError) )
            {
                options.manageError(response);
            }
            else
            {
                if(response.message)
                {
                    console.log(response.message);
                }
            }
        };

        var registerEventsForDeleteLink = function( link, image )
        {
            link.click(function(e){
                e.preventDefault();
                var tagId = link.attr('href').substring(1);
                var parameters = getParametersForImage(image);
                parameters[options.tag.tagIdParameter] = tagId;

                $.getJSON(options.deleteTagsUrl,parameters,
                    function( data )
                    {
                        if(!data.tagList)
                        {
                            manageError(data);
                        }

                        prepareTagListAfterEdit(data);
                    }
                );

                $('#' + options.tag.deleteLinkIdPrefix + tagId).parent().remove();
            });
        }

        var registerEventsForAddTagLink = function( link, image, image_id )
        {
            $(link).click(function(e){
                e.preventDefault();
                enableTag = !enableTag;

                var $imageWrapper = $('#' + options.imageWrapBox.canvasIdPrefix + image_id, $(options.imageWrapper)),
                    $image = $('#' + options.imageWrapBox.idPrefix + image_id, $(options.imageWrapper)),
                    $tagControl = $('#' + options.imageWrapBox.controlPaneIdPrefix + image_id, $(options.imageWrapper)),
                    $photoAction = $('.pwPhotoActions', $imageWrapper),
                    $photoCaption = $('.caption', $imageWrapper);

                if (enableTag == false)
                {
                    removeNewTempTag();
                    showAllTags(image_id);

                    $('.photo', $image).css({cursor: 'pointer'});
                    $('.prevPhoto', $imageWrapper).css({visibility: 'visible'});
                    $('.nextPhoto', $imageWrapper).css({visibility: 'visible'});

                    $tagControl.hide();
                    if ($photoCaption.html())
                    {
                        $photoCaption.show();
                    }
                    $photoAction.show();

                    $('a', $tagControl).unbind('click');
                    $('.photo', $image).unbind('click');
                }
                else
                {
                    hideAllTags(image_id);
                    $('.prevPhoto', $imageWrapper).css({visibility: 'hidden'});
                    $('.nextPhoto', $imageWrapper).css({visibility: 'hidden'});

                    $photoAction.hide();
                    $photoCaption.hide();
                    $tagControl.show();

                    $('a', $tagControl).click(function(e){
                        e.preventDefault();
                        $(link).trigger('click');
                        return false;
                    });

                    $('.photo', $image).css({cursor: 'crosshair'}).click(function(e)
                    {
                        e.preventDefault();
                        hideAllTags(image_id);
                        removeNewTempTag();

                        if($('#' + options.tag.idPrefix + 'temp', $(options.imageWrapper)).length == 0 && options.addTagUrl)
                        {
                            $image.append(createTempTag(e, image, image_id));
                            prepareTempTagBox($('#' + options.tag.idPrefix + 'temp', $(options.imageWrapper)),image, image_id);
                        }

                        return false;
                    });

                    $(XenForo.getPageScrollTagName()).animate({scrollTop: $imageWrapper.offset().top});
                }

                return false;
            });
        };

        var prepareTempTagBox = function( tempTagBox, image, image_id )
        {
            createNewTagForm(tempTagBox,image,image_id);
        };

        var createNewTagForm = function( tempTagBox, image, image_id )
        {
            var imageWrapper = $("#" + options.imageWrapBox.idPrefix + image_id, $(options.imageWrapper));
            var form = $('<form method="POST" id="tempNewTagForm" style="line-height: normal;" action="'+options.addTagUrl+'"></form>');
            var newTagFormBox = $('<div id="tempTagBoxForm" class="photoTagForm secondaryContent"></div>').hide();

            newTagFormBox.append($('<div id="tempNewTagFormContent" class="photoTagContent"></div>'));
            imageWrapper.append(newTagFormBox);
            $('#tempNewTagFormContent').append(form);

            $.each(options.parametersForNewTag,function( i, properties )
            {
                var input = $('<div><input type="text" class="textCtrl AutoComplete AcSingle" autofocus="true" autocomplete="off" id="tempInput_'+i+'" name="'+properties.parameterKey+'" /></div>');

                if(properties.label)
                {
                    var label = $('<label></label>');
                    var div = $('<div/>');
                    label.append(properties.label);
                    newTagFormBox.append(label);
                };

                form.append(input);
            });

            var div = $('<div />');
            var submit = $('<input class="button primary" type="submit" value="' + options.literals.saveTag + '" />');
            var cancel = $('<input class="button primary" type="button" value="' + options.literals.cancelTag + '"/>');

            cancel.click(function(e){
                e.preventDefault();
                removeNewTempTag();
                showAllTags(image_id);
            });

            div.append(submit).append(cancel);
            form.append(div);

            var top = parseInt(tempTagBox.css('top')) + tempTagBox.outerHeight(),
                left = parseInt(tempTagBox.css('left')),
                rTop = parseInt(tempTagBox.css('top')) + tempTagBox.outerHeight();

            if(top + newTagFormBox.outerHeight(true) >= image.outerHeight())
            {
                rTop = parseInt(tempTagBox.css('top')) - newTagFormBox.outerHeight();
            }

            newTagFormBox.css({
                top: rTop,
                left: left,
                width : options.tag.newTagFormWidth
            }).show();

            form.submit(function(e)
            {
                e.preventDefault();
                var tempTagBox = $('#'+options.tag.idPrefix+'temp', $(options.imageWrapper)),
                    realHeight = image.data('height'),
                    realWidth = image.data('width'),
                    actualHeight = image.height(),
                    actualWidth = image.width(),
                    imageTop = image.position().top,
                    imageleft = image.position().left,
                    $imageContainer = image.parent(),
                    imageContainerWidth = $imageContainer.width(),
                    imageContainerHeight = $imageContainer.height();

                var tag =
                {
                    width: tempTagBox.data('width'),
                    height: tempTagBox.data('height'),
                    _xfToken: options.parametersForNewTag.username._xfToken,
                    username: $('input[name="username"]', form).val(),
                    _xfNoRedirect: 1,
                    _xfResponseType: 'json'
                }

                if ($('body > div.galleryOverlay').is(':visible'))
                {
                    tag.tag_x = tempTagBox.position().left/(actualWidth/realWidth) + imageleft;
                    tag.tag_y = tempTagBox.position().top/(actualHeight/realHeight) + imageTop;
                }
                else
                {
                    tag.tag_x = tempTagBox.position().left/(actualWidth/realWidth) - (imageContainerWidth -  actualWidth)/2;
                    tag.tag_y = tempTagBox.position().top/(actualHeight/realHeight) - (imageContainerHeight -  actualHeight)/2;
                }

                $.post(options.addTagUrl, tag,function(response)
                {
                    if(response.result != undefined && !response.result)
                    {
                        manageError(response);
                        return;
                    }

                    if (!response.tag)
                    {
                        return;
                    }

                    var tagBox = createTagBoxFromJSON(response.tag,image);
                    $('#' + options.imageWrapBox.idPrefix + image_id, $(options.imageWrapper)).append(tagBox);
                    extendTagBoxAttributes(tagBox,response.tag,image,image_id);

                    prepareTagListAfterEdit(response);
                });

                removeNewTempTag();
                showAllTags(image_id);
            });

            XenForo.activate(newTagFormBox);
        };

        var prepareTagListAfterEdit = function (response)
        {
            $('.' + options.imageWrapBox.tagListCssClass, $(options.imageWrapper)).html(response.tagList);
            XenForo.activate($('.' + options.imageWrapBox.tagListCssClass));
            $.each(response.image,function()
            {
                prepareTagHoverAfterEdit(this.tags);
            });
        }

        var prepareTagHoverAfterEdit = function (tags)
        {
            $.each(tags, function()
            {
                var prefix = options.tag.idPrefix + this.tag_id;

                $('.' + prefix, $(options.imageWrapper)).mouseover(function(){
                    $('#' + prefix, $(options.imageWrapper)).trigger('mouseover');
                }).mouseout(function(){
                        $('#' + prefix, $(options.imageWrapper)).trigger('mouseout');
                    });
            });
        }

        var removeNewTempTag = function()
        {
            $('#'+options.tag.idPrefix+'temp', $(options.imageWrapper)).remove();
            $('#tempTagBoxForm', $(options.imageWrapper)).remove();
        };

        var createTagBox = function( tagId, dimension, position, opacity )
        {
            var tagBox = $('<div class="'+ options.tag.cssClass +'" data-width="' + dimension.width + '" data-height="' + dimension.height + '" id="' + options.tag.idPrefix + tagId +'"></div>');

            var css = {
                position: 'absolute',
                top: position.top + 'px',
                left: position.left + 'px',
                opacity: opacity,
                height: options.tag.defaultHeight,
                width: options.tag.defaultWidth
            };

            tagBox.css(css);
            return tagBox
        };

        var createTagBoxFromJSON = function( tagJSON, image )
        {
            if (!tagJSON)
            {
                return;
            }

            if( !(tagJSON.height && tagJSON.width))
            {
                tagJSON.height = options.tag.defaultHeight;
                tagJSON.width = options.tag.defaultWidth;
            };

            var realHeight = image.data('height'),
                realWidth = image.data('width'),
                actualHeight = image.height(),
                actualWidth = image.width(),
                imageTop = image.position().top,
                imageleft = image.position().left,
                $imageContainer = image.parent(),
                imageContainerWidth = $imageContainer.width(),
                imageContainerHeight = $imageContainer.height();

            var dimension = {
                width: (actualWidth/realWidth)*tagJSON.width,
                height: (actualHeight/realHeight)*tagJSON.height
            };

            if ($('body > div.galleryOverlay').is(':visible'))
            {
                var position = {
                    top: (actualHeight/realHeight)*tagJSON.top + imageTop,
                    left: (actualWidth/realWidth)*tagJSON.left + imageleft
                };
            }
            else
            {
                var position = {
                    top: (actualHeight/realHeight)*tagJSON.top + (imageContainerHeight -  actualHeight)/2,
                    left: (actualWidth/realWidth)*tagJSON.left + (imageContainerWidth -  actualWidth)/2
                };
            }

            if (dimension.height > options.tag.defaultHeight)
            {
                dimension.height = options.tag.defaultHeight;
            }
            if (dimension.width > options.tag.defaultWidth)
            {
                dimension.width = options.tag.defaultWidth;
            }

            var tagBox = createTagBox(tagJSON.tag_id,dimension,position,0);

            registerEventsForTagBox(tagBox, tagJSON, image);

            /**
             *
             */
            var prefix = options.tag.idPrefix + tagJSON.tag_id;

            $('.' + prefix).mouseover(function(){
                $('#' + prefix, $(options.imageWrapper)).trigger('mouseover');
            }).mouseout(function(){
                    $('#' + prefix, $(options.imageWrapper)).trigger('mouseout');
                });

            var innerElement = $("<div class='innerTag'></div>");
            innerElement.append(tagJSON.username);
            tagBox.append(innerElement);

            if(options.isEnabledToEditTags && tagJSON.isDeleteEnable && options.tag.showDeleteLinkOnTag && options.deleteTagsUrl)
            {
                var deleteLink = $('<a id="'+ options.tag.deleteLinkIdPrefix + tagJSON.tag_id +'" class="'+ options.tag.deleteLinkCssClass +'" href="#'+ tagJSON.tag_id +'"></a>');
                registerEventsForDeleteLink(deleteLink,image);
                tagBox.append(deleteLink);
            };

            return tagBox;
        }

        var createTempTag = function(event, image, image_id )
        {
            var wrapper = $('#' + options.imageWrapBox.idPrefix + image_id, $(options.imageWrapper)),
                realHeight = image.data('height'),
                realWidth = image.data('width'),
                actualHeight = image.height(),
                actualWidth = image.width(),
                imageTop = image.position().top,
                imageleft = image.position().left,

                dimension = {
                    width: (actualWidth/realWidth)*options.tag.defaultWidth,
                    height: (actualHeight/realHeight)*options.tag.defaultHeight
                };

            var top = event.pageY - image.offset().top - dimension.height/2 + image.position().top,
                left = event.pageX - image.offset().left - dimension.width/2 + image.position().left;

            if (top < 0)
            {
                top = 0;
            }

            if (left < 0)
            {
                left = 0;
            }

            var position = {
                top: top,
                left: left
            };

            cache.tempId++;
            var tempTagBox = createTagBox('temp', dimension, position, 1);

            if (parseInt(tempTagBox.css('top')) + dimension.height >= image.outerHeight() + image.position().top)
            {
                top = image.outerHeight() - dimension.height + image.position().top;
            }

            if (parseInt(tempTagBox.css('top')) < image.position().top)
            {
                top = image.position().top;
            }

            if (parseInt(tempTagBox.css('left')) + dimension.width >= image.outerWidth() + image.position().left)
            {
                left = image.outerWidth() - dimension.width + image.position().left;
            }

            if (parseInt(tempTagBox.css('left')) < image.position().left)
            {
                left = image.position().left;
            }

            tempTagBox.css({top: top, left: left});

            return tempTagBox;
        };

        var hideAllTags = function( image_id )
        {
            $.each(cache.tags[image_id],function()
            {
                $(this).css({'opacity':0.0});
                $(this).hide();
            });
        };

        var showAllTags = function( image_id )
        {
            $.each(cache.tags[image_id],function()
            {
                $(this).show();
            });
        }

        var extendTagBoxAttributes = function( tagBox, tagJSON, image, image_id )
        {
            if(options.tag.flashAfterCreation)
            {
                $(tagBox).css({'opacity':1.0});

                if(!$.browser.msie)
                {
                    $(tagBox).stop().animate({ opacity: 0.0 }, 800);
                }
                else
                {
                    $(tagBox).css({ opacity: 0.0 });
                }
            };
        }

        var prepareImage = function( imageDetailsJSON, image )
        {
            var externalAddLinks = $(options.externalAddTagLinks.selector);
            externalAddLinks.each(function(){
                registerEventsForAddTagLink(this,image, imageDetailsJSON.content_id);
            });

            var cachedInstance = cache.tags[imageDetailsJSON.content_id] = {};

            if (imageDetailsJSON.tags)
            {
                $.each(imageDetailsJSON.tags,function(){
                    var tagBox = createTagBoxFromJSON(this,image);
                    cachedInstance[this.tag_id] = tagBox;
                    $('#' + options.imageWrapBox.idPrefix + imageDetailsJSON.content_id, $(options.imageWrapper)).append(tagBox);
                    extendTagBoxAttributes(tagBox,this,image,imageDetailsJSON.content_id);
                });
            }
        };

        this.each(function(){

            var $this = $(this);

            var parameters = getParametersForImage($this);

            if( !$.isFunction(options.beforeTagRequest) || options.beforeTagRequest(parameters) )
            {
                $.getJSON(
                    options.requestTagsUrl,
                    parameters,
                    function( response )
                    {
                        if(response.result != undefined && !response.result)
                        {
                            manageError(response);
                            return;
                        }

                        if(response.options)
                        {
                            options = $.extend(true,options,response.options);
                        }

                        $.each(response.image,function()
                        {
                            prepareImage(this,$this);
                        });
                    }
                );
            }

        });

        return this;
    };

}(jQuery, this, document);