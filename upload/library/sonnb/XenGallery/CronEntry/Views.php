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
class sonnb_XenGallery_CronEntry_Views
{
	public static function runViewUpdate()
	{
		XenForo_Model::create('sonnb_XenGallery_Model_Album')->updateAlbumViews();
		XenForo_Model::create('sonnb_XenGallery_Model_Content')->updateContentViews();
	}
}