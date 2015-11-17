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
class sonnb_XenGallery_ControllerPublic_XenGallery_Member extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('gallery/authors', array('user_id' => $userId))
		);
	}
}