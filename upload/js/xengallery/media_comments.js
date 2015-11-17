/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryMedia =
	{
		/**
		 * A bit like quick reply, but for XenForo Media Gallery
		 *
		 * @param jQuery form.MediaComment
		 */
		Comment: function($form)
		{
			// bind a function onto the AutoValidationComplete event of the form AutoValidator
			$form.bind('AutoValidationComplete', function(e)
			{
				// check that templateHtml was received from the AJAX request
				if (e.ajaxData.templateHtml)
				{
					// prevent the normal AutoValidator success message and redirect stuff
					e.preventDefault();

					// hide the 'no comments' message if it's there, and when it is hidden...
					$('#MediaNoComments').slideUp(XenForo.speed.fast, function()
					{
						// ... load any externals specified by the template, and when that's done...
						new XenForo.ExtLoader(e.ajaxData, function()
						{
							// ... append the templateHtml into the comments area
							$(e.ajaxData.templateHtml).xfInsert('appendTo', '#MediaNewComments');
						});

						// clear the textarea contents and refocus it
						$('.MediaComment').find('textarea').val('');

						var $textarea = $('#CommentForm').find('textarea');
						$textarea.val('');
						var ed = $textarea.data('XenForo.BbCodeWysiwygEditor');
						if (ed)
						{
							ed.resetEditor();
						}

						// set the 'date' input field to contain the date of the most recent post (from ajaxData)
						$form.find('input[name=date]').val(e.ajaxData.date);

						// re-enable the submit button if it's been disabled
						$form.find('input:submit').removeAttr('disabled').removeClass('disabled');
					});
				}
			});
		},

		InlineComment: function($form)
		{
			// bind a function onto the AutoValidationComplete event of the form AutoValidator
			$form.bind('AutoValidationComplete', function(e)
			{
				var overlay = $form.closest('div.xenOverlay').data('overlay');

				if (overlay)
				{
					var	target = overlay.getTrigger().data('target');
				}

				// check that templateHtml was received from the AJAX request
				if (e.ajaxData.templateHtml)
				{
					// prevent the normal AutoValidator success message and redirect stuff
					e.preventDefault();

					// hide the overlay, remove its cache
					if (overlay)
					{
						overlay.close().getTrigger().data('XenForo.OverlayTrigger').deCache();
					}

					// ... load any externals specified by the template, and when that's done...
					new XenForo.ExtLoader(e.ajaxData, function()
					{
						var commentId = e.ajaxData.commentId;

						// ... prepend the templateHtml into the notes area
						if (commentId)
						{
							$(e.ajaxData.templateHtml).xfInsert('replaceAll', '#comment-' + commentId, 'xfShow');
						}
					});

					// clear the textarea contents and refocus it
					$('.MediaComment').find('textarea').val('');

					// set the 'date' input field to contain the date of the most recent post (from ajaxData)
					$form.find('input[name=date]').val(e.ajaxData.date);

					// re-enable the submit button if it's been disabled
					$form.find('input:submit').removeAttr('disabled').removeClass('disabled');
				}
			});
		},

		ShowComment: function($ctrl)
		{
			$ctrl.click(function(e)
			{
				e.preventDefault();

				var commentId = $ctrl.data('commentid');

				if (commentId)
				{
					XenForo.ajax
					(
						$ctrl.attr('href'),
						{},
						function(ajaxData, textStatus)
						{
							$('#comment-' + commentId).xfFadeUp();
							$(ajaxData.templateHtml).xfInsert('insertAfter', '#comment-' + commentId).xfFadeDown();
						}
					);
				}
			});
		},

		CreateReply: function($ctrl)
		{
			$ctrl.click(function(e)
			{
				e.preventDefault();

				var username = $ctrl.data('username'),
					$form = $('#CommentForm');

				if (username)
				{
					var ed = XenForo.getEditorInForm($form),
						replytext = '@' + username + ' ';

					if (!ed)
					{
						return false;
					}

					$(document).scrollTop($form.offset().top);

					if (ed.$editor)
					{
						ed.focus(true);
						ed.insertHtml(replytext);
						if (ed.$editor.data('xenForoElastic'))
						{
							ed.$editor.data('xenForoElastic')();
						}
					}
					else
					{
						ed.focus();
						ed.val(ed.val() + replytext);
					}
				}
			});
		}
	};

	XenForo.register('form.MediaComment', 'XenForo.XenGalleryMedia.Comment');
	XenForo.register('form.MediaInlineComment', 'XenForo.XenGalleryMedia.InlineComment');
	XenForo.register('a.MediaShowComment', 'XenForo.XenGalleryMedia.ShowComment');
	XenForo.register('a.ReplyLink', 'XenForo.XenGalleryMedia.CreateReply');

}
(jQuery, this, document);