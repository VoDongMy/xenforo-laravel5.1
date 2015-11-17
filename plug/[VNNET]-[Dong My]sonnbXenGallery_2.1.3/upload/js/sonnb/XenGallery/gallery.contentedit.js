
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function(e,t,n,r){XenForo.XenGalleryContentEditOverlay=function(t){if(t.data("AutoValidator")!=true){new XenForo.AutoValidator(t);t.data("AutoValidator",true);t.bind({AutoValidationBeforeSubmit:function(t){if(e(t.clickedSubmitButton).is('input[name="more_options"]')){t.preventDefault();t.returnValue=true}},AutoValidationComplete:function(t){if(t.textStatus=="success"){var n=t.ajaxData.contentId,r=t.ajaxData.description,i=t.ajaxData.fields;var s=e("#content-"+n).find(".messageContent blockquote"),o=e("#content-overlay-"+n).find(".messageContent blockquote"),u=e("#caption-content-overlay-"+n),a=e("#fieldList-content-overlay-"+n),f=e("#fieldList-content-"+n);s.html(r);o.html(r);u.html(r);f.html(i);a.html(i)}}})}};XenForo.register("form.ContentEditOverlay","XenForo.XenGalleryContentEditOverlay")}(jQuery,this,document)