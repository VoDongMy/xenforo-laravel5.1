/*!
 * jQuery imagesLoaded plugin v2.1.2
 * http://github.com/desandro/imagesloaded
 *
 * MIT License. by Paul Irish et al.
 */

/*jshint curly: true, eqeqeq: true, noempty: true, strict: true, undef: true, browser: true */
/*global jQuery: false */

;(function($, undefined) {
    //'use strict';

    // blank image data-uri bypasses webkit log warning (thx doug jones)
    //var BLANK = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    var BLANK = window.console ? 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' : window.location.href+'#';

    $.fn.imagesLoaded = function( callback ) {
        var $this = this,
            deferred = $.isFunction($.Deferred) ? $.Deferred() : 0,
            hasNotify = $.isFunction(deferred.notify),
            $images = $this.find('img').add( $this.filter('img') ),
            loaded = [],
            proper = [],
            broken = [];

        // some browsers (IE9) fail to trigger image loaded when using cached images
        // This flag is so we can account for this dumb MSIE situation.
        var can_use_cached_images = confirm_can_use_cached_images();

        function confirm_can_use_cached_images() {
            var result = 'Good browser';
            var arr_known_browsers_with_issues = ['MSIE 10.0', 'MSIE 9.0'];

            // block to check for specific know buggy browsers.
            if (window.navigator.appName.match(/Microsoft/)) {
                result = window.navigator.appVersion.match(/MSIE [^;/]+/);
                if(result)
                {
                    result = result[0];
                }
            }

            return ($.inArray(result, arr_known_browsers_with_issues) == -1);  // -1 means no match found.
        }

        // Register deferred callbacks
        if ($.isPlainObject(callback)) {
            $.each(callback, function (key, value) {
                if (key === 'callback') {
                    callback = value;
                } else if (deferred) {
                    deferred[key](value);
                }
            });
        }

        function doneLoading() {
            var $proper = $(proper),
                $broken = $(broken);

            if ( deferred ) {
                if ( broken.length ) {
                    deferred.reject( $images, $proper, $broken );
                } else {
                    deferred.resolve( $images );
                }
            }

            if ( $.isFunction( callback ) ) {
                callback.call( $this, $images, $proper, $broken );
            }
        }

        function imgLoadedHandler( event ) {
            imgLoaded( event.target, event.type === 'error' );
        }

        function imgLoaded( img, isBroken ) {
            // don't proceed if BLANK image, or image is already loaded
            if ( img.src === BLANK || $.inArray( img, loaded ) !== -1 ) {
                return;
            }

            // store element in loaded images array
            loaded.push( img );

            // keep track of broken and properly loaded images
            if ( isBroken ) {
                broken.push( img );
            } else {
                proper.push( img );
            }

            // cache image and its state for future calls
            $.data( img, 'imagesLoaded', { isBroken: isBroken, src: img.src } );

            // trigger deferred progress method if present
            if ( hasNotify ) {
                deferred.notifyWith( $(img), [ isBroken, $images, $(proper), $(broken) ] );
            }

            // call doneLoading and clean listeners if all images are loaded
            if ( $images.length === loaded.length ) {
                setTimeout( doneLoading );
                $images.unbind( '.imagesLoaded', imgLoadedHandler );
            }
        }

        // if no images, trigger immediately
        if ( !$images.length ) {
            doneLoading();
        } else {
            $images.bind( 'load.imagesLoaded error.imagesLoaded', imgLoadedHandler )
                .each( function( i, el ) {
                    var src = el.src;

                    // find out if this image has been already checked for status
                    // if it was, and src has not changed, call imgLoaded on it
                    var cached = $.data( el, 'imagesLoaded' );
                    if ( cached && cached.src === src ) {
                        imgLoaded( el, cached.isBroken );
                        return;
                    }

                    //if (can_use_cached_images == false) {
                    //src = src.split('?')[0];
                    //var img = new Image();
                    //$(img).bind( 'load.imagesLoaded error.imagesLoaded', imgLoaded );
                    //}

                    // if complete is true and browser supports natural sizes, try
                    // to check for image status manually
                    if ( el.complete && el.naturalWidth !== undefined ) {
                        imgLoaded( el, el.naturalWidth === 0 || el.naturalHeight === 0 );
                        return;
                    }

                    // cached images don't fire load sometimes, so we reset src, but only when
                    // dealing with IE, or image is complete (loaded) and failed manual check
                    // webkit hack from http://groups.google.com/group/jquery-dev/browse_thread/thread/eee6ab7b2da50e1f
                    if ( el.readyState || el.complete) {
                        if ($.browser.msie) {
                            el.src = '';
                        } else {
                            el.src = BLANK;
                        }
                        //el.src = BLANK;
                        el.src = src;
                    }
                });
        }

        return deferred ? deferred.promise( $this ) : $this;
    };

})(jQuery);

/**
 * jQuery Masonry v2.1.08
 * A dynamic layout plugin for jQuery
 * The flip-side of CSS Floats
 * http://masonry.desandro.com
 *
 * Licensed under the MIT license.
 * Copyright 2012 David DeSandro
 */

/*jshint browser: true, curly: true, eqeqeq: true, forin: false, immed: false, newcap: true, noempty: true, strict: true, undef: true */
/*global jQuery: false */

(function( window, $, undefined ){

    'use strict';

    /*
     * smartresize: debounced resize event for jQuery
     *
     * latest version and complete README available on Github:
     * https://github.com/louisremi/jquery.smartresize.js
     *
     * Copyright 2011 @louis_remi
     * Licensed under the MIT license.
     */

    var $event = $.event,
        dispatchMethod = $.event.handle ? 'handle' : 'dispatch',
        resizeTimeout;

    $event.special.smartresize = {
        setup: function() {
            $(this).bind( "resize", $event.special.smartresize.handler );
        },
        teardown: function() {
            $(this).unbind( "resize", $event.special.smartresize.handler );
        },
        handler: function( event, execAsap ) {
            // Save the context
            var context = this,
                args = arguments;

            // set correct event type
            event.type = "smartresize";

            if ( resizeTimeout ) { clearTimeout( resizeTimeout ); }
            resizeTimeout = setTimeout(function() {
                //$event.dispatch.apply( context, args );
                $event[ dispatchMethod ].apply( context, args );
            }, execAsap === "execAsap"? 0 : 100 );
        }
    };

    $.fn.smartresize = function( fn ) {
        return fn ? this.bind( "smartresize", fn ) : this.trigger( "smartresize", ["execAsap"] );
    };



// ========================= Masonry ===============================


    // our "Widget" object constructor
    $.Mason = function( options, element ){
        this.element = $( element );

        this._create( options );
        this._init();
    };

    $.Mason.settings = {
        isResizable: true,
        isAnimated: false,
        animationOptions: {
            queue: false,
            duration: 500
        },
        gutterWidth: 0,
        isRTL: false,
        isFitWidth: false,
        containerStyle: {
            position: 'relative'
        }
    };

    $.Mason.prototype = {

        _filterFindBricks: function( $elems ) {
            var selector = this.options.itemSelector;
            // if there is a selector
            // filter/find appropriate item elements
            return !selector ? $elems : $elems.filter( selector ).add( $elems.find( selector ) );
        },

        _getBricks: function( $elems ) {
            var $bricks = this._filterFindBricks( $elems )
                .css({ position: 'absolute' })
                .addClass('masonry-brick');
            return $bricks;
        },

        // sets up widget
        _create : function( options ) {

            this.options = $.extend( true, {}, $.Mason.settings, options );
            this.styleQueue = [];

            // get original styles in case we re-apply them in .destroy()
            var elemStyle = this.element[0].style;
            this.originalStyle = {
                // get height
                height: elemStyle.height || ''
            };
            // get other styles that will be overwritten
            var containerStyle = this.options.containerStyle;
            for ( var prop in containerStyle ) {
                this.originalStyle[ prop ] = elemStyle[ prop ] || '';
            }

            this.element.css( containerStyle );

            this.horizontalDirection = this.options.isRTL ? 'right' : 'left';

            var x = this.element.css( 'padding-' + this.horizontalDirection );
            var y = this.element.css( 'padding-top' );
            this.offset = {
                x: x ? parseInt( x, 10 ) : 0,
                y: y ? parseInt( y, 10 ) : 0
            };

            this.isFluid = this.options.columnWidth && typeof this.options.columnWidth === 'function';

            // add masonry class first time around
            var instance = this;
            setTimeout( function() {
                instance.element.addClass('masonry');
            }, 0 );

            // bind resize method
            if ( this.options.isResizable ) {
                $(window).bind( 'smartresize.masonry', function() {
                    instance.resize();
                });
            }


            // need to get bricks
            this.reloadItems();

        },

        // _init fires when instance is first created
        // and when instance is triggered again -> $el.masonry();
        _init : function( callback ) {
            this._getColumns();
            this._reLayout( callback );
        },

        option: function( key, value ){
            // set options AFTER initialization:
            // signature: $('#foo').bar({ cool:false });
            if ( $.isPlainObject( key ) ){
                this.options = $.extend(true, this.options, key);
            }
        },

        // ====================== General Layout ======================

        // used on collection of atoms (should be filtered, and sorted before )
        // accepts atoms-to-be-laid-out to start with
        layout : function( $bricks, callback ) {

            // place each brick
            for (var i=0, len = $bricks.length; i < len; i++) {
                this._placeBrick( $bricks[i] );
            }

            // set the size of the container
            var containerSize = {};
            containerSize.height = Math.max.apply( Math, this.colYs );
            if ( this.options.isFitWidth ) {
                var unusedCols = 0;
                i = this.cols;
                // count unused columns
                while ( --i ) {
                    if ( this.colYs[i] !== 0 ) {
                        break;
                    }
                    unusedCols++;
                }
                // fit container to columns that have been used;
                containerSize.width = (this.cols - unusedCols) * this.columnWidth - this.options.gutterWidth;
            }
            this.styleQueue.push({ $el: this.element, style: containerSize });

            // are we animating the layout arrangement?
            // use plugin-ish syntax for css or animate
            var styleFn = !this.isLaidOut ? 'css' : (
                    this.options.isAnimated ? 'animate' : 'css'
                    ),
                animOpts = this.options.animationOptions;

            // process styleQueue
            var obj;
            for (i=0, len = this.styleQueue.length; i < len; i++) {
                obj = this.styleQueue[i];
                obj.$el[ styleFn ]( obj.style, animOpts );
            }

            // clear out queue for next time
            this.styleQueue = [];

            // provide $elems as context for the callback
            if ( callback ) {
                callback.call( $bricks );
            }

            this.isLaidOut = true;
        },

        // calculates number of columns
        // i.e. this.columnWidth = 200
        _getColumns : function() {
            var container = this.options.isFitWidth ? this.element.parent() : this.element,
                containerWidth = container.width();

            // use fluid columnWidth function if there
            this.columnWidth = this.isFluid ? this.options.columnWidth( containerWidth ) :
                // if not, how about the explicitly set option?
                this.options.columnWidth ||
                    // or use the size of the first item
                    this.$bricks.outerWidth(true) ||
                    // if there's no items, use size of container
                    containerWidth;

            this.columnWidth += this.options.gutterWidth;

            this.cols = Math.floor( ( containerWidth + this.options.gutterWidth ) / this.columnWidth );
            this.cols = Math.max( this.cols, 1 );

        },

        // layout logic
        _placeBrick: function( brick ) {
            var $brick = $(brick),
                colSpan, groupCount, groupY, groupColY, j;

            //how many columns does this brick span
            colSpan = Math.ceil( $brick.outerWidth(true) / this.columnWidth );
            colSpan = Math.min( colSpan, this.cols );

            if ( colSpan === 1 ) {
                // if brick spans only one column, just like singleMode
                groupY = this.colYs;
            } else {
                // brick spans more than one column
                // how many different places could this brick fit horizontally
                groupCount = this.cols + 1 - colSpan;
                groupY = [];

                // for each group potential horizontal position
                for ( j=0; j < groupCount; j++ ) {
                    // make an array of colY values for that one group
                    groupColY = this.colYs.slice( j, j+colSpan );
                    // and get the max value of the array
                    groupY[j] = Math.max.apply( Math, groupColY );
                }

            }

            // get the minimum Y value from the columns
            var minimumY = Math.min.apply( Math, groupY ),
                shortCol = 0;

            // Find index of short column, the first from the left
            for (var i=0, len = groupY.length; i < len; i++) {
                if ( groupY[i] === minimumY ) {
                    shortCol = i;
                    break;
                }
            }

            // position the brick
            var position = {
                top: minimumY + this.offset.y
            };
            // position.left or position.right
            position[ this.horizontalDirection ] = this.columnWidth * shortCol + this.offset.x;
            this.styleQueue.push({ $el: $brick, style: position });

            // apply setHeight to necessary columns
            var setHeight = minimumY + $brick.outerHeight(true),
                setSpan = this.cols + 1 - len;
            for ( i=0; i < setSpan; i++ ) {
                this.colYs[ shortCol + i ] = setHeight;
            }

        },


        resize: function() {
            var prevColCount = this.cols;
            // get updated colCount
            this._getColumns();
            if ( this.isFluid || this.cols !== prevColCount ) {
                // if column count has changed, trigger new layout
                this._reLayout();
            }
        },


        _reLayout : function( callback ) {
            // reset columns
            var i = this.cols;
            this.colYs = [];
            while (i--) {
                this.colYs.push( 0 );
            }
            // apply layout logic to all bricks
            this.layout( this.$bricks, callback );
        },

        // ====================== Convenience methods ======================

        // goes through all children again and gets bricks in proper order
        reloadItems : function() {
            this.$bricks = this._getBricks( this.element.children() );
        },


        reload : function( callback ) {
            this.reloadItems();
            this._init( callback );
        },


        // convienence method for working with Infinite Scroll
        appended : function( $content, isAnimatedFromBottom, callback ) {
            if ( isAnimatedFromBottom ) {
                // set new stuff to the bottom
                this._filterFindBricks( $content ).css({ top: this.element.height() });
                var instance = this;
                setTimeout( function(){
                    instance._appended( $content, callback );
                }, 1 );
            } else {
                this._appended( $content, callback );
            }
        },

        _appended : function( $content, callback ) {
            var $newBricks = this._getBricks( $content );
            // add new bricks to brick pool
            this.$bricks = this.$bricks.add( $newBricks );
            this.layout( $newBricks, callback );
        },

        // removes elements from Masonry widget
        remove : function( $content ) {
            this.$bricks = this.$bricks.not( $content );
            $content.remove();
        },

        // destroys widget, returns elements and container back (close) to original style
        destroy : function() {

            this.$bricks
                .removeClass('masonry-brick')
                .each(function(){
                    this.style.position = '';
                    this.style.top = '';
                    this.style.left = '';
                });

            // re-apply saved container styles
            var elemStyle = this.element[0].style;
            for ( var prop in this.originalStyle ) {
                elemStyle[ prop ] = this.originalStyle[ prop ];
            }

            this.element
                .unbind('.masonry')
                .removeClass('masonry')
                .removeData('masonry');

            $(window).unbind('.masonry');

        }

    };

    // =======================  Plugin bridge  ===============================
    // leverages data method to either create or return $.Mason constructor
    // A bit from jQuery UI
    //   https://github.com/jquery/jquery-ui/blob/master/ui/jquery.ui.widget.js
    // A bit from jcarousel
    //   https://github.com/jsor/jcarousel/blob/master/lib/jquery.jcarousel.js

    $.fn.masonry = function( options ) {
        if ( typeof options === 'string' ) {
            // call method
            var args = Array.prototype.slice.call( arguments, 1 );

            this.each(function(){
                var instance = $.data( this, 'masonry' );
                if ( !instance ) {
                    console.log( "cannot call methods on masonry prior to initialization; " +
                        "attempted to call method '" + options + "'" );
                    return;
                }
                if ( !$.isFunction( instance[options] ) || options.charAt(0) === "_" ) {
                    console.log( "no such method '" + options + "' for masonry instance" );
                    return;
                }
                // apply method
                instance[ options ].apply( instance, args );
            });
        } else {
            this.each(function() {
                var instance = $.data( this, 'masonry' );
                if ( instance ) {
                    // apply options & init
                    instance.option( options || {} );
                    instance._init();
                } else {
                    // initialize new instance
                    $.data( this, 'masonry', new $.Mason( options, this ) );
                }
            });
        }
        return this;
    };

})( window, jQuery );