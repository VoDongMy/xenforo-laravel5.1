/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.XenGalleryMediaViewNextPrev = function(toolbar)
	{
		var	previous = $('a.PreviousMedia')[0],
			next = $('a.NextMedia')[0];

		$(window).keydown(function(e)
		{
			if ($('.mfp-ready').length)
			{
				return false;
			}

			if ($('textarea, input').is(':focus'))
			{
				return;
			}

			if (e.which === 37)
			{
				if (previous)
				{
					window.location.href = previous.href;
				}
			}
			else if (e.which === 39)
			{
				if (next)
				{
					window.location.href = next.href;
				}
			}
		});
	}

	XenForo.register('.buttonToolbar', 'XenForo.XenGalleryMediaViewNextPrev');
}
(jQuery, this, document);