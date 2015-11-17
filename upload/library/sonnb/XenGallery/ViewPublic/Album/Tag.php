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
class sonnb_XenGallery_ViewPublic_Album_Tag extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$content = $this->_params['album'];

		$output = array();

		$output['message'] = isset($this->_params['message']) ? $this->_params['message'] : '';

		if (!empty($content['tagUsers']))
		{
			$output['tagList'] = sonnb_XenGallery_Template_Helper_Tag::helperTags(
				$content['tagUsers'],
				XenForo_Link::buildPublicLink('gallery/albums/tags', $content)
			);
		}
		else
		{
			$output['tagList'] = '';
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}