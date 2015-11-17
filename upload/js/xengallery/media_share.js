/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryMediaShare = function($element) { this.__construct($element); };
	XenForo.XenGalleryMediaShare.prototype =
	{
		__construct: function($input)
		{
			$input.on('keydown', function(e)
			{
				if (e.keyCode != 67 && e.keyCode !== 91)
				{
					e.preventDefault();
					e.stopPropagation();
					return false;
				}
			});

			$input.on('cut', function(e)
			{
				e.preventDefault();
				e.stopPropagation();
				return false;
			});

			$input.on('paste', function(e)
			{
				e.preventDefault();
				e.stopPropagation();
				return false;
			});

			$input.on('click', function(e)
			{
				this.select();
			});
		}
	}

	XenForo.register('.CopyInput', 'XenForo.XenGalleryMediaShare');
}
(jQuery, this, document);