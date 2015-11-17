<?php

class XenGallery_Model_MediaWatch extends XenForo_Model
{
	/**
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getUserMediaWatchByUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_media_watch
			WHERE user_id = ?
		', 'media_id', $userId);
	}

	public function getUserMediaWatchByMediaId($userId, $mediaId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_media_watch
			WHERE user_id = ?
				AND media_id = ?
		', array($userId, $mediaId));
	}

	public function getUserMediaWatchByMediaIds($userId, array $mediaIds)
	{
		if (!$mediaIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT *
			FROM xengallery_media_watch
			WHERE user_id = ?
				AND media_id IN (' . $db->quote($mediaIds) . ')
		', $userId);
	}

	/**
	 * Sets the media watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $mediaId
	 * @param string|null $notifyOn If "delete", watch record is removed
	 * @param boolean|null $sendAlert
	 * @param boolean|null $sendEmail
	 *
	 * @return boolean
	 */
	public function setMediaWatchState($userId, $mediaId, $notifyOn = null, $sendAlert = null, $sendEmail = null)
	{
		if (!$userId)
		{
			return false;
		}

		$mediaWatch = $this->getUserMediaWatchByMediaId($userId, $mediaId);

		if ($notifyOn === 'delete')
		{
			if ($mediaWatch)
			{
				$dw = XenForo_DataWriter::create('XenGallery_DataWriter_MediaWatch');
				$dw->setExistingData($mediaWatch, true);
				$dw->delete();
			}
			return true;
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_MediaWatch');
		if ($mediaWatch)
		{
			$dw->setExistingData($mediaWatch, true);
			if ($notifyOn == 'watch_no_email')
			{
				$sendEmail = 0;
			}
			$notifyOn = $dw->get('notify_on');
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('media_id', $mediaId);
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

	public function setMediaWatchStateForAll($userId, $state)
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
				return $db->update('xengallery_media_watch',
					array('send_email' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_email':
				return $db->update('xengallery_media_watch',
					array('send_email' => 0),
					"user_id = " . $db->quote($userId)
				);

			case '':
				return $db->delete('xengallery_media_watch', "user_id = " . $db->quote($userId));

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
	public function getUsersWatchingMedia($mediaId, $watchType = '')
	{
		// bit superfluous in its current state. but future.
		switch ($watchType)
		{
			case 'comment':
				$notificationLimit = "AND media_watch.notify_on = 'comment'";
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
				media_watch.notify_on,
				media_watch.send_alert,
				media_watch.send_email,
				permission_combination.cache_value AS global_permission_cache
			FROM xengallery_media_watch AS media_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = media_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			LEFT JOIN xf_permission_combination AS permission_combination ON
				(permission_combination.permission_combination_id = user.permission_combination_id)
			WHERE media_watch.media_id = ?
				' . $notificationLimit . $activeLimit . '
				AND (media_watch.send_alert <> 0 OR media_watch.send_email <> 0)
		', 'user_id', $mediaId);
	}

	protected static $_preventDoubleNotify = array();

	public function sendNotificationToWatchUsersOnCommentInsert(array $comment, array $media, $alreadyAlerted = array())
	{
		if ($comment['comment_state'] != 'visible')
		{
			return array();
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$mediaModel = $this->_getMediaModel();

		if (!$media)
		{
			$media = $mediaModel->getMediaById($media['media_id'], array());
		}
		if (!$media || $media['media_state'] != 'visible')
		{
			return array();
		}

		$media['titleCensored'] = XenForo_Helper_String::censorString($media['media_title']);
		$media['descCensored'] = XenForo_Helper_String::censorString($media['media_description']);

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

		$users = $this->getUsersWatchingMedia($media['media_id'], 'comment');
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

			if (isset(self::$_preventDoubleNotify[$media['media_id']][$user['user_id']]))
			{
				continue;
			}
			self::$_preventDoubleNotify[$media['media_id']][$user['user_id']] = true;

			if ($user['send_email'] && $user['email'] && $user['user_state'] == 'valid')
			{
				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create('xengallery_watched_media_comment', array(
					'comment' => $comment,
					'media' => $media,
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
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}