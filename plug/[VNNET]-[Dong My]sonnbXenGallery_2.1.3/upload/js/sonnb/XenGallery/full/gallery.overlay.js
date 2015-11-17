
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

    XenForo.XenGalleryOverlayToggle = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryOverlayToggle.prototype =
    {
        __construct: function($toggle)
        {
            this.$toggle = $toggle;
            this.$trigger = $toggle.click($.context(this, 'show'));
            this.$wrapper = null;

            if (typeof window.galleryOverlayXhr === "undefined")
            {
                window.galleryOverlayXhr = false;
            }

            if (!$('body > .galleryOverlay').length)
            {
                window.currentUrl = window.location.href;
            }
        },

        show: function(e)
        {
            if (window.galleryOverlayXhr)
            {
                return false;
            }

            var target = this.$toggle.attr('href');

            window.galleryOverlayXhr = XenForo.ajax(
                target,
                {},
                $.context(this, 'onDataReceived'),
                { type: 'GET' }
            );

            return false;
        },

        onDataReceived: function (ajaxData, textStatus)
        {
            if (!ajaxData)
            {
                return false;
            }

            if (XenForo.hasResponseError(ajaxData))
            {
                return false;
            }

            if (ajaxData.templateHtml)
            {
                new XenForo.ExtLoader(ajaxData, $.context(this, 'createOverlay'));

                var target = this.$toggle.attr('href');

                if (window.history.pushState)
                {
                    window.history.pushState(
                        {
                            target: target
                        },
                        this.$toggle.attr('title'),
                        target
                    );
                }
            }
            else
            {
                console.warn('No valid ajax data returned.');
            }

            return false;
        },

        createOverlay: function (ajaxData)
        {
            this.$wrapper = $('<div class="galleryOverlay __XenForoActivator" />');
            this.$wrapper.css({
                position: 'fixed',
                top: '0',
                left: '0',
                right: '0',
                bottom: '0',
                'background-color' : 'rgba(0, 0, 0, 0.75)',
                'z-index': '400'
            });

            $('div.galleryOverlay').each(function(e){
                var $exist = $(this);

//                $('body').css({
//                    position: 'relative',
//                    top: ''
//                });
                $('.shareContainerOverlay').closest('.shareOverlay').empty().remove();
                $exist.find('object').remove();
                $exist.fadeOut(
                    'slow',
                    function(){
                        $exist.empty().remove()
                    }
                );
            });

            var $templateHtml = $(ajaxData.templateHtml),
                windowHeight = window.innerHeight,
                windowWidth = window.innerWidth,
                $mediaHolder = $('.goMediaHolder', $templateHtml),
                $wrapper = this.$wrapper,
                $mediaContainer = $('.goMediaContainer', $templateHtml),
                $commentWrapper = $('.goCommentWrapper', $templateHtml),
                $media = $('img.photo', $templateHtml).length ?  $('img.photo', $templateHtml) : $('.goMediaContainer .videoHolder', $templateHtml),
                maxHeight = $media.data('height'),
                maxWidth = $media.data('width'),
                height,
                width;

            if ($mediaHolder.hasClass('hasRelated') && windowWidth > 370 && windowHeight > 370)
            {
                height = windowHeight - 110;
            }
            else
            {
                if (windowWidth > 370 && windowHeight > 370)
                {
                    height = windowHeight - 40;
                }
                else
                {
                    height = windowHeight;
                }
            }
            width = (height/$media.data('height'))*$media.data('width');

            $mediaHolder.css({
                height: height +'px',
                'line-height': height + 'px'
            });

            if ($.getCookie('xengallery_comment_sidebar') ==  null)
            {
                $.setCookie('xengallery_comment_sidebar', 1);
            }

            if ($mediaHolder.hasClass('video'))
            {
                /*
                height = height - 28; //action bar

                var setHeight = height,
                    setWidth = (height/maxHeight)*maxWidth,
                    holderWidth = windowWidth;

                if (parseInt($.getCookie('xengallery_comment_sidebar')) || windowWidth > 710)
                {
                    holderWidth = windowWidth - 330;
                }

                if (setWidth > holderWidth)
                {
                    setWidth = holderWidth;
                    setHeight = (setWidth/maxWidth)*maxHeight - 28; //action bar
                }

                $mediaContainer.css({
                    height: setHeight +'px',
                    'line-height': setHeight +'px',
                    width: setWidth +'px',
                    top: (height > setHeight ? (height - setHeight)/2 : '0') +'px'
                });
                 */

                if ($mediaContainer.find('iframe').length)
                {
                    $mediaContainer.find('iframe').attr('height', height-34);
                }
                else
                {
                    $mediaContainer.find('object').attr('height', height-34);
                    $mediaContainer.find('embed').attr('height', height-34);
                }
            }
            else
            {
                $mediaContainer.css({
                    height: (height > maxHeight ? maxHeight : height) +'px',
                    'line-height': (height > maxHeight ? maxHeight : height) +'px',
                    width: (width > maxWidth ? maxWidth : width) +'px',
                    top: (height > maxHeight ? (height - maxHeight)/2 : '0') +'px'
                });
            }

            if (!parseInt($.getCookie('xengallery_comment_sidebar')) || windowWidth < 710)
            {
                var $goMedia = $('.goMedia', $templateHtml),
                    $closeBtn = $($templateHtml[2]),
                    $regularBtn = $($templateHtml[0]),
                    $commentAction = $('.pwPhotoActions .action.comment', $templateHtml);

                $commentWrapper.hide();
                $commentAction.removeClass('active');
                $goMedia.addClass('noSidebar');
                $closeBtn.addClass('noSidebar');
                $regularBtn.addClass('noSidebar');
            }
            else
            {
                $commentWrapper.show();
                $('.pwPhotoActions .action.comment', $templateHtml).addClass('active');
            }

            $wrapper.append($templateHtml).appendTo('body').xfActivate();

//            $('body').css({
//                position: 'fixed',
//                top: '-'+$(window).scrollTop()+'px'
//            });
            $('body').addClass('noscroll');
            $('.OverlayCloser', this.$wrapper).click($.context(this, 'closeOverlay'));
            $(window).keyup($.context(this, 'keyPress'));
            $(window).bind('orientationchange resize', $.context(this, 'onResize'));

            var $photoLazy = $('.photo.lazy', this.$wrapper);
            $photoLazy.bind('load', function(){
                $photoLazy.removeClass('lazy').addClass('loaded').data('src', '').parent().addClass('loaded');
            }).bind('error', function(){
                    $photoLazy.parent().addClass('broken');
                    $mediaContainer.css({top: (height - 32)/2 +'px'});
                }).attr('src', $photoLazy.data('src'));

            $photoLazy.mousedown(function(){
                return false;
            }).click($.context(this, 'clickOnPhoto'));

            $('.pwPhotoActions .action.like', this.$wrapper).click($.context(this, 'actionLike'));
            $('.pwPhotoActions .action.share', this.$wrapper).click($.context(this, 'actionShare'));
            $('.pwPhotoActions .action.comment', this.$wrapper).click($.context(this, 'actionCommentToggle'));
            $('.goActions .action.share', this.$wrapper).click($.context(this, 'actionShare'));

            if (XenForo.isRTL())
            {
                $('.goMediaHolder', this.$wrapper).bind('swipeleft', $.context(this, 'actionSwipePrev'));
                $('.goMediaHolder', this.$wrapper).bind('swiperight', $.context(this, 'actionSwipeNext'));
                $photoLazy.bind('swipeleft', $.context(this, 'actionSwipePrev'));
                $photoLazy.bind('swiperight', $.context(this, 'actionSwipeNext'));
            }
            else
            {
                $('.goMediaHolder', this.$wrapper).bind('swiperight', $.context(this, 'actionSwipePrev'));
                $('.goMediaHolder', this.$wrapper).bind('swipeleft', $.context(this, 'actionSwipeNext'));
                $photoLazy.bind('swiperight', $.context(this, 'actionSwipePrev'));
                $photoLazy.bind('swipeleft', $.context(this, 'actionSwipeNext'));
            }

            $('.goCommentWrapper', this.$wrapper).bind('swiperight', $.context(this, 'actionSwipeRight'));

            if (window.galleryOverlayXhr)
            {
                window.galleryOverlayXhr = false;
            }

            return false;
        },

        onResize: function (e)
        {
            this.$wrapper.css({
                top: '0',
                left: '0',
                right: '0',
                bottom: '0'
            });

            var windowHeight = window.innerHeight,
                windowWidth = window.innerWidth,
                $mediaHolder = $('.goMediaHolder', this.$wrapper),
                $mediaContainer = $('.goMediaContainer', this.$wrapper),
                $commentWrapper = $('.goCommentWrapper', this.$wrapper),
                $media = $('img.photo', this.$wrapper).length ?  $('img.photo', this.$wrapper) : $('.goMediaContainer .videoHolder', this.$wrapper),
                maxHeight = $media.data('height'),
                maxWidth = $media.data('width'),
                height,
                width;

            if ($mediaHolder.hasClass('hasRelated') && windowWidth > 370 && windowHeight > 370)
            {
                height = windowHeight - 110;
            }
            else
            {
                if (windowWidth > 370 && windowHeight > 370)
                {
                    height = windowHeight - 40;
                }
                else
                {
                    height = windowHeight;
                }
            }

            width = (height/$media.data('height'))*$media.data('width');

            $mediaHolder.css({
                height: height +'px',
                'line-height': height + 'px'
            });

            if ($mediaHolder.hasClass('video'))
            {
                /*
                height = height - 28; //action bar

                var setHeight = height,
                    setWidth = (height/maxHeight)*maxWidth,
                    holderWidth = windowWidth;

                if ($commentWrapper.css('display') == 'block')
                {
                    holderWidth = windowWidth - 330;
                }

                if (setWidth > holderWidth)
                {
                    setWidth = holderWidth;
                    setHeight = (setWidth/maxWidth)*maxHeight - 28; //action bar
                }

                $mediaContainer.css({
                    height: setHeight +'px',
                    'line-height': setHeight +'px',
                    width: setWidth +'px',
                    top: (height > setHeight ? (height - setHeight)/2 : '0') +'px'
                });
                 */

                if ($mediaContainer.find('iframe').length)
                {
                    $mediaContainer.find('iframe').attr('height', height-34);
                }
                else
                {
                    $mediaContainer.find('object').attr('height', height-34);
                    $mediaContainer.find('embed').attr('height', height-34);
                }
            }
            else
            {
                $mediaContainer.css({
                    height: (height > maxHeight ? maxHeight : height) +'px',
                    'line-height': (height > maxHeight ? maxHeight : height) +'px',
                    width: (width > maxWidth ? maxWidth : width) +'px',
                    top: (height > maxHeight ? (height - maxHeight)/2 : '0') +'px'
                });

                if ($mediaContainer.hasClass('broken'))
                {
                    $mediaContainer.css({top: (height - 32)/2 +'px'});
                }
            }
        },

        clickOnPhoto: function(e)
        {
            var isMobile = !$('html').hasClass('desktop'),
                $mediaHolder = $('.goMediaHolder', this.$wrapper),
                $description = $('.caption', $mediaHolder),
                $title = $('.goActions .title', this.$wrapper),
                $btnClose = $('.OverlayCloser', this.$wrapper),
                $btnOriginal = $('.originalLink', this.$wrapper);

            if ($description.is(':visible'))
            {
                $description.fadeOut();

                if (isMobile)
                {
                    $btnClose.hide();
                    $btnOriginal.hide();
                }
            }
            else
            {
                $description.fadeIn();

                $btnClose.show();
                $btnOriginal.show();
            }
        },

        keyPress: function (e)
        {
            var code = e.keyCode || e.charCode;

            if (code == 27 && $('div.galleryOverlay').is(':visible'))
            {
                this.closeOverlay(e);
            }

            if (!$('.photoTag-cpanel', this.$wrapper).is(':visible') && !$('body > div.xenOverlay').is(':visible'))
            {
                switch(code)
                {
                    case 37: // left
                        e.preventDefault();
                        $('.prevPhoto.hasPhoto', this.$wrapper).trigger('click');
                        return false;
                        break;
                    case 39: // right
                        e.preventDefault();
                        $('.nextPhoto.hasPhoto', this.$wrapper).trigger('click');
                        return false;
                        break;
                    default:
                        return true;
                }
            }
        },

        closeOverlay: function(e)
        {
            var $wrapper = this.$wrapper;

//            $('body').css({
//                position: 'relative'
//            });
            $('body').removeClass('noscroll');
            $('.shareContainerOverlay').closest('.shareOverlay').empty().remove();
            $wrapper.find('object').remove();
            $wrapper.fadeOut(
                'slow',
                function(){
                    $wrapper.empty().remove()
                }
            );

            if (window.currentUrl && window.history.pushState)
            {
                window.history.pushState(
                    {
                        target: window.currentUrl
                    },
                    null,
                    window.currentUrl
                );
            }
        },

        actionSwipePrev: function(e)
        {
            console.log('actionSwipePrev');
            $('.prevPhoto.hasPhoto', this.$wrapper).trigger('click');

            return false;
        },

        actionSwipeNext: function(e)
        {
            $('.nextPhoto.hasPhoto', this.$wrapper).trigger('click');

            return false;
        },

        actionSwipeRight: function(e)
        {
            var $class = this,
                $this = $('.pwPhotoActions .action.comment', this.$wrapper),
                $commentWrapper = $('.goCommentWrapper', this.$wrapper),
                $goMedia = $('.goMedia', this.$wrapper),
                $closeBtn = $('.OverlayCloser', this.$wrapper),
                $regularBtn = $('.originalLink', this.$wrapper);

            if ($this.hasClass('active') || $commentWrapper.is(':visible'))
            {
                $commentWrapper.fadeOut('fast', function(){
                    $this.removeClass('active');
                    $.setCookie('xengallery_comment_sidebar', 0);
                    $goMedia.addClass('noSidebar');
                    $closeBtn.addClass('noSidebar');
                    $regularBtn.addClass('noSidebar');

                    $class.onResize(e);
                });
            }

            return false;
        },

        actionCommentToggle: function (e)
        {
            e.preventDefault();

            var $class = this,
                $this = $(e.target),
                $commentWrapper = $('.goCommentWrapper', this.$wrapper),
                $goMedia = $('.goMedia', this.$wrapper),
                $closeBtn = $('.OverlayCloser', this.$wrapper),
                $regularBtn = $('.originalLink', this.$wrapper);

            if ($this.is('i'))
            {
                $this = $this.parent();
            }

            if ($this.hasClass('active'))
            {
                $commentWrapper.fadeOut('fast', function(){
                    $.setCookie('xengallery_comment_sidebar', 0);
                    $this.removeClass('active');
                    $goMedia.addClass('noSidebar');
                    $closeBtn.addClass('noSidebar');
                    $regularBtn.addClass('noSidebar');

                    $class.onResize(e);
                });
            }
            else
            {
                $.setCookie('xengallery_comment_sidebar', 1);
                $this.addClass('active');
                $goMedia.removeClass('noSidebar');
                $closeBtn.removeClass('noSidebar');
                $regularBtn.removeClass('noSidebar');
                $commentWrapper.show();

                $class.onResize(e);
            }

            return false;
        },

        actionLike: function (e)
        {
            e.preventDefault();

            var $link = $('.pwPhotoActions .action.like', this.$wrapper);
            var $secondLink = $('a.LikeLink', this.$wrapper);

            XenForo.ajax($link.attr('href'), {}, function(ajaxData, textStatus)
            {
                if (XenForo.hasResponseError(ajaxData))
                {
                    return false;
                }

                $link.stop(true, true);

                if (ajaxData.term)
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

                if (ajaxData.templateHtml == '')
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

            var $share = $('.goMedia .action.share', this.$wrapper),
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

            options = $.extend(options, $share.data('overlayoptions'));

            XenForo.createOverlay(null, $('.shareContainerOverlay'), options).load();
        }
    }

    XenForo.XenGalleryPopupMenu = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryPopupMenu.prototype =
    {
        __construct: function($container)
        {
            $container.find('ul.Menu1').addClass('Menu');

            var $api = new XenForo.PopupMenu($container);

            $api.triggersMenuHide = function(e)
            {
                return true;
            };

            this.$container = $container;
        }
    }

    XenForo.register('.img a.hasOverlay, .action.fullscreen, .goMedia .scrollable .items > a, .goMedia .prevPhoto.hasPhoto, .goMedia .nextPhoto.hasPhoto', 'XenForo.XenGalleryOverlayToggle');
    XenForo.register('.goWrapper .goPopup', 'XenForo.XenGalleryPopupMenu');

}(jQuery, this, document);