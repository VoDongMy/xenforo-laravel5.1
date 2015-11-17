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
class sonnb_XenGallery_Importer_XenGallery extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'sonnb - XenGallery => sonnb - XenGallery (XF 1.2)';
	}

	public function getSteps()
	{
		return array(
			'categories' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_categories')
			),
			'collections' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_collections')
			),
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums'),
				'depends' => array('categories', 'collections')
			),
			'photos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_photos'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('albums', 'photos')
			),
			'collectionItems' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_collection_items'),
				'depends' => array('collections', 'photos', 'albums')
			),
			'streams' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_streams'),
				'depends' => array('photos', 'albums')
			),
			'tags' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_tags'),
				'depends' => array('photos')
			),
			'locations' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_locations'),
				'depends' => array('photos', 'albums')
			),
			'watches' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_watches'),
				'depends' => array('photos', 'albums')
			),
		);
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			if ($errors = $this->validateConfiguration($config))
			{
				return $controller->responseError($errors);
			}

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$viewParams = array('input' => array
			(
				'sql_host' => 'localhost',
				'sql_port' => 3306,
				'sql_user' => '',
				'sql_pass' => '',
				'sql_database' => '',

				'data_path' => $_SERVER['DOCUMENT_ROOT'].PATH_SEPARATOR.'data'
			));

			return $controller->responseView(
				'XenForo_ViewAdmin_Import_XenGallery_Config',
				'sonnb_xengallery_import_xengallery_config',
				$viewParams
			);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		if (preg_match('/[^a-z0-9_]/i', $config['importlog']))
		{
			$errors[] = new XenForo_Phrase('error_table_name_illegal');
		}
		else
		{
			try
			{
				$table = $this->_db->describeTable($config['importlog']);

				if (!isset($table['content_type']) || !isset($table['old_id']) || !isset($table['new_id']))
				{
					$errors[] = new XenForo_Phrase('sonnb_xengallery_import_log_table_invalid');
				}
			}
			catch (Zend_Db_Exception $e)
			{
				$errors[] = $e->getMessage();
			}
		}

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf8',
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

		try
		{
			$db->query('
				SELECT user_id
				FROM xf_user
				LIMIT 1
			');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['db']['dbname'] === '')
			{
				$errors[] = new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		if (!empty($config['data_path']))
		{
			if (!file_exists($config['data_path']) || !is_dir($config['data_path'] . PATH_SEPARATOR . 'photos'))
			{
				$errors[] = new XenForo_Phrase('error_could_not_find_uploads_directory_at_specified_path');
			}
		}

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
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
				'charset' => 'utf8',
			)
		);

		define('IMPORT_LOG_TABLE', $this->_config['importlog']);
	}

	public function stepCategories($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(category_id) AS max, COUNT(category_id) AS rows
                FROM sonnb_xengallery_category
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM sonnb_xengallery_category
                WHERE category_id > ' . $sDb->quote($start) . '
                ORDER BY category_id ASC
            ', $options['limit']
		));

		if (!$categories)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		foreach ($categories AS $category)
		{
			$next = $category['category_id'];

			if ($last <> $next)
			{
				$last = $next;

				$import = $category;

				if (!isset($import['category_privacy']))
				{
					$import['category_privacy'] = serialize(array(
						'post' => array(-1),
						'view' => array(-1)
					));
				}

				if ($this->_config['retainKeys'])
				{
					$import['category_id'] = $category['category_id'];
				}

				$categoryId = $model->importXenGalleryCategory($category['category_id'], $import);
				$model->logImportData('sonnb_xengallery_category', $category['category_id'], $categoryId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Category::$allCacheKey, false);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepCollections($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(collection_id) AS max, COUNT(collection_id) AS rows
                FROM sonnb_xengallery_collection
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Collections...");
		}

		$collections = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM sonnb_xengallery_collection
                WHERE collection_id > ' . $sDb->quote($start) . '
                ORDER BY collection_id ASC
            ', $options['limit']
		));

		if (!$collections)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		foreach ($collections AS $collection)
		{
			$next = $collection['collection_id'];

			if ($last <> $next)
			{
				$last = $next;

				$import = $collection;

				if ($this->_config['retainKeys'])
				{
					$import['collection_id'] = $collection['collection_id'];
				}

				$collectionId = $model->importXenGalleryCollection($collection['collection_id'], $import);
				$model->logImportData('sonnb_xengallery_col', $collection['collection_id'], $collectionId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Collection::$allCacheKey, false);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(album_id) AS max, COUNT(album_id) AS rows
                FROM sonnb_xengallery_album
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit("
                SELECT *
                FROM sonnb_xengallery_album AS album
                WHERE album.album_id > " . $sDb->quote($start) . " AND album_state IN ('visible', 'moderated')
                ORDER BY album.album_id ASC
            ", $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$categoryIdMap = $model->getCategoryIdsMapFromArray($albums, 'category_id');
		$collectionIdMap = $model->getCategoryIdsMapFromArray($albums, 'collection_id');
		$userIdMap = $model->getUserIdsMapFromArray($albums, 'user_id');

		foreach ($albums AS $album)
		{
			$next = $album['album_id'];

			if (!isset($userIdMap[$album['user_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$album['user_id']];
				$categoryId = $this->_mapLookUp($categoryIdMap, $album['category_id'], 0);
				$collectionId = $this->_mapLookUp($collectionIdMap, $album['collection_id'], 0);

				$import = $album;
				$import['user_id'] = $userId;
				$import['category_id'] = $categoryId;
				$import['collection_id'] = $collectionId;

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['album_id'];
				}

				$albumId = $model->importXenGalleryAlbum($album['album_id'], $import);

				$model->logImportData('sonnb_xengallery_album', $album['album_id'], $albumId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepPhotos($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['data_path']) ? trim($this->_config['data_path']) : '',
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(content_id) AS max, COUNT(content_id) AS rows
    				FROM sonnb_xengallery_photo
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$photos = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_content AS content
    				LEFT JOIN sonnb_xengallery_content_data AS content_data
    					ON (content.content_data_id = content_data.content_data_id)
    				WHERE content.content_id > " . $sDb->quote($start) . " AND content_state IN ('visible', 'moderated')
    				ORDER BY content.content_id ASC
    			", $options['limit']
		));

		if (!$photos)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$userIdMap = $model->getUserIdsMapFromArray($photos, 'user_id');
		$collectionIdMap = $model->getCategoryIdsMapFromArray($photos, 'collection_id');
		$albumIdMap = $model->getAlbumIdsMapFromArray($photos, 'album_id');

		foreach ($photos AS $photo)
		{
			$next = $photo['content_id'];

			if (!isset($userIdMap[$photo['user_id']]))
			{
				continue;
			}

			if (!isset($albumIdMap[$photo['album_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$photo['user_id']];
				$albumId = $albumIdMap[$photo['album_id']];
				$collectionId = $this->_mapLookUp($collectionIdMap, $photo['collection_id'], 0);

				$file = $this->_getPhotoDataModel()->getContentDataFile($photo, $this->_config['data_path']);

				if (!$file || !file_exists($file))
				{
					continue;
				}

				if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$photo['extension']]))
				{
					continue;
				}

				$importPhotoData = array(
					'file_size' => $photo['file_size'],
					'width' => $photo['width'],
					'height' => $photo['height'],
					'file_hash' => $photo['file_hash'],
					'upload_date' => $photo['upload_date'],
					'temp_hash' => $photo['temp_hash'],
					'unassociated' => 1,
					'extension' => $photo['extension']
				);

				$photoData = $model->importXenGalleryPhotoData($photo['content_data_id'], $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $photo['content_data_id'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $photo, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$import = $photo;
				foreach ($importPhotoData as $key => $value)
				{
					unset($import[$key]);
				}
				unset($import['content_data_id']);
				$import['user_id'] = $userId;
				$import['album_id'] = $albumId;
				$import['collection_id'] = $collectionId;

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $photo['content_id'];
				}

				$photoId = $model->importXenGalleryPhoto($photo['content_id'], $import);
				$model->logImportData('sonnb_xengallery_photo', $photo['content_id'], $photoId);

				$position++;
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(comment_id) AS max, COUNT(comment_id) AS rows
    				FROM sonnb_xengallery_comment
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT comment.*
    				FROM sonnb_xengallery_comment AS comment
    				WHERE comment_id > " . $sDb->quote($start) . " AND comment_state IN ('visible', 'moderated')
    				ORDER BY comment.comment_id ASC
    			", $options['limit']
		));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($comments AS $comment)
		{
			switch ($comment['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $comment['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $comment['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($comments, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);

		foreach ($comments AS $comment)
		{
			$next = $comment['comment_id'];

			if (!isset($userIdMap[$comment['user_id']]))
			{
				continue;
			}

			switch ($comment['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$comment['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$comment['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['user_id']];

				$import = $comment;
				$import['user_id'] = $userId;
				switch ($comment['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_type'] = $albumIdMap[$comment['content_type']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_type'] = $photoIdMap[$comment['content_type']];
						break;
				}

				if ($this->_config['retainKeys'])
				{
					$import['comment_id'] = $comment['comment_id'];
				}

				$commentId = $model->importXenGalleryComment($comment['comment_id'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['comment_id'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepCollectionItems($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(collection_item_id) AS max, COUNT(collection_item_id) AS rows
    				FROM sonnb_xengallery_collection_item
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Collection Items ...");
		}

		$items = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_collection_item
    				WHERE collection_item_id > " . $sDb->quote($start) . "
    				ORDER BY collection_item_id ASC
    			", $options['limit']
		));

		if (!$items)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($items AS $item)
		{
			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $item['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $item['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($items, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);
		$collectionIdMap = $model->getCollectionIdsMapFromArray($items, 'collection_id');

		foreach ($items AS $item)
		{
			$next = $item['collection_item_id'];

			if (!isset($userIdMap[$item['user_id']]) || !isset($collectionIdMap[$item['collection_id']]))
			{
				continue;
			}

			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$item['user_id']];

				$import = $item;
				$import['user_id'] = $userId;
				$import['collection_id'] = $collectionIdMap[$item['collection_id']];

				switch ($item['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_id'] = $albumIdMap[$item['content_id']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_id'] = $photoIdMap[$item['content_id']];
						break;
				}

				$itemId = $model->importXenGalleryCollectionItem($item['collection_item_id'], $import);

				$model->logImportData('sonnb_xengallery_colitem', $item['collection_item_id'], $itemId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));

	}

	public function stepStreams($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(stream_id) AS max, COUNT(stream_id) AS rows
    				FROM sonnb_xengallery_stream
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Streams ...");
		}

		$items = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_stream
    				WHERE stream_id > " . $sDb->quote($start) . "
    				ORDER BY stream_id ASC
    			", $options['limit']
		));

		if (!$items)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($items AS $item)
		{
			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $item['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $item['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($items, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);

		foreach ($items AS $item)
		{
			$next = $item['stream_id'];

			if (!isset($userIdMap[$item['user_id']]))
			{
				continue;
			}

			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$item['user_id']];

				$import = $item;
				$import['user_id'] = $userId;

				switch ($item['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_id'] = $albumIdMap[$item['content_id']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_id'] = $photoIdMap[$item['content_id']];
						break;
				}

				$itemId = $model->importXenGalleryStream($item['stream_id'], $import);

				$model->logImportData('sonnb_xengallery_stream', $item['stream_id'], $itemId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));

	}

	public function stepTags($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(tag_id) AS max, COUNT(tag_id) AS rows
    				FROM sonnb_xengallery_tag
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Tags ...");
		}

		$items = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_tag
    				WHERE tag_id > " . $sDb->quote($start) . "
    				ORDER BY tag_id ASC
    			", $options['limit']
		));

		if (!$items)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($items AS $item)
		{
			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $item['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $item['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($items, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);

		foreach ($items AS $item)
		{
			$next = $item['tag_id'];

			if (!isset($userIdMap[$item['user_id']]))
			{
				continue;
			}

			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$item['user_id']];

				$import = $item;
				$import['user_id'] = $userId;

				switch ($item['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_id'] = $albumIdMap[$item['content_id']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_id'] = $photoIdMap[$item['content_id']];
						break;
				}

				$itemId = $model->importXenGalleryTag($item['tag_id'], $import);

				$model->logImportData('sonnb_xengallery_tag', $item['tag_id'], $itemId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));

	}

	public function stepWatches($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(watch_id) AS max, COUNT(watch_id) AS rows
    				FROM sonnb_xengallery_watch
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Watches...");
		}

		$items = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_watch
    				WHERE watch_id > " . $sDb->quote($start) . "
    				ORDER BY watch_id ASC
    			", $options['limit']
		));

		if (!$items)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($items AS $item)
		{
			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $item['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $item['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($items, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);

		foreach ($items AS $item)
		{
			$next = $item['watch_id'];

			if (!isset($userIdMap[$item['user_id']]))
			{
				continue;
			}

			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$item['user_id']];

				$import = $item;
				$import['user_id'] = $userId;

				switch ($item['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_id'] = $albumIdMap[$item['content_id']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_id'] = $photoIdMap[$item['content_id']];
						break;
				}

				$itemId = $model->importXenGalleryWatch($item['watch_id'], $import);

				$model->logImportData('sonnb_xengallery_watch', $item['watch_id'], $itemId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));

	}

	public function stepLocations($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(location_id) AS max, COUNT(location_id) AS rows
    				FROM sonnb_xengallery_location
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Locations...");
		}

		$items = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM sonnb_xengallery_location
    				WHERE location_id > " . $sDb->quote($start) . "
    				ORDER BY location_id ASC
    			", $options['limit']
		));

		if (!$items)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$photoIds = $albumIds = array();
		foreach ($items AS $item)
		{
			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$albumIds[] = $item['content_id'];
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photoIds[] = $item['content_id'];
					break;
			}
		}

		$userIdMap = $model->getUserIdsMapFromArray($items, 'user_id');
		$photoIdMap = $model->getImportContentMap('sonnb_xengallery_photo', $photoIds);
		$albumIdMap = $model->getImportContentMap('sonnb_xengallery_album', $albumIds);

		foreach ($items AS $item)
		{
			$next = $item['location_id'];

			if (!isset($userIdMap[$item['user_id']]))
			{
				continue;
			}

			switch ($item['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					if (!isset($albumIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					if (!isset($photoIdMap[$item['content_id']]))
					{
						continue;
					}
					break;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$item['user_id']];

				$import = $item;
				$import['user_id'] = $userId;

				switch ($item['content_type'])
				{
					case sonnb_XenGallery_Model_Album::$contentType:
						$import['content_id'] = $albumIdMap[$item['content_id']];
						break;
					case sonnb_XenGallery_Model_Photo::$contentType:
						$import['content_id'] = $photoIdMap[$item['content_id']];
						break;
				}

				$itemId = $model->importXenGalleryLocation($item['location_id'], $import);

				$model->logImportData('sonnb_xengallery_loc', $item['location_id'], $itemId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));

	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$photoDataModel = $this->_getPhotoDataModel();

		$smallThumbFile = $photoDataModel->getContentDataSmallThumbnailFile($data);
		$mediumThumbFile = $photoDataModel->getContentDataMediumThumbnailFile($data);
		$largeThumbFile = $photoDataModel->getContentDataLargeThumbnailFile($data);
		$originalFile = $photoDataModel->getContentDataFile($data);

		if ($smallThumbFile)
		{
			$directory = dirname($smallThumbFile);
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$oldSmallFile = $photoDataModel->getContentDataSmallThumbnailFile($attachment, $options['path']);
				@copy($oldSmallFile, $smallThumbFile);
				XenForo_Helper_File::makeWritableByFtpUser($smallThumbFile);
			}
		}

		if ($mediumThumbFile)
		{
			$directory = dirname($mediumThumbFile);
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$oldMediumFile = $photoDataModel->getContentDataMediumThumbnailFile($attachment, $options['path']);
				@copy($oldMediumFile, $mediumThumbFile);
				XenForo_Helper_File::makeWritableByFtpUser($mediumThumbFile);
			}
		}

		if ($largeThumbFile)
		{
			$directory = dirname($largeThumbFile);
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$oldLargeFile = $photoDataModel->getContentDataLargeThumbnailFile($attachment, $options['path']);
				@copy($oldLargeFile, $largeThumbFile);
				XenForo_Helper_File::makeWritableByFtpUser($largeThumbFile);
			}
		}

		if ($originalFile)
		{
			$directory = dirname($originalFile);
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$oldOriginalFile = $photoDataModel->getContentDataFile($attachment, $options['path']);
				@copy($oldOriginalFile, $originalFile);
				XenForo_Helper_File::makeWritableByFtpUser($originalFile);
			}
		}

		return true;
	}

	/**
	 * @return sonnb_XenGallery_Model_PhotoData
	 */
	protected function _getPhotoDataModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_PhotoData');
	}
}