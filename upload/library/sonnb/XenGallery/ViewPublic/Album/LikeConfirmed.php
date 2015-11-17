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
class sonnb_XenGallery_ViewPublic_Album_LikeConfirmed extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$album = $this->_params['album'];

		if (!empty($album['likeUsers']))
		{
			$params = array(
				'message' => $album,
				'likesUrl' => XenForo_Link::buildPublicLink('gallery/albums/likes', $album)
			);

			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}
		
		$output['content'] = $album;
		$output['liked'] = $this->_params['liked'];

		$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}