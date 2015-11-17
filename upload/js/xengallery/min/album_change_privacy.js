/*
 * XenForo album_change_privacy.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b){XenForo.XenGallerySetAllPrivacy=function(a){this.__construct(a)};XenForo.XenGallerySetAllPrivacy.prototype={__construct:function(a){this.$select=a;this.$select.bind({change:b.context(this,"setAllPrivacy")})},setAllPrivacy:function(){$privacySelect=b(".PrivacySelect");if(this.$select.val()==="")return $privacySelect.val($privacySelect.data("original-value")),!1;$privacySelect.val(this.$select.val())}};XenForo.register(".SetAllPrivacy","XenForo.XenGallerySetAllPrivacy")})(jQuery,this,document);
