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
class sonnb_XenGallery_ViewPublic_Author_CoverUpload extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$output = array(
			'url' => $this->_params['url'],
			'message' => $this->_params['message'],
			'cover' => $this->_params['cover']
		);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}