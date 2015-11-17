/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryMediaMove = function($element) { this.__construct($element); };
	XenForo.XenGalleryMediaMove.prototype =
	{
		__construct: function($element)
		{
			this.$element = $element;

			$element.bind(
			{
				AutoComplete: $.context(this, 'userChosen')
			});

			$element.bind({
				change: $.context(this, 'resetAlbums')
			});
		},

		resetAlbums: function(e)
		{
			if (!this.$element.val().length)
			{
				$('.UserAlbumsList').xfFadeUp();
			}
		},

		userChosen: function(e)
		{
			url = 'index.php?xengallery/load-user-albums';

			this.xhr = XenForo.ajax(
				url,
				{
					username: this.$element.val()
				},
				$.context(this, 'ajaxSuccess')
			);
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData.error)
			{
				XenForo.alert(ajaxData.error);
				return false;
			}

			if (ajaxData.templateHtml)
			{
				new XenForo.ExtLoader(ajaxData, function()
				{
					$(ajaxData.templateHtml).xfInsert('replaceAll', '.UserAlbumsList', 'xfFadeDown', XenForo.speed.normal);
				});
			}
		}
	}
	XenForo.register('.AlbumOwner', 'XenForo.XenGalleryMediaMove');
}
(jQuery, this, document);