
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function(e,t,n,r){XenForo.XenGalleryMyPlaylist=function(e){this.__construct(e)};XenForo.XenGalleryMyPlaylist.prototype={__construct:function(t){this.phrase=t.data("phrase");this.removeurl=t.data("removeurl");this.$container=t;this.$container.find("div.itemGallery").each(e.context(this,"addDeleteButton"))},addDeleteButton:function(t,n){var r=e(n),i=e('<span title="'+this.phrase+'" class="button delete"><i></i></span>');r.prepend(i);i.click(e.context(this,"deleteClick"))},deleteClick:function(t){var n=e(t.target),r=n.closest(".itemGallery"),i=r.attr("id").match(/\d+/g);XenForo.ajax(this.removeurl,{content_id:i[0],content_type:r.hasClass("video")?"video":""},e.context(this,"deleteClickResponse"))},deleteClickResponse:function(t){if(XenForo.hasResponseError(t)){return false}if(t.message){XenForo.alert(t.message,"",2e3)}if(t.content_id){e("#content_"+t.content_id).remove();this.$container.masonry({itemSelector:".itemGallery",isAnimated:!Modernizr.csstransitions,easing:"linear",gutterWidth:10})}}};XenForo.register("div.masonryContainer.myPlaylist","XenForo.XenGalleryMyPlaylist")}(jQuery,this,document)