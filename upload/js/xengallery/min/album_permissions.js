/*
 * XenForo album_permissions.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(a){XenForo.XenGalleryPermissionsList=function(a){this.__construct(a)};XenForo.XenGalleryPermissionsList.prototype={__construct:function(b){this.$select=b;this.$className=".CustomUsers"+this.$select.data("type");this.$select.bind({change:a.context(this,"permissionSet")})},permissionSet:function(){if(this.$select.val()=="shared")return a(this.$className).xfSlideDown();a(this.$className).xfSlideUp()}};XenForo.register(".PermissionsList","XenForo.XenGalleryPermissionsList")})(jQuery,this,document);
