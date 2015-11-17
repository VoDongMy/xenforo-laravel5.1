/*
 * XenForo media_view.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(c,b){XenForo.PrepareImage=function(a){this.__construct(a)};XenForo.PrepareImage.prototype={__construct:function(a){(b.navigator.userAgent.indexOf("MSIE ")>0||navigator.userAgent.match(/Trident.*rv\:11\./))&&a.addClass("IE");return!1}};XenForo.register(".imageContainer","XenForo.PrepareImage")})(jQuery,this,document);
