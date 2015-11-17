/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.PrepareImage = function($element) { this.__construct($element); };
	XenForo.PrepareImage.prototype =
	{
		__construct: function($element)
		{
			var ua = window.navigator.userAgent;
			var msie = ua.indexOf("MSIE ");

			if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./))
			{
				$element.addClass('IE');
			}

			return false;
		}
	}

	XenForo.register('.imageContainer', 'XenForo.PrepareImage');
}
(jQuery, this, document);