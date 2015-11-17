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
class sonnb_XenGallery_Template_Helper_Cover
{

	/**
	 * @param $user
	 * @return mixed|string
	 */
	public static function helperCover($user)
	{
		if (!is_array($user['sonnb_xengallery_cover']) || empty($user['sonnb_xengallery_cover']))
		{
			return;
		}

		/* @var sonnb_XenGallery_Model_Gallery $galleryModel */
		$galleryModel = XenForo_Model::create('sonnb_XenGallery_Model_Gallery');

		return $galleryModel->getAuthorCoverUrl($user);
	}
}