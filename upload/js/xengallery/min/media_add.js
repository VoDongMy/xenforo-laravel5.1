/*
 * XenForo media_add.min.js
 * Copyright 2010-2015 XenForo Ltd.
 * Released under the XenForo License Agreement: http://xenforo.com/license-agreement
 */
(function(b,k,j){XenForo.XenGalleryContainerChooser=function(b){this.__construct(b)};XenForo.XenGalleryContainerChooser.prototype={__construct:function(c){this.$form=c;this.$albumChosen=b(".AlbumSelect");this.$albumChosen.chosen({width:"100%",search_contains:!0});this.$categoryChosen=b(".CategorySelect");this.$categoryChosen.chosen({width:"100%",search_contains:!0});b(".AlbumDisabler").bind({DisablerDisabled:b.context(this,"enableDisable"),DisablerEnabled:b.context(this,"enableDisable")});b(".CategoryDisabler").bind({DisablerDisabled:b.context(this,
"enableDisable"),DisablerEnabled:b.context(this,"enableDisable")});$chooseAlbum=b("input.ChooseAlbum");$chooseAlbum.bind({click:b.context(this,"albumChosen")});$chooseCategory=b("input.ChooseCategory");$chooseCategory.bind({click:b.context(this,"categoryChosen")});$albumSelect=b("#SelectAlbum");$categorySelect=b("#SelectCategory");$containerType=b('input[name="container_type"]');$containerId=b('input[name="container_id"]');if($albumSelect.val()>0||$albumSelect.prop("checked")||$chooseAlbum.prop("checked"))this.albumChosen(),
this.albumChange();if($categorySelect.val()>0||$categorySelect.prop("checked")||$chooseCategory.prop("checked"))this.categoryChosen(),this.categoryChange()},enableDisable:function(c){$disabler=b(c.target);$target=b($disabler.data("linkedto"));c.type=="DisablerDisabled"&&$target.attr("disabled",!1).trigger("chosen:updated");c.type=="DisablerEnabled"&&$target.attr("disabled",!0).trigger("chosen:updated")},albumChosen:function(){b(".MediaEntryArea").xfFadeUp(XenForo.speed.normal);$categorySelect.val("");
$containerType.val("album");$containerId.val(0);$albumSelect.bind("chosen:showing_dropdown",function(){$albumSelect.val("").trigger("chosen:updated")});$albumSelect.bind({change:b.context(this,"albumChange")})},categoryChosen:function(){b(".MediaEntryArea").xfFadeUp(XenForo.speed.normal);$albumSelect.val("").trigger("change");$containerType.val("category");$containerId.val(0);$categorySelect.bind({change:b.context(this,"categoryChange")})},albumChange:function(){var c=b($albumSelect).val();if(typeof c==
"undefined"||c==null)c="0.0";this.$albumId=c.substr(0,c.indexOf("."));if(this.$albumId=="")this.$albumId=c;b.isNumeric(this.$albumId)&&this.$albumId>0?($containerId.val(this.$albumId),this.loadEditForm("album")):$containerId.val(0);this.$albumId=="create"&&(this.albumCreateShow(),b(".MediaEntryArea").xfFadeUp(XenForo.speed.normal))},categoryChange:function(){this.$categoryId=b($categorySelect).val();b.isNumeric(this.$categoryId)&&this.$categoryId>0?($containerId.val(this.$categoryId),this.loadEditForm("category")):
(b(".MediaEntryArea").xfFadeUp(XenForo.speed.normal),$containerId.val(0))},albumCreateShow:function(){cache=!1;options={};options.speed=XenForo.speed.fast;this.trigger={href:"index.php?xengallery/albums/create"};this.OverlayLoader=new XenForo.OverlayLoader(b(this.trigger),!1,options);this.OverlayLoader.load()},loadEditForm:function(c){url="index.php?xengallery/load-edit-form";this.xhr=XenForo.ajax(url,{container_type:c,container_id:$containerId.val()},b.context(this,"ajaxSuccess"))},ajaxSuccess:function(c){if(c.error)return XenForo.alert(c.error),
!1;c.templateHtml&&new XenForo.ExtLoader(c,function(){b(c.templateHtml).xfInsert("replaceAll",".MediaEntryArea","xfFadeDown",XenForo.speed.normal)})}};XenForo.PreventSubmit=function(b){this.__construct(b)};XenForo.PreventSubmit.prototype={__construct:function(c){c.bind("keypress",function(g){g.keyCode==13&&(g.preventDefault(),c.val()&&b(".DownloadTrigger").click())})}};XenForo.SetAllTrigger=function(c){c.click(function(c){c.preventDefault();options={};options.speed=XenForo.speed.fast;this.trigger=
{href:b(this).attr("href")};this.OverlayLoader=new XenForo.OverlayLoader(b(this.trigger),!0,options);this.OverlayLoader.load();b(j).on("XFOverlay",function(b){$overlay=b.overlay.getOverlay();$overlay.find("form").trigger("reset");$overlay.find("input.TagInput").length&&$overlay.find("input.TagInput").importTags("")})})};XenForo.SetAll=function(b){this.__construct(b)};XenForo.SetAll.prototype={__construct:function(c){this.$form=c;c.find(".SubmitSetAll").bind({click:b.context(this,"submitClick")})},
submitClick:function(){var c=this.$form,g=b(c.data("target")),f=c.data("type"),j=c.find("input.TagInput"),i=c.find('input[name="set_all_titles_'+f+'"]').is(":checked"),a=c.find("#TitleText_"+f).val(),d=c.find('input[name="set_all_descriptions_'+f+'"]').is(":checked"),e=c.find("#DescriptionText_"+f).val(),c=c.find('input[name="set_all_tags_'+f+'"]').is(":checked");if(i||d||c)i&&g.find(".TitleInput").each(function(d,e){var i=b(e),c=a,c=c.replace("%f",i.data("filename")),c=c.replace("%n",d+1);b(this).val(c)}),
d&&g.find(".DescriptionInput").each(function(){b(this).val(e)}),c&&(g.find(".TagInput").each(function(){b(this).importTags(j.val())}),g.parents(".AttachedFilesUnit, #VideoPreviewArea").find('.ExpandCollapse[data-action="expand"]').click())}};XenForo.ExpandCollapse=function(b){this.__construct(b)};XenForo.ExpandCollapse.prototype={__construct:function(c){this.$link=c;c.bind("click",b.context(this,"click"))},click:function(c){c.preventDefault();this.$link.data("type")=="image_upload"||this.$link.data("type")==
"video_upload"?$parent=this.$link.parents(".AttachedFilesUnit"):this.$link.data("type")=="video_embed"&&($parent=this.$link.parents("#VideoPreviewArea"));$items=$parent.find(".AttachedFile:not(#AttachedFileTemplate)");if($items.length)switch(this.$link.data("action")){case "expand":$items.each(function(){$item=b(this);$item.hasClass("expanded")||($button=$item.find(".ToggleTrigger.button"),$button.length&&$button.click())});break;case "collapse":$items.each(function(){$item=b(this);$item.hasClass("collapsed")||
($button=$item.find(".ToggleTrigger.button"),$button.length&&$button.click())})}}};XenForo.ItemToggleTrigger=function(){b(j).on("ToggleTriggerEvent",function(b,g){if(g){var f=g.$target.closest(".itemRow");g.closing?(f.removeClass("expanded"),f.addClass("collapsed"),f.data("expanded","0")):(f.addClass("expanded"),f.removeClass("collapsed"),f.data("expanded","1"))}})};XenForo.register(".ContainerChooser","XenForo.XenGalleryContainerChooser");XenForo.register("input.PreventSubmit","XenForo.PreventSubmit");
XenForo.register("a.SetAllTrigger","XenForo.SetAllTrigger");XenForo.register("form.SetAll","XenForo.SetAll");XenForo.register("a.ExpandCollapse","XenForo.ExpandCollapse");XenForo.register("a.ToggleTrigger.ItemToggleTrigger","XenForo.ItemToggleTrigger")})(jQuery,this,document);
(function(){var b,k,j,c,g,f={}.hasOwnProperty,m=function(b,a){function d(){this.constructor=b}for(var e in a)f.call(a,e)&&(b[e]=a[e]);d.prototype=a.prototype;b.prototype=new d;b.__super__=a.prototype;return b};c=function(){function b(){this.options_index=0;this.parsed=[]}b.prototype.add_node=function(a){return a.nodeName.toUpperCase()==="OPTGROUP"?this.add_group(a):this.add_option(a)};b.prototype.add_group=function(a){var d,e,b,c,i,f;d=this.parsed.length;this.parsed.push({array_index:d,group:!0,label:this.escapeExpression(a.label),
title:a.title?a.title:void 0,children:0,disabled:a.disabled,classes:a.className});i=a.childNodes;f=[];for(b=0,c=i.length;b<c;b++)e=i[b],f.push(this.add_option(e,d,a.disabled));return f};b.prototype.add_option=function(a,d,e){if(a.nodeName.toUpperCase()==="OPTION")return a.text!==""?(d!=null&&(this.parsed[d].children+=1),this.parsed.push({array_index:this.parsed.length,options_index:this.options_index,value:a.value,text:a.text,html:a.innerHTML,title:a.title?a.title:void 0,selected:a.selected,disabled:e===
!0?e:a.disabled,group_array_index:d,group_label:d!=null?this.parsed[d].label:null,classes:a.className,style:a.style.cssText})):this.parsed.push({array_index:this.parsed.length,options_index:this.options_index,empty:!0}),this.options_index+=1};b.prototype.escapeExpression=function(a){var d;if(a==null||a===!1)return"";if(!/[\&\<\>\"\'\`]/.test(a))return a;d={"<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#x27;","`":"&#x60;"};return a.replace(/&(?!\w+;)|[\<\>\"\'\`]/g,function(a){return d[a]||"&amp;"})};return b}();
c.select_to_array=function(b){var a,d,e,l;a=new c;l=b.childNodes;for(d=0,e=l.length;d<e;d++)b=l[d],a.add_node(b);return a.parsed};k=function(){function b(a,d){this.form_field=a;this.options=d!=null?d:{};if(b.browser_is_supported())this.is_multiple=this.form_field.multiple,this.set_default_text(),this.set_default_values(),this.setup(),this.set_up_html(),this.register_observers(),this.on_ready()}b.prototype.set_default_values=function(){var a=this;this.click_test_action=function(d){return a.test_active_click(d)};
this.activate_action=function(d){return a.activate_field(d)};this.results_showing=this.mouse_on_container=this.active_field=!1;this.result_highlighted=null;this.allow_single_deselect=this.options.allow_single_deselect!=null&&this.form_field.options[0]!=null&&this.form_field.options[0].text===""?this.options.allow_single_deselect:!1;this.disable_search_threshold=this.options.disable_search_threshold||0;this.disable_search=this.options.disable_search||!1;this.enable_split_word_search=this.options.enable_split_word_search!=
null?this.options.enable_split_word_search:!0;this.group_search=this.options.group_search!=null?this.options.group_search:!0;this.search_contains=this.options.search_contains||!1;this.single_backstroke_delete=this.options.single_backstroke_delete!=null?this.options.single_backstroke_delete:!0;this.max_selected_options=this.options.max_selected_options||Infinity;this.inherit_select_classes=this.options.inherit_select_classes||!1;this.display_selected_options=this.options.display_selected_options!=
null?this.options.display_selected_options:!0;this.display_disabled_options=this.options.display_disabled_options!=null?this.options.display_disabled_options:!0;return this.include_group_label_in_selected=this.options.include_group_label_in_selected||!1};b.prototype.set_default_text=function(){this.default_text=this.form_field.getAttribute("data-placeholder")?this.form_field.getAttribute("data-placeholder"):this.is_multiple?this.options.placeholder_text_multiple||this.options.placeholder_text||b.default_multiple_text:
this.options.placeholder_text_single||this.options.placeholder_text||b.default_single_text;return this.results_none_found=this.form_field.getAttribute("data-no_results_text")||this.options.no_results_text||b.default_no_result_text};b.prototype.choice_label=function(a){return this.include_group_label_in_selected&&a.group_label!=null?"<b class='group-name'>"+a.group_label+"</b>"+a.html:a.html};b.prototype.mouse_enter=function(){return this.mouse_on_container=!0};b.prototype.mouse_leave=function(){return this.mouse_on_container=
!1};b.prototype.input_focus=function(){var a=this;if(this.is_multiple){if(!this.active_field)return setTimeout(function(){return a.container_mousedown()},50)}else if(!this.active_field)return this.activate_field()};b.prototype.input_blur=function(){var a=this;if(!this.mouse_on_container)return this.active_field=!1,setTimeout(function(){return a.blur_test()},100)};b.prototype.results_option_build=function(a){var d,e,b,c,h;d="";h=this.results_data;for(b=0,c=h.length;b<c;b++)e=h[b],d+=e.group?this.result_add_group(e):
this.result_add_option(e),a!=null&&a.first&&(e.selected&&this.is_multiple?this.choice_build(e):e.selected&&!this.is_multiple&&this.single_set_selected_text(this.choice_label(e)));return d};b.prototype.result_add_option=function(a){var d,e;if(!a.search_match)return"";if(!this.include_option_in_results(a))return"";d=[];!a.disabled&&(!a.selected||!this.is_multiple)&&d.push("active-result");a.disabled&&(!a.selected||!this.is_multiple)&&d.push("disabled-result");a.selected&&d.push("result-selected");a.group_array_index!=
null&&d.push("group-option");a.classes!==""&&d.push(a.classes);e=document.createElement("li");e.className=d.join(" ");e.style.cssText=a.style;e.setAttribute("data-option-array-index",a.array_index);e.innerHTML=a.search_text;if(a.title)e.title=a.title;return this.outerHTML(e)};b.prototype.result_add_group=function(a){var d,e;if(!a.search_match&&!a.group_match)return"";if(!(a.active_options>0))return"";d=[];d.push("group-result");a.classes&&d.push(a.classes);e=document.createElement("li");e.className=
d.join(" ");e.innerHTML=a.search_text;if(a.title)e.title=a.title;return this.outerHTML(e)};b.prototype.results_update_field=function(){this.set_default_text();this.is_multiple||this.results_reset_cleanup();this.result_clear_highlight();this.results_build();if(this.results_showing)return this.winnow_results()};b.prototype.reset_single_select_options=function(){var a,d,e,b,c;b=this.results_data;c=[];for(d=0,e=b.length;d<e;d++)a=b[d],a.selected?c.push(a.selected=!1):c.push(void 0);return c};b.prototype.results_toggle=
function(){return this.results_showing?this.results_hide():this.results_show()};b.prototype.results_search=function(){return this.results_showing?this.winnow_results():this.results_show()};b.prototype.winnow_results=function(){var a,d,e,b,c,h,f,g,i,j,k;this.no_results_clear();e=0;c=this.get_search_text();a=c.replace(/[-[\]{}()*+?.,\\^$|#\s]/g,"\\$&");g=RegExp(a,"i");d=this.get_search_regex(a);k=this.results_data;for(i=0,j=k.length;i<j;i++)if(a=k[i],a.search_match=!1,b=null,this.include_option_in_results(a)){if(a.group)a.group_match=
!1,a.active_options=0;a.group_array_index!=null&&this.results_data[a.group_array_index]&&(b=this.results_data[a.group_array_index],b.active_options===0&&b.search_match&&(e+=1),b.active_options+=1);a.search_text=a.group?a.label:a.html;if(!a.group||this.group_search)if(a.search_match=this.search_string_match(a.search_text,d),a.search_match&&!a.group&&(e+=1),a.search_match){if(c.length)h=a.search_text.search(g),f=a.search_text.substr(0,h+c.length)+"</em>"+a.search_text.substr(h+c.length),a.search_text=
f.substr(0,h)+"<em>"+f.substr(h);if(b!=null)b.group_match=!0}else if(a.group_array_index!=null&&this.results_data[a.group_array_index].search_match)a.search_match=!0}this.result_clear_highlight();return e<1&&c.length?(this.update_results_content(""),this.no_results(c)):(this.update_results_content(this.results_option_build()),this.winnow_results_set_highlight())};b.prototype.get_search_regex=function(a){return RegExp((this.search_contains?"":"^")+a,"i")};b.prototype.search_string_match=function(a,
d){var b,c,f,h;if(d.test(a))return!0;else if(this.enable_split_word_search&&(a.indexOf(" ")>=0||a.indexOf("[")===0))if(c=a.replace(/\[|\]/g,"").split(" "),c.length)for(f=0,h=c.length;f<h;f++)if(b=c[f],d.test(b))return!0};b.prototype.choices_count=function(){var a,d,b,c;if(this.selected_option_count!=null)return this.selected_option_count;this.selected_option_count=0;c=this.form_field.options;for(d=0,b=c.length;d<b;d++)a=c[d],a.selected&&(this.selected_option_count+=1);return this.selected_option_count};
b.prototype.choices_click=function(a){a.preventDefault();if(!this.results_showing&&!this.is_disabled)return this.results_show()};b.prototype.keyup_checker=function(a){var d,b;d=(b=a.which)!=null?b:a.keyCode;this.search_field_scale();switch(d){case 8:if(this.is_multiple&&this.backstroke_length<1&&this.choices_count()>0)return this.keydown_backstroke();else if(!this.pending_backstroke)return this.result_clear_highlight(),this.results_search();break;case 13:a.preventDefault();if(this.results_showing)return this.result_select(a);
break;case 27:return this.results_showing&&this.results_hide(),!0;case 9:case 38:case 40:case 16:case 91:case 17:break;default:return this.results_search()}};b.prototype.clipboard_event_checker=function(){var a=this;return setTimeout(function(){return a.results_search()},50)};b.prototype.container_width=function(){return this.options.width!=null?this.options.width:""+this.form_field.offsetWidth+"px"};b.prototype.include_option_in_results=function(a){return this.is_multiple&&!this.display_selected_options&&
a.selected?!1:!this.display_disabled_options&&a.disabled?!1:a.empty?!1:!0};b.prototype.search_results_touchstart=function(a){this.touch_started=!0;return this.search_results_mouseover(a)};b.prototype.search_results_touchmove=function(a){this.touch_started=!1;return this.search_results_mouseout(a)};b.prototype.search_results_touchend=function(a){if(this.touch_started)return this.search_results_mouseup(a)};b.prototype.outerHTML=function(a){var d;if(a.outerHTML)return a.outerHTML;d=document.createElement("div");
d.appendChild(a);return d.innerHTML};b.browser_is_supported=function(){return window.navigator.appName==="Microsoft Internet Explorer"?document.documentMode>=8:/iP(od|hone)/i.test(window.navigator.userAgent)?!1:/Android/i.test(window.navigator.userAgent)&&/Mobile/i.test(window.navigator.userAgent)?!1:!0};b.default_multiple_text="Select Some Options";b.default_single_text="Select an Option";b.default_no_result_text="No results match";return b}();b=jQuery;b.fn.extend({chosen:function(c){return!k.browser_is_supported()?
this:this.each(function(){var a,d;a=b(this);d=a.data("chosen");c==="destroy"&&d instanceof j?d.destroy():d instanceof j||a.data("chosen",new j(this,c))})}});j=function(f){function a(){return g=a.__super__.constructor.apply(this,arguments)}m(a,f);a.prototype.setup=function(){this.form_field_jq=b(this.form_field);this.current_selectedIndex=this.form_field.selectedIndex;return this.is_rtl=this.form_field_jq.hasClass("chosen-rtl")};a.prototype.set_up_html=function(){var d;d=["chosen-container"];d.push("chosen-container-"+
(this.is_multiple?"multi":"single"));this.inherit_select_classes&&this.form_field.className&&d.push(this.form_field.className);this.is_rtl&&d.push("chosen-rtl");d={"class":d.join(" "),style:"width: "+this.container_width()+";",title:this.form_field.title};if(this.form_field.id.length)d.id=this.form_field.id.replace(/[^\w]/g,"_")+"_chosen";this.container=b("<div />",d);this.is_multiple?this.container.html('<ul class="chosen-choices"><li class="search-field"><input type="text" value="'+this.default_text+
'" class="default" autocomplete="off" style="width:25px;" /></li></ul><div class="chosen-drop"><ul class="chosen-results"></ul></div>'):this.container.html('<a class="chosen-single chosen-default" tabindex="-1"><span>'+this.default_text+'</span><div><b></b></div></a><div class="chosen-drop"><div class="chosen-search"><input type="text" autocomplete="off" /></div><ul class="chosen-results"></ul></div>');this.form_field_jq.hide().after(this.container);this.dropdown=this.container.find("div.chosen-drop").first();
this.search_field=this.container.find("input").first();this.search_results=this.container.find("ul.chosen-results").first();this.search_field_scale();this.search_no_results=this.container.find("li.no-results").first();this.is_multiple?(this.search_choices=this.container.find("ul.chosen-choices").first(),this.search_container=this.container.find("li.search-field").first()):(this.search_container=this.container.find("div.chosen-search").first(),this.selected_item=this.container.find(".chosen-single").first());
this.results_build();this.set_tab_index();return this.set_label_behavior()};a.prototype.on_ready=function(){return this.form_field_jq.trigger("chosen:ready",{chosen:this})};a.prototype.register_observers=function(){var d=this;this.container.bind("touchstart.chosen",function(a){d.container_mousedown(a);return a.preventDefault()});this.container.bind("touchend.chosen",function(a){d.container_mouseup(a);return a.preventDefault()});this.container.bind("mousedown.chosen",function(a){d.container_mousedown(a)});
this.container.bind("mouseup.chosen",function(a){d.container_mouseup(a)});this.container.bind("mouseenter.chosen",function(a){d.mouse_enter(a)});this.container.bind("mouseleave.chosen",function(a){d.mouse_leave(a)});this.search_results.bind("mouseup.chosen",function(a){d.search_results_mouseup(a)});this.search_results.bind("mouseover.chosen",function(a){d.search_results_mouseover(a)});this.search_results.bind("mouseout.chosen",function(a){d.search_results_mouseout(a)});this.search_results.bind("mousewheel.chosen DOMMouseScroll.chosen",
function(a){d.search_results_mousewheel(a)});this.search_results.bind("touchstart.chosen",function(a){d.search_results_touchstart(a)});this.search_results.bind("touchmove.chosen",function(a){d.search_results_touchmove(a)});this.search_results.bind("touchend.chosen",function(a){d.search_results_touchend(a)});this.form_field_jq.bind("chosen:updated.chosen",function(a){d.results_update_field(a)});this.form_field_jq.bind("chosen:activate.chosen",function(a){d.activate_field(a)});this.form_field_jq.bind("chosen:open.chosen",
function(a){d.container_mousedown(a)});this.form_field_jq.bind("chosen:close.chosen",function(a){d.input_blur(a)});this.search_field.bind("blur.chosen",function(a){d.input_blur(a)});this.search_field.bind("keyup.chosen",function(a){d.keyup_checker(a)});this.search_field.bind("keydown.chosen",function(a){d.keydown_checker(a)});this.search_field.bind("focus.chosen",function(a){d.input_focus(a)});this.search_field.bind("cut.chosen",function(a){d.clipboard_event_checker(a)});this.search_field.bind("paste.chosen",
function(a){d.clipboard_event_checker(a)});return this.is_multiple?this.search_choices.bind("click.chosen",function(a){d.choices_click(a)}):this.container.bind("click.chosen",function(a){a.preventDefault()})};a.prototype.destroy=function(){b(this.container[0].ownerDocument).unbind("click.chosen",this.click_test_action);if(this.search_field[0].tabIndex)this.form_field_jq[0].tabIndex=this.search_field[0].tabIndex;this.container.remove();this.form_field_jq.removeData("chosen");return this.form_field_jq.show()};
a.prototype.search_field_disabled=function(){if(this.is_disabled=this.form_field_jq[0].disabled)return this.container.addClass("chosen-disabled"),this.search_field[0].disabled=!0,this.is_multiple||this.selected_item.unbind("focus.chosen",this.activate_action),this.close_field();else if(this.container.removeClass("chosen-disabled"),this.search_field[0].disabled=!1,!this.is_multiple)return this.selected_item.bind("focus.chosen",this.activate_action)};a.prototype.container_mousedown=function(a){if(!this.is_disabled&&
(a&&a.type==="mousedown"&&!this.results_showing&&a.preventDefault(),!(a!=null&&b(a.target).hasClass("search-choice-close")))){if(this.active_field){if(!this.is_multiple&&a&&(b(a.target)[0]===this.selected_item[0]||b(a.target).parents("a.chosen-single").length))a.preventDefault(),this.results_toggle()}else this.is_multiple&&this.search_field.val(""),b(this.container[0].ownerDocument).bind("click.chosen",this.click_test_action),this.results_show();return this.activate_field()}};a.prototype.container_mouseup=
function(a){if(a.target.nodeName==="ABBR"&&!this.is_disabled)return this.results_reset(a)};a.prototype.search_results_mousewheel=function(a){var b;a.originalEvent&&(b=a.originalEvent.deltaY||-a.originalEvent.wheelDelta||a.originalEvent.detail);if(b!=null)return a.preventDefault(),a.type==="DOMMouseScroll"&&(b*=40),this.search_results.scrollTop(b+this.search_results.scrollTop())};a.prototype.blur_test=function(){if(!this.active_field&&this.container.hasClass("chosen-container-active"))return this.close_field()};
a.prototype.close_field=function(){b(this.container[0].ownerDocument).unbind("click.chosen",this.click_test_action);this.active_field=!1;this.results_hide();this.container.removeClass("chosen-container-active");this.clear_backstroke();this.show_search_field_default();return this.search_field_scale()};a.prototype.activate_field=function(){this.container.addClass("chosen-container-active");this.active_field=!0;this.search_field.val(this.search_field.val());return this.search_field.focus()};a.prototype.test_active_click=
function(a){a=b(a.target).closest(".chosen-container");return a.length&&this.container[0]===a[0]?this.active_field=!0:this.close_field()};a.prototype.results_build=function(){this.parsing=!0;this.selected_option_count=null;this.results_data=c.select_to_array(this.form_field);if(this.is_multiple)this.search_choices.find("li.search-choice").remove();else if(!this.is_multiple)this.single_set_selected_text(),this.disable_search||this.form_field.options.length<=this.disable_search_threshold?(this.search_field[0].readOnly=
!0,this.container.addClass("chosen-container-single-nosearch")):(this.search_field[0].readOnly=!1,this.container.removeClass("chosen-container-single-nosearch"));this.update_results_content(this.results_option_build({first:!0}));this.search_field_disabled();this.show_search_field_default();this.search_field_scale();return this.parsing=!1};a.prototype.result_do_highlight=function(a){var b,c,f,h;if(a.length)if(this.result_clear_highlight(),this.result_highlight=a,this.result_highlight.addClass("highlighted"),
c=parseInt(this.search_results.css("maxHeight"),10),h=this.search_results.scrollTop(),f=c+h,b=this.result_highlight.position().top+this.search_results.scrollTop(),a=b+this.result_highlight.outerHeight(),a>=f)return this.search_results.scrollTop(a-c>0?a-c:0);else if(b<h)return this.search_results.scrollTop(b)};a.prototype.result_clear_highlight=function(){this.result_highlight&&this.result_highlight.removeClass("highlighted");return this.result_highlight=null};a.prototype.results_show=function(){if(this.is_multiple&&
this.max_selected_options<=this.choices_count())return this.form_field_jq.trigger("chosen:maxselected",{chosen:this}),!1;this.container.addClass("chosen-with-drop");this.results_showing=!0;this.search_field.focus();this.search_field.val(this.search_field.val());this.winnow_results();return this.form_field_jq.trigger("chosen:showing_dropdown",{chosen:this})};a.prototype.update_results_content=function(a){return this.search_results.html(a)};a.prototype.results_hide=function(){this.results_showing&&
(this.result_clear_highlight(),this.container.removeClass("chosen-with-drop"),this.form_field_jq.trigger("chosen:hiding_dropdown",{chosen:this}));return this.results_showing=!1};a.prototype.set_tab_index=function(){var a;if(this.form_field.tabIndex)return a=this.form_field.tabIndex,this.form_field.tabIndex=-1,this.search_field[0].tabIndex=a};a.prototype.set_label_behavior=function(){var a=this;this.form_field_label=this.form_field_jq.parents("label");if(!this.form_field_label.length&&this.form_field.id.length)this.form_field_label=
b("label[for='"+this.form_field.id+"']");if(this.form_field_label.length>0)return this.form_field_label.bind("click.chosen",function(b){return a.is_multiple?a.container_mousedown(b):a.activate_field()})};a.prototype.show_search_field_default=function(){return this.is_multiple&&this.choices_count()<1&&!this.active_field?(this.search_field.val(this.default_text),this.search_field.addClass("default")):(this.search_field.val(""),this.search_field.removeClass("default"))};a.prototype.search_results_mouseup=
function(a){var c;c=b(a.target).hasClass("active-result")?b(a.target):b(a.target).parents(".active-result").first();if(c.length)return this.result_highlight=c,this.result_select(a),this.search_field.focus()};a.prototype.search_results_mouseover=function(a){if(a=b(a.target).hasClass("active-result")?b(a.target):b(a.target).parents(".active-result").first())return this.result_do_highlight(a)};a.prototype.search_results_mouseout=function(a){if(b(a.target).hasClass("active-result"))return this.result_clear_highlight()};
a.prototype.choice_build=function(a){var c,f=this;c=b("<li />",{"class":"search-choice"}).html("<span>"+this.choice_label(a)+"</span>");a.disabled?c.addClass("search-choice-disabled"):(a=b("<a />",{"class":"search-choice-close","data-option-array-index":a.array_index}),a.bind("click.chosen",function(a){return f.choice_destroy_link_click(a)}),c.append(a));return this.search_container.before(c)};a.prototype.choice_destroy_link_click=function(a){a.preventDefault();a.stopPropagation();if(!this.is_disabled)return this.choice_destroy(b(a.target))};
a.prototype.choice_destroy=function(a){if(this.result_deselect(a[0].getAttribute("data-option-array-index")))return this.show_search_field_default(),this.is_multiple&&this.choices_count()>0&&this.search_field.val().length<1&&this.results_hide(),a.parents("li").first().remove(),this.search_field_scale()};a.prototype.results_reset=function(){this.reset_single_select_options();this.form_field.options[0].selected=!0;this.single_set_selected_text();this.show_search_field_default();this.results_reset_cleanup();
this.form_field_jq.trigger("change");if(this.active_field)return this.results_hide()};a.prototype.results_reset_cleanup=function(){this.current_selectedIndex=this.form_field.selectedIndex;return this.selected_item.find("abbr").remove()};a.prototype.result_select=function(a){var b;if(this.result_highlight){b=this.result_highlight;this.result_clear_highlight();if(this.is_multiple&&this.max_selected_options<=this.choices_count())return this.form_field_jq.trigger("chosen:maxselected",{chosen:this}),!1;
this.is_multiple?b.removeClass("active-result"):this.reset_single_select_options();b.addClass("result-selected");b=this.results_data[b[0].getAttribute("data-option-array-index")];b.selected=!0;this.form_field.options[b.options_index].selected=!0;this.selected_option_count=null;this.is_multiple?this.choice_build(b):this.single_set_selected_text(this.choice_label(b));(!a.metaKey&&!a.ctrlKey||!this.is_multiple)&&this.results_hide();this.search_field.val("");(this.is_multiple||this.form_field.selectedIndex!==
this.current_selectedIndex)&&this.form_field_jq.trigger("change",{selected:this.form_field.options[b.options_index].value});this.current_selectedIndex=this.form_field.selectedIndex;a.preventDefault();return this.search_field_scale()}};a.prototype.single_set_selected_text=function(a){if(a==null)a=this.default_text;a===this.default_text?this.selected_item.addClass("chosen-default"):(this.single_deselect_control_build(),this.selected_item.removeClass("chosen-default"));return this.selected_item.find("span").html(a)};
a.prototype.result_deselect=function(a){a=this.results_data[a];return this.form_field.options[a.options_index].disabled?!1:(a.selected=!1,this.form_field.options[a.options_index].selected=!1,this.selected_option_count=null,this.result_clear_highlight(),this.results_showing&&this.winnow_results(),this.form_field_jq.trigger("change",{deselected:this.form_field.options[a.options_index].value}),this.search_field_scale(),!0)};a.prototype.single_deselect_control_build=function(){if(this.allow_single_deselect)return this.selected_item.find("abbr").length||
this.selected_item.find("span").first().after('<abbr class="search-choice-close"></abbr>'),this.selected_item.addClass("chosen-single-with-deselect")};a.prototype.get_search_text=function(){return b("<div/>").text(b.trim(this.search_field.val())).html()};a.prototype.winnow_results_set_highlight=function(){var a;a=!this.is_multiple?this.search_results.find(".result-selected.active-result"):[];a=a.length?a.first():this.search_results.find(".active-result").first();if(a!=null)return this.result_do_highlight(a)};
a.prototype.no_results=function(a){var c;c=b('<li class="no-results">'+this.results_none_found+' "<span></span>"</li>');c.find("span").first().html(a);this.search_results.append(c);return this.form_field_jq.trigger("chosen:no_results",{chosen:this})};a.prototype.no_results_clear=function(){return this.search_results.find(".no-results").remove()};a.prototype.keydown_arrow=function(){var a;if(this.results_showing&&this.result_highlight){if(a=this.result_highlight.nextAll("li.active-result").first())return this.result_do_highlight(a)}else return this.results_show()};
a.prototype.keyup_arrow=function(){var a;if(!this.results_showing&&!this.is_multiple)return this.results_show();else if(this.result_highlight)return a=this.result_highlight.prevAll("li.active-result"),a.length?this.result_do_highlight(a.first()):(this.choices_count()>0&&this.results_hide(),this.result_clear_highlight())};a.prototype.keydown_backstroke=function(){var a;if(this.pending_backstroke)return this.choice_destroy(this.pending_backstroke.find("a").first()),this.clear_backstroke();else if(a=
this.search_container.siblings("li.search-choice").last(),a.length&&!a.hasClass("search-choice-disabled"))return this.pending_backstroke=a,this.single_backstroke_delete?this.keydown_backstroke():this.pending_backstroke.addClass("search-choice-focus")};a.prototype.clear_backstroke=function(){this.pending_backstroke&&this.pending_backstroke.removeClass("search-choice-focus");return this.pending_backstroke=null};a.prototype.keydown_checker=function(a){var b,c;b=(c=a.which)!=null?c:a.keyCode;this.search_field_scale();
b!==8&&this.pending_backstroke&&this.clear_backstroke();switch(b){case 8:this.backstroke_length=this.search_field.val().length;break;case 9:this.results_showing&&!this.is_multiple&&this.result_select(a);this.mouse_on_container=!1;break;case 13:this.results_showing&&a.preventDefault();break;case 32:this.disable_search&&a.preventDefault();break;case 38:a.preventDefault();this.keyup_arrow();break;case 40:a.preventDefault(),this.keydown_arrow()}};a.prototype.search_field_scale=function(){var a,c,f,g,
h;if(this.is_multiple){a="position:absolute; left: -1000px; top: -1000px; display:none;";f="font-size,font-style,font-weight,font-family,line-height,text-transform,letter-spacing".split(",");for(g=0,h=f.length;g<h;g++)c=f[g],a+=c+":"+this.search_field.css(c)+";";a=b("<div />",{style:a});a.text(this.search_field.val());b("body").append(a);c=a.width()+25;a.remove();a=this.container.outerWidth();c>a-10&&(c=a-10);return this.search_field.css({width:c+"px"})}};return a}(k)}).call(this);