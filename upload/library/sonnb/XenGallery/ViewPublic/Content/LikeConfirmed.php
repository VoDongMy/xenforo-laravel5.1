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
class sonnb_XenGallery_ViewPublic_Content_LikeConfirmed extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$content = $this->_params['content'];

		if (!empty($content['likeUsers']))
		{
			$params = array(
				'message' => $content,
				'likesUrl' => XenForo_Link::buildPublicLink("gallery/{$content['content_type']}s/likes", $content)
			);

			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}
		
		$output['content'] = $content;
		$output['liked'] = $this->_params['liked'];

		$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}