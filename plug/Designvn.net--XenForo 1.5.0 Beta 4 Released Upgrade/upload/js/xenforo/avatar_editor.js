/*
 * XenForo avatar_editor.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b,k){XenForo.AvatarEditor=function(a){this.__construct(a)};XenForo.AvatarEditor.prototype={__construct:function(a){this.$form=a.bind({submit:b.context(this,"saveChanges"),reset:b.context(this,"resetForm"),AutoInlineUploadComplete:b.context(this,"uploadComplete")});this.$cropObj=a.find(".AvatarCropControl").bind({dragstart:b.context(this,"dragStart"),dragend:b.context(this,"dragEnd"),drag:b.context(this,"drag")});this.$cropImg=this.$cropObj.find("img").load(b.context(this,"imageLoaded"));
b(k).load(b.context(this,"imageLoaded"));this.$form.find("#GravatarTest").click(b.context(this,"gravatarTest"));this.$outputX=a.find("input[name=avatar_crop_x]");this.$outputY=a.find("input[name=avatar_crop_y]");this.setCropFormVisibility(a.find("input[name=avatar_date]").val());this.cropX=this.$outputX.val()*-1;this.cropY=this.$outputY.val()*-1},getPositions:function(){this.objSizeX=this.$cropObj.innerWidth();this.objSizeY=this.$cropObj.innerHeight();this.imageSizeX=this.$cropImg.outerWidth();this.imageSizeY=
this.$cropImg.outerHeight();this.deltaX=(this.imageSizeX-this.objSizeX)*-1;this.deltaY=(this.imageSizeY-this.objSizeY)*-1;this.imagePos=this.$cropImg.position();this.objOffset=this.$cropObj.offset()},imageLoaded:function(){if(!this.positionSet)this.getPositions(),this.setPosition(this.$outputX.val()*-1,this.$outputY.val()*-1,!1),this.positionSet=!0},setPosition:function(a,b,g){if(a>0)a=0;else if(g&&a<this.deltaX)a=this.deltaX;if(b>0)b=0;else if(g&&b<this.deltaY)b=this.deltaY;this.$cropImg.css({left:a,
top:b})},dragStart:function(){this.positionSet||this.imageLoaded();this.getPositions()},drag:function(a){this.setPosition(a.offsetX-this.objOffset.left+this.imagePos.left,a.offsetY-this.objOffset.top+this.imagePos.top,!0)},dragEnd:function(){var a=this.$cropImg.position();this.$outputX.val(a.left*-1);this.$outputY.val(a.top*-1);console.info("Avatar crop dragged to %d, %d %o",this.$outputX.val(),this.$outputY.val(),this.$cropObj)},uploadComplete:function(a){this.updateEditor(a.ajaxData)},updateEditor:function(a){console.info("Update Avatar Editor %o",
a);XenForo.updateUserAvatars(a.user_id,a.urls,b("#ctrl_useGravatar_0").is(":checked"));b(".avatarCropper .Av"+a.user_id+"l img").css(a.cropCss);this.setCropFormVisibility(a.avatar_date);this.$cropImg.css({width:"auto",height:"auto"});this.$cropImg.css(a.maxDimension,a.maxWidth);this.cropX=a.cropX*-1;this.cropY=a.cropY*-1;this.$outputX.val(a.cropX);this.$outputY.val(a.cropY);this.setPosition(this.cropX,this.cropY,!1)},setCropFormVisibility:function(a){this.$form.find("#DeleteAvatar").removeAttr("checked");
parseInt(a,10)?b("label[for=DeleteAvatar], #ExistingCustom").xfFadeIn(XenForo.speed.normal):b("label[for=DeleteAvatar], #ExistingCustom").hide()},saveChanges:function(a){if(this.$form.find("input[name=_xfUploader]").length)return!0;a.preventDefault();XenForo.ajax(this.$form.attr("action"),this.$form.serializeArray(),b.context(this,"saveChangesSuccess"))},saveChangesSuccess:function(a){if(XenForo.hasResponseError(a))return!1;this.updateEditor(a);var b;(b=this.$form.closest(".xenOverlay").data("overlay"))&&
b.close()},resetForm:function(){this.setPosition(this.cropX,this.cropY,!1)},gravatarTest:function(a){var i=b(a.target),g=b(i.data("testsrc")),j=b(i.data("testimg")),f=b(i.data("testerr"));i.data("testurl");var a=g.val(),d=this.$form.data("maxwidth");g.data("XenForo.Prompt")&&(a=g.data("XenForo.Prompt").val());if(a){if(a.length<5)return!1}else return f.slideUp(XenForo.speed.fast),!0;i.prop("disabled",!0);XenForo.ajax(i.data("testurl"),{email:a,size:d},function(c){i.removeAttr("disabled");typeof c==
"object"&&(c.error?f.hide().html(c.error[0]).xfFadeDown(XenForo.speed.fast):f.slideUp(XenForo.speed.fast),c.gravatarUrl&&j.attr("src",c.gravatarUrl),g.focus())})}};XenForo.register(".AvatarEditor","XenForo.AvatarEditor")})(jQuery,this,document);
(function(b){function k(c){var h=this,l,e=c.data||{};if(e.elem)h=c.dragTarget=e.elem,c.dragProxy=d.proxy||h,c.cursorOffsetX=e.pageX-e.left,c.cursorOffsetY=e.pageY-e.top,c.offsetX=c.pageX-c.cursorOffsetX,c.offsetY=c.pageY-c.cursorOffsetY;else if(d.dragging||e.which>0&&c.which!=e.which||b(c.target).is(e.not))return;switch(c.type){case "mousedown":return b.extend(e,b(h).offset(),{elem:h,target:c.target,pageX:c.pageX,pageY:c.pageY}),j.add(document,"mousemove mouseup",k,e),g(h,!1),d.dragging=null,!1;case !d.dragging&&
"mousemove":if(Math.pow(c.pageX-e.pageX,2)+Math.pow(c.pageY-e.pageY,2)<e.distance)break;c.target=e.target;l=a(c,"dragstart",h);if(l!==!1)d.dragging=h,d.proxy=c.dragProxy=b(l||h)[0];case "mousemove":if(d.dragging){l=a(c,"drag",h);if(f.drop)f.drop.allowed=l!==!1,f.drop.handler(c);if(l!==!1)break;c.type="mouseup"}case "mouseup":j.remove(document,"mousemove mouseup",k),d.dragging&&(f.drop&&f.drop.handler(c),a(c,"dragend",h)),g(h,!0),d.dragging=d.proxy=e.elem=!1}return!0}function a(c,a,d){c.type=a;a=b.event.handle.call(d,
c);return a===!1?!1:a||c.result}function i(){return d.dragging===!1}function g(a,b){if(a&&(a.unselectable=b?"off":"on",a.onselectstart=function(){return b},a.style))a.style.MozUserSelect=b?"":"none"}b.fn.drag=function(a,b,d){b&&this.bind("dragstart",a);d&&this.bind("dragend",d);return!a?this.trigger("drag"):this.bind("drag",b?b:a)};var j=b.event,f=j.special,d=f.drag={not:":input",distance:0,which:1,dragging:!1,setup:function(a){a=b.extend({distance:d.distance,which:d.which,not:d.not},a||{});a.distance=
Math.pow(a.distance,2);j.add(this,"mousedown",k,a);this.attachEvent&&this.attachEvent("ondragstart",i)},teardown:function(){j.remove(this,"mousedown",k);if(this===d.dragging)d.dragging=d.proxy=!1;g(this,!0);this.detachEvent&&this.detachEvent("ondragstart",i)}};f.dragstart=f.dragend={setup:function(){},teardown:function(){}}})(jQuery);
