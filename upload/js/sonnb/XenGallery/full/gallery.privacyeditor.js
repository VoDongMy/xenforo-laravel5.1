
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryPrivacy = function($select) 
	{ 
		this.__construct($select); 
	};
	
	XenForo.XenGalleryPrivacy.prototype =
	{
		__construct: function($select)
		{
			this.select = $select;
			
			$select.bind('change', $.context(this, 'onChange'));
		},

		onChange: function(e)
		{
			$ctrlId = this.select.attr('id');
			$ctrlUsername = $('#'+$ctrlId+'_username');
			$pCtrl = $('p.explain', this.select.parent());
			
			$val = this.select.val();
			
			if ($val == 'custom')
			{
				$ctrlUsername.show();
				$pCtrl.show();
			}
			else
			{
				$ctrlUsername.hide();
				$pCtrl.hide();
			}
		}
	};

	XenForo.register('select.xenGalleryCtrl', 'XenForo.XenGalleryPrivacy');
}
(jQuery, this, document);