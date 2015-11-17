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
class sonnb_XenGallery_Importer_PhotopostProXf extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'PhotoPost Pro 8.4 (XF Integration) => sonnb - XenGallery';
	}

	public function getSteps()
	{
		return array(
			'categories' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_categories')
			),
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums'),
				'depends' => array('categories')
			),
			'photos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_photos'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('photos')
			)
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

			if (empty($config['data_path']))
			{
				$settings = $this->_sourceDb->fetchPairs('
					SELECT varname, setting
					FROM ' . $this->_prefix . 'settings
					WHERE varname=\'origfull\'
				');

				if (!isset($settings['origfull']))
				{
					return $controller->responseError('PhotoPost Pro was not found in provided database.');
				}

				$config['data_path'] = !empty($settings['origfull']) ? $settings['origfull'] : '';

				return $controller->responseView(
					'XenForo_ViewAdmin_Import_PPXF_Config',
					'sonnb_xengallery_import_ppxf_config',
					array(
						'config' => $config,
						'retainKeys' => $config['retainKeys']
					));
			}

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
				'sql_tbl_prefix' => 'pp_'
			));

			return $controller->responseView(
				'XenForo_ViewAdmin_Import_PPXF_Config',
				'sonnb_xengallery_import_ppxf_config',
				$viewParams
			);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname']
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
				SELECT userid
				FROM ' . $config['db']['prefix'] . 'users
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

		if (!empty($config['confirm']))
		{
			if (!empty($config['data_path']))
			{
				if (!file_exists($config['data_path']) || !is_dir($config['data_path'] . DIRECTORY_SEPARATOR . 'medium'))
				{
					$errors[] = new XenForo_Phrase('sonnb_xengallery_error_could_not_find_ppp_data_directory_at_specified_path');
				}
			}
			else
			{
				$errors[] = new XenForo_Phrase('sonnb_xengallery_error_could_not_find_ppp_data_directory_at_specified_path');
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
				'charset' => 'utf8'
			)
		);

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);
	}

	public function stepCategories($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(id) AS max, COUNT(id) AS rows
                FROM '.$prefix.'categories
                WHERE cattype = \'c\'
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
	            SELECT *
	            FROM '.$prefix.'categories
	            WHERE id > ' . $sDb->quote($start) . ' AND cattype = \'c\'
	            ORDER BY id ASC
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
			$next = $category['id'];

			if ($last <> $next)
			{
				$last = $next;

				$categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'parent');
				$import = array(
					'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['parent'], 0),
					'title' => $this->_convertToUtf8($category['catname'], true),
					'description' => $this->_convertToUtf8($category['description'], true),
					'display_order' => $category['catorder'],
					'album_count' => 0,
					'category_breadcrumb' => 'a:0:{}',
					'lft' => 0,
					'rgt' => 0,
					'depth' => 0,
					'category_privacy' => serialize(array(
						'post' => array(-1),
						'view' => array(-1)
					))
				);

				if ($this->_config['retainKeys'])
				{
					$import['category_id'] = $category['id'];
				}

				$categoryId = $model->importXenGalleryCategory($category['id'], $import);
				$model->logImportData('sonnb_xengallery_category', $category['id'], $categoryId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Category::$allCacheKey, false);

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
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(id) AS max, COUNT(id) AS rows
                FROM '.$this->_prefix.'categories
                WHERE photos > 0
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT category.*, photo.userid, photo.user, photo.date
                FROM '.$prefix.'categories AS category
                LEFT JOIN ' . $prefix . 'photos AS photo ON (photo.id=category.lastphoto)
                WHERE category.id > ' . $sDb->quote($start) . ' AND category.photos > 0
                ORDER BY category.id ASC
            ', $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$categoryIdMap = $model->getCategoryIdsMapFromArray($albums, 'parent');
		$visitor = XenForo_Visitor::getInstance();

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($albums AS $album)
		{
			$next = $album['id'];

			if ($last <> $next)
			{
				$last = $next;
				$categoryId = $this->_mapLookUp($categoryIdMap, $album['parent'], 0);

				$import = array(
					'title' => $this->_convertToUtf8($album['catname'], true),
					'description' => $parser->render($this->_convertToUtf8($album['description'], true)),
					'category_id' => $categoryId,
					'album_date' => $album['date'] ? $album['date'] : XenForo_Application::$time,
					'album_updated_date' => $album['date'] ? $album['date'] : XenForo_Application::$time,
					'album_state' => 'visible',
					'cover_content_id' => 0,
					'album_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'everyone',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'everyone',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					),
					'photo_count' => 0,
					'comment_count' => 0
				);

				switch ($album['cattype'])
				{
					case 'c':
						$import['user_id'] = $visitor['user_id'];
						$import['username'] = $visitor['username'];
						break;
					case 'a':
						$import['user_id'] = $album['userid'];
						$import['username'] = $album['user'];
						break;
				}

				if ($album['private'] == 'yes')
				{
					$import['album_privacy']['allow_view'] = 'none';
					$import['album_privacy']['allow_comment'] = 'none';
					$import['album_privacy']['allow_add_photo'] = 'none';
					$import['album_privacy']['allow_download'] = 'none';
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['id'];
				}

				$albumId = $model->importXenGalleryAlbum($album['id'], $import);

				$model->logImportData('sonnb_xengallery_album', $album['id'], $albumId);
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
			'limit' => 10,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
    				SELECT MAX(id) AS max, COUNT(id) AS rows
    				FROM '.$prefix.'photos
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$images = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefix . "photos AS image
    				WHERE image.id > " . $sDb->quote($start) . "
    				ORDER BY image.id ASC
    			", $options['limit']
		));

		if (!$images)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$albumIdMap = $model->getAlbumIdsMapFromArray($images, 'cat');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($images AS $image)
		{
			$next = $image['id'];

			if (!isset($albumIdMap[$image['cat']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$albumId = $albumIdMap[$image['cat']];

				$file = $options['path'] . DIRECTORY_SEPARATOR . $image['cat'] . DIRECTORY_SEPARATOR . $image['bigimage'];
				if (!file_exists($file))
				{
					continue;
				}

				if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[XenForo_Helper_File::getFileExtension($image['bigimage'])]))
				{
					continue;
				}

				$importPhotoData = array(
					'file_size' => $image['filesize'],
					'width' => $image['width'],
					'height' => $image['height'],
					'file_hash' => @md5_file($file),
					'upload_date' => $image['date'],
					'unassociated' => 1,
					'extension' => XenForo_Helper_File::getFileExtension($image['bigimage'])
				);

				$photoData = $model->importXenGalleryPhotoData($image['id'], $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $image['id'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $image, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$import = array(
					'album_id' => $albumId,
					'content_data_id' => $photoData['content_data_id'],
					'title' => $parser->render($this->_convertToUtf8($image['title'], true)),
					'description' => $parser->render($this->_convertToUtf8($image['description'], true)),
					'user_id' => $image['userid'],
					'username' => $image['user'],
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => $image['allowcoms'] ? 'everyone' : 'none',
						'allow_comment_data' => array()
					),
					'view_count' => $image['views'],
					'comment_count' => $image['numcom'],
					'position' => $position,
					'content_state' => $image['approved'] ? 'visible' : 'moderated',
					'content_date' => $image['date'],
					'content_updated_date' => $image['date']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $image['id'];
				}

				$photoId = $model->importXenGalleryPhoto($image['id'], $import);
				$model->logImportData('sonnb_xengallery_photo', $image['id'], $photoId);

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
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(id) AS max, COUNT(id) AS rows
    				FROM ".$prefix."comments
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM ".$prefix."comments
    				WHERE id > " . $sDb->quote($start) . "
    				ORDER BY id ASC
    			", $options['limit']
		));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'photo');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($comments AS $comment)
		{
			$next = $comment['id'];

			if (!isset($photoIdMap[$comment['photo']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$photoId = $photoIdMap[$comment['photo']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $comment['userid'],
					'username' => $comment['username'],
					'message' => $parser->render($this->_convertToUtf8($comment['comment'], true)),
					'comment_state' => $comment['approved'] ? 'visible' : 'moderated',
					'comment_date' => $comment['date']
				);

				$commentId = $model->importXenGalleryComment($comment['id'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['id'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$file = $options['path'] . DIRECTORY_SEPARATOR . $attachment['cat'] . DIRECTORY_SEPARATOR . $attachment['bigimage'];
		if (!is_file($file) || !file_exists($file))
		{
			return false;
		}

		return $this->_createPhotoFiles($file, $data);
	}
}