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
class sonnb_XenGallery_Model_UserTagging extends XenForo_Model_UserTagging
{
	public function alertTaggedMembers($content, $contentId, $contentType, $tagged, array $alreadyAlerted = array())
	{
		$userIds = XenForo_Application::arrayColumn($tagged, 'user_id');
		$userIds = array_diff($userIds, $alreadyAlerted);
		$alertedUserIds = array();

		if ($userIds)
		{
			$userModel = $this->_getUserModel();
			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION
					| XenForo_Model_User::FETCH_USER_PROFILE
					| XenForo_Model_User::FETCH_USER_PERMISSIONS
			));

			foreach ($users AS $user)
			{
				if (isset($alertedUserIds[$user['user_id']])
					|| $user['user_id'] == $content['user_id']
					|| $user['user_id'] == $content['user_id']
				)
				{
					continue;
				}

				$xfContentType = $this->_getXfContentType($contentType);

				if (!$userModel->isUserIgnored($user, $content['user_id'])
					&& !$userModel->isUserIgnored($user, $content['user_id'])
					&& XenForo_Model_Alert::userReceivesAlert($user, $xfContentType, 'tagging')
				)
				{
					$alertedUserIds[$user['user_id']] = true;

					XenForo_Model_Alert::alert($user['user_id'],
						$content['user_id'], $content['username'],
						$contentType, $contentId,
						'tagging'
					);
				}
			}
		}

		return array_keys($alertedUserIds);
	}

	protected function _getXfContentType($contentType)
	{
		$xfContentType = '';

		switch ($contentType)
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Photo::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Video::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Comment::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Comment::$xfContentType;
				break;
		}

		return $xfContentType;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}