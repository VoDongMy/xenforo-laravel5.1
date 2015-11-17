/*
 * XenForo media_lightbox.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(e){XenForo.XenGalleryLightbox=function(a){this.__construct(a)};XenForo.XenGalleryLightbox.prototype={__construct:function(a){if(!this.element)this.element=a;e(".mediaContainer .Image").click(function(){e(".LightboxContainer a.Lightbox").click()});e.extend(!0,e.magnificPopup.defaults,{tClose:XenForo.phrases.xengallery_lightbox_close,tLoading:XenForo.phrases.xengallery_lightbox_loading,gallery:{tPrev:XenForo.phrases.xengallery_lightbox_previous,tNext:XenForo.phrases.xengallery_lightbox_next,
tCounter:XenForo.phrases.xengallery_lightbox_counter},image:{tError:XenForo.phrases.xengallery_lightbox_error},ajax:{tError:XenForo.phrases.xengallery_lightbox_error}});$self=this;a.magnificPopup({gallery:{enabled:!0,preload:0},type:a.data("type"),delegate:".Lightbox",image:{titleSrc:function(a){var e=XenForo.htmlspecialchars(a.el.data("media-title")),n=XenForo.htmlspecialchars(a.el.data("media-url")),a=XenForo.htmlspecialchars(a.el.data("username"));return'<a href="'+n+'">'+e+'</a><small><a href="'+
n+'">'+a+"</a></small>"}},callbacks:{beforeOpen:function(){this.totalCount=parseInt($self.element.data("total-count"))},open:function(){if(!$self.element.hasClass("LightboxOpenFired"))return this.limit=50,$self.performAjax(this.limit)},beforeChange:function(){if(this.$previousItem=this.currItem)this.$previousItem=this.$previousItem.el.data("img-index")},change:function(){if(this.$previousItem){var a=this.currItem;if(a){var a=a.el.data("img-index"),o="next",n=this.limit/2,i=!1,p=this.items.length,
l=0;a<this.$previousItem&&(o="prev");switch(o){case "prev":a<n&&(e(".LightboxNoMorePrev").length||(i=!0),l=e(".Lightbox").first().data("media-id"));break;case "next":a>p-n&&(e(".LightboxNoMoreNext").length||(i=!0),l=e(".Lightbox").last().data("media-id"))}if(i)return $self.performAjax(n,o,l)}}}},iframe:{markup:'<div class="mfp-iframe-scaler iframe mfp-figure"><div class="mfp-close"></div><iframe class="mfp-iframe" src="//about:blank" frameborder="0" allowfullscreen></iframe><div class="mfp-bottom-bar"><div class="mfp-title"></div><div class="mfp-counter"></div></div></div>'}})},
performAjax:function(a,t,o){this.element.addClass("LightboxOpenFired");a={limit:a,direction:t};if(o)a.last_media_id=o;XenForo.ajax(this.element.data("fetch-url"),a,e.context(this,"ajaxSuccess"))},ajaxSuccess:function(a){a.prevTemplateHtml&&e(a.prevTemplateHtml).prependTo(this.element);a.nextTemplateHtml&&e(a.nextTemplateHtml).appendTo(this.element);this.element.magnificPopup("open",e.magnificPopup.instance.index);this.reindexInputs()},reindexInputs:function(){var a=0;e(".Lightbox").each(function(){a++;
e(this).data("img-index",a)})}};XenForo.register(".LightboxContainer","XenForo.XenGalleryLightbox")})(jQuery,this,document);
(function(e){var a,t=function(){},o=!!window.jQuery,n,i=e(window),p,l,v,s,D,j=function(b,c){a.ev.on("mfp"+b+".mfp",c)},k=function(a,c,d,f){var g=document.createElement("div");g.className="mfp-"+a;if(d)g.innerHTML=d;f?c&&c.appendChild(g):(g=e(g),c&&g.appendTo(c));return g},h=function(b,c){a.ev.triggerHandler("mfp"+b,c);a.st.callbacks&&(b=b.charAt(0).toLowerCase()+b.slice(1),a.st.callbacks[b]&&a.st.callbacks[b].apply(a,e.isArray(c)?c:[c]))},y=function(b){if(b!==D||!a.currTemplate.closeBtn)a.currTemplate.closeBtn=
e(a.st.closeMarkup.replace("%title%",a.st.tClose)),D=b;return a.currTemplate.closeBtn},z=function(){if(!e.magnificPopup.instance)a=new t,a.init(),e.magnificPopup.instance=a},H=function(){var a=document.createElement("p").style,c=["ms","O","Moz","Webkit"];if(a.transition!==void 0)return!0;for(;c.length;)if(c.pop()+"Transition"in a)return!0;return!1};t.prototype={constructor:t,init:function(){var b=navigator.appVersion;a.isIE7=b.indexOf("MSIE 7.")!==-1;a.isIE8=b.indexOf("MSIE 8.")!==-1;a.isLowIE=a.isIE7||
a.isIE8;a.isAndroid=/android/gi.test(b);a.isIOS=/iphone|ipad|ipod/gi.test(b);a.supportsTransition=H();a.probablyMobile=a.isAndroid||a.isIOS||/(Opera Mini)|Kindle|webOS|BlackBerry|(Opera Mobi)|(Windows Phone)|IEMobile/i.test(navigator.userAgent);p=e(document.body);l=e(document);a.popupsCache={}},open:function(b){var c;if(b.isObj===!1){a.items=b.items.toArray();a.index=0;var d=b.items,f;for(c=0;c<d.length;c++)if(f=d[c],f.parsed&&(f=f.el[0]),f===b.el[0]){a.index=c;break}}else a.items=e.isArray(b.items)?
b.items:[b.items],a.index=b.index||0;if(a.isOpen)a.updateItemHTML();else{a.types=[];s="";a.ev=b.mainEl&&b.mainEl.length?b.mainEl.eq(0):l;b.key?(a.popupsCache[b.key]||(a.popupsCache[b.key]={}),a.currTemplate=a.popupsCache[b.key]):a.currTemplate={};a.st=e.extend(!0,{},e.magnificPopup.defaults,b);a.fixedContentPos=a.st.fixedContentPos==="auto"?!a.probablyMobile:a.st.fixedContentPos;if(a.st.modal)a.st.closeOnContentClick=!1,a.st.closeOnBgClick=!1,a.st.showCloseBtn=!1,a.st.enableEscapeKey=!1;if(!a.bgOverlay)a.bgOverlay=
k("bg").on("click.mfp",function(){a.close()}),a.wrap=k("wrap").attr("tabindex",-1).on("click.mfp",function(b){a._checkIfClose(b.target)&&a.close()}),a.container=k("container",a.wrap);a.contentContainer=k("content");if(a.st.preloader)a.preloader=k("preloader",a.container,a.st.tLoading);d=e.magnificPopup.modules;for(c=0;c<d.length;c++)f=d[c],f=f.charAt(0).toUpperCase()+f.slice(1),a["init"+f].call(a);h("BeforeOpen");a.st.showCloseBtn&&(a.st.closeBtnInside?(j("MarkupParse",function(a,b,c,d){c.close_replaceWith=
y(d.type)}),s+=" mfp-close-btn-in"):a.wrap.append(y()));a.st.alignTop&&(s+=" mfp-align-top");a.fixedContentPos?a.wrap.css({overflow:a.st.overflowY,overflowX:"hidden",overflowY:a.st.overflowY}):a.wrap.css({top:i.scrollTop(),position:"absolute"});(a.st.fixedBgPos===!1||a.st.fixedBgPos==="auto"&&!a.fixedContentPos)&&a.bgOverlay.css({height:l.height(),position:"absolute"});if(a.st.enableEscapeKey)l.on("keyup.mfp",function(b){b.keyCode===27&&a.close()});i.on("resize.mfp",function(){a.updateSize()});a.st.closeOnContentClick||
(s+=" mfp-auto-cursor");s&&a.wrap.addClass(s);c=a.wH=i.height();d={};a.fixedContentPos&&a._hasScrollBar(c)&&a._getScrollbarSize();if(a.fixedContentPos)a.isIE7?e("body, html").css("overflow","hidden"):d.overflow="hidden";f=a.st.mainClass;a.isIE7&&(f+=" mfp-ie7");f&&a._addClassToMFP(f);a.updateItemHTML();h("BuildControls");e("html").css(d);a.bgOverlay.add(a.wrap).prependTo(document.body);a._lastFocusedEl=document.activeElement;setTimeout(function(){a.content?(a._addClassToMFP("mfp-ready"),a._setFocus()):
a.bgOverlay.addClass("mfp-ready");l.on("focusin.mfp",a._onFocusIn)},16);a.isOpen=!0;a.updateSize(c);h("Open");return b}},close:function(){if(a.isOpen)h("BeforeClose"),a.isOpen=!1,a.st.removalDelay&&!a.isLowIE&&a.supportsTransition?(a._addClassToMFP("mfp-removing"),setTimeout(function(){a._close()},a.st.removalDelay)):a._close()},_close:function(){h("Close");var b="mfp-removing mfp-ready ";a.bgOverlay.detach();a.wrap.detach();a.container.empty();a.st.mainClass&&(b+=a.st.mainClass+" ");a._removeClassFromMFP(b);
if(a.fixedContentPos)b={marginRight:""},a.isIE7?e("body, html").css("overflow",""):b.overflow="",e("html").css(b);l.off("keyup.mfp focusin.mfp");a.ev.off(".mfp");a.wrap.attr("class","mfp-wrap").removeAttr("style");a.bgOverlay.attr("class","mfp-bg");a.container.attr("class","mfp-container");a.st.showCloseBtn&&(!a.st.closeBtnInside||a.currTemplate[a.currItem.type]===!0)&&a.currTemplate.closeBtn&&a.currTemplate.closeBtn.detach();a._lastFocusedEl&&e(a._lastFocusedEl).focus();a.currItem=null;a.content=
null;a.currTemplate=null;a.prevHeight=0;h("AfterClose")},updateSize:function(b){a.isIOS?(b=window.innerHeight*(document.documentElement.clientWidth/window.innerWidth),a.wrap.css("height",b),a.wH=b):a.wH=b||i.height();a.fixedContentPos||a.wrap.css("height",a.wH);h("Resize")},updateItemHTML:function(){var b=a.items[a.index];a.contentContainer.detach();a.content&&a.content.detach();b.parsed||(b=a.parseEl(a.index));var c=b.type;h("BeforeChange",[a.currItem?a.currItem.type:"",c]);a.currItem=b;if(!a.currTemplate[c]){var d=
a.st[c]?a.st[c].markup:!1;h("FirstMarkupParse",d);a.currTemplate[c]=d?e(d):!0}v&&v!==b.type&&a.container.removeClass("mfp-"+v+"-holder");d=a["get"+c.charAt(0).toUpperCase()+c.slice(1)](b,a.currTemplate[c]);a.appendContent(d,c);b.preloaded=!0;h("Change",b);v=b.type;a.container.prepend(a.contentContainer);h("AfterChange")},appendContent:function(b,c){(a.content=b)?a.st.showCloseBtn&&a.st.closeBtnInside&&a.currTemplate[c]===!0?a.content.find(".mfp-close").length||a.content.append(y()):a.content=b:a.content=
"";h("BeforeAppend");a.container.addClass("mfp-"+c+"-holder");a.contentContainer.append(a.content)},parseEl:function(b){var c=a.items[b],d=c.type,c=c.tagName?{el:e(c)}:{data:c,src:c.src};if(c.el){for(var f=a.types,g=0;g<f.length;g++)if(c.el.hasClass("mfp-"+f[g])){d=f[g];break}c.src=c.el.attr("data-mfp-src");if(!c.src)c.src=c.el.attr("href")}c.type=d||a.st.type||"inline";c.index=b;c.parsed=!0;a.items[b]=c;h("ElementParse",c);return a.items[b]},addGroup:function(b,c){var d=function(d){d.mfpEl=this;
a._openClick(d,b,c)};c||(c={});c.mainEl=b;if(c.items)c.isObj=!0,b.off("click.magnificPopup").on("click.magnificPopup",d);else if(c.isObj=!1,c.delegate)b.off("click.magnificPopup").on("click.magnificPopup",c.delegate,d);else c.items=b,b.off("click.magnificPopup").on("click.magnificPopup",d)},_openClick:function(b,c,d){if((d.midClick!==void 0?d.midClick:e.magnificPopup.defaults.midClick)||!(b.which===2||b.ctrlKey||b.metaKey)){var f=d.disableOn!==void 0?d.disableOn:e.magnificPopup.defaults.disableOn;
if(f)if(e.isFunction(f)){if(!f.call(a))return!0}else if(i.width()<f)return!0;b.type&&(b.preventDefault(),a.isOpen&&b.stopPropagation());d.el=e(b.mfpEl);if(d.delegate)d.items=c.find(d.delegate);a.open(d)}},updateStatus:function(b,c){if(a.preloader){n!==b&&a.container.removeClass("mfp-s-"+n);if(!c&&b==="loading")c=a.st.tLoading;var d={status:b,text:c};h("UpdateStatus",d);b=d.status;c=d.text;a.preloader.html(c);a.preloader.find("a").on("click",function(a){a.stopImmediatePropagation()});a.container.addClass("mfp-s-"+
b);n=b}},_checkIfClose:function(b){if(!e(b).hasClass("mfp-prevent-close")){var c=a.st.closeOnContentClick,d=a.st.closeOnBgClick;if(c&&d)return!0;else{if(!a.content||e(b).hasClass("mfp-close")||a.preloader&&b===a.preloader[0])return!0;if(b!==a.content[0]&&!e.contains(a.content[0],b)){if(d&&e.contains(document,b))return!0}else if(c)return!0}return!1}},_addClassToMFP:function(b){a.bgOverlay.addClass(b);a.wrap.addClass(b)},_removeClassFromMFP:function(b){this.bgOverlay.removeClass(b);a.wrap.removeClass(b)},
_hasScrollBar:function(b){return(a.isIE7?l.height():document.body.scrollHeight)>(b||i.height())},_setFocus:function(){(a.st.focus?a.content.find(a.st.focus).eq(0):a.wrap).focus()},_onFocusIn:function(b){if(b.target!==a.wrap[0]&&!e.contains(a.wrap[0],b.target))return a._setFocus(),!1},_parseMarkup:function(a,c,d){var f;d.data&&(c=e.extend(d.data,c));h("MarkupParse",[a,c,d]);e.each(c,function(c,d){if(d===void 0||d===!1)return!0;f=c.split("_");if(f.length>1){var e=a.find(".mfp-"+f[0]);if(e.length>0){var h=
f[1];h==="replaceWith"?e[0]!==d[0]&&e.replaceWith(d):h==="img"?e.is("img")?e.attr("src",d):e.replaceWith('<img src="'+d+'" class="'+e.attr("class")+'" />'):e.attr(f[1],d)}}else a.find(".mfp-"+c).html(d)})},_getScrollbarSize:function(){if(a.scrollbarSize===void 0){var b=document.createElement("div");b.id="mfp-sbm";b.style.cssText="width: 99px; height: 99px; overflow: scroll; position: absolute; top: -9999px;";document.body.appendChild(b);a.scrollbarSize=b.offsetWidth-b.clientWidth;document.body.removeChild(b)}return a.scrollbarSize}};
e.magnificPopup={instance:null,proto:t.prototype,modules:[],open:function(a,c){z();a=a?e.extend(!0,{},a):{};a.isObj=!0;a.index=c||0;return this.instance.open(a)},close:function(){return e.magnificPopup.instance&&e.magnificPopup.instance.close()},registerModule:function(a,c){if(c.options)e.magnificPopup.defaults[a]=c.options;e.extend(this.proto,c.proto);this.modules.push(a)},defaults:{disableOn:0,key:null,midClick:!1,mainClass:"",preloader:!0,focus:"",closeOnContentClick:!1,closeOnBgClick:!0,closeBtnInside:!0,
showCloseBtn:!0,enableEscapeKey:!0,modal:!1,alignTop:!1,removalDelay:0,fixedContentPos:"auto",fixedBgPos:"auto",overflowY:"auto",closeMarkup:'<button title="%title%" type="button" class="mfp-close">&times;</button>',tClose:"Close (Esc)",tLoading:"Loading..."}};e.fn.magnificPopup=function(b){z();var c=e(this);if(typeof b==="string")if(b==="open"){var d,f=o?c.data("magnificPopup"):c[0].magnificPopup,g=parseInt(arguments[1],10)||0;f.items?d=f.items[g]:(d=c,f.delegate&&(d=d.find(f.delegate)),d=d.eq(g));
a._openClick({mfpEl:d},c,f)}else a.isOpen&&a[b].apply(a,Array.prototype.slice.call(arguments,1));else b=e.extend(!0,{},b),o?c.data("magnificPopup",b):c[0].magnificPopup=b,a.addGroup(c,b);return c};var u,w,x,E=function(){x&&(w.after(x.addClass(u)).detach(),x=null)};e.magnificPopup.registerModule("inline",{options:{hiddenClass:"hide",markup:"",tNotFound:"Content not found"},proto:{initInline:function(){a.types.push("inline");j("Close.inline",function(){E()})},getInline:function(b,c){E();if(b.src){var d=
a.st.inline,f=e(b.src);if(f.length){var g=f[0].parentNode;if(g&&g.tagName){if(!w)u=d.hiddenClass,w=k(u),u="mfp-"+u;x=f.after(w).detach().removeClass(u)}a.updateStatus("ready")}else a.updateStatus("error",d.tNotFound),f=e("<div>");return b.inlineElement=f}a.updateStatus("ready");a._parseMarkup(c,{},b);return c}}});var q,F=function(){q&&p.removeClass(q);a.req&&a.req.abort()};e.magnificPopup.registerModule("ajax",{options:{settings:null,cursor:"mfp-ajax-cur",tError:'<a href="%url%">The content</a> could not be loaded.'},
proto:{initAjax:function(){a.types.push("ajax");q=a.st.ajax.cursor;j("Close.ajax",F);j("BeforeChange.ajax",F)},getAjax:function(b){q&&p.addClass(q);a.updateStatus("loading");var c=e.extend({url:b.src,success:function(c,f,g){c={data:c,xhr:g};h("ParseAjax",c);a.appendContent(e(c.data),"ajax");b.finished=!0;q&&p.removeClass(q);a._setFocus();setTimeout(function(){a.wrap.addClass("mfp-ready")},16);a.updateStatus("ready");h("AjaxContentAdded")},error:function(){q&&p.removeClass(q);b.finished=b.loadError=
!0;a.updateStatus("error",a.st.ajax.tError.replace("%url%",b.src))}},a.st.ajax.settings);a.req=e.ajax(c);return""}}});var r,G=function(b){if(b.data&&b.data.title!==void 0)return b.data.title;var c=a.st.image.titleSrc;if(c)if(e.isFunction(c))return c.call(a,b);else if(b.el)return b.el.attr(c)||"";return""};e.magnificPopup.registerModule("image",{options:{markup:'<div class="mfp-figure"><div class="mfp-close"></div><figure><div class="mfp-img"></div><figcaption><div class="mfp-bottom-bar"><div class="mfp-title"></div><div class="mfp-counter"></div></div></figcaption></figure></div>',
cursor:"mfp-zoom-out-cur",titleSrc:"title",verticalFit:!0,arrowMarkup:'<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir%"></button>',arrows:!0,tPrev:"Previous (Left arrow key)",tNext:"Next (Right arrow key)",tError:'<a href="%url%">The image</a> could not be loaded.'},proto:{initImage:function(){var b=a.st.image;a.types.push("image");j("Open.image",function(){a.currItem.type==="image"&&b.cursor&&p.addClass(b.cursor)});j("Close.image",function(){b.cursor&&p.removeClass(b.cursor);
i.off("resize.mfp")});j("Resize.image",a.resizeImage);a.isLowIE&&j("AfterChange",a.resizeImage)},resizeImage:function(){var b=a.currItem;if(b&&b.img&&a.st.image.verticalFit){var c=0;a.isLowIE&&(c=parseInt(b.img.css("padding-top"),10)+parseInt(b.img.css("padding-bottom"),10));b.img.css("max-height",a.wH-c)}},_onImageHasSize:function(b){if(b.img&&(b.hasSize=!0,r&&clearInterval(r),b.isCheckingImgSize=!1,h("ImageHasSize",b),b.imgHidden))a.content&&a.content.removeClass("mfp-loading"),b.imgHidden=!1},
findImageSize:function(b){var c=0,d=b.img[0],e=function(g){r&&clearInterval(r);r=setInterval(function(){d.naturalWidth>0?a._onImageHasSize(b):(c>200&&clearInterval(r),c++,c===3?e(10):c===40?e(50):c===100&&e(500))},g)};e(1)},getImage:function(b,c){var d=0,f=function(){if(b)b.img[0].complete?(b.img.off(".mfploader"),b===a.currItem&&(a._onImageHasSize(b),a.updateStatus("ready")),b.hasSize=!0,b.loaded=!0,h("ImageLoadComplete")):(d++,d<200?setTimeout(f,100):g())},g=function(){if(b)b.img.off(".mfploader"),
b===a.currItem&&(a._onImageHasSize(b),a.updateStatus("error",j.tError.replace("%url%",b.src))),b.hasSize=!0,b.loaded=!0,b.loadError=!0},j=a.st.image,m=c.find(".mfp-img");if(m.length){var i=document.createElement("img");i.className="mfp-img";b.img=e(i).on("load.mfploader",f).on("error.mfploader",g);i.src=b.src;if(m.is("img"))b.img=b.img.clone();i=b.img[0];if(i.naturalWidth>0)b.hasSize=!0;else if(!i.width)b.hasSize=!1}a._parseMarkup(c,{title:G(b),img_replaceWith:b.img},b);a.resizeImage();if(b.hasSize)return r&&
clearInterval(r),b.loadError?(c.addClass("mfp-loading"),a.updateStatus("error",j.tError.replace("%url%",b.src))):(c.removeClass("mfp-loading"),a.updateStatus("ready")),c;a.updateStatus("loading");b.loading=!0;if(!b.hasSize)b.imgHidden=!0,c.addClass("mfp-loading"),a.findImageSize(b);return c}}});var A;e.magnificPopup.registerModule("zoom",{options:{enabled:!1,easing:"ease-in-out",duration:300,opener:function(a){return a.is("img")?a:a.find("img")}},proto:{initZoom:function(){var b=a.st.zoom,c;if(b.enabled&&
a.supportsTransition){var d=b.duration,e=function(a){var a=a.clone().removeAttr("style").removeAttr("class").addClass("mfp-animated-image"),c={position:"fixed",zIndex:9999,left:0,top:0,"-webkit-backface-visibility":"hidden"};c["-webkit-transition"]=c["-moz-transition"]=c["-o-transition"]=c.transition="all "+b.duration/1E3+"s "+b.easing;a.css(c);return a},g=function(){a.content.css("visibility","visible")},i,m;j("BuildControls.zoom",function(){a._allowZoom()&&(clearTimeout(i),a.content.css("visibility",
"hidden"),(c=a._getItemToZoom())?(m=e(c),m.css(a._getOffset()),a.wrap.append(m),i=setTimeout(function(){m.css(a._getOffset(!0));i=setTimeout(function(){g();setTimeout(function(){m.remove();c=m=null;h("ZoomAnimationEnded")},16)},d)},16)):g())});j("BeforeClose.zoom",function(){if(a._allowZoom()){clearTimeout(i);a.st.removalDelay=d;if(!c){c=a._getItemToZoom();if(!c)return;m=e(c)}m.css(a._getOffset(!0));a.wrap.append(m);a.content.css("visibility","hidden");setTimeout(function(){m.css(a._getOffset())},
16)}});j("Close.zoom",function(){a._allowZoom()&&(g(),m&&m.remove(),c=null)})}},_allowZoom:function(){return a.currItem.type==="image"},_getItemToZoom:function(){return a.currItem.hasSize?a.currItem.img:!1},_getOffset:function(b){var c;c=b?a.currItem.img:a.st.zoom.opener(a.currItem.el||a.currItem);var b=c.offset(),d=parseInt(c.css("padding-top"),10),f=parseInt(c.css("padding-bottom"),10);b.top-=e(window).scrollTop()-d;c={width:c.width(),height:(o?c.innerHeight():c[0].offsetHeight)-f-d};A===void 0&&
(A=document.createElement("p").style.MozTransform!==void 0);A?c["-moz-transform"]=c.transform="translate("+b.left+"px,"+b.top+"px)":(c.left=b.left,c.top=b.top);return c}}});var B=function(b){if(a.currTemplate.iframe){var c=a.currTemplate.iframe.find("iframe");if(c.length){if(!b)c[0].src="//about:blank";a.isIE8&&c.css("display",b?"block":"none")}}};e.magnificPopup.registerModule("iframe",{options:{markup:'<div class="mfp-iframe-scaler"><div class="mfp-close"></div><iframe class="mfp-iframe" src="//about:blank" frameborder="0" allowfullscreen></iframe></div>',
srcAction:"iframe_src",patterns:{youtube:{index:"youtube.com",id:"v=",src:"//www.youtube.com/embed/%id%?autoplay=1"},vimeo:{index:"vimeo.com/",id:"/",src:"//player.vimeo.com/video/%id%?autoplay=1"},gmaps:{index:"//maps.google.",src:"%id%&output=embed"}}},proto:{initIframe:function(){a.types.push("iframe");j("BeforeChange",function(a,c,d){c!==d&&(c==="iframe"?B():d==="iframe"&&B(!0))});j("Close.iframe",function(){B()})},getIframe:function(b,c){var d=b.src,f=a.st.iframe;e.each(f.patterns,function(){if(d.indexOf(this.index)>
-1)return this.id&&(d=typeof this.id==="string"?d.substr(d.lastIndexOf(this.id)+this.id.length,d.length):this.id.call(this,d)),d=this.src.replace("%id%",d),!1});var g={};f.srcAction&&(g[f.srcAction]=d);g.title=G(b);a._parseMarkup(c,g,b);a.updateStatus("ready");return c}}});var C=function(b){var c=a.items.length;if(b>c-1)return b-c;else if(b<0)return c+b;return b};e.magnificPopup.registerModule("gallery",{options:{enabled:!1,arrowMarkup:'<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir%"></button>',
preload:[0,2],navigateByImgClick:!0,arrows:!0,tPrev:"Previous (Left arrow key)",tNext:"Next (Right arrow key)",tCounter:"%curr% of %total%"},proto:{initGallery:function(){var b=a.st.gallery,c=Boolean(e.fn.mfpFastClick);a.direction=!0;if(!b||!b.enabled)return!1;s+=" mfp-gallery";j("Open.mfp-gallery",function(){if(b.navigateByImgClick)a.wrap.on("click.mfp-gallery",".mfp-img",function(){if(a.items.length>1)return a.next(),!1});l.on("keydown.mfp-gallery",function(b){b.keyCode===37?a.prev():b.keyCode===
39&&a.next()})});j("UpdateStatus.mfp-gallery",function(b,c){if(c.text){var e=a.items.length;if(a.totalCount)e=a.totalCount;c.text=c.text.replace(/%curr%/gi,a.currItem.index+1).replace(/%total%/gi,e)}});j("MarkupParse.mfp-gallery",function(c,e,g,i){c=a.items.length;if(a.totalCount)c=a.totalCount;g.counter=c>1?b.tCounter.replace(/%curr%/gi,i.index+1).replace(/%total%/gi,c):""});j("BuildControls.mfp-gallery",function(){if(a.items.length>0&&b.arrows&&!a.arrowLeft){var d=b.arrowMarkup,f=a.arrowLeft=e(d.replace(/%title%/gi,
b.tPrev).replace(/%dir%/gi,"left")).addClass("mfp-prevent-close"),d=a.arrowRight=e(d.replace(/%title%/gi,b.tNext).replace(/%dir%/gi,"right")).addClass("mfp-prevent-close"),g=c?"mfpFastClick":"click";f[g](function(){a.prev()});d[g](function(){a.next()});a.isIE7&&(k("b",f[0],!1,!0),k("a",f[0],!1,!0),k("b",d[0],!1,!0),k("a",d[0],!1,!0));a.container.append(f.add(d))}});j("Change.mfp-gallery",function(){a._preloadTimeout&&clearTimeout(a._preloadTimeout);a._preloadTimeout=setTimeout(function(){a.preloadNearbyImages();
a._preloadTimeout=null},16)});j("Close.mfp-gallery",function(){l.off(".mfp-gallery");a.wrap.off("click.mfp-gallery");a.arrowLeft&&c&&a.arrowLeft.add(a.arrowRight).destroyMfpFastClick();a.arrowRight=a.arrowLeft=null})},next:function(){a.direction=!0;a.index=C(a.index+1);a.updateItemHTML()},prev:function(){a.direction=!1;a.index=C(a.index-1);a.updateItemHTML()},goTo:function(b){a.direction=b>=a.index;a.index=b;a.updateItemHTML()},preloadNearbyImages:function(){var b=a.st.gallery.preload,c=Math.min(b[0],
a.items.length),b=Math.min(b[1],a.items.length),d;for(d=1;d<=(a.direction?b:c);d++)a._preloadItem(a.index+d);for(d=1;d<=(a.direction?c:b);d++)a._preloadItem(a.index-d)},_preloadItem:function(b){b=C(b);if(!a.items[b].preloaded){var c=a.items[b];c.parsed||(c=a.parseEl(b));h("LazyLoad",c);if(c.type==="image")c.img=e('<img class="mfp-img" />').on("load.mfploader",function(){c.hasSize=!0}).on("error.mfploader",function(){c.hasSize=!0;c.loadError=!0;h("LazyLoadError",c)}).attr("src",c.src);c.preloaded=
!0}}}});e.magnificPopup.registerModule("retina",{options:{replaceSrc:function(a){return a.src.replace(/\.\w+$/,function(a){return"@2x"+a})},ratio:1},proto:{initRetina:function(){if(window.devicePixelRatio>1){var b=a.st.retina,c=b.ratio,c=!isNaN(c)?c:c();c>1&&(j("ImageHasSize.retina",function(a,b){b.img.css({"max-width":b.img[0].naturalWidth/c,width:"100%"})}),j("ElementParse.retina",function(a,e){e.src=b.replaceSrc(e,c)}))}}}});(function(){var a="ontouchstart"in window,c=function(){i.off("touchmove"+
d+" touchend"+d)},d=".mfpFastClick";e.fn.mfpFastClick=function(f){return e(this).each(function(){var g=e(this),h;if(a){var j,l,o,n,k,p;g.on("touchstart"+d,function(a){n=!1;p=1;k=a.originalEvent?a.originalEvent.touches[0]:a.touches[0];l=k.clientX;o=k.clientY;i.on("touchmove"+d,function(a){k=a.originalEvent?a.originalEvent.touches:a.touches;p=k.length;k=k[0];if(Math.abs(k.clientX-l)>10||Math.abs(k.clientY-o)>10)n=!0,c()}).on("touchend"+d,function(a){c();n||p>1||(h=!0,a.preventDefault(),clearTimeout(j),
j=setTimeout(function(){h=!1},1E3),f())})})}g.on("click"+d,function(){h||f()})})};e.fn.destroyMfpFastClick=function(){e(this).off("touchstart"+d+" click"+d);a&&i.off("touchmove"+d+" touchend"+d)}})();z()})(window.jQuery||window.Zepto);
