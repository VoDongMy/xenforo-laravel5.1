/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.acpNavInit = function()
	{
		var $sidebar = $('#sideNav'),
			$heightTarget = $('#body'),
			$tabsContainer = $('#tabsNav .acpTabs');

		var sidebarActive = $sidebar.hasClass('active'),
			sidebarTransitioning = false;

		var toggleSidebar = function(newValue)
		{
			if (sidebarTransitioning)
			{
				return;
			}

			if (newValue == sidebarActive)
			{
				return;
			}

			sidebarTransitioning = true;

			if (newValue)
			{
				$sidebar.addClass('active');
				recalcSidebarHeight();

				$sidebar.css('left', -$sidebar.width()).animate({left: 0}, function()
				{
					$sidebar.css('left', '');
					sidebarActive = true;
					sidebarTransitioning = false;
				});
			}
			else
			{
				$sidebar.animate({left: -$sidebar.width()}, function()
				{
					$sidebar.css('left', '')
						.removeClass('active');
					sidebarActive = false;
					sidebarTransitioning = false;
				});
			}
		};

		var recalcSidebarHeight = function()
		{
			if (!$heightTarget.length)
			{
				return;
			}

			var sidebarHeight = $sidebar.css('height', '').height(),
				testHeight = Math.max($heightTarget.height(), $(window).height() - $heightTarget.offset().top);

			if (testHeight && sidebarHeight < testHeight)
			{
				$sidebar.css('height', testHeight);
			}
		};

		$(document).on('click', '.AcpSidebarToggler', function(e)
		{
			e.preventDefault();
			toggleSidebar(sidebarActive ? false : true);
		});
		$(document).on('click', '.AcpSidebarCloser', function(e)
		{
			e.preventDefault();
			toggleSidebar(false);
		});

		$(window).resize(function()
		{
			if (sidebarActive)
			{
				recalcSidebarHeight();
			}
		});

		var checkTabsOverflow = function()
		{
			if (!$tabsContainer.length)
			{
				return;
			}

			var tabsContainer = $tabsContainer[0];

			$tabsContainer.removeClass('withNoLinks');

			if (tabsContainer.scrollHeight >= $tabsContainer.height() * 1.1)
			{
				$sidebar.addClass('withSections');
				$tabsContainer.addClass('withNoLinks');
			}
			else
			{
				$sidebar.removeClass('withSections');
			}
		};

		checkTabsOverflow();

		$(window).resize(function()
		{
			checkTabsOverflow();
		});
	};

	$(function()
	{
		XenForo.acpNavInit();
	});
}
(jQuery, this, document);