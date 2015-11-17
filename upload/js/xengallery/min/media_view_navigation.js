/*
 * XenForo media_view_navigation.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(a,b){XenForo.XenGalleryMediaViewNextPrev=function(){var c=a("a.PreviousMedia")[0],d=a("a.NextMedia")[0];a(b).keydown(function(e){if(a(".mfp-ready").length)return!1;if(!a("textarea, input").is(":focus"))if(e.which===37){if(c)b.location.href=c.href}else if(e.which===39&&d)b.location.href=d.href})};XenForo.register(".buttonToolbar","XenForo.XenGalleryMediaViewNextPrev")})(jQuery,this,document);
