<?php

class XenGallery_ViewPublic_Album_LikeConfirmed extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$album = $this->_params['album'];

		if (!empty($album['likes']))
		{
			$params = array(
				'message' => $album,
				'likesUrl' => XenForo_Link::buildPublicLink('xengallery/albums/likes', $album)
			);

			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}

		if ($this->_params['inline'])
		{
			$likeCount = $this->_params['album']['likes'];
			$output += XenGallery_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked'], XenForo_Locale::numberFormat($likeCount));
		}
		else
		{
			$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}