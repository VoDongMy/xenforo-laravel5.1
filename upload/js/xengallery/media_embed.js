/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryMediaVideo = function($element) { this.__construct($element); };
	XenForo.XenGalleryMediaVideo.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;
			this.media_id = $input.data('mediaid');

			if (this.media_id)
			{
				$('#VideoPreviewArea').show();
			}

			this.url = 'index.php?xengallery/preview-video&_xfResponseType=json&media_id=' + this.media_id;

			if (!this.url)
			{
				return;
			}

			this.$addButton = $('#AddButton');
			this.$addButton.bind(
			{
				click: $.context(this, 'getVideoPreview')
			});

			this.$updateButton = $('#UpdateButton');
			this.$updateButton.bind(
			{
				click: $.context(this, 'replaceVideoPreview')
			});

			$('#VideoPreviewArea').on('click', '.DeleteVideo', function(e)
			{
				e.preventDefault();

				var deleteButton = $(e.currentTarget);
				var videoId = deleteButton.data('target');

				$(videoId).remove();

				var mediaCount = $('.videoEmbed').length;

				if (!mediaCount)
				{
					$('#VideoPreviewArea').xfFadeUp(XenForo.speed.fast);
					$('.VideoPreviewSubmitUnit').hide();
				}

				return false;
			});
		},

		getVideoPreview: function()
		{
			this.xhr = XenForo.ajax(
			this.url,
			{
				embed_url: this.$input.val(),
				container_type: $('input[name="container_type"]').val(),
				container_id: $('input[name="container_id"]').val()
			},
			$.context(this, 'ajaxSuccess'),
			{
				error: 'failure'
			});

			this.$input.val('');
		},

		replaceVideoPreview: function()
		{
			this.xhr = XenForo.ajax(
				this.url,
				{
					embed_url: this.$input.val(),
					container_type: $('input[name="container_type"]').val(),
					container_id: $('input[name="container_id"]').val()
				},
				$.context(this, 'ajaxSuccess'),
				{
					error: false
				});

			$('.thumbContainer').remove();
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData.templateHtml)
			{
				new XenForo.ExtLoader(ajaxData, function()
				{
					$('#VideoPreviewArea').xfFadeDown(XenForo.speed.fast);

					var $templateHtml = $(ajaxData.templateHtml),
						$newAttachmentText = $templateHtml.find('.AttachmentText');

					$templateHtml.xfInsert('prependTo', '.VideoPreviewContainer .AttachmentList_video_embed', 'xfShow');

					if ($newAttachmentText.find('.itemExtraInput.itemInputs').data('expanded'))
					{
						$newAttachmentText.find('a.ToggleTrigger.ItemToggleTrigger').click();
					}
				});

				$('.VideoPreviewSubmitUnit').show();
			}
			else
			{
				XenForo.alert(ajaxData.error);

				var mediaCount = $('.videoEmbed').length;

				if (!mediaCount)
				{
					$('#VideoPreviewArea').xfFadeUp(XenForo.speed.fast);
					$('.VideoPreviewSubmitUnit').hide();
				}
			}
		}
	}

	XenForo.register('input.VideoLoader', 'XenForo.XenGalleryMediaVideo');
}
(jQuery, this, document);