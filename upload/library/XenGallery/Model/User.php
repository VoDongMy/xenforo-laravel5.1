<?php

class XenGallery_Model_User extends XFCP_XenGallery_Model_User
{
	public function prepareUserOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'xengallery_media_count' => 'user.xengallery_media_count',
			'xengallery_album_count' => 'user.xengallery_album_count'
		);
		$order = $this->getOrderByClause($choices, $fetchOptions);
		if ($order)
		{
			return $order;
		}

		return parent::prepareUserOrderOptions($fetchOptions, $defaultOrderSql);
	}

	public function follow(array $followUsers, $dupeCheck = true, array $user = null)
	{
		$parent = parent::follow($followUsers, $dupeCheck, $user);

		if ($user === null)
		{
			$user = XenForo_Visitor::getInstance();
		}

		$albumModel = $this->_getAlbumModel();

		$addConditions = array(
			'album_user_id' => $user['user_id'],
			'add_type' => 'followed',
		);
		$addAlbums = $albumModel->getAlbums($addConditions);

		$viewConditions = array(
			'album_user_id' => $user['user_id'],
			'view_type' => 'followed',
		);
		$viewAlbums = $albumModel->getAlbums($viewConditions);

		$albums = $addAlbums + $viewAlbums;

		$existingUsers = $this->getFollowingDenormalizedValue($user['user_id']);
		$existingUsers = array_map('intval', explode(',', $existingUsers));

		if (!empty($followUsers['user_id']))
		{
			$existingUsers[] = $followUsers['user_id'];
		}

		$mergedUsers = $this->getUsersByIds($existingUsers);
		$mergedUsers[$user['user_id']] = $user;

		foreach ($albums AS $album)
		{
			$album = $albumModel->prepareAlbumWithPermissions($album);

			if (!isset($album['albumPermissions']))
			{
				continue;
			}

			$shareUsers = $albumModel->prepareViewShareUsers($mergedUsers, $album);

			if ($album['albumPermissions']['view']['access_type'] == 'followed')
			{
				$albumViewData = array(
					'album_id' => $album['album_id'],
					'permission' => 'view',
					'access_type' => 'followed',
					'share_users' => $shareUsers
				);
				$albumModel->writeAlbumPermission($albumViewData, $album['albumPermissions']['view']);
			}

			if ($album['albumPermissions']['add']['access_type'] == 'followed')
			{
				$albumAddData = array(
					'album_id' => $album['album_id'],
					'permission' => 'add',
					'access_type' => 'followed',
					'share_users' => $shareUsers
				);
				$albumModel->writeAlbumPermission($albumAddData, $album['albumPermissions']['add']);
			}
		}

		return $parent;
	}

	public function unfollow($followUserId, $userId = null)
	{
		$parent = parent::unfollow($followUserId, $userId);

		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$albumModel = $this->_getAlbumModel();

		$addConditions = array(
			'album_user_id' => $userId,
			'add_type' => 'followed',
		);
		$addAlbums = $albumModel->getAlbums($addConditions);

		$viewConditions = array(
			'album_user_id' => $userId,
			'view_type' => 'followed',
		);
		$viewAlbums = $albumModel->getAlbums($viewConditions);

		$albums = $addAlbums + $viewAlbums;

		foreach ($albums AS $album)
		{
			$album = $albumModel->prepareAlbumWithPermissions($album);

			if (!isset($album['albumPermissions']))
			{
				continue;
			}

			$shareUsers = $albumModel->unshare($followUserId, $album);

			if ($album['albumPermissions']['view']['access_type'] == 'followed')
			{
				$albumViewData = array(
					'album_id' => $album['album_id'],
					'permission' => 'view',
					'access_type' => 'followed',
					'share_users' => $shareUsers
				);
				$albumModel->writeAlbumPermission($albumViewData, $album['albumPermissions']['view']);
			}

			if ($album['albumPermissions']['add']['access_type'] == 'followed')
			{
				$albumAddData = array(
					'album_id' => $album['album_id'],
					'permission' => 'add',
					'access_type' => 'followed',
					'share_users' => $shareUsers
				);
				$albumModel->writeAlbumPermission($albumAddData, $album['albumPermissions']['add']);
			}
		}

		return $parent;
	}

	/**
	 * Returns an array containing the user ids found from the complete result given the range specified who have a media count greater than 0,
	 * along with the total number of users found.
	 *
	 * @param integer Find users with user_id greater than...
	 * @param integer Maximum users to return at once
	 *
	 * @return array
	 */
	public function getUserIdsWithMediaInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT user_id
			FROM xf_user
			WHERE user_id > ?
			AND xengallery_media_count > ?
			ORDER BY user_id
		', $limit), array($start, 0));
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}
}