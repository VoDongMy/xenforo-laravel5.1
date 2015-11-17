/*
 * XenForo album_privacy_description.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b){XenForo.XenGalleryPrivacyOption=function(a){this.__construct(a)};XenForo.XenGalleryPrivacyOption.prototype={__construct:function(a){this.$select=a;this.url=a.data("descurl");this.$target=b(a.data("desctarget"));this.$type=a.data("desctype");this.url&&this.$target.length&&(a.bind({keyup:b.context(this,"fetchDescriptionDelayed"),change:b.context(this,"fetchDescription")}),a.val().length&&this.fetchDescription())},fetchDescriptionDelayed:function(){this.delayTimer&&clearTimeout(this.delayTimer);
this.delayTimer=setTimeout(b.context(this,"fetchDescription"),250)},fetchDescription:function(){this.$select.val().length?this.xhr=XenForo.ajax(this.url,{privacy_type:this.$select.val(),phrase_type:this.$type},b.context(this,"ajaxSuccess"),{error:!1}):this.$target.html("")},ajaxSuccess:function(a){a?this.$target.html(a.description):this.$target.html("")}};XenForo.register("select.PrivacyOption","XenForo.XenGalleryPrivacyOption")})(jQuery,this,document);
