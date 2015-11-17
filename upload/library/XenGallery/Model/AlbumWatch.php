<?php

class XenGallery_Model_AlbumWatch extends XenForo_Model
{
	/**
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getUserAlbumWatchByUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_album_watch
			WHERE user_id = ?
		', 'album_id', $userId);
	}

	public function getUserAlbumWatchByAlbumId($userId, $albumId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_album_watch
			WHERE user_id = ?
				AND album_id = ?
		', array($userId, $albumId));
	}

	public function getUserAlbumWatchByAlbumIds($userId, array $albumIds)
	{
		if (!$albumIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT *
			FROM xengallery_album_watch
			WHERE user_id = ?
				AND album_id IN (' . $db->quote($albumIds) . ')
		', $userId);
	}

	/**
	 * Sets the album watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $albumId
	 * @param string|null $notifyOn If "delete", watch record is removed
	 * @param boolean|null $sendAlert
	 * @param boolean|null $sendEmail
	 *
	 * @return boolean
	 */
	public function setAlbumWatchState($userId, $albumId, $notifyOn = null, $sendAlert = null, $sendEmail = null)
	{
		if (!$userId)
		{
			return false;
		}

		$albumWatch = $this->getUserAlbumWatchByAlbumId($userId, $albumId);

		if ($notifyOn === 'delete')
		{
			if ($albumWatch)
			{
				$dw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumWatch');
				$dw->setExistingData($albumWatch, true);
				$dw->delete();
			}
			return true;
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumWatch');
		if ($albumWatch)
		{
			$dw->setExistingData($albumWatch, true);
			if ($notifyOn == 'watch_no_email')
			{
				$sendEmail = 0;
		    }
			$notifyOn = $dw->get('notify_on');
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('album_id', $albumId);
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
		$dw->save();
		return true;
	}

	public function setAlbumWatchStateForAll($userId, $state)
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
				return $db->update('xengallery_album_watch',
					array('send_email' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_email':
				return $db->update('xengallery_album_watch',
					array('send_email' => 0),
					"user_id = " . $db->quote($userId)
				);

			case '':
				return $db->delete('xengallery_album_watch', "user_id = " . $db->quote($userId));

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
	public function getUsersWatchingAlbum($albumId, $watchType = '')
	{
		switch ($watchType)
		{
			case 'media':
				$notificationLimit = "AND (album_watch.notify_on = 'media' OR album_watch.notify_on = 'media_comment')";
				break;

			case 'comment':
				$notificationLimit = "AND (album_watch.notify_on = 'comment' OR album_watch.notify_on = 'media_comment')";
				break;

			default:
				$notificationLimit = "AND album_watch.notify_on IN ('media', 'comment', 'media_comment')";
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

		return $this->fetchAllKeyed('
			SELECT user.*,
				user_option.*,
				user_profile.*,
				album_watch.notify_on,
				album_watch.send_alert,
				album_watch.send_email,
				permission_combination.cache_value AS global_permission_cache
			FROM xengallery_album_watch AS album_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = album_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			LEFT JOIN xf_permission_combination AS permission_combination ON
				(permission_combination.permission_combination_id = user.permission_combination_id)
			WHERE album_watch.album_id = ?
				 ' . $notificationLimit . $activeLimit . '
				AND (album_watch.send_alert <> 0 OR album_watch.send_email <> 0)
		', 'user_id', $albumId);
	}

	protected static $_preventDoubleNotify = array();

	public function sendNotificationToWatchUsersOnMediaInsert(array $media, array $album = null)
	{
		if ($media['media_state'] != 'visible')
		{
			return array();
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$albumModel = $this->_getAlbumModel();

		if (!$album)
		{
			$album = $albumModel->getAlbumById($media['album_id'], array());
		}
		if (!$album || $album['album_state'] != 'visible')
		{
			return array();
		}
		$album = $albumModel->prepareAlbumWithPermissions($album);

		$media['titleCensored'] = XenForo_Helper_String::censorString($media['media_title']);
		$media['descCensored'] = XenForo_Helper_String::censorString($media['media_description']);
		$album['titleCensored'] = XenForo_Helper_String::censorString($album['album_title']);
		$album['descCensored'] = XenForo_Helper_String::censorString($album['album_description']);

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

		$users = $this->getUsersWatchingAlbum($album['album_id'], 'media');
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
			if (!$albumModel->canViewAlbum($album, $null, $user))
			{
				continue;
			}

			if (isset(self::$_preventDoubleNotify[$album['album_id']][$user['user_id']]))
			{
				continue;
			}
			self::$_preventDoubleNotify[$album['album_id']][$user['user_id']] = true;

			if ($user['send_email'] && $user['email'] && $user['user_state'] == 'valid')
			{
				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create('xengallery_watched_album_media_insert', array(
					'media' => $media,
					'album' => $album,
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

	public function sendNotificationToWatchUsersOnCommentInsert(array $comment, array $album, $alreadyAlerted = array())
	{
		if ($comment['comment_state'] != 'visible')
		{
			return array();
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		if (!$album || $album['album_state'] != 'visible')
		{
			return array();
		}

		$album['titleCensored'] = XenForo_Helper_String::censorString($album['album_title']);
		$album['descCensored'] = XenForo_Helper_String::censorString($album['album_description']);

		$comment['messageCensored'] = XenForo_Helper_String::censorString($comment['message']);

		$bbCodeParserText = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Text'));
		$comment['messageText'] = new XenForo_BbCode_TextWrapper($comment['messageCensored'], $bbCodeParserText);

		$bbCodeParserHtml = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('HtmlEmail'));
		$comment['messageHtml'] = new XenForo_BbCode_TextWrapper($comment['messageCensored'], $bbCodeParserHtml);

		// fetch a full user record if we don't have one already
		if (!isset($comment['avatar_width']) || !isset($comment['custom_title']))
		{
			$commentUser = $this->getModelFromCache('XenForo_Model_User')->getUserById($comment['user_id']);
			if ($commentUser)
			{
				$comment = array_merge($commentUser, $comment);
			}
			else
			{
				$comment['avatar_width'] = 0;
				$comment['custom_title'] = '';
			}
		}

		$alerted = array();
		$emailed = array();

		$users = $this->getUsersWatchingAlbum($album['album_id'], 'comment');
		foreach ($users AS $user)
		{
			if ($user['user_id'] == $comment['user_id'])
			{
				continue;
			}

			if ($userModel->isUserIgnored($user, $comment['user_id']))
			{
				continue;
			}

			if (in_array($user['user_id'], $alreadyAlerted))
			{
				continue;
			}

			if (isset(self::$_preventDoubleNotify[$album['album_id']][$user['user_id']]))
			{
				continue;
			}
			self::$_preventDoubleNotify[$album['album_id']][$user['user_id']] = true;

			if ($user['send_email'] && $user['email'] && $user['user_state'] == 'valid')
			{
				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create('xengallery_watched_album_comment', array(
					'comment' => $comment,
					'album' => $album,
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
					$comment['user_id'],
					$comment['username'],
					'xengallery_comment',
					$comment['comment_id'],
					'watch_comment'
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
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}
}