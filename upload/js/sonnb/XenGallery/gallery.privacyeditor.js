
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function(e,t,n,r){XenForo.XenGalleryPrivacy=function(e){this.__construct(e)};XenForo.XenGalleryPrivacy.prototype={__construct:function(t){this.select=t;t.bind("change",e.context(this,"onChange"))},onChange:function(t){$ctrlId=this.select.attr("id");$ctrlUsername=e("#"+$ctrlId+"_username");$pCtrl=e("p.explain",this.select.parent());$val=this.select.val();if($val=="custom"){$ctrlUsername.show();$pCtrl.show()}else{$ctrlUsername.hide();$pCtrl.hide()}}};XenForo.register("select.xenGalleryCtrl","XenForo.XenGalleryPrivacy")}(jQuery,this,document)