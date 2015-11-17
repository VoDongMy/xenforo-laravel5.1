<?php

class XenGallery_Importer_XenMedio extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_config;

	protected $_defaultTables = array(
		'EWRmedio_categories',
		'EWRmedio_comments',
		'EWRmedio_keywords',
		'EWRmedio_media',
		'EWRmedio_services'
	);

	public static function getName()
	{
		return ' XFMG: Import From [8wayRun.Com] XenMedio';
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
				'addOnName' => str_replace('XFMG: Import From ', '', self::getName())
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

		$categoryCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_category
			WHERE category_id > 1
		');

		if ($mediaCount || $categoryCount)
		{
			throw new XenForo_Exception(new XenForo_Phrase('xengallery_gallery_must_be_empty_before_importing'), true);
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Category', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData(1))
		{
			$dw->delete();
		}
	}

	public function getSteps()
	{
		return array(
			'categories' => array(
				'title' => new XenForo_Phrase('xengallery_import_categories')
			),
			'media' => array(
				'title' => new XenForo_Phrase('xengallery_import_media'),
				'depends' => array('categories')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xengallery_import_comments'),
				'depends' => array('categories', 'media')
			),
			'contenttags' => array(
				'title' => new XenForo_Phrase('import_content_tags'),
				'depends' => array('categories', 'media')
			),
		);
	}

	public function stepCategories($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 9999,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(category_id)
				FROM EWRmedio_categories
			');
		}

		$categories = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM EWRmedio_categories
				WHERE category_id > ?
				ORDER BY category_id
			', $options['limit'])
			, $start);
		if (!$categories)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;

		foreach ($categories AS $category)
		{
			$next = $category['category_id'];

			$imported = $this->_importCategory($category, $options);
			if ($imported)
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$categoryIdMap = $this->_importModel->getImportContentMap('xengallery_category');
		foreach ($categories AS $category)
		{
			if ($category['category_parent'] == 0)
			{
				continue;
			}

			$newCategoryId = $categoryIdMap[$category['category_id']];
			$newParentCategoryId = $categoryIdMap[$category['category_parent']];

			$sDb->update('xengallery_category', array('parent_category_id' => $newParentCategoryId), 'category_id = ' . $sDb->quote($newCategoryId));
		}

		XenForo_Model::create('XenGallery_Model_Category')->rebuildCategoryStructure();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importCategory(array $category, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$mediaCount = $model->getMediaCountFromCategoryIdXenMedio($category['category_id']);

		$uploadUserGroups = unserialize('a:1:{i:0;i:-1;}');
		if ($category['category_disabled'])
		{
			$uploadUserGroups = array();
		}

		$xengalleryCategory = array(
			'category_title' => $category['category_name'],
			'category_description' => XenForo_Helper_String::bbCodeStrip($category['category_description'], true),
			'upload_user_groups' => $uploadUserGroups,
			'view_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'allowed_types' => unserialize('a:1:{i:0;s:3:"all";}'),
			'parent_category_id' => 0,
			'display_order' => intval($category['category_id']) + 20000,
			'category_breadcrumb' => unserialize('a:0:{}'),
			'depth' => 0,
			'category_media_count' => $mediaCount,
			'field_cache' => unserialize('a:0:{}')
		);

		$importedCategoryId = $model->importCategory($category['category_id'], $xengalleryCategory);

		return $importedCategoryId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(media_id)
				FROM EWRmedio_media
			');
		}

		$media = $sDb->fetchAll($sDb->limit(
			'
				SELECT media.*, category.*, service.*
				FROM EWRmedio_media AS media
				INNER JOIN EWRmedio_categories AS category ON
					(category.category_id = media.category_id)
				INNER JOIN EWRmedio_services AS service ON
					(service.service_id = media.service_id)
				WHERE media.media_id > ' . $sDb->quote($start) . '
				ORDER BY media.media_id ASC
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
			$next = $item['media_id'];

			$user = $sDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $item['user_id']);
			if (!$user)
			{
				return false;
			}

			$success = $this->_importMedia($item, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importMedia(array $item, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$categoryId = $model->mapCategoryId($item['category_id']);

		$mediaUrl = str_replace('{serviceVAL}', $item['service_value'], $item['service_url']);
		if (strstr($item['service_url'], 'metacafe'))
		{
			//The default XenForo BB Code Media Site appears to be bugged
			// Ref: http://xenforo.com/community/threads/66019/
			$mediaUrl .= '/';
		}
		$mediaTag = XenForo_Helper_Media::convertMediaLinkToEmbedHtml($mediaUrl);

		if (!$mediaTag)
		{
			return false;
		}

		$xengalleryMedia = array(
			'media_title' => $this->_convertToUtf8($item['media_title'], true),
			'media_description' => XenForo_Helper_String::bbCodeStrip($item['media_description'], true),
			'media_date' => $item['media_date'],
			'last_edit_date' => XenForo_Application::$time,
			'last_comment_date' => $item['last_comment_date'],
			'media_type' => 'video_embed',
			'media_tag' => $mediaTag,
			'media_embed_url' => $mediaUrl,
			'media_state' => $item['media_state'],
			'album_id' => 0,
			'category_id' => $categoryId,
			'media_privacy' => 'category',
			'attachment_id' => 0,
			'user_id' => $item['user_id'],
			'username' => $item['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($item['user_id']),
			'likes' => $item['media_likes'],
			'like_users' => unserialize($item['media_like_users']),
			'comment_count' => $item['media_comments'],
			'rating_count' => 0,
			'media_view_count' => $item['media_views']
		);

		$importedMediaId = $model->importMedia($item['media_id'], '', 'media', $xengalleryMedia);

		return $importedMediaId;
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 20,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(comment_id)
				FROM EWRmedio_comments
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM EWRmedio_comments
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

			$user = $sDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $item['user_id']);
			if (!$user)
			{
				return false;
			}

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

		$xengalleryComment = array(
			'content_id' => $model->mapMediaId($comment['media_id']),
			'content_type' => 'media',
			'message' => $comment['comment_message'],
			'user_id' => $comment['user_id'],
			'username' => $comment['username'],
			'comment_date' => $comment['comment_date'],
			'comment_state' => $comment['comment_state'],
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['comment_id'], $xengalleryComment);

		return $importedCommentId;
	}

	public function stepContentTags($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(keyword_id)
				FROM EWRmedio_keywords
			');
		}

		$tags = $sDb->fetchAll($sDb->limit(
			'
				SELECT tag.*, map.*
				FROM EWRmedio_keywords AS tag
				INNER JOIN EWRmedio_keylinks AS map ON
					(tag.keyword_id = map.keyword_id)
				WHERE tag.keyword_id >' . $sDb->quote($start) . '
				GROUP BY tag.keyword_text
				ORDER BY tag.keyword_id ASC
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
			$next = $tag['keyword_id'];

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
			SELECT tag.*, map.*
			FROM EWRmedio_keywords AS tag
			INNER JOIN EWRmedio_keylinks AS map ON
				(tag.keyword_id = map.keyword_id)
			WHERE tag.keyword_id = ?
		', $tag['keyword_id']);

		foreach ($tagMap AS $contentTag)
		{
			$mediaId = $model->mapMediaId($contentTag['content_id']);
			$media = $mediaModel->getMediaById($mediaId);

			if (!$media)
			{
				continue;
			}

			$model->importTag($tag['keyword_text'], 'xengallery_media', $mediaId, array(
				'add_user_id' => $contentTag['user_id'],
				'add_date' => $contentTag['keylink_date'],
				'visible' => ($media['media_state'] == 'visible'),
				'content_date' => $media['media_date']
			));
		}

		return true;
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