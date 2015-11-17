<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_ViewPublic_Video_EmbedVideo extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$content = $this->_prepareVideoForJson($this->_params['content'], $this->_params['album']);

		if (!empty($this->_params['message']))
		{
            $content['message'] = $this->_params['message'];
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($content);
	}

	protected function _prepareVideoForJson(array $content, array $album)
	{
		$keys = array('content_data_id', 'upload_date', 'thumbnailUrl', 'title', 'description');

		$template = $this->createTemplateObject('sonnb_xengallery_upload', array('content' => $content, 'album' => $album));

        $content = array_intersect_key($keys, $content);

        $content['templateHtml'] = $template;

		return $content;
	}
}