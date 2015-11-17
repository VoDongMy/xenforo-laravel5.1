/*
 * XenForo category_list.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(){XenForo.CategoryList=function(a){this.__construct(a)};XenForo.CategoryList.prototype={__construct:function(a){a.sapling();a.data("liststyle")=="collapsibleExpanded"&&a.data("sapling").expand()}};XenForo.register(".CategoryList","XenForo.CategoryList")})(jQuery,this,document);
(function(a,j,k,d){a.sapling=function(e,f){var b=this,c=a(e),d={multiexpand:!0,animation:!1},g=function(a){a.addClass("sapling-expanded")},h=function(a){a.removeClass("sapling-expanded")},i=function(){a(this).hasClass("sapling-expanded")?h(a(this)):(b.settings.multiexpand||c.find(".sapling-expanded").not(a(this).parents()).trigger("click"),g(a(this)))};b.settings={};b.init=function(){b.settings=a.extend({},d,f);b.settings.animation&&(g=function(a){a.children("ul,ol").slideDown(function(){a.addClass("sapling-expanded")})},
h=function(a){a.children("ul,ol").slideUp(function(){a.removeClass("sapling-expanded")})});c.addClass("sapling-list");c.children("li").addClass("sapling-top-level");c.find("li").each(function(){a(this).children("ul,ol").index()!=-1&&(a(this).addClass("sapling-item"),a(this).bind("click",i),a(this).children("ul,ol").bind("click",function(a){if(a.target.nodeName!="A")return!1}))})};b.expand=function(){g(c.find(".sapling-item"))};b.collapse=function(){h(c.find(".sapling-expanded"))};b.init()};a.fn.sapling=
function(e){return this.each(function(){if(d==a(this).data("sapling")){var f=new a.sapling(this,e);a(this).data("sapling",f)}})}})(jQuery,window,document);
