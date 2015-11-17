<?php

class XenGallery_ViewPublic_Media_BbCode extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = array(
			'gallery' => array()
		);

		foreach ($this->_params['albums'] AS $albumId => &$album)
		{
			$album['contentLink'] = XenForo_Link::buildPublicLink('xengallery/albums', $album);
			$album['uniqueId'] = uniqid('GalleryPanes');
			$album['type'] = 'album';

			$output['gallery'][".album-$albumId"] =
				$this->createTemplateObject('xengallery_bb_code_tag_gallery_content', array('album' => $album))->render();
		}

		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$bbCodeOption = XenForo_Application::getOptions()->xengalleryGalleryBbCode;
		switch ($bbCodeOption)
		{
			case 'simple':
			case 'media':

				$templateName = 'xengallery_bb_code_tag_gallery_media';
				break;

			case 'extended':
			default:

				$templateName = 'xengallery_bb_code_tag_gallery_content';
				break;
		}

		if ($this->_params['media'])
		{
			foreach ($this->_params['media'] AS $key => &$media)
			{
				$media['contentLink'] = XenForo_Link::buildPublicLink('xengallery', $media);
				$media['uniqueId'] = uniqid('GalleryPanes');
				$media['type'] = 'media';
				$media['videoHtml'] = new XenForo_BbCode_TextWrapper($media['media_tag'], $bbCodeParser);
				$media['bbCodeOption'] = $bbCodeOption;

				if (!empty($media['comments']))
				{
					foreach ($media['comments'] AS &$comment)
					{
						$comment['messageHtml'] = new XenForo_BbCode_TextWrapper($comment['message'], $bbCodeParser);
						$comment['message'] = $comment['messageHtml']; // sanity check in case template not update
					}
				}

				$output['gallery']["$key/.media-$media[media_id]"] = array(
					'html' => $this->createTemplateObject($templateName, array('media' => $media))->render(),
					'contentLink' => $media['contentLink'],
					'contentLinkSelector' => '.media-' . $key . 'ContentLink'
				);
			}

			$template = $this->createTemplateObject('', array());

			$output['css'] = $template->getRequiredExternals('css');
			$output['js'] = $template->getRequiredExternals('js');
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}