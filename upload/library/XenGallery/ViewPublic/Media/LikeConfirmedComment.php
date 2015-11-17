<?php

class XenGallery_ViewPublic_Media_LikeConfirmedComment extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$comment = $this->_params['comment'];

		if (!empty($comment['likes']))
		{
			$params = array(
				'message' => $comment,
				'likesUrl' => XenForo_Link::buildPublicLink('xengallery/comments/likes', $comment)
			);

			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}

		$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}