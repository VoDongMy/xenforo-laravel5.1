!function($, window, document, _undefined)
{
	XenForo.XenGalleryButtons = function($textarea) { this.__construct($textarea); };
	XenForo.XenGalleryButtons.prototype =
	{
		__construct: function($textarea)
		{
			this.textarea = $textarea;

			$(document).bind(
			{
				EditorInit: $.context(this, 'editorInitFunc')
			});
		},

		editorInitFunc: function(e, data)
		{
			if (data.$textarea[0] == this.textarea[0]
				&& data.editor.options.enableXmgButton
				&& data.config.buttonsCustom.custom_gallery
			)
			{
				data.config.buttonsCustom.custom_gallery.callback = $.context(this, 'galleryButtonCallback');
			}
		},

		galleryButtonCallback: function(data)
		{
			var $textarea = this.textarea;
			this.ed = data;

			var ed = this.ed,
				$galBrowser = ed.$box.find('.xengallery_browser'),
				self = this,
				$smilies = ed.$box.find('.redactor_smilies'),
				$smiliesToggle = ed.$box.find('.redactor_btn_smilies');

			$smilies.hide();

			$smiliesToggle.bind(
			{
				click: function()
				{
					$galBrowser.hide();
				}
			});

			if ($galBrowser.length)
			{
				$galBrowser.slideToggle();
				return;
			}

			if (self.loadPending)
			{
				return;
			}
			self.loadPending = true;

			XenForo.ajax('index.php?xengallery/editor-browser', '',
				$.context(this, 'loadSuccess')
			).complete(function()
			{
				$('.mediaTabContent').show();
				self.loadPending = false;
			});
		},

		loadSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return;
			}

			if (ajaxData.templateHtml)
			{
				new XenForo.ExtLoader(ajaxData, $.context(this, 'createBrowser'));
			}
		},

		createBrowser: function(ajaxData)
		{
			var ed = this.ed;

			var $galBrowser = $('<div class="xengallery_browser" />').html(ajaxData.templateHtml);
			$galBrowser.hide();

			$galBrowser.on('click', '.Thumb', function(e) {
				e.preventDefault();

				var $thumb = $(this),
					bbcode = '[GALLERY=' + $thumb.data('type') + ', ' + $thumb.data('id') + ']' + XenForo.htmlspecialchars($thumb.data('title')) + '[/GALLERY]';

				ed.execCommand('inserthtml', bbcode);
				ed.focus();
			});

			ed.$box.append($galBrowser);
			$galBrowser.xfActivate();
			$galBrowser.slideToggle();

			this.browser = $galBrowser.find('.browserSection');
			this.browser.bind(
			{
				scroll: $.context(this, 'beginScroll')
			});
		},

		beginScroll: function(e)
		{
			var $browser = this.browser;
			if ($browser.find('.IsLastPage').length)
			{
				return false;
			}

			var scrollPosition = $browser.scrollTop() + $browser.outerHeight(),
				divHeight = $browser.get(0).scrollHeight,
				self = this;

			if (scrollPosition == divHeight)
			{
				if (this.loadPending)
				{
					return;
				}
				this.loadPending = true;

				var $lastPage = $browser.find('.PageNumber:last');

				XenForo.ajax('index.php?xengallery/editor-browser',
					{ last_page: $lastPage.val() },
					$.context(this, 'loadMoreSuccess')
				).complete(function()
				{
					$('.mediaTabContent').show();
					self.loadPending = false;
				});
			}
		},

		loadMoreSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return;
			}

			if (ajaxData.templateHtml)
			{
				new XenForo.ExtLoader(ajaxData, $.context(this, 'updateBrowser'));
			}
		},

		updateBrowser: function(ajaxData)
		{
			var $browser = this.browser;
			var $section = $browser.find('.gridSection');

			$section.append(ajaxData.templateHtml).xfShow();
		}
	};

	XenForo.XenGalleryLazyLoader = function($element) { this.__construct($element); };
	XenForo.XenGalleryLazyLoader.prototype =
	{
		__construct: function($element)
		{
			this.ignoreActivate = false;

			$element.bind({
				XenForoActivate: $.context(this, 'elementActivate')
			});

			this.checkForItems();
		},

		checkForItems: function()
		{
			if (this.timer)
			{
				clearTimeout(this.timer);
			}

			var galleryItems = [],
				$lazyLoads = $('.GalleryLazyLoad');

			$.each($lazyLoads, function(key, value)
			{
				galleryItems.push({
					type: $(value).data('type'),
					id: $(value).data('id')
				});
			});
			if (!galleryItems.length)
			{
				return false;
			}

			this.loadGalleryItems(galleryItems, $lazyLoads);
		},

		elementActivate: function()
		{
			if (this.ignoreActivate)
			{
				return;
			}

			if (this.timer)
			{
				clearTimeout(this.timer);
			}
			this.timer = setTimeout($.context(this, 'checkForItems'), 50);
		},

		loadGalleryItems: function(galleryItems, $lazyLoads)
		{
			var url = 'index.php?xengallery/content-loader';

			this.xhr = XenForo.ajax(
				url, { items: galleryItems },
				$.context(this, 'ajaxSuccess')
			);
			this.xhr.complete(function()
			{
				if ($lazyLoads)
				{
					$lazyLoads.removeClass('GalleryLazyLoad');
				}
			});
		},

		ajaxSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			if (ajaxData.gallery)
			{
				new XenForo.ExtLoader(ajaxData, function()
				{
					this.ignoreActivate = true;

					$.each(ajaxData.gallery, function(key, value)
					{
						var insertRef = key.split('/').pop(),
							$insert = $(insertRef + ':first');
						$insert.removeClass('GalleryLazyLoad');
						$(value.html).xfInsert('replaceAll', $insert, 'xfFadeIn', XenForo.speed.normal, function()
						{
							// This event is picked up by the quote height detection system.
							$(this).trigger('elementResized');
						});

						$(insertRef + 'ContentLink').attr('href', value.contentLink);
					});

					this.ignoreActivate = false;
				});
			}
		}
	};

	XenForo.register('textarea.BbCodeWysiwygEditor', 'XenForo.XenGalleryButtons');
	XenForo.register('.GalleryLazyLoader', 'XenForo.XenGalleryLazyLoader');

}(jQuery, this, document);