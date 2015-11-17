/*
 * XenForo media_share.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(){XenForo.XenGalleryMediaShare=function(b){this.__construct(b)};XenForo.XenGalleryMediaShare.prototype={__construct:function(b){b.on("keydown",function(a){if(a.keyCode!=67&&a.keyCode!==91)return a.preventDefault(),a.stopPropagation(),!1});b.on("cut",function(a){a.preventDefault();a.stopPropagation();return!1});b.on("paste",function(a){a.preventDefault();a.stopPropagation();return!1});b.on("click",function(){this.select()})}};XenForo.register(".CopyInput","XenForo.XenGalleryMediaShare")})(jQuery,
this,document);
