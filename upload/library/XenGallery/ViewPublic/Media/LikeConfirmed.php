<?php

class XenGallery_ViewPublic_Media_LikeConfirmed extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$media = $this->_params['media'];

		if (!empty($media['likes']))
		{
			$params = array(
				'message' => $media,
				'likesUrl' => XenForo_Link::buildPublicLink('xengallery/likes', $media)
			);

			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}

		if ($this->_params['inline'])
		{
			$likeCount = $this->_params['media']['likes'];
			$output += XenGallery_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked'], XenForo_Locale::numberFormat($likeCount));
		}
		else
		{
			$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}