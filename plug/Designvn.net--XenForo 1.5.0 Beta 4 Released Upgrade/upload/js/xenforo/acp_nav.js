/*
 * XenForo acp_nav.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b,g,i){XenForo.acpNavInit=function(){var a=b("#sideNav"),h=b("#body"),c=b("#tabsNav .acpTabs"),d=a.hasClass("active"),e=!1,k=function(f){!e&&f!=d&&(e=!0,f?(a.addClass("active"),j(),a.css("left",-a.width()).animate({left:0},function(){a.css("left","");d=!0;e=!1})):a.animate({left:-a.width()},function(){a.css("left","").removeClass("active");e=d=!1}))},j=function(){if(h.length){var f=a.css("height","").height(),c=Math.max(h.height(),b(g).height()-h.offset().top);c&&f<c&&a.css("height",c)}};
b(i).on("click",".AcpSidebarToggler",function(a){a.preventDefault();k(d?!1:!0)});b(i).on("click",".AcpSidebarCloser",function(a){a.preventDefault();k(!1)});b(g).resize(function(){d&&j()});var l=function(){if(c.length){var b=c[0];c.removeClass("withNoLinks");b.scrollHeight>=c.height()*1.1?(a.addClass("withSections"),c.addClass("withNoLinks")):a.removeClass("withSections")}};l();b(g).resize(function(){l()})};b(function(){XenForo.acpNavInit()})})(jQuery,this,document);
