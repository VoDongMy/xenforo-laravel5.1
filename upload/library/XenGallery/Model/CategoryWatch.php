<?php

class XenGallery_Model_CategoryWatch extends XenForo_Model
{
	/**
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getUserCategoryWatchByUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_category_watch
			WHERE user_id = ?
		', 'category_id', $userId);
	}

	public function getUserCategoryWatchByCategoryId($userId, $categoryId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_category_watch
			WHERE user_id = ?
				AND category_id = ?
		', array($userId, $categoryId));
	}

	public function getUserCategoryWatchByCategoryIds($userId, array $categoryIds)
	{
		if (!$categoryIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT *
			FROM xengallery_category_watch
			WHERE user_id = ?
				AND category_id IN (' . $db->quote($categoryIds) . ')
		', $userId);
	}

	/**
	 * Sets the category watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $categoryId
	 * @param string|null $notifyOn If "delete", watch record is removed
	 * @param boolean|null $sendAlert
	 * @param boolean|null $sendEmail
	 *
	 * @return boolean
	 */
	public function setCategoryWatchState($userId, $categoryId, $notifyOn = null, $sendAlert = null, $sendEmail = null, $includeChildren = null)
	{
		if (!$userId)
		{
			return false;
		}

		$categoryWatch = $this->getUserCategoryWatchByCategoryId($userId, $categoryId);

		if ($notifyOn === 'delete')
		{
			if ($categoryWatch)
			{
				$dw = XenForo_DataWriter::create('XenGallery_DataWriter_CategoryWatch');
				$dw->setExistingData($categoryWatch, true);
				$dw->delete();
			}
			return true;
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_CategoryWatch');
		if ($categoryWatch)
		{
			$dw->setExistingData($categoryWatch, true);
			if ($notifyOn == 'watch_no_email')
			{
				$sendEmail = 0;
		    }
			$notifyOn = $dw->get('notify_on');
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('category_id', $categoryId);
		}
		if ($notifyOn !== null)
		{
			$dw->set('notify_on', $notifyOn);
		}
		if ($sendAlert !== null)
		{
			$dw->set('send_alert', $sendAlert ? 1 : 0);
		}
		if ($sendEmail !== null)
		{
			$dw->set('send_email', $sendEmail ? 1 : 0);
		}
		if ($includeChildren !== null)
		{
			$dw->set('include_children', $includeChildren ? 1 : 0);
		}
		$dw->save();
		return true;
	}

	public function setCategoryWatchStateForAll($userId, $state)
	{
		$userId = intval($userId);
		if (!$userId)
		{
			return false;
		}

		$db = $this->_getDb();

		switch ($state)
		{
			case 'watch_email':
				return $db->update('xengallery_category_watch',
					array('send_email' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_email':
				return $db->update('xengallery_category_watch',
					array('send_email' => 0),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_include_children':
				return $db->update('xengallery_category_watch',
					array('include_children' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_include_children':
				return $db->update('xengallery_category_watch',
					array('include_children' => 0),
					"user_id = " . $db->quote($userId)
				);

			case '':
				return $db->delete('xengallery_category_watch', "user_id = " . $db->quote($userId));

			default:
				return false;
		}
	}

	/**
	 * Get a list of all users watching a forum. Includes permissions for the forum.
	 *
	 * @param integer $nodeId
	 * @param integer $threadId
	 * @param boolean $isReply
	 *
	 * @return array Format: [user_id] => info
	 */
	public function getUsersWatchingCategory(array $category, $watchType = '')
	{
		switch ($watchType)
		{
			case 'media':
			default:
				$notificationLimit = "AND category_watch.notify_on = 'media'";
				break;
		}

		$activeLimitOption = XenForo_Application::getOptions()->watchAlertActiveOnly;
		if ($activeLimitOption && !empty($activeLimitOption['enabled']))
		{
			$activeLimit = ' AND user.last_activity >= ' . (XenForo_Application::$time - 86400 * $activeLimitOption['days']);
		}
		else
		{
			$activeLimit = '';
		}

		$breadcrumb = unserialize($category['category_breadcrumb']);
		$categoryIds = array_keys($breadcrumb);
		$categoryIds[] = $category['category_id'];

		return $this->fetchAllKeyed('
			SELECT user.*,
				user_option.*,
				user_profile.*,
				category_watch.category_id AS watch_category_id,
				category_watch.notify_on,
				category_watch.send_alert,
				category_watch.send_email,
				permission_combination.cache_value AS global_permission_cache
			FROM xengallery_category_watch AS category_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = category_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			LEFT JOIN xf_permission_combination AS permission_combination ON
				(permission_combination.permission_combination_id = user.permission_combination_id)
			WHERE category_watch.category_id IN (' . $this->_getDb()->quote($categoryIds) . ')
				' . $notificationLimit . $activeLimit . '
				AND (category_watch.include_children <> 0 OR category_watch.category_id = ?)
				AND (category_watch.send_alert <> 0 OR category_watch.send_email <> 0)
		', 'user_id', $category['category_id']);
	}

	protected static $_preventDoubleNotify = array();

	public function sendNotificationToWatchUsersOnMediaInsert(array $media, array $category = null)
	{
		if ($media['media_state'] != 'visible')
		{
			return array();
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$categoryModel = $this->_getCategoryModel();

		if (!$category)
		{
			$category = $categoryModel->getCategoryById($media['category_id'], array());
		}

		$media['titleCensored'] = XenForo_Helper_String::censorString($media['media_title']);
		$media['descCensored'] = XenForo_Helper_String::censorString($media['media_description']);
		$category['titleCensored'] = XenForo_Helper_String::censorString($category['category_title']);
		$category['descCensored'] = XenForo_Helper_String::censorString($category['category_description']);

		// fetch a full user record if we don't have one already
		if (!isset($media['avatar_width']) || !isset($media['custom_title']))
		{
			$mediaUser = $this->getModelFromCache('XenForo_Model_User')->getUserById($media['user_id']);
			if ($mediaUser)
			{
				$media = array_merge($mediaUser, $media);
			}
			else
			{
				$media['avatar_width'] = 0;
				$media['custom_title'] = '';
			}
		}

		$alerted = array();
		$emailed = array();

		$users = $this->getUsersWatchingCategory($category, 'media');
		foreach ($users AS $user)
		{
			if ($user['user_id'] == $media['user_id'])
			{
				continue;
			}

			if ($userModel->isUserIgnored($user, $media['user_id']))
			{
				continue;
			}

			$user['permissions'] = XenForo_Permission::unserializePermissions($user['global_permission_cache']);
			if (!$categoryModel->canViewCategory($category, $null, $user))
			{
				continue;
			}

			if (isset(self::$_preventDoubleNotify[$category['category_id']][$user['user_id']]))
			{
				continue;
			}
			self::$_preventDoubleNotify[$category['category_id']][$user['user_id']] = true;

			if ($user['send_email'] && $user['email'] && $user['user_state'] == 'valid')
			{
				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create('xengallery_watched_category_media_insert', array(
					'media' => $media,
					'category' => $category,
					'receiver' => $user
				), $user['language_id']);
				$mail->enableAllLanguagePreCache();
				$mail->queue($user['email'], $user['username']);

				$emailed[] = $user['user_id'];
			}

			if ($user['send_alert'])
			{
				XenForo_Model_Alert::alert(
					$user['user_id'],
					$media['user_id'],
					$media['username'],
					'xengallery_media',
					$media['media_id'],
					'watch_insert'
				);

				$alerted[] = $user['user_id'];
			}
		}

		return array(
			'emailed' => $emailed,
			'alerted' => $alerted
		);
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}
}