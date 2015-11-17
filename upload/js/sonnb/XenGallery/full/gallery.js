
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

    XenForo.XenGalleryQuickEdit = function($element)
    {
        var $form = $('#'+$element.data('quickform')),
            $reset = $element.data('reset'),
            $overlay = $form.closest('.galleryOverlay'),
            $closer = $form.find('input.closer');

        $element.click(function(e)
        {
            e.preventDefault();

            if ($form.length)
            {
                $('.quickEditForm').hide();

                $form.find('dl.ctrlUnit').addClass('surplusLabel');

                var w = $(window);

                if ($overlay.length)
                {
                    $form.fadeIn().css({
                        top: Math.max(0, ((w.height() - $form.outerHeight()) / 2)),
                        left: Math.max(0, ((w.width() - $form.outerWidth()) / 2))
                    });
                }
                else
                {
                    $form.fadeIn().css({
                        top: Math.max(0, ((w.height() - $form.outerHeight()) / 2) + w.scrollTop()),
                        left: Math.max(0, ((w.width() - $form.outerWidth()) / 2) + w.scrollLeft())
                    });
                }

                $(document).bind('click', function(clickEvent)
                {
                    var target = clickEvent.target;

                    if (!$element.has(target).length && !$form.has(target).length
                        && target != $element[0] && target != $form[0])
                    {
                        $(this).unbind(clickEvent);

                        $form.hide();
                    }
                });

                $closer.bind('click', function(clickEvent)
                {
                    $closer.unbind(clickEvent);
                    $form.hide();
                });

                if ($form.data('AutoValidator') != true)
                {
                    var $return = new XenForo.AutoValidator($form);

                    $form.data('AutoValidator', true);
                    $form.bind('AutoValidationComplete', function(e){
                        if (e.textStatus == 'success')
                        {
                            $form.hide();

                            if ($reset)
                            {
                                $form.find('input').val();
                            }

                            if (e.ajaxData._redirectMessage)
                            {
                                XenForo.alert(e.ajaxData._redirectMessage, '', 3000);

                                return false;
                            }
                            else if (e.ajaxData.message)
                            {
                                XenForo.alert(e.ajaxData.message, '', 3000);

                                return false;
                            }
                        }
                    });
                }
            };
        });
    };

    XenForo.XenGalleryLikeAlbum = function ($container)
    {
        $container.click(function(e)
        {
            e.preventDefault();

            var $container = $(this);

            XenForo.ajax(this.href, {}, function(ajaxData, textStatus)
            {
                if (XenForo.hasResponseError(ajaxData))
                {
                    return false;
                }

                $container.stop(true, true);

                if (ajaxData.liked)
                {
                    $container.addClass('active');
                }
                else
                {
                    $container.removeClass('active');
                }

                var $likeContainer = $($container.data('container'));

                if (ajaxData.content && typeof(ajaxData.content.likes) != null)
                {
                    $likeContainer.text(ajaxData.content.likes);
                }
            });
        });
    }

    XenForo.WatchLink = function($link)
    {
        $link.click(function(e)
        {
            e.preventDefault();

            var $link = $(this);

            XenForo.ajax(this.href, {}, function(ajaxData, textStatus)
            {
                if (XenForo.hasResponseError(ajaxData))
                {
                    return false;
                }

                $link.stop(true, true);

                if (ajaxData.term)
                {
                    $link.find('.WatchLabel').html(ajaxData.term);

                    if (ajaxData.cssClasses)
                    {
                        $.each(ajaxData.cssClasses, function(className, action)
                        {
                            $link[action == '+' ? 'addClass' : 'removeClass'](className);
                        });
                    }
                }
            });
        });
    };

    XenForo.XenGalleryPhotoWrapper = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryPhotoWrapper.prototype =
    {
        __construct: function($container)
        {
            this.container = $container;
            this.prev = $('.pwPhoto .prevPhoto', $container);
            this.next = $('.pwPhoto .nextPhoto', $container);
            this.comment = $('.pwPhotoActions .action.comment', $container);
            this.like = $('.pwPhotoActions .action.like', $container);
            this.share = $('.pwPhotoActions .action.share', $container);

            var $photoLazy = $('.photo.lazy', $container);
            this.photoLazy = $photoLazy;

            this.photoLazy.bind('load', function(){
                $photoLazy.removeClass('lazy').addClass('loaded').data('src', '').closest('.pwPhoto').addClass('loaded');
            }).bind('error', function(){
                    $photoLazy.parent().addClass('broken');
                }).attr('src', $photoLazy.data('src'));

            this.comment.click($.context(this, 'actionComment'));
            this.like.click($.context(this, 'actionLike'));
            this.share.click($.context(this, 'actionShare'));

            $(document).keyup($.context(this, 'keyPress'));
        },

        keyPress: function (e)
        {
            if (!$('.photoTag-tag').is(':visible') && !$('body > div.xenOverlay').is(':visible'))
            {
                var code = e.keyCode || e.charCode;

                switch(code)
                {
                    case 37: // left
                        e.preventDefault();
                        if (this.prev.hasClass('.hasPhoto'))
                        {
                            window.location = XenForo.canonicalizeUrl(this.prev.attr('href'));
                        }
                        break;
                    case 39: // right
                        e.preventDefault();
                        if (this.prev.hasClass('.hasPhoto'))
                        {
                            window.location = XenForo.canonicalizeUrl(this.next.attr('href'));
                        }
                        break;
                    default:
                        return;
                }
            }
        },

        actionComment: function (e)
        {
            e.preventDefault();

            var $link = $('a.XenGalleryCommentPoster'),
                $commentArea = $($link.data('commentarea'));

            $commentArea.xfFadeDown(XenForo.speed.fast, function()
            {
                $(this).find('textarea[name="message"]').focus();
            });
        },

        actionLike: function (e)
        {
            e.preventDefault();

            var $link = this.like;
            var $secondLink = $('a.LikeLink');

            XenForo.ajax($link.attr('href'), {}, function(ajaxData, textStatus)
            {
                if (XenForo.hasResponseError(ajaxData))
                {
                    return false;
                }

                $link.stop(true, true);

                if (ajaxData.term) // term = Like / Unlike
                {
                    $secondLink.find('.LikeLabel').html(ajaxData.term);

                    if (ajaxData.cssClasses)
                    {
                        $.each(ajaxData.cssClasses, function(className, action)
                        {
                            if (action == '+' && className == 'like')
                            {
                                $link.removeClass('active');
                                return;
                            }

                            if (action == '+' && className == 'unlike')
                            {
                                $link.addClass('active');
                                return;
                            }

                            $secondLink[action == '+' ? 'addClass' : 'removeClass'](className);
                        });
                    }
                }

                if (ajaxData.templateHtml === '')
                {
                    $($secondLink.data('container')).xfFadeUp(XenForo.speed.fast, function()
                    {
                        $(this).empty().xfFadeDown(0);
                    });
                }
                else
                {
                    var $container    = $($secondLink.data('container')),
                        $likeText     = $container.find('.LikeText'),
                        $templateHtml = $(ajaxData.templateHtml);

                    if ($likeText.length)
                    {
                        $likeText.xfFadeOut(50, function()
                        {
                            var textContainer = this.parentNode;

                            $(this).remove();

                            $templateHtml.find('.LikeText').xfInsert('appendTo', textContainer, 'xfFadeIn', 50);
                        });
                    }
                    else
                    {
                        new XenForo.ExtLoader(ajaxData, function()
                        {
                            $templateHtml.xfInsert('appendTo', $container);
                        });
                    }
                }
            });
        },

        actionShare: function(e)
        {
            e.preventDefault();

            var $share = this.share,
                options = {
                    fixed: false,
                    className: 'shareOverlay',
                    onBeforeLoad: function(){
                        $share.addClass('active');
                    },
                    onClose: function(){
                        $share.removeClass('active');
                    }
                };
            options = $.extend(options, this.share.data('overlayoptions'));

            XenForo.createOverlay(null, $('.shareContainer'), options).load();
        }
    }

    XenForo.XenGalleryPhotoShare = function ($element)
    {
        this.__construct($element);
    }

    XenForo.XenGalleryPhotoShare.prototype =
    {
        __construct: function($element)
        {
            this.element = $element;

            $element.click($.context(this, 'actionClick'));
        },

        actionClick: function (e)
        {
            e.preventDefault();
            var $target = $(e.target).parent(),
                link = encodeURIComponent(XenForo.canonicalizeUrl($target.data('url'))),
                thumbnail = encodeURIComponent(XenForo.canonicalizeUrl($target.data('thumbnail')));

            //TODO: Callback after popup windows were closed.
            if ($target.hasClass('google'))
            {
                window.open('http://plus.google.com/share?url='+link,'google','toolbar=0,status=0,width=580,height=325');
            }
            else if ($target.hasClass('facebook'))
            {
                window.open('http://www.facebook.com/sharer/sharer.php?u='+link,'facebook','toolbar=0,status=0,width=580,height=325');
            }
            else if ($target.hasClass('twitter'))
            {
                window.open('https://twitter.com/intent/tweet?url='+link+'&ptext=','twitter','toolbar=0,status=0,width=580,height=325');
            }
            else if ($target.hasClass('tumblr'))
            {
                window.open('//www.tumblr.com/share/photo?click_thru='+link+'&caption=&source='+thumbnail,'tumblr','toolbar=0,status=0,width=580,height=325');
            }
            else if ($target.hasClass('pinterest'))
            {
                window.open('http://pinterest.com/pin/create/button/?url='+link+'&media='+thumbnail,'pinterest','toolbar=0,status=0,width=580,height=325');
            }
        }
    }

    XenForo.XenGalleryListLazyLoad = function ($element)
    {
        $element.each(function(){
            var $ele = $(this);

            $ele.bind('load', function(){
                $ele.removeClass('lazy')
                    .addClass('loaded')
                    .data('src', '')
                    .parent().css({width: 'auto'})
                    .closest('.img').addClass('loaded');

                var height = $ele.height();
                if (height < 110)
                {
                    $ele.css({top: (110-height)/2});
                }
            }).bind('error', function(){
                    $ele.parent().parent().addClass('broken');
                }).attr('src', $ele.data('src'));
        });
    }

    XenForo.XenGalleryContainer = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryContainer.prototype =
    {
        __construct: function($container)
        {
            this.$container = $container;
            this.width = 0;
            this.isNoResize = $($container).data('noresize');

            this.checkWindowSize(null, false);

            if ($container.data('nomasonry'))
            {
                return true;
            }

            if ($container.data('imagesloaded'))
            {
                $container.imagesLoaded(function(){
                    $container.masonry({
                        itemSelector: '.itemGallery',
                        isAnimated: !Modernizr.csstransitions,
                        easing: 'linear',
                        gutterWidth: 10
                    });
                });
            }
            else
            {
                $container.masonry({
                    itemSelector: '.itemGallery',
                    isAnimated: !Modernizr.csstransitions,
                    easing: 'linear',
                    gutterWidth: 10
                });
            }

            $(window).bind('orientationchange resize', $.context(this, 'windowResize'));

            if (!$container.data('noautoscroll'))
            {
                var $this = this;

                $container.infinitescroll({
                        navSelector  : '.xengallery .PageNav nav > a.currentPage',
                        nextSelector : '.xengallery .PageNav nav > a:last-child',
                        itemSelector : '.itemGallery',
                        extraScrollPx: 50,
                        bufferPx: 1000,
                        localMode    : true,
                        loading: {
                            msgText: $container.data('loading'),
                            finishedMsg: $container.data('finish'),
                            img: $container.data('ajax')
                        },
                        dataType: 'html',
                        maxPage: $('.xengallery .PageNav').data('last')
                    },
                    function( newElements )
                    {
                        var $newElems = $( newElements ).css({ opacity: 0 });

                        if ($container.data('imagesloaded'))
                        {
                            $newElems.imagesLoaded(function(){
                                $newElems.animate({ opacity: 1 });
                                $newElems.addClass('__XenForoActivator').xfActivate();
                                $container.masonry('appended', $newElems, true, function(){
                                    $this.checkWindowSize(null, true);
                                    $container.masonry();
                                });
                            });
                        }
                        else
                        {
                            $newElems.animate({ opacity: 1 });
                            $newElems.addClass('__XenForoActivator').xfActivate();
                            $container.masonry('appended', $newElems, true, function(){
                                $this.checkWindowSize(null, true);
                                $container.masonry();
                            });
                        }
                    }
                );
            }
        },

        windowResize: function (e)
        {
            if (!this.isNoResize)
            {
                this.checkWindowSize(null, false);
            }

            this.$container.masonry();
        },

        checkWindowSize: function(windowSize, force)
        {
            if (windowSize === null)
            {
                windowSize = $(window).width();
            }

            if (this.isNoResize)
            {
                return false;
            }

            if (this.width === windowSize && force === false)
            {
                return true;
            }

            this.width = windowSize;

            var $items = $('.itemGallery', this.$container),
                containerSize = this.$container.outerWidth(),
                marginRight = parseInt($items.css('margin-right')),
                itemSize = 0;

            if (marginRight < 1)
            {
                marginRight = 10;
            }

            if (this.width <= 320 && containerSize <= 320)
            {
                itemSize = Math.floor(containerSize);
                $items.width(itemSize);
            }
            else if (this.width <= 500 && containerSize <= 500)
            {
                itemSize = Math.floor((containerSize - marginRight)/2);
                $items.width(itemSize);
            }
            /*
             else if (this.width <= 700 && containerSize <= 700)
             {
             itemSize = Math.floor((containerSize - marginRight*2)/3);
             $items.width(itemSize);
             }
             else if (this.width <= 900 && containerSize <= 900)
             {
             itemSize = Math.floor((containerSize - marginRight*3)/4);
             $items.width(itemSize);
             }
             */
            else
            {
                var itemNum = Math.floor(containerSize/200);
                itemSize = Math.floor((containerSize - (itemNum-1)*marginRight)/itemNum);

                $items
                    .width(itemSize)
                    .css({'min-width': '0', 'max-width': 'none'}).
                    find('img').css('max-width', '100%');
            }

            if (itemSize > 0)
            {
                $items.each(function(e){
                    var $img = $('div.img', $(this)),
                        $a = $('a', $img),
                        realImageWidth = $img.data('width'),
                        realImageHeight = $img.data('height'),
                        aHeight = Math.floor(realImageHeight*(itemSize/realImageWidth));

                    if (realImageWidth > itemSize)
                    {
                        aHeight = Math.floor(realImageHeight*(itemSize/realImageWidth));
                    }
                    else
                    {
                        aHeight = Math.floor(realImageHeight);
                    }

                    if (aHeight < 110)
                    {
                        aHeight = 110;
                    }

                    $a.css({
                        'height': aHeight,
                        'max-height': aHeight
                    });
                });
            }
        }
    }

    XenForo.XenGalleryStreamEditor = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryStreamEditor.prototype =
    {
        __construct: function($container)
        {
            this.container = $container;
            this.streamList = $('.streamList ul', $container);
            this.form = $('form.xenForm', $container);
            this.input = $('input[name="stream_name"]', this.form);

            $('.streamItem a.delete').bind('click', $.context(this, 'onDelete'));

            this.form.bind('submit', $.context(this, 'onFormSubmit'));
            this.form.find('input[type="submit"]').click($.context(this, 'onSubmitClick'));
        },

        onFormSubmit: function (e)
        {
            e.preventDefault();
            var clickedSubmitButton = this.form.data('clickedsubmitbutton'),
                serialized,
                $clickedSubmitButton;

            if (!this.input.val())
            {
                return true;
            }

            this.form.removeData('clickedsubmitbutton');
            serialized = this.form.serializeArray();
            if (clickedSubmitButton)
            {
                $clickedSubmitButton = $(clickedSubmitButton);
                if ($clickedSubmitButton.attr('name'))
                {
                    serialized.push({
                        name: $clickedSubmitButton.attr('name'),
                        value: $clickedSubmitButton.attr('value')
                    });
                }
            }

            XenForo.ajax(
                this.form.attr('action'),
                serialized,
                $.context(this, 'onDataReceived')
            );
        },

        onSubmitClick: function (e)
        {
            this.form.data('clickedsubmitbutton', e.target);
        },

        onDataReceived: function (ajaxData, textStatus)
        {
            var $target = this.streamList,
                $input = this.input;

            if (!ajaxData)
            {
                console.warn('No ajax data returned.');
                return false;
            }

            if (XenForo.hasResponseError(ajaxData))
            {
                return false;
            }

            if (ajaxData.streams)
            {
                $.each(ajaxData.streams, function(streamName, streamHtml)
                {
                    $(streamHtml).xfInsert('appendTo', $target, 'xfFadeIn', 0);
                });

                $input.val('');
            }
            else
            {
                console.warn('No valid ajax data returned.');
            }

            return false;
        },

        onDelete: function (e)
        {
            e.preventDefault();

            var $target = $(e.target);

            XenForo.ajax(
                $target.attr('href'),
                {},
                function(ajaxData, textStatus)
                {
                    if (XenForo.hasResponseError(ajaxData))
                    {
                        return false;
                    }

                    if (XenForo.hasTemplateHtml(ajaxData) || ajaxData.message || ajaxData.stream)
                    {
                        $target.closest('li').fadeOut().remove();
                    }
                }
            );
        }
    }

    XenForo.XenGalleryStreamEditorToggle = function($link)
    {
        var $form = $('.streamingEditor .xenForm');

        $link.click(function(e){
            e.preventDefault();

            $form.show();
            $(this).fadeOut();
        });
    }

    XenForo.XenGalleryStreamJump = function($form)
    {
        $form.submit(function(e){
            e.preventDefault();

            if (!$('input[name="stream_name"]', $form).val())
            {
                return false;
            }

            XenForo.ajax(
                $form.attr('action'),
                $form.serializeArray(),
                function (ajaxData, textStatus)
                {
                    if (XenForo.hasResponseError(ajaxData))
                    {
                        return false;
                    }

                    if (!ajaxData.valid)
                    {
                        XenForo.alert(
                            ajaxData.errorPhrase,
                            XenForo.phrases.following_error_occurred + ':'
                        );
                    }
                    else
                    {
                        url = XenForo.canonicalizeUrl(ajaxData.link);

                        if (url == window.location.href)
                        {
                            window.location.reload();
                        }
                        else
                        {
                            window.location = url;
                        }
                    }
                }
            );
        });
    }

    XenForo.XenGalleryCommentLoader = function($element) { this.__construct($element); };
    XenForo.XenGalleryCommentLoader.prototype =
    {
        __construct: function($link)
        {
            this.$link = $link;

            $link.click($.context(this, 'click'));
        },

        click: function(e)
        {
            var params = this.$link.data('loadparams');

            if (typeof params != 'object')
            {
                params = {};
            }

            e.preventDefault();

            XenForo.ajax(
                this.$link.attr('href'),
                params,
                $.context(this, 'loadSuccess'),
                { type: 'GET' }
            );
        },

        loadSuccess: function(ajaxData)
        {
            var $replace,
                replaceSelector = this.$link.data('replace'),
                i;

            if (replaceSelector)
            {
                $replace = $(replaceSelector);
            }
            else
            {
                $replace = this.$link.parent();
            }

            if (XenForo.hasResponseError(ajaxData))
            {
                $replace.xfRemove();
                return false;
            }

            if (ajaxData.comments && ajaxData.comments.length)
            {
                for (i = 0; i < ajaxData.comments.length; i++)
                {
                    $(ajaxData.comments[i]).xfInsert('insertBefore', $replace);
                }
            }

            $replace.xfRemove();
        }
    };

    XenForo.XenGalleryCommentPoster = function($element) { this.__construct($element); };
    XenForo.XenGalleryCommentPoster.prototype =
    {
        __construct: function($link)
        {
            this.$link = $link;
            this.$parent = $link.closest('.commentWrapper');
            this.$allCommentArea = this.$commentArea;

            var commentArea = $link.data('commentarea');
            this.$commentArea = $(commentArea, this.$parent);

            if (commentArea.indexOf('#') >= 0)
            {
                commentArea = commentArea.replace('#', '');
                this.$allCommentArea = $('[id="'+commentArea+'"]');
            }

            if (this.$commentArea.data('submiturl'))
            {
                this.submitUrl = this.$commentArea.data('submiturl');
            }
            else
            {
                this.submitUrl = $link.attr('href');
            }

            $link.click($.context(this, 'click'));

            this.$commentArea.find('input:submit, button').click($.context(this, 'submit'));
        },

        click: function(e)
        {
            e.preventDefault();

            this.$commentArea.xfFadeDown(XenForo.speed.fast, function()
            {
                $(this).find('textarea[name="message"]').focus();
            });
        },

        submit: function(e)
        {
            e.preventDefault();

            var params = this.$link.data('postparams'),
                $form = this.$commentArea.closest('form:not(.InlineModForm)');

            if (typeof params != 'object')
            {
                params = {};
            }

            params.message = this.$commentArea.find('textarea[name="message"]').val();

            if ($form.length)
            {
                if (!$form.data('multisubmitdisable'))
                {
                    XenForo.MultiSubmitFix($form);
                }

                $form.data('multisubmitdisable')();
            }

            XenForo.ajax(
                this.submitUrl,
                params,
                $.context(this, 'submitSuccess')
            );
        },

        submitSuccess: function(ajaxData)
        {
            var $form = this.$commentArea.closest('form'),
                i;

            if ($form.data('multisubmitenable'))
            {
                $form.data('multisubmitenable')();
            }

            if (XenForo.hasResponseError(ajaxData))
            {
                return false;
            }

            if (ajaxData.comments && ajaxData.comments.length)
            {
                for (i = 0; i < ajaxData.comments.length; i++)
                {
                    $(ajaxData.comments[i]).xfInsert('insertBefore', this.$allCommentArea, 'slideDown', XenForo.speed.normal);
                }
            }

            $('a.XenGalleryCommentPoster').data('postparams', {after: ajaxData.lastShownCommentDate});
            this.$commentArea.find('textarea[name="message"]').val('');
        }
    };

    XenForo.XenGalleryCommentInlineEdit = function ($editLink)
    {
        this.__construct($editLink);
    }

    XenForo.XenGalleryCommentInlineEdit.prototype =
    {
        __construct: function($editLink)
        {
            this.container = $editLink;///
            this.$trigger = $editLink.click($.context(this, 'show'));
        },

        show: function(e)
        {
            var parentOverlay = this.$trigger.closest('.xenOverlay').data('overlay'),
                cache,
                options;

            if (parentOverlay && parentOverlay.isOpened())
            {
                parentOverlay.getTrigger().one('onClose', $.context(this, 'show'));
                parentOverlay.getConf().mask.closeSpeed = 0;
                parentOverlay.close();
                return;
            }

            if (!this.OverlayLoader)
            {
                options = {};
                options = $.extend(options, this.$trigger.data('overlayoptions'));
                cache = this.$trigger.data('cacheoverlay');

                if (cache !== undefined)
                {
                    if (XenForo.isPositive(cache))
                    {
                        cache = true;
                    }
                    else
                    {
                        cache = false;
                        options.onClose = $.context(this, 'deCache');
                    }
                }
                else
                {
                    cache = false;
                    options.onClose = $.context(this, 'deCache');
                }

                this.OverlayLoader = new XenForo.OverlayLoader(this.$trigger, cache, options);
                this.OverlayLoader.load();

                e.preventDefault();
                return true;
            }

            this.OverlayLoader.show();
        },

        deCache: function()
        {
            if (this.OverlayLoader && this.OverlayLoader.overlay)
            {
                console.info('DeCache %o', this.OverlayLoader.overlay.getOverlay());
                this.OverlayLoader.overlay.getTrigger().removeData('overlay');
                this.OverlayLoader.overlay.getOverlay().empty().remove();
            }

            delete(this.OverlayLoader);
        }
    }

    XenForo.XenGalleryInlineCommentEditor = function($form)
    {
        new XenForo.AutoValidator($form);
        $form.bind(
            {
                AutoValidationComplete: function(e)
                {
                    var overlay = $form.closest('div.xenOverlay').data('overlay'),
                        $trigger = overlay.getTrigger();

                    if (XenForo.hasTemplateHtml(e.ajaxData, 'messagesTemplateHtml') || XenForo.hasTemplateHtml(e.ajaxData))
                    {
                        e.preventDefault();

                        $trigger.closest('.commentInfo').find('.commentContent article blockquote').html(e.ajaxData._message);
                        overlay.close();
                    }
                }
            });
    }

    XenForo.XenGalleryInlineCommentDelete = function($form)
    {
        $form.bind(
            {
                AutoValidationComplete: function(e)
                {
                    var overlay = $form.closest('div.xenOverlay').data('overlay'),
                        $trigger = overlay.getTrigger(),
                        $container = $trigger.closest('li.comment');

                    if (XenForo.hasTemplateHtml(e.ajaxData, 'messagesTemplateHtml') || XenForo.hasTemplateHtml(e.ajaxData))
                    {
                        e.preventDefault();

                        overlay.close();

                        if (e.ajaxData.hardDelete)
                        {
                            $container.slideUp().remove();
                            XenForo.alert(e.ajaxData.phrase, '', 2000);
                        }
                        else
                        {
                            $(e.ajaxData.messagesTemplateHtml).xfInsert('replaceAll', $container, 'xfFadeIn', XenForo.speed.fast);
                        }
                    }
                }
            });
    }

    XenForo.XenGalleryOverlayPhotoTagging = function ($container)
    {
        $container.photoTag({
            requestTagsUrl: $container.data('tagrequesturl'),
            deleteTagsUrl: $container.data('tagdeleteurl'),
            addTagUrl: $container.data('tagaddurl'),
            parametersForRequest: ['_xfNoRedirect'],
            imageWrapper: $container.data('imagewrapper'),
            externalAddTagLinks: {
                bind: true
            },
            parametersForNewTag: {
                username: {
                    label: '',
                    _xfToken: XenForo._csrfToken
                }
            },
            literals: {
                saveTag: $container.data('savephrase'),
                cancelTag: $container.data('cancelphrase')
            }
        });
    }

    XenForo.XenGalleryRelatedPhotos = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryRelatedPhotos.prototype =
    {
        __construct: function($container)
        {
            this.$container = $container;
            $container.scrollable({
                mousewheel: false,
                keyboard: false
            });

            //TODO:
            /*
             var $api = this.$api,
             $currentItem = $('a.active', $container).index();

             $api = $container.data('scrollable');
             $api.seekTo($currentItem, 50);

             $(window).resize($.context(function() {
             $api.seekTo($currentItem, 50);
             }));
             */
        }
    }

    XenForo.register('img.photo', 'XenForo.XenGalleryOverlayPhotoTagging');
    XenForo.register('.toolAlbum .likeAlbum .control.like', 'XenForo.XenGalleryLikeAlbum');
    XenForo.register('.item.quickEdit', 'XenForo.XenGalleryQuickEdit');
    XenForo.register('.photoWrapper', 'XenForo.XenGalleryPhotoWrapper');
    XenForo.register('a.WatchLink', 'XenForo.WatchLink');
    XenForo.register('.shareList .share-action', 'XenForo.XenGalleryPhotoShare');
    XenForo.register('.masonryContainer', 'XenForo.XenGalleryContainer');
    XenForo.register('.streamingEditor', 'XenForo.XenGalleryStreamEditor');
    XenForo.register('.streamingHeader .editToggle, .streaming .editToggle', 'XenForo.XenGalleryStreamEditorToggle');
    XenForo.register('.cloudForm .xenForm', 'XenForo.XenGalleryStreamJump');
    XenForo.register('a.XenGalleryCommentLoader', 'XenForo.XenGalleryCommentLoader');
    XenForo.register('a.XenGalleryCommentPoster', 'XenForo.XenGalleryCommentPoster');
    XenForo.register('a.item.edit:not(.NoOverlay), a.item.delete:not(.NoOverlay)', 'XenForo.XenGalleryCommentInlineEdit');
    XenForo.register('form.XenGalleryInlineCommentEditor', 'XenForo.XenGalleryInlineCommentEditor');
    XenForo.register('form.XenGalleryInlineCommentDelete', 'XenForo.XenGalleryInlineCommentDelete');
    XenForo.register('.scrollable', 'XenForo.XenGalleryRelatedPhotos');
    XenForo.register('.img img.lazy', 'XenForo.XenGalleryListLazyLoad');

}(jQuery, this, document);