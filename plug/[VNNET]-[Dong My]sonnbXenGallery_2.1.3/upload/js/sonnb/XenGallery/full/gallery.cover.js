
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

    XenForo.XenGalleryCoverEditor = function($container) { this.__construct($container); };
    XenForo.XenGalleryCoverEditor.prototype =
    {
        __construct: function($container)
        {
            this.$btnEdit = $container.find('.controls li.edit');
            this.$btnUpload = $container.find('.controls li.upload');
            this.$btnDelete = $container.find('.controls li.delete');
            this.$cover = $container.find('.CoverCropControl');
            this.$coverImg = this.$cover.find('img').load($.context(this, 'imageLoaded'));
            this.$coverImage = $container.find('img.coverImage');
            this.$btnUploadForm = this.$btnUpload.find('form.AutoInlineUploader');

            this.$btnEdit.click($.context(this, 'editClick'));

            $(window).load($.context(this, 'imageLoaded'));

            this.$outputX = this.$btnUploadForm.find('input[name=crop_x]');
            this.$outputY = this.$btnUploadForm.find('input[name=crop_y]');
            this.$outputWidth = this.$btnUploadForm.find('input[name=width]');
            this.$outputHeight = this.$btnUploadForm.find('input[name=height]');

            this.$outputWidth.val(this.$cover.width());
            this.$outputHeight.val(this.$cover.height());

            this.cropX = this.$outputX.val() * -1;
            this.cropY = this.$outputY.val() * -1;
        },

        editClick: function(e)
        {
            if (this.$btnEdit.hasClass('active'))
            {
                this.triggerSave(e);

                this.$btnEdit.removeClass('active');
                this.$btnUpload.removeClass('active');
                this.$btnDelete.removeClass('active');

                this.$cover.unbind();
                this.$btnUploadForm.unbind();
                this.$btnDelete.unbind();
            }
            else
            {
                this.$btnEdit.addClass('active');
                this.$btnUpload.addClass('active');
                this.$btnDelete.addClass('active');

                this.$btnDelete.click($.context(this, 'deleteClick'));
                this.$btnUploadForm.bind(
                    {
                        submit: $.context(this, 'saveChanges'),
                        AutoInlineUploadComplete: $.context(this, 'uploadComplete')
                    });

                this.$cover.bind(
                    {
                        dragstart: $.context(this, 'dragStart'),
                        dragend:   $.context(this, 'dragEnd'),
                        drag:      $.context(this, 'drag')
                    });

            }
        },

        triggerSave: function(e)
        {
            this.$btnUploadForm.submit();
        },

        deleteClick: function(e)
        {
            this.$btnUploadForm.find('input[name="delete"]').val('1');
            this.$btnUploadForm.submit();
            this.$btnDelete.hide();
        },

        saveChanges: function(e)
        {
            if (this.$btnUploadForm.find('input[name=_xfUploader]').length)
            {
                return true;
            }

            e.preventDefault();

            XenForo.ajax(
                this.$btnUploadForm.attr('action'),
                this.$btnUploadForm.serializeArray(),
                $.context(this, 'saveChangesSuccess')
            );
        },

        saveChangesSuccess: function(ajaxData)
        {
            if (XenForo.hasResponseError(ajaxData))
            {
                return false;
            }

            if (ajaxData.url)
            {
                //Crop
                this.$coverImage.load($.context(this, 'imageLoaded')).attr('src', ajaxData.url).show();
            }
            else
            {
                //Delete
                this.$coverImage.attr('src', '').hide();
            }

            if (ajaxData.message)
            {
                XenForo.alert(ajaxData.message, '', 2000);
            }

            this.$coverImg.css({ left: 0, top: 0 });
        },

        uploadComplete: function(e)
        {
            this.$coverImage.load($.context(this, 'imageLoaded')).attr('src', e.ajaxData.url).css({ left: 0, top: 0 }).show();

            this.$cover.show();
            this.$btnUploadForm.find('input[name="delete"]').val('');

            this.$btnDelete.show();
        },

        getPositions: function()
        {
            // dimensions of the crop control container box
            this.objSizeX = this.$cover.innerWidth();
            this.objSizeY = this.$cover.innerHeight();

            // dimensions of the image within the crop container
            this.imageSizeX = this.$coverImg.outerWidth();
            this.imageSizeY = this.$coverImg.outerHeight();


            this.deltaX = (this.imageSizeX - this.objSizeX) * -1;
            this.deltaY = (this.imageSizeY - this.objSizeY) * -1;

            this.imagePos = this.$coverImg.position();

            this.objOffset = this.$cover.offset();
        },

        imageLoaded: function(e)
        {
            this.$outputWidth.val(this.$cover.width());
            this.$outputHeight.val(this.$cover.height());
        },

        setPosition: function(x, y, checkDelta)
        {
            if (x > 0)
            {
                x = 0;
            }
            else if (checkDelta && x < this.deltaX)
            {
                x = this.deltaX;
            }

            if (y > 0)
            {
                y = 0;
            }
            else if (checkDelta && y < this.deltaY)
            {
                y = this.deltaY;
            }

            this.$coverImg.css({ left: x, top: y });
        },

        dragStart: function(e)
        {
            if (!this.positionSet)
            {
                this.imageLoaded(e);
            }

            this.getPositions();
        },

        drag: function(e)
        {
            this.setPosition(
                e.offsetX - this.objOffset.left + this.imagePos.left,
                e.offsetY - this.objOffset.top + this.imagePos.top,
                true
            );
        },

        dragEnd: function(e)
        {
            var imagePos = this.$coverImg.position();

            this.$outputX.val(imagePos.left * -1);
            this.$outputY.val(imagePos.top * -1);
        }
    };

    // *********************************************************************

    XenForo.register('.userCover', 'XenForo.XenGalleryCoverEditor');

}
    (jQuery, this, document);

/*
 jquery.event.drag.js ~ v1.5 ~ Copyright (c) 2008, Three Dub Media (http://threedubmedia.com)
 Liscensed under the MIT License ~ http://threedubmedia.googlecode.com/files/MIT-LICENSE.txt
 */
(function(E){E.fn.drag=function(L,K,J){if(K){this.bind("dragstart",L)}if(J){this.bind("dragend",J)}return !L?this.trigger("drag"):this.bind("drag",K?K:L)};var A=E.event,B=A.special,F=B.drag={not:":input",distance:0,which:1,dragging:false,setup:function(J){J=E.extend({distance:F.distance,which:F.which,not:F.not},J||{});J.distance=I(J.distance);A.add(this,"mousedown",H,J);if(this.attachEvent){this.attachEvent("ondragstart",D)}},teardown:function(){A.remove(this,"mousedown",H);if(this===F.dragging){F.dragging=F.proxy=false}G(this,true);if(this.detachEvent){this.detachEvent("ondragstart",D)}}};B.dragstart=B.dragend={setup:function(){},teardown:function(){}};function H(L){var K=this,J,M=L.data||{};if(M.elem){K=L.dragTarget=M.elem;L.dragProxy=F.proxy||K;L.cursorOffsetX=M.pageX-M.left;L.cursorOffsetY=M.pageY-M.top;L.offsetX=L.pageX-L.cursorOffsetX;L.offsetY=L.pageY-L.cursorOffsetY}else{if(F.dragging||(M.which>0&&L.which!=M.which)||E(L.target).is(M.not)){return }}switch(L.type){case"mousedown":E.extend(M,E(K).offset(),{elem:K,target:L.target,pageX:L.pageX,pageY:L.pageY});A.add(document,"mousemove mouseup",H,M);G(K,false);F.dragging=null;return false;case !F.dragging&&"mousemove":if(I(L.pageX-M.pageX)+I(L.pageY-M.pageY)<M.distance){break}L.target=M.target;J=C(L,"dragstart",K);if(J!==false){F.dragging=K;F.proxy=L.dragProxy=E(J||K)[0]}case"mousemove":if(F.dragging){J=C(L,"drag",K);if(B.drop){B.drop.allowed=(J!==false);B.drop.handler(L)}if(J!==false){break}L.type="mouseup"}case"mouseup":A.remove(document,"mousemove mouseup",H);if(F.dragging){if(B.drop){B.drop.handler(L)}C(L,"dragend",K)}G(K,true);F.dragging=F.proxy=M.elem=false;break}return true}function C(M,K,L){M.type=K;var J=E.event.handle.call(L,M);return J===false?false:J||M.result}function I(J){return Math.pow(J,2)}function D(){return(F.dragging===false)}function G(K,J){if(!K){return }K.unselectable=J?"off":"on";K.onselectstart=function(){return J};if(K.style){K.style.MozUserSelect=J?"":"none"}}})(jQuery);