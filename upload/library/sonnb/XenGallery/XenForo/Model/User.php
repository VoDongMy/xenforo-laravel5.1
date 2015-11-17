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
class sonnb_XenGallery_XenForo_Model_User extends XFCP_sonnb_XenGallery_XenForo_Model_User
{
	public function getVisitingGuestUser()
	{
		$userInfo = parent::getVisitingGuestUser();

		$userInfo['xengallery'] = array();
        $userInfo['sonnb_xengallery_watermark'] = array();
		$userInfo['sonnb_xengallery_album_count'] = 0;
		$userInfo['sonnb_xengallery_photo_count'] = 0;
		$userInfo['sonnb_xengallery_cover'] = array();

		return $userInfo;
	}

	public function prepareUser(array $user)
	{
		$user = parent::prepareUser($user);

		if (isset($user['sonnb_xengallery_cover']) && !is_array($user['sonnb_xengallery_cover']))
		{
			$user['sonnb_xengallery_cover'] = @unserialize($user['sonnb_xengallery_cover']);
		}

        if (isset($user['sonnb_xengallery_watermark']) && !is_array($user['sonnb_xengallery_watermark']))
        {
            $user['sonnb_xengallery_watermark'] = @unserialize($user['sonnb_xengallery_watermark']);
        }

		if (isset($user['xengallery']))
		{
			if (!is_array($user['xengallery']))
			{
				$user['xengallery'] = @unserialize($user['xengallery']);
			}

			if (!empty($user['xengallery']) &&
					!isset($user['xengallery']['album_allow_download']))
			{
				$user['xengallery']['album_allow_download'] = 'everyone';
			}
		}

		return $user;
	}
}