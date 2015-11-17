/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryPrivacyOption = function($element) { this.__construct($element); };
	XenForo.XenGalleryPrivacyOption.prototype =
	{
		__construct: function($select)
		{
			this.$select = $select;
			this.url = $select.data('descurl');
			this.$target = $($select.data('desctarget'));
			this.$type = $select.data('desctype');

			if (!this.url || !this.$target.length)
			{
				return;
			}

			$select.bind(
				{
					keyup: $.context(this, 'fetchDescriptionDelayed'),
					change: $.context(this, 'fetchDescription')
				});
			if ($select.val().length)
			{
				this.fetchDescription();
			}
		},

		fetchDescriptionDelayed: function()
		{
			if (this.delayTimer)
			{
				clearTimeout(this.delayTimer);
			}

			this.delayTimer = setTimeout($.context(this, 'fetchDescription'), 250);
		},

		fetchDescription: function()
		{
			if (!this.$select.val().length)
			{
				this.$target.html('');
				return;
			}

			this.xhr = XenForo.ajax(
				this.url,
				{
					privacy_type: this.$select.val(),
					phrase_type: this.$type
				},
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData)
			{
				this.$target.html(ajaxData.description);
			}
			else
			{
				this.$target.html('');
			}
		}
	};
	XenForo.register('select.PrivacyOption', 'XenForo.XenGalleryPrivacyOption');
}
(jQuery, this, document);