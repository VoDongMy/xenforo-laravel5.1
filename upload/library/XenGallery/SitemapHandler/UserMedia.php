<?php

class XenGallery_SitemapHandler_UserMedia extends XenGallery_SitemapHandler_Abstract
{
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewProfile'))
		{
			return array();
		}

		$userModel = $this->_getUserModel();
		$ids = $userModel->getUserIdsInRange($previousLast, $limit);

		$users = $userModel->getUsersByIds($ids, array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'followingUserId' => $viewingUser['user_id']
		));
		ksort($users);

		return $users;
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		if (!$entry['xengallery_media_count'])
		{
			return false;
		}

		return $this->_getUserProfileModel()->canViewFullUserProfile($entry, $null, $viewingUser);
	}

	public function getData(array $entry)
	{
		$result = array(
			'loc' => XenForo_Link::buildPublicLink('canonical:xengallery/users', $entry),
			'priority' => 0.3
		);

		if ($entry['gravatar'] || $entry['avatar_date'])
		{
			$avatarUrl = htmlspecialchars_decode(
				XenForo_Template_Helper_Core::callHelper('avatar', array($entry, 'l'))
			);
			$avatarUrl = XenForo_Link::convertUriToAbsoluteUri($avatarUrl, true, $this->getCanonicalPaths());
			$result['image'] = $avatarUrl;
		}

		return $result;
	}

	public function isInterruptable()
	{
		return true;
	}

	public function getPhraseKey($key)
	{
		return 'xengallery_sitemap_user_media';
	}
}