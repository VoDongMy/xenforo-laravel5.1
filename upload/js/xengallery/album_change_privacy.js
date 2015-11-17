/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGallerySetAllPrivacy = function($element) { this.__construct($element); };
	XenForo.XenGallerySetAllPrivacy.prototype =
	{
		__construct: function($select)
		{
			this.$select = $select;

			this.$select.bind(
			{
				change: $.context(this, 'setAllPrivacy')
			});
		},

		setAllPrivacy: function(e)
		{
			$privacySelect = $('.PrivacySelect');

			if (this.$select.val() === '')
			{
				$privacySelect.val($privacySelect.data('original-value'));
				return false;
			}

			$privacySelect.val(this.$select.val());
		}
	};

	XenForo.register('.SetAllPrivacy', 'XenForo.XenGallerySetAllPrivacy');
}
(jQuery, this, document);