<?php

class XenGallery_Model_Importers extends XenForo_Model
{
	/**
	 * If true, wherever possible, keep the existing data primary key
	 *
	 * @var boolean
	 */
	protected $_retainKeys = false;

	public function importAlbum($oldId, array $info, $albumPrivacy = 'public', $contentType = '', array $shareUsers = array())
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_Album */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
		$dw->setImportMode(true);

		if ($this->_retainKeys && is_int($oldId) && $oldId > 0)
		{
			$dw->set('album_id', $oldId);
		}

		$dw->bulkSet($info);
		if ($dw->save())
		{
			$newId = $dw->get('album_id');
			$this->_getImportModel()->logImportData('xengallery_album', $oldId, $newId);
		}
		else
		{
			$newId = false;
		}

		$dw->setImportMode(false);
		if ($albumPrivacy == 'private'
			|| $albumPrivacy == 'public'
			|| $albumPrivacy == 'members'
		)
		{
			$dw->setExtraData(XenGallery_DataWriter_Album::DATA_ACCESS_TYPE, $albumPrivacy);
			$dw->changeAlbumPrivacy('view');
		}
		else
		{
			$album = $dw->getMergedData();

			if (($albumPrivacy == 'followed' || $albumPrivacy == 'shared')
				&& sizeof($shareUsers)
			)
			{
				/** @var $albumModel XenGallery_Model_Album */
				$albumModel = XenForo_Model::create('XenGallery_Model_Album');
				$shareUsers = $albumModel->prepareViewShareUsers($shareUsers, $dw->getMergedData());

				unset ($shareUsers[$album['album_user_id']]);

				$albumViewData = array(
					'album_id' => $album['album_id'],
					'permission' => 'view',
					'access_type' => $albumPrivacy,
					'share_users' => $shareUsers
				);
				$albumModel->writeAlbumPermission($albumViewData, $album);
			}
		}

		if ($info['album_likes'] && $info['album_like_users'] && $contentType)
		{
			$this->_convertLikesToNewContentType($oldId, $newId, $contentType, 'xengallery_album');
		}

		XenForo_Db::commit();

		return $newId;
	}

	public function importCategory($oldId, array $info, $contentType = 'xengallery_category')
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_Category */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Category');
		$dw->setImportMode(true);

		if ($contentType == 'xengallery_category')
		{
			if ($this->_retainKeys)
			{
				$dw->set('category_id', $oldId);
			}
		}

		$dw->bulkSet($info);
		if ($dw->save())
		{
			$im = $this->_getImportModel();

			$newId = $dw->get('category_id');

			if ($contentType == 'xengallery_album')
			{
				$newId = 'category_' . $newId;
			}
			$dw->rebuildCategoryStructure();

			$im->logImportData($contentType, $oldId, $newId);
		}
		else
		{
			$newId = false;
		}

		$userGroupIds = $this->_getDb()->fetchCol('
			SELECT user_group_id FROM xf_user_group
		');
		foreach ($userGroupIds AS $userGroupId)
		{
			$this->_getDb()->query('
				INSERT IGNORE INTO xengallery_category_map
					(category_id, view_user_group_id)
				VALUES
					(?, ?)
			', array($newId, $userGroupId));
		}

		XenForo_Db::commit();

		return $newId;
	}

	public function importMedia($oldId, $tempFile, $contentType = '', array $xengalleryMedia = array(), array $xfAttachment = array(), array $xfAttachmentData = array())
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$attachmentId = 0;
		if ($xfAttachment)
		{
			/** @var $attachmentDw XenForo_DataWriter_Attachment */
			$attachmentDw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
			$attachmentDw->setImportMode(true);

			$attachmentDw->bulkSet($xfAttachment);
			$attachmentDw->save();

			$attachmentId = $attachmentDw->get('attachment_id');
		}

		$newId = false;
		if ($xengalleryMedia)
		{
			/** @var $mediaDw XenGallery_DataWriter_Media */
			$mediaDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

			$mediaDw->setImportMode(true);
			$mediaDw->set('imported', XenForo_Application::$time);

			if ($this->_retainKeys)
			{
				$mediaDw->set('media_id', $oldId);
			}

			$xengalleryMedia['attachment_id'] = $attachmentId;
			$mediaDw->bulkSet($xengalleryMedia);
			if ($mediaDw->save())
			{
				$newId = $mediaDw->get('media_id');
				$this->_getImportModel()->logImportData('xengallery_media', $oldId, $newId);
			}

			$media = $mediaDw->getMergedData();
			if ($media['likes'] && $media['like_users'] && $contentType)
			{
				$this->_convertLikesToNewContentType($oldId, $newId, $contentType, 'xengallery_media');
			}
		}

		if ($xfAttachmentData)
		{
			$fileIsVideo = false;

			$db->update('xf_attachment', array('content_id' => $media['media_id']), 'attachment_id = ' . $db->quote($attachmentId));

			$options = XenForo_Application::getOptions();

			$upload = new XenForo_Upload($xfAttachmentData['filename'], $tempFile);
			if ($upload->isImage())
			{
				$image = new XenGallery_Helper_Image($tempFile);
				$image->importMode = true;

				$dimensions = array(
					'width' => $image->getWidth(),
					'height' => $image->getHeight(),
				);

				$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
				if ($tempThumbFile && $image)
				{
					$resized = $image->resize(
						$dimensions['thumbnail_width'] = $options->xengalleryThumbnailDimension['width'],
						$dimensions['thumbnail_height'] = $options->xengalleryThumbnailDimension['height'], 'crop'
					);

					if (!$resized)
					{
						return false;
					}

					$image->saveToPath($tempThumbFile);

					unset($image);
				}
				else
				{
					return false;
				}
			}
			else
			{
				$dimensions = array();

				$fileIsVideo = true;
				$tempThumbFile = false;

				if ($options->get('xengalleryVideoTranscoding', 'thumbnail'))
				{
					try
					{
						$video = new XenGallery_Helper_Video($upload->getTempFile());
						$tempThumbFile = $video->getKeyFrame();

						list($width, $height) = $video->getVideoDimensions();

						$dimensions['width'] = $width;
						$dimensions['height'] = $height;
					}
					catch (XenForo_Exception $e) {}
				}

				if (!$tempThumbFile)
				{
					$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
					if ($tempThumbFile)
					{
						@copy($options->xengalleryDefaultNoThumb, $tempThumbFile);
					}
				}

				$image = new XenGallery_Helper_Image($tempThumbFile);
				if ($image)
				{
					$image->resize(
						$dimensions['thumbnail_width'] = $options->xengalleryThumbnailDimension['width'],
						$dimensions['thumbnail_height'] = $options->xengalleryThumbnailDimension['height'], 'crop'
					);

					$image->saveToPath($tempThumbFile);

					unset($image);
				}
			}

			$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

			try
			{
				$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');

				$filename = $upload->getFileName();

				if ($fileIsVideo)
				{
					$filename = strtr($filename, strtolower(substr(strrchr($filename, '.'), 1)), 'mp4');
					$dataDw->set('file_path', $mediaModel->getVideoFilePath());
				}

				$dataDw->set('filename', $filename);
				$dataDw->bulkSet($dimensions);
				$dataDw->bulkSet($xfAttachmentData);

				$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $tempFile);
				if ($tempThumbFile)
				{
					$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
				}

				$dataDw->setExtraData(XenGallery_DataWriter_AttachmentData::DATA_XMG_FILE_IS_VIDEO, $fileIsVideo);
				$dataDw->setExtraData(XenGallery_DataWriter_AttachmentData::DATA_XMG_DATA, true);

				$dataDw->save();

				$attachmentData = $dataDw->getMergedData();
				$db->update('xf_attachment', array('data_id' => $attachmentData['data_id']), 'attachment_id = ' . $db->quote($attachmentId));
			}
			catch (Exception $e)
			{
				if ($tempThumbFile)
				{
					@unlink($tempThumbFile);
				}

				throw $e;
			}

			if ($tempThumbFile)
			{
				@unlink($tempThumbFile);
			}
		}

		if ($newId)
		{
			XenForo_Db::commit($db);
		}
		else
		{
			XenForo_Db::rollback($db);
		}

		return $newId;
	}

	public function importComment($oldId, array $info)
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_Comment */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
		$dw->setImportMode(true);

		if ($this->_retainKeys)
		{
			$dw->set('comment_id', $oldId);
		}

		$dw->bulkSet($info);
		if ($dw->save())
		{
			$newId = $dw->get('comment_id');
			$this->_getImportModel()->logImportData('xengallery_comment', $oldId, $newId);
		}
		else
		{
			$newId = false;
		}

		XenForo_Db::commit();

		return $newId;
	}

	public function importField(array $xengalleryField, array $fieldChoices, array $valuesGrouped, $fieldTitle, $fieldDescription, array $categoryIds)
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_Field */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Field');
		// Let's let verification and pre/postSave run:
		$dw->setImportMode(false);

		$dw->setFieldChoices($fieldChoices);

		$dw->setExtraData(XenGallery_DataWriter_Field::DATA_TITLE, $fieldTitle);
		$dw->setExtraData(XenGallery_DataWriter_Field::DATA_DESCRIPTION, $fieldDescription);
		$dw->setExtraData(XenGallery_DataWriter_Field::DATA_CATEGORY_IDS, $categoryIds);

		$dw->bulkSet($xengalleryField);
		if ($dw->save())
		{
			$fieldId = $dw->get('field_id');
		}
		else
		{
			$fieldId = false;
		}

		foreach ($valuesGrouped AS $mediaId => $valuesArray)
		{
			foreach ($valuesArray AS $value)
			{
				/** @var $mediaWriter XenGallery_DataWriter_Media */
				$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
				$mediaWriter->setImportMode(true);

				$mediaWriter->setExistingData($mediaId);

				$existingValues = $mediaWriter->get('custom_media_fields');
				$existingValues = @unserialize($existingValues);
				if (!is_array($existingValues))
				{
					$existingValues = array();
				}

				$serialize = false;
				$testValue = @unserialize($value);
				if (is_array($testValue))
				{
					$value = $testValue;
					$serialize = true;
				}

				$existingValues[$fieldId] = $value;
				$finalValues = serialize($existingValues);

				$mediaWriter->set('custom_media_fields', $finalValues);
				$mediaWriter->save();

				$db = XenForo_Application::getDb();
				$db->query('
					INSERT IGNORE INTO xengallery_field_value
						(media_id, field_id, field_value)
					VALUES
						(?, ?, ?)
				', array($mediaId, $fieldId, $serialize ? serialize($value) : $value));
			}
		}

		XenForo_Db::commit();

		/** @var $fieldModel XenGallery_Model_Field */
		$fieldModel = XenForo_Model::create('XenGallery_Model_Field');
		$fieldModel->rebuildGalleryFieldCache();

		return $fieldId;
	}

	public function importFieldValues(array $valuesGrouped, $fieldId)
	{
		foreach ($valuesGrouped AS $mediaId => $valuesArray)
		{
			foreach ($valuesArray AS $value)
			{
				/** @var $mediaWriter XenGallery_DataWriter_Media */
				$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
				$mediaWriter->setImportMode(true);

				$mediaWriter->setExistingData($mediaId);

				$existingValues = $mediaWriter->get('custom_media_fields');
				$existingValues = @unserialize($existingValues);
				if (!is_array($existingValues))
				{
					$existingValues = array();
				}

				$serialize = false;
				$testValue = @unserialize($value);
				if (is_array($testValue))
				{
					$value = $testValue;
					$serialize = true;
				}

				$existingValues[$fieldId] = $value;
				$finalValues = serialize($existingValues);

				$mediaWriter->set('custom_media_fields', $finalValues);
				$mediaWriter->save();

				$db = XenForo_Application::getDb();
				$db->query('
					INSERT IGNORE INTO xengallery_field_value
						(media_id, field_id, field_value)
					VALUES
						(?, ?, ?)
				', array($mediaId, $fieldId, $serialize ? serialize($value) : $value));
			}
		}
	}

	public function importTag($tag, $contentType, $contentId, array $content)
	{
		return $this->getModelFromCache('XenForo_Model_Import')->importTag($tag, $contentType, $contentId, $content);
	}

	public function importUserTag(array $userTag)
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_UserTag */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_UserTag');
		$dw->setImportMode(true);

		$dw->bulkSet($userTag);
		$dw->save();

		XenForo_Db::commit();

		return true;
	}

	public function importRating($oldId, array $info, $noRatingsTable = false)
	{
		XenForo_Db::beginTransaction();

		/* @var $dw XenGallery_DataWriter_Rating */
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Rating', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExtraData(XenGallery_DataWriter_Rating::DATA_PREVENT_ALERTS, true);
		$dw->setImportMode(false);

		if ($this->_retainKeys)
		{
			$dw->set('rating_id', $oldId);
		}

		$dw->bulkSet($info);
		if ($dw->save())
		{
			$newId = $dw->get('rating_id');
			$this->_getImportModel()->logImportData('xengallery_rating', $oldId, $newId);

			if ($noRatingsTable)
			{
				$commentId = $this->mapCommentId($oldId);

				$this->_getDb()->update('xengallery_comment', array('rating_id' => $newId), 'comment_id = ' . $this->_getDb()->quote($commentId));
			}
		}
		else
		{
			$newId = false;
		}

		XenForo_Db::commit();

		return $newId;
	}

	protected function _convertLikesToNewContentType($oldId, $newId, $oldContentType, $newContentType)
	{
		$db = $this->_getDb();

		return $db->update('xf_liked_content', array(
			'content_id' => $newId,
			'content_type' => $newContentType
		), 'content_id = ' . $db->quote($oldId) . ' AND content_type = ' . $db->quote($oldContentType));
	}

	public function getAlbumByTitle($title)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_album
			WHERE album_title = ?
		', $title);
	}

	public function getUsernameByUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT username
			FROM xf_user
			WHERE user_id = ?
		', $userId);
	}

	public function getLatestIpIdFromUserId($userId)
	{
		$db = $this->_getDb();

		$ipId = $db->fetchOne($db->limit('
			SELECT ip_id
			FROM xf_ip
			WHERE user_id = ?
			ORDER BY log_date DESC
		', 1), $userId);

		if (!$ipId)
		{
			$ipId = 0;
		}

		return $ipId;
	}

	/**
	 * Gets a last comment date for a xfr_useralbum image.
	 *
	 * @param $imageId
	 * @return int
	 */
	public function getLastCommentDateFromImageIdXFRUA($imageId)
	{
		$db = $this->_getDb();

		return $db->fetchOne($db->limit('
			SELECT comment_date
			FROM xfr_useralbum_image_comment
			WHERE image_id = ?
			ORDER BY comment_date DESC
		', 1), $imageId);
	}

	public function getMediaCountFromCategoryIdXenMedio($categoryId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM EWRmedio_media
			WHERE category_id = ?
		', $categoryId);
	}

	public function getAlbumPrivacyAndShareUsersFromAlbumPrivacyXenGallery(array $album)
	{
		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$albumPrivacy = @unserialize($album['album_privacy']);

		$viewPrivacy = 'public';
		$shareUsers = array();

		if (isset($album['album_user_id']))
		{
			$album['user_id'] = $album['album_user_id'];
		}

		if (is_array($albumPrivacy))
		{
			if (!empty($albumPrivacy['allow_view']))
			{
				$userIds = array();
				switch ($albumPrivacy['allow_view'])
				{
					case 'everyone':

						$viewPrivacy = 'public';
						break;

					case 'members':

						$viewPrivacy = 'members';
						break;

					case 'none':

						$viewPrivacy = 'private';
						break;

					case 'custom':

						$viewPrivacy = 'shared';
						if (!empty($albumPrivacy['allow_view_data']))
						{
							$userIds = @unserialize($albumPrivacy['allow_view_data']);
							if (!is_array($userIds))
							{
								$userIds = array();
							}

							$userIds = array_keys($userIds);
						}
						break;

					case 'followed':

						$viewPrivacy = 'followed';
						$followedUsers = $userModel->getFollowedUserProfiles($album['user_id'], 0, 'user.user_id');
						$userIds = array_keys($followedUsers);

						break;

					case 'following':

						$viewPrivacy = 'shared';
						$followingUsers = $userModel->getUsersFollowingUserId($album['user_id'], 0, 'user.user_id');
						$userIds = array_keys($followingUsers);

						break;
				}

				$userIds[$album['user_id']] = $album['user_id'];
				$shareUsers = $userModel->getUsersByIds($userIds);
			}
		}

		return array($viewPrivacy, $shareUsers);
	}

	/**
	 * Gets a last comment date for a xfr_useralbum image.
	 *
	 * @param $imageId
	 * @return int
	 */
	public function getLastCommentDateFromContentXenGallery($contentId, $contentType)
	{
		$db = $this->_getDb();

		return $db->fetchOne($db->limit('
			SELECT comment_date
			FROM sonnb_xengallery_comment
			WHERE content_id = ?
				AND content_type = ?
			ORDER BY comment_date DESC
		', 1), array($contentId, $contentType));
	}

	public function getLastTagUseDateFromTagName($tagName)
	{
		$db = $this->_getDb();

		return $db->fetchOne($db->limit('
			SELECT stream_date
			FROM sonnb_xengallery_stream
			WHERE stream_name = ?
				AND content_type <> \'album\'
			ORDER BY stream_date DESC
		', 1), $tagName);
	}

	/**
	 * Sets the value of the $_retainKeys option, in order to retain the existing keys where possible
	 *
	 * @param boolean $retainKeys
	 */
	public function retainKeys($retainKeys)
	{
		$this->_retainKeys = ($retainKeys ? true : false);
	}

	/**
	 * Maps an old category ID to a new/imported category ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapCategoryId($id, $default = null)
	{
		$logTable = (defined('IMPORT_LOG_TABLE') ? IMPORT_LOG_TABLE : 'xf_import_log');

		if ($logTable != 'xf_import_log')
		{
			$ids = $this->getImportContentMap('xengallery_category', $id, 'xf_import_log');
			return ($ids ? reset($ids) : $default);
		}

		$ids = $this->_getImportModel()->getImportContentMap('xengallery_category', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old album ID to a new/imported album ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapAlbumId($id, $default = null)
	{
		$logTable = (defined('IMPORT_LOG_TABLE') ? IMPORT_LOG_TABLE : 'xf_import_log');

		if ($logTable != 'xf_import_log')
		{
			$ids = $this->getImportContentMap('xengallery_album', $id, 'xf_import_log');
			return ($ids ? reset($ids) : $default);
		}

		$ids = $this->_getImportModel()->getImportContentMap('xengallery_album', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old media ID to a new/imported media ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapMediaId($id, $default = null)
	{
		$logTable = (defined('IMPORT_LOG_TABLE') ? IMPORT_LOG_TABLE : 'xf_import_log');

		if ($logTable != 'xf_import_log')
		{
			$ids = $this->getImportContentMap('xengallery_media', $id, 'xf_import_log');
			return ($ids ? reset($ids) : $default);
		}

		$ids = $this->_getImportModel()->getImportContentMap('xengallery_media', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old media ID to a new/imported media ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapCommentId($id, $default = null)
	{
		$logTable = (defined('IMPORT_LOG_TABLE') ? IMPORT_LOG_TABLE : 'xf_import_log');

		if ($logTable != 'xf_import_log')
		{
			$ids = $this->getImportContentMap('xengallery_comment', $id, 'xf_import_log');
			return ($ids ? reset($ids) : $default);
		}

		$ids = $this->_getImportModel()->getImportContentMap('xengallery_comment', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Gets an import content map to map old IDs to new IDs for the given content type.
	 *
	 * @param string $contentType
	 * @param array $ids
	 *
	 * @return array
	 */
	public function getImportContentMap($contentType, $ids = false, $logTable)
	{
		$db = $this->_getDb();

		if ($ids === false)
		{
			return $db->fetchPairs('
				SELECT old_id, new_id
				FROM ' . $logTable . '
				WHERE content_type = ?
			', $contentType);
		}

		if (!is_array($ids))
		{
			$ids = array($ids);
		}
		if (!$ids)
		{
			return array();
		}

		$final = array();
		if (isset($this->_contentMapCache[$contentType]))
		{
			$lookup = $this->_contentMapCache[$contentType];
			foreach ($ids AS $key => $id)
			{
				if (isset($lookup[$id]))
				{
					$final[$id] = $lookup[$id];
					unset($ids[$key]);
				}
			}
		}

		if (!$ids)
		{
			return $final;
		}

		foreach ($ids AS &$id)
		{
			$id = strval($id);
		}

		$merge = $db->fetchPairs('
			SELECT old_id, new_id
			FROM ' . $logTable . '
			WHERE content_type = ?
				AND old_id IN (' . $db->quote($ids) . ')
		', $contentType);

		if (isset($this->_contentMapCache[$contentType]))
		{
			$this->_contentMapCache[$contentType] += $merge;
		}
		else
		{
			$this->_contentMapCache[$contentType] = $merge;
		}

		return $final + $merge;
	}

	public function checkImportLogTableExists($logTableName)
	{
		return $this->_getDb()->fetchOne('
			SHOW TABLES
			LIKE ' . $this->_getDb()->quote($logTableName));
	}

	/**
	 * @return XenForo_Model_Import
	 */
	protected function _getImportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Import');
	}
}