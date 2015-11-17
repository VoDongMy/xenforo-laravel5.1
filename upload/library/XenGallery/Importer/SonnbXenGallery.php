<?php

class XenGallery_Importer_SonnbXenGallery extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_config;

	protected $_defaultTables = array(
		'sonnb_xengallery_album',
		'sonnb_xengallery_comment',
		'sonnb_xengallery_content',
		'sonnb_xengallery_content_data',
		'sonnb_xengallery_field',
		'sonnb_xengallery_field_value',
		'sonnb_xengallery_stream',
		'sonnb_xengallery_tag',
		'sonnb_xengallery_video'
	);

	public static function getName()
	{
		return ' XFMG: Import From sonnb - XenGallery';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$errors = $this->validateConfiguration($config);
			if ($errors)
			{
				return $controller->responseError($errors);
			}

			return true;
		}
		else
		{
			$config = XenForo_Application::getConfig();
			$dbConfig = $config->get('db');

			$viewParams = array(
				'config' => array(
					'db' => array(
						'host' => $dbConfig->host,
						'port' => $dbConfig->port,
						'username' => $dbConfig->username,
						'password' => $dbConfig->password,
						'dbname' => $dbConfig->dbname
					)
				),
				'addOnName' => str_replace('XFMG: Import From ', '', self::getName()),
			);
		}

		return $controller->responseView('XenGallery_ViewAdmin_Import_Config', 'xengallery_import_config', $viewParams);
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf-8'
				)
			);
			$db->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
		}

		if ($errors)
		{
			return $errors;
		}

		foreach ($this->_defaultTables AS $table)
		{
			$exists = $db->fetchOne("SHOW TABLES LIKE '$table'");

			if (!$exists)
			{
				$errors[] = new XenForo_Phrase('xengallery_table_x_does_not_exist', array('tablename' => $table));
			}
		}

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			// already run
			return;
		}

		@set_time_limit(0);

		$this->_config = $config;

		$this->_sourceDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['db']['host'],
				'port' => $config['db']['port'],
				'username' => $config['db']['username'],
				'password' => $config['db']['password'],
				'dbname' => $config['db']['dbname'],
				'charset' => 'utf8'
			)
		);
	}

	public function retainKeysReset()
	{
		$db = XenForo_Application::getDb();

		$mediaCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_media
		');

		if ($mediaCount)
		{
			throw new XenForo_Exception(new XenForo_Phrase('xengallery_gallery_must_be_empty_before_importing'), true);
		}
	}

	public function getSteps()
	{
		return array(
			'albums' => array(
				'title' => new XenForo_Phrase('xengallery_import_albums')
			),
			'media' => array(
				'title' => new XenForo_Phrase('xengallery_import_media'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xengallery_import_comments'),
				'depends' => array('albums', 'media')
			),
			'galleryfields' => array(
				'title' => new XenForo_Phrase('xengallery_import_gallery_fields'),
				'depends' => array('albums', 'media')
			),
			'contenttags' => array(
				'title' => new XenForo_Phrase('import_content_tags'),
				'depends' => array('albums', 'media')
			),
			'usertags' => array(
				'title' => new XenForo_Phrase('xengallery_import_member_tags'),
				'depends' => array('albums', 'media')
			)
		);
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(album_id)
				FROM sonnb_xengallery_album
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM sonnb_xengallery_album
				WHERE album_id > ?
				ORDER BY album_id ASC
			', $options['limit'])
			, $start);
		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($albums AS $album)
		{
			$next = $album['album_id'];

			$imported = $this->_importAlbum($album, $options);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importAlbum(array $album, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$sDb = $this->_sourceDb;

		$user = $sDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $album['user_id']);
		if (!$user)
		{
			return false;
		}

		list($albumPrivacy, $shareUsers) = $model->getAlbumPrivacyAndShareUsersFromAlbumPrivacyXenGallery($album);

		$lastCommentId = @explode(',', $album['latest_comment_ids']);
		if (is_array($lastCommentId))
		{
			$lastCommentId = end($lastCommentId);
		}
		elseif (is_int($album['latest_comment_ids']))
		{
			$lastCommentId = $album['latest_comment_ids'];
		}

		if ($lastCommentId)
		{
			$lastCommentDate = $model->getLastCommentDateFromContentXenGallery($album['album_id'], 'album');
		}
		else
		{
			$lastCommentDate = 0;
		}

		$xengalleryAlbum = array(
			'album_title' => $album['title'],
			'album_description' => $album['description'],
			'album_create_date' => $album['album_date'],
			'last_update_date' => $album['album_updated_date'],
			'media_cache' => array(),
			'album_state' => $album['album_state'],
			'album_user_id' => $album['user_id'],
			'album_username' => $album['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($album['user_id']),
			'album_likes' => $album['likes'],
			'album_like_users' => unserialize($album['like_users']),
			'album_media_count' => $album['photo_count'] + $album['video_count'],
			'album_view_count' => $album['view_count'],
			'album_comment_count' => $album['comment_count'],
			'album_last_comment_date' => $lastCommentDate
		);

		$importedAlbumId = $model->importAlbum($album['album_id'], $xengalleryAlbum, $albumPrivacy, 'sonnb_xengallery_album', $shareUsers);

		return $importedAlbumId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'path' => XenForo_Helper_File::getExternalDataPath() . '/photos/o',
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(content_id)
				FROM sonnb_xengallery_content
			');
		}

		$media = $sDb->fetchAll($sDb->limit(
			'
				SELECT content.*, contentdata.*,
				video.video_type, video.video_key,
				album.album_privacy
				FROM sonnb_xengallery_content AS content
				INNER JOIN sonnb_xengallery_content_data AS contentdata ON
					(content.content_data_id = contentdata.content_data_id)
				LEFT JOIN sonnb_xengallery_album AS album ON
					(content.album_id = album.album_id)
				LEFT JOIN sonnb_xengallery_video AS video ON
					(content.content_id = video.content_id)
				WHERE content.content_id > ' . $sDb->quote($start) . '
					AND contentdata.unassociated = 0
				ORDER BY content.content_id ASC
			', $options['limit']
		));
		if (!$media)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($media AS $item)
		{
			$next = $item['content_id'];

			$video = false;
			if ($item['content_type'] == 'video')
			{
				$video = true;
			}

			$success = $this->_importMedia($item, $options, $video);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importMedia(array $item, array $options, $video = false)
	{
		$sDb = $this->_sourceDb;

		$user = $sDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $item['user_id']);
		if (!$user)
		{
			return false;
		}

		if (!$video)
		{
			$originalFilePath = sprintf('%s/photos/o/%d/%d-%d-%s.%s',
				XenForo_Helper_File::getExternalDataPath(),
				floor($item['content_data_id'] / 1000),
				$item['content_data_id'],
				$item['upload_date'],
				md5('o'.$item['file_hash']),
				$item['extension']
			);
			if (!file_exists($originalFilePath))
			{
				return false;
			}

			$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
			copy($originalFilePath, $tempFile);
		}
		else
		{
			$tempFile = false;
		}

		$model = $this->_getMediaGalleryImportersModel();

		$albumId = $model->mapAlbumId($item['album_id']);

		list($viewPrivacy, $shareUsers) = $model->getAlbumPrivacyAndShareUsersFromAlbumPrivacyXenGallery($item);

		$lastCommentId = @explode(',', $item['latest_comment_ids']);
		if (is_array($lastCommentId))
		{
			$lastCommentId = end($lastCommentId);
		}
		elseif (is_int($item['latest_comment_ids']))
		{
			$lastCommentId = $item['latest_comment_ids'];
		}

		if ($lastCommentId)
		{
			$lastCommentDate = $model->getLastCommentDateFromContentXenGallery($item['content_id'], $item['content_type']);
		}
		else
		{
			$lastCommentDate = 0;
		}

		$noTitle = new XenForo_Phrase('xengallery_imported_item');
		$xengalleryMedia = array(
			'media_title' => $item['title'] ? $item['title'] : $noTitle->render(),
			'media_description' => $item['description'],
			'media_date' => $item['upload_date'],
			'last_edit_date' => XenForo_Application::$time,
			'last_comment_date' => $lastCommentDate,
			'media_type' => $item['content_type'] == 'photo' ? 'image_upload' : 'video_embed',
			'media_state' => $item['content_state'],
			'album_id' => $albumId,
			'category_id' => 0,
			'media_privacy' => $viewPrivacy,
			'attachment_id' => 0,
			'user_id' => $item['user_id'],
			'username' => $item['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($item['user_id']),
			'likes' => $item['likes'],
			'like_users' => unserialize($item['like_users']),
			'comment_count' => $item['comment_count'],
			'rating_count' => 0,
			'media_view_count' => $item['view_count']
		);

		if ($video)
		{
			$xengalleryMedia['media_tag'] = '[media=' . $item['video_type'] . ']' . $item['video_key'] . '[/media]';
			$xengalleryMedia['media_embed_url'] = 'n/a';

			$xfAttachment = array();
			$xfAttachmentData = array();
		}
		else
		{
			$xfAttachment = array(
				'data_id' => 0,
				'content_type' => 'xengallery_media',
				'content_id' => 0,
				'attach_date' => $item['upload_date'],
				'temp_hash' => '',
				'unassociated' => 0,
				'view_count' => $item['view_count']
			);

			$xfAttachmentData = array(
				'user_id' => $item['user_id'],
				'upload_date' => $item['upload_date'],
				'filename' => sprintf('%d-%d-%s.%s',
					$item['content_data_id'],
					$item['upload_date'],
					md5('o'.$item['file_hash']),
					$item['extension']
				),
				'attach_count' => 1
			);
		}

		$importedMediaId = $model->importMedia($item['content_id'], $tempFile, 'sonnb_xengallery_' . $item['content_type'], $xengalleryMedia, $xfAttachment, $xfAttachmentData);

		@unlink($tempFile);

		return $importedMediaId;
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(comment_id)
				FROM sonnb_xengallery_comment
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM sonnb_xengallery_comment
				WHERE comment_id > ' . $sDb->quote($start) . '
				ORDER BY comment_id
			', $options['limit']
		));
		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($comments AS $comment)
		{
			$next = $comment['comment_id'];

			$success = $this->_importComment($comment, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importComment(array $comment, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$sDb = $this->_sourceDb;

		$user = $sDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $comment['user_id']);
		if (!$user)
		{
			return false;
		}

		$contentType = 'media';
		if ($comment['content_type'] == 'album')
		{
			$contentType = 'album';
			$contentId = $model->mapAlbumId($comment['content_id']);
		}
		else
		{
			$contentId = $model->mapMediaId($comment['content_id']);
		}

		$xengalleryComment = array(
			'content_id' => $contentId,
			'content_type' => $contentType,
			'message' => $comment['message'],
			'user_id' => $comment['user_id'],
			'username' => $comment['username'],
			'comment_date' => $comment['comment_date'],
			'comment_state' => 'visible',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['comment_id'], $xengalleryComment);

		return $importedCommentId;
	}

	public function stepGalleryFields($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$fields = $sDb->fetchAll(
			'
				SELECT *
				FROM sonnb_xengallery_field
				ORDER BY field_id'
		);
		if (!$fields)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($fields AS $field)
		{
			$success = $this->_importField($field, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	protected function _importField(array $field, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$xengalleryField = array(
			'field_id' => $field['field_id'],
			'display_group' => 'extra_tab',
			'display_order' => $field['display_order'],
			'field_type' => $field['field_type'],
			'match_type' => $field['match_type'],
			'match_regex' => $field['match_regex'],
			'max_length' => $field['max_length'],
			'album_use' => 1
		);

		$fieldChoices = @unserialize($field['field_choices']);
		if (!is_array($fieldChoices))
		{
			$fieldChoices = array();
		}

		$sDb = $this->_sourceDb;

		$categoryIds = $sDb->fetchCol('
			SELECT category_id
			FROM xengallery_category
		');

		$fieldValues = $sDb->fetchAll('
			SELECT *
			FROM sonnb_xengallery_field_value
			WHERE field_id = ?
				AND content_type <> \'album\'
		', $field['field_id']);

		$valuesGrouped = array();
		foreach ($fieldValues AS $value)
		{
			$mediaId = $model->mapMediaId($value['content_id']);
			$valuesGrouped[$mediaId][] = $value['field_value'];
		}

		return $model->importField($xengalleryField, $fieldChoices, $valuesGrouped, $field['title'], $field['description'], $categoryIds);
	}

	public function stepContentTags($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(stream_id)
				FROM sonnb_xengallery_stream
				WHERE content_type <> \'album\'
			');
		}

		$tags = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM sonnb_xengallery_stream
				WHERE stream_id > ' . $sDb->quote($start) . '
					AND content_type <> \'album\'
				ORDER BY stream_id
			', $options['limit']
		));
		if (!$tags)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($tags AS $tag)
		{
			$next = $tag['stream_id'];

			$success = $this->_importTag($tag, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importTag(array $tag, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$tagMap = $this->_sourceDb->fetchAll('
			SELECT *
			FROM sonnb_xengallery_stream
			WHERE stream_name = ?
				AND content_type <> \'album\'
		', $tag['stream_name']);

		foreach ($tagMap AS $contentTag)
		{
			$mediaId = $model->mapMediaId($contentTag['content_id']);
			$media = $mediaModel->getMediaById($mediaId);

			if (!$media)
			{
				continue;
			}

			$model->importTag($contentTag['stream_name'], 'xengallery_media', $mediaId, array(
				'add_user_id' => $contentTag['user_id'],
				'add_date' => $contentTag['stream_date'],
				'visible' => ($media['media_state'] == 'visible'),
				'content_date' => $media['media_date']
			));
		}

		return true;
	}

	public function stepUserTags($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(tag_id)
				FROM sonnb_xengallery_tag
				WHERE content_type = \'photo\'
			');
		}

		$userTags = $sDb->fetchAll($sDb->limit(
			'
				SELECT tag.*, content.*, contentdata.*
				FROM sonnb_xengallery_tag AS tag
				INNER JOIN sonnb_xengallery_content AS content ON
					(content.content_id = tag.content_id)
				INNER JOIN sonnb_xengallery_content_data AS contentdata ON
					(content.content_data_id = contentdata.content_data_id)
				WHERE tag.tag_id > ' . $sDb->quote($start) . '
					AND tag.content_type = \'photo\'
					AND tag.tag_state = \'accepted\'
				ORDER BY tag.tag_id
			', $options['limit']
		));
		if (!$userTags)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($userTags AS $userTag)
		{
			$next = $userTag['tag_id'];

			$success = $this->_importUserTag($userTag, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importUserTag(array $tag, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$sDb = $this->_sourceDb;

		$user = $sDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $tag['user_id']);
		if (!$user)
		{
			return false;
		}

		$xengalleryUserTag = array(
			'tag_id' => $tag['tag_id'],
			'media_id' => $model->mapMediaId($tag['content_id']),
			'user_id' => $tag['user_id'],
			'username' => $tag['username'],
			'tag_date' => $tag['tag_date']
		);

		$wRatio = $tag['width'] / $tag['large_width'];
		$hRatio = $tag['height'] / $tag['large_height'];

		$tagX = $tag['tag_x'] * $wRatio;
		$tagY = $tag['tag_y'] * $hRatio;

		$tagData = array(
			'tag_x1' => intval($tagX),
			'tag_y1' => intval($tagY),
			'tag_x2' => 0,
			'tag_y2' => 0,
			'tag_width' => intval($wRatio * 100),
			'tag_height' => intval($hRatio * 100),
			'tag_multiplier' => 1
		);

		$xengalleryUserTag['tag_data'] = @serialize($tagData);

		$importedTagId = $model->importUserTag($xengalleryUserTag);

		return $importedTagId;
	}

	/**
	 * @return XenGallery_Model_Importers
	 */
	protected function _getMediaGalleryImportersModel()
	{
		$retainKeys = false;
		if (!empty($this->_config['retain_keys']))
		{
			$retainKeys = true;
		}

		/* @var $model XenGallery_Model_Importers */
		$model = XenForo_Model::create('XenGallery_Model_Importers');

		$model->retainKeys($retainKeys);

		return $model;
	}
}