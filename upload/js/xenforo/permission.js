/*
 * XenForo permission.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(d,g,f){XenForo.PermissionChoicesOld=function(a){this.__construct(a)};XenForo.PermissionChoicesOld.prototype={__construct:function(a){var b,c={};this.$form=a;this.$selects=a.find("select.PermissionChoice");this.$revokeOption=a.find('.RevokeOption input[type="checkbox"]');a=a.find(".PermissionTooltipOption");a.length&&a.each(function(){var a=d(this);d(a.data("permissionstate").split(" ")).each(function(b,d){c[d]=a})});this.tooltipOptions=c;this.$revokeOption.click(d.context(this,"updateRevokeStatus"));
this.updateRevokeStatus(!0);b=this;this.$selects.each(function(){var a=d(this),c,e;c=d("<span />").click(d.context(b,"handleClick")).data("select",a);e=d('<div class="xenTooltip permissionTooltip" />').hide();a.hide().before(d("<div />").append(c).append(e));c.attr("title",XenForo.htmlspecialchars(c.attr("title"))).tooltip({offset:[-12,5],position:"bottom right",relative:!0,onBeforeShow:function(a){this.getTip().is(":empty")&&a.preventDefault()}});c.data("tooltip",e);b.setReplaceState(c,a,e)})},handleClick:function(a){a=
d(a.currentTarget);a.data("tooltip");var b=a.data("select"),c=b.get(0);c.selectedIndex+1<c.length?c.selectedIndex+=1:c.selectedIndex=0;this.setReplaceState(a,b)},setReplaceState:function(a,b){var c=b.val();a.attr("class","permissionChoice permissionChoice_"+c);a.text(b.find(":selected").text());this.setTooltipState(a)},setTooltipState:function(a){var b=a.data("select").val(),a=a.data("tooltip");this.tooltipOptions[b]?a.html(this.tooltipOptions[b].clone()):a.empty()},updateRevokeStatus:function(a){var b=
this.$form.find(".PermissionOptions");this.$revokeOption.is(":checked")?a===!0?b.hide():b.xfSlideUp():b.xfSlideDown()}};XenForo.PermissionNeverWarning=function(a){var b=d("#PermissionNeverTooltip");b.length&&(b.appendTo(f.body),a.find(".permission td.deny").tooltip({tip:"#PermissionNeverTooltip",position:"center right",offset:[0,5],effect:"fade",predelay:250}))};XenForo.register("form.PermissionChoices","XenForo.PermissionNeverWarning")})(jQuery,this,document);
