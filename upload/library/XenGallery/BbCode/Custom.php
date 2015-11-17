<?php

class XenGallery_BbCode_Custom
{
	public static function tagGallery(array $tag, array $rendererStates, XenForo_BbCode_Formatter_Base $formatter)
	{
		if ($formatter instanceof XenForo_BbCode_Formatter_Text)
		{
			return '[GALLERY]';
		}

		$options = XenForo_Application::getOptions();
		$bbCodeOption = $options->xengalleryGalleryBbCode;

		if (!$tag['option'] || !$bbCodeOption)
		{
			return $formatter->renderTagUnparsed($tag, $rendererStates);
		}

		$parts = explode(',', $tag['option']);
		foreach ($parts AS &$part)
		{
			$part = trim($part);
			$part = str_replace(' ', '', $part);
		}

		$type = $formatter->filterString(array_shift($parts),
			array_merge($rendererStates, array(
				'stopSmilies' => true,
				'stopLineBreakConversion' => true
			))
		);
		$type = strtolower($type);
		$id = array_shift($parts);

		$viewParams = array(
			'type' => $type,
			'id' => intval($id),
			'uniqueId' => uniqid('GalleryPanes'),
			'text' => isset($tag['children'][0]) ? $tag['children'][0] : ''
		);

		if ($type == 'album')
		{
			$viewParams['link'] = XenForo_Link::buildPublicLink('xengallery/albums', array('album_id' => $id));
		}
		else
		{
			$viewParams['link'] = XenForo_Link::buildPublicLink('xengallery', array('media_id' => $id));
		}

		if ($formatter instanceof XenForo_BbCode_Formatter_HtmlEmail)
		{
			return '<table cellpadding="0" cellspacing="0" border="0" width="100%"'
				. ' style="background-color: #F0F7FC; border: 1px solid #A5CAE4; border-radius: 5px; margin: 5px 0; padding: 5px; font-size: 11px; text-align: center">'
				. '<tr><td><a href="' . htmlspecialchars($viewParams['link']) . '">' . htmlspecialchars($viewParams['text']) .'</a></td></tr></table>';
		}

		if ($bbCodeOption == 'media')
		{
			$viewParams['text'] = '';
			$viewParams['link'] = '';
		}
		$viewParams['bbCodeOption'] = $bbCodeOption;

		$view = $formatter->getView();
		if ($view)
		{
			$template = $view->createTemplateObject('xengallery_bb_code_tag_gallery', $viewParams);
			$template->addRequiredExternal('js', 'js/xengallery/gallery_bb_code.js');

			return $template->render();
		}
		else
		{
			$fallbackHtml = '
				<div class="GalleryLazyLoad galleryContainer %s %s-%d" data-type="%s" data-id="%d"></div>' .
					'<a href="%s"><span class="galleryText">%s</span></a>';

			return sprintf($fallbackHtml, $viewParams['type'],
				$viewParams['type'], $viewParams['id'],
				$viewParams['type'], $viewParams['id'],
				$viewParams['link'], $viewParams['type']
			);
		}
	}
}