<?php

class XenGallery_ControllerPublic_Tagging extends XenGallery_ControllerPublic_Abstract
{
	/**
	 * Remains in place for legacy reasons for the purposes of providing 301 redirects
	 */
	public function actionIndex()
	{
		$tagId = $this->_input->filterSingle('tag_clean', XenForo_Input::STRING);
		if ($tagId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('tags', array('tag_url' => $tagId))
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('tags')
		);
	}
}