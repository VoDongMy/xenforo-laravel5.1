/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryPermissionsList = function($element) { this.__construct($element); };
	XenForo.XenGalleryPermissionsList.prototype =
	{
		__construct: function($select)
		{
			this.$select = $select;
			this.$className = '.CustomUsers' + this.$select.data('type');

			this.$select.bind(
			{
				change: $.context(this, 'permissionSet')
			});
		},

		permissionSet: function(e)
		{
			if (this.$select.val() == 'shared')
			{
				return $(this.$className).xfSlideDown();
			}

			$(this.$className).xfSlideUp();
		}
	}

	XenForo.register('.PermissionsList', 'XenForo.XenGalleryPermissionsList');
}
(jQuery, this, document);