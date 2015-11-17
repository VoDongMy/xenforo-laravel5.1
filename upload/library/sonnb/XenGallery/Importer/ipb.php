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
class sonnb_XenGallery_Importer_ipb extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'IPB.Gallery 5.0.2+ => sonnb - XenGallery';
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
				'sql_tbl_prefix' => '',

				'ipboard_path' => $_SERVER['DOCUMENT_ROOT']
			));

			$configPath = getcwd() . '/conf_global.php';
			if (file_exists($configPath))
			{
				include($configPath);

				$viewParams['input'] = array_merge($viewParams['input'], $INFO);
			}

			return $controller->responseView(
				'XenForo_ViewAdmin_Import_IPBoard_Config',
				'sonnb_xengallery_import_ipboard_config',
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
				SELECT member_id
				FROM ' . $config['db']['prefix'] . 'members
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

		if (!empty($config['ipboard_path']))
		{
			if (!file_exists($config['ipboard_path']) || !is_dir($config['ipboard_path'] . '/uploads'))
			{
				$errors[] = new XenForo_Phrase('error_could_not_find_uploads_directory_at_specified_path');
			}
		}

		if (!$errors)
		{
			$defaultCharset = $db->fetchOne("
				SELECT IF(conf_value = '' OR conf_value IS NULL, conf_default, conf_value)
				FROM {$config['db']['prefix']}core_sys_conf_settings
				WHERE conf_key = 'gb_char_set'
			");
			if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
			{
				$config['charset'] = 'windows-1252';
			}
			else
			{
				$config['charset'] = strtolower($defaultCharset);
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
				'dbname' => $config['db']['dbname']
			)
		);

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
		}

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
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(category_id) AS max, COUNT(category_id) AS rows
                FROM ' . $prefix . 'gallery_categories
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM ' . $prefix . 'gallery_categories
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

				$categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'category_id');
				$import = array(
					'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['category_parent_id'], 0),
					'title' => $this->_convertToUtf8($category['category_name'], true),
					'description' => $this->_convertToUtf8($category['category_description'], true),
					'display_order' => $category['category_position'],
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
                SELECT MAX(album_id) AS max, COUNT(album_id) AS rows
                FROM ' . $prefix . 'gallery_albums
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT album.*, user.member_id, user.name
                FROM ' . $prefix . 'gallery_albums AS album
                LEFT JOIN ' . $prefix . 'members AS user ON (album.album_owner_id = user.member_id)
                WHERE album_id > ' . $sDb->quote($start) . '
                ORDER BY album_id ASC
            ', $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$categoryIdMap = $model->getCategoryIdsMapFromArray($albums, 'album_category_id');
		$userIdMap = $model->getUserIdsMapFromArray($albums, 'album_owner_id');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($albums AS $album)
		{
			$next = $album['album_id'];

			if (!isset($userIdMap[$album['album_owner_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$album['album_owner_id']];
				$username = $this->_convertToUtf8($album['name'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$categoryId = $this->_mapLookUp($categoryIdMap, $album['album_category_id'], 0);

				$import = array(
					'title' => $this->_convertToUtf8($album['album_name'], true),
					'description' => $parser->render($this->_convertToUtf8($album['album_description'], true)),
					'user_id' => $userId,
					'username' => $username,
					'category_id' => $categoryId,
					'album_date' => $album['album_last_img_date'],
					'album_updated_date' => $album['album_last_img_date'],
					'album_state' => 'visible',
					'cover_content_id' => 0,
					'album_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'everyone',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					),
					'photo_count' => $album['album_count_imgs'],
					'comment_count' => $album['album_count_comments']
				);

				if (!$album['album_allow_comments'])
				{
					$import['album_privacy']['allow_comment'] = 'none';
				}

				switch ($album['album_type'])
				{
					case 2:
						$import['album_privacy']['allow_view'] = 'none';
						break;
					case 3:
						$import['album_privacy']['allow_view'] = 'member';
						break;
				}

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
			'path' => isset($this->_config['ipboard_path']) ? trim($this->_config['ipboard_path']) : '',
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
    				SELECT MAX(image_id) AS max, COUNT(image_id) AS rows
    				FROM ' . $prefix . 'gallery_images
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$images = $sDb->fetchAll($sDb->limit("
    				SELECT image.*, user.name
    				FROM " . $prefix . "gallery_images AS image
    				LEFT JOIN " . $prefix . "members AS user ON (image.image_member_id = user.member_id)
    				WHERE image.image_id > " . $sDb->quote($start) . "
    				ORDER BY image.image_id ASC
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
		$userIdMap = $model->getUserIdsMapFromArray($images, 'image_member_id');
		$albumIdMap = $model->getAlbumIdsMapFromArray($images, 'image_album_id');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($images AS $image)
		{
			$next = $image['image_id'];

			if (!isset($userIdMap[$image['image_member_id']]))
			{
				continue;
			}

			if (!isset($albumIdMap[$image['image_album_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$image['image_member_id']];
				$username = $this->_convertToUtf8($image['name'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$albumId = $albumIdMap[$image['image_album_id']];

				$dir	= $image['image_directory'] ? $image['image_directory'] . "/" : '';
				$large	= $this->_config['ipboard_path'] . '/uploads/' . '/' . $dir . $image['image_masked_file_name'];
				$orig	= $this->_config['ipboard_path'] . '/uploads/' . '/' . $dir . $image['image_original_file_name'];
				if ( $image['image_original_file_name'] && file_exists( $orig ) )
				{
					$file = $orig;
				}
				else
				{
					$file = $large;
				}

				if (!$file)
				{
					continue;
				}

				if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[XenForo_Helper_File::getFileExtension($file)]))
				{
					continue;
				}

				$width = $height = 0;
				$imageData = @unserialize($image['image_data']);
				if ($imageData)
				{
					if ($image['image_original_file_name'])
					{
						$key = 'original';
					}
					else
					{
						$key = 'max';
					}

					$width = $imageData['sizes'][$key][0];
					$height = $imageData['sizes'][$key][1];
				}

				$importPhotoData = array(
					'file_size' => $image['image_file_size'],
					'width' => $width,
					'height' => $height,
					'file_hash' => md5_file($file),
					'upload_date' => $image['image_date'],
					'unassociated' => 1,
					'extension' => XenForo_Helper_File::getFileExtension($file)
				);

				$photoData = $model->importXenGalleryPhotoData($image['image_id'], $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $image['image_id'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $image, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$import = array(
					'album_id' => $albumId,
					'content_data_id' => $photoData['content_data_id'],
					'title' => $parser->render($this->_convertToUtf8($image['image_caption'], true)),
					'description' => $parser->render($this->_convertToUtf8($image['image_description'], true)),
					'user_id' => $userId,
					'username' => $username,
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'view_count' => $image['image_views'],
					'comment_count' => $image['image_comments'],
					'position' => $position,
					'content_state' => $image['image_approved'] ? 'visible' : 'moderated',
					'content_date' => $image['image_date'],
					'content_updated_date' => $image['image_date']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $image['image_id'];
				}

				$photoId = $model->importXenGalleryPhoto($image['image_id'], $import);
				$model->logImportData('sonnb_xengallery_photo', $image['image_id'], $photoId);

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
    				SELECT MAX(comment_id) AS max, COUNT(comment_id) AS rows
    				FROM " . $prefix . "gallery_comments
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT comment.*
    				FROM " . $prefix . "gallery_comments AS comment
    				WHERE comment.comment_id > " . $sDb->quote($start) . "
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
		$userIdMap = $model->getUserIdsMapFromArray($comments, 'comment_author_id');
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'comment_img_id');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($comments AS $comment)
		{
			$next = $comment['comment_id'];

			if (!isset($userIdMap[$comment['comment_author_id']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['comment_img_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['comment_author_id']];
				$username = $this->_convertToUtf8($comment['comment_author_name'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['comment_img_id']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $parser->render($this->_convertToUtf8($comment['comment_text'], true)),
					'comment_state' => $comment['comment_approved'] ? 'visible' : 'moderated',
					'comment_date' => $comment['comment_post_date']
				);

				$commentId = $model->importXenGalleryComment($comment['comment_id'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['comment_id'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$dir = $attachment['image_directory'] ? $attachment['image_directory'] . "/" : '';
		$large = $this->_config['ipboard_path'] . '/uploads/' . '/' . $dir . $attachment['image_masked_file_name'];
		$orig = $this->_config['ipboard_path'] . '/uploads/' . '/' . $dir . $attachment['image_original_file_name'];

		if ( $attachment['image_original_file_name'] && file_exists( $orig ) )
		{
			$file = $orig;
		}
		else
		{
			$file = $large;
		}

		if (!$file)
		{
			return false;
		}
		if (!is_file($file) || !file_exists($file))
		{
			return false;
		}

		return $this->_createPhotoFiles($file, $data);
	}
}