
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function(e,t,n,r){XenForo.XenGalleryEditor=function(e){this.__construct(e)};XenForo.XenGalleryEditor.prototype={__construct:function(t){this.redactor=t.data("redactor");this.redactorOptions=t.data("options");this.dialogAlbumUrl="gallery/editor?type=album";this.titleAlbum="";this.dialogContentUrl="gallery/editor?type=content";this.titleContent="";if(typeof this.redactor!="undefined"){this.redactor.opts.buttonsCustom.insertAlbum.callback=e.context(this,"getAlbumModal");this.dialogAlbumUrl=this.redactorOptions.buttons.insertAlbum.dialogUrl;this.titleAlbum=this.redactorOptions.buttons.insertAlbum.title;this.redactor.opts.buttonsCustom.insertContent.callback=e.context(this,"getContentModal");this.dialogContentUrl=this.redactorOptions.buttons.insertContent.dialogUrl;this.titleContent=this.redactorOptions.buttons.insertContent.title}},getAlbumModal:function(t){var n=this;t.saveSelection();t.modalInit(this.titleAlbum,{url:this.dialogAlbumUrl},600,e.proxy(function(){e("#redactor_insert_album_btn").click(function(e){e.preventDefault();n.insertAlbumBbcode(e,t)});setTimeout(function(){e("#redactor_album_url").focus()},100)},t))},insertAlbumBbcode:function(t,n){XenForo.ajax(this.dialogAlbumUrl,{url:e("#redactor_album_url").val(),size:e('input[name="redactor_cover_size"]:checked').val()},function(e){if(XenForo.hasResponseError(e)){return}if(e.bbcode){n.restoreSelection();n.execCommand("inserthtml",e.bbcode);n.modalClose()}else if(e.message){alert(e.message)}})},getContentModal:function(t){var n=this;t.saveSelection();t.modalInit(this.titleContent,{url:this.dialogContentUrl},600,e.proxy(function(){e("#redactor_insert_content_btn").click(function(e){e.preventDefault();n.insertContentBbcode(e,t)});setTimeout(function(){e("#redactor_content_url").focus()},100)},t))},insertContentBbcode:function(t,n){XenForo.ajax(this.dialogContentUrl,{url:e("#redactor_content_url").val(),size:e('input[name="redactor_content_size"]:checked').val()},function(e){if(XenForo.hasResponseError(e)){return}if(e.bbcode){n.restoreSelection();n.execCommand("inserthtml",e.bbcode);n.modalClose()}else if(e.message){alert(e.message)}})}};XenForo.register("textarea.BbCodeWysiwygEditor","XenForo.XenGalleryEditor")}(jQuery,this,document)