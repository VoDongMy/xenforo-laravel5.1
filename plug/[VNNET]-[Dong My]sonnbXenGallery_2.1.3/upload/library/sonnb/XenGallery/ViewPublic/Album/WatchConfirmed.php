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
class sonnb_XenGallery_ViewPublic_Album_WatchConfirmed extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		$output['watched'] = $this->_params['watched'];

		$output += sonnb_XenGallery_ViewPublic_Helper::getWatchViewParams($this->_params['watched']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}