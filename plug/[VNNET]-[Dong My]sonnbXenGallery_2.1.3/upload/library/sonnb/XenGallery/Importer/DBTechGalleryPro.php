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
class sonnb_XenGallery_Importer_DBTechGalleryPro extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return '[DBTech] Gallery PRO 1.2.8 (vB 4.x) => sonnb - XenGallery';
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

			if (isset($config['attachmentPath']))
			{
				return true;
			}

			$this->_bootstrap($config);

			$settings = $this->_sourceDb->fetchPairs('
					SELECT varname, value
					FROM ' . $this->_prefix . 'setting
					WHERE varname=\'dbtech_gallery_filepath_text\'
				');

			if (!isset($settings['dbtech_gallery_filepath_text']))
			{
				return $controller->responseError('[DBTech] Gallery Pro was not found in selected database. Please try again.');
			}

			if (($settings['dbtech_gallery_filepath_text']))
			{
				return $controller->responseView(
					'XenForo_ViewAdmin_Import_vBulletin_Config',
					'sonnb_xengallery_import_vbulletion_config',
					array(
						'config' => $config,
						'attachmentPath' => ($settings['dbtech_gallery_filepath_text'] ? $settings['dbtech_gallery_filepath_text'] : ''),
						'retainKeys' => $config['retainKeys'],
				));
			}

			return true;
		}

		$configPath = getcwd() . '/includes/config.php';
		if (file_exists($configPath) && is_readable($configPath))
		{
			$config = array();
			include($configPath);

			$viewParams = array('input' => $config);
		}
		else
		{
			$viewParams = array('input' => array
			(
				'MasterServer' => array
				(
					'servername' => 'localhost',
					'port' => 3306,
					'username' => '',
					'password' => '',
				),
				'Database' => array
				(
					'dbname' => '',
					'tableprefix' => ''
				),
				'Mysqli' => array
				(
					'charset' => ''
				),
			));
		}

		return $controller->responseView(
			'XenForo_ViewAdmin_Import_vBulletin_Config',
			'sonnb_xengallery_import_vbulletion_config',
			$viewParams
		);
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
					$errors[] = new XenForo_Phrase('sonnb_xengallery_import_log_table_invalid');
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
				SELECT userid
				FROM ' . $config['db']['prefix'] . 'user
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

		if (!empty($config['attachmentPath']))
		{
			if (!file_exists($config['attachmentPath']) || !is_dir($config['attachmentPath']))
			{
				$errors[] = new XenForo_Phrase('attachments_directory_not_found');
			}
		}

		if (!$errors)
		{
			$defaultLanguageId = $db->fetchOne('
				SELECT value
				FROM ' . $config['db']['prefix'] . 'setting
				WHERE varname = \'languageid\'
			');
			$defaultCharset = $db->fetchOne('
				SELECT charset
				FROM ' . $config['db']['prefix'] . 'language
				WHERE languageid = ?
			', $defaultLanguageId);

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
				'dbname' => $config['db']['dbname'],
				'charset' => $config['db']['charset']
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
                SELECT MAX(catid) AS max, COUNT(catid) AS rows
                FROM ' . $prefix . 'dbtech_gallery_categories
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM ' . $prefix . 'dbtech_gallery_categories
                WHERE catid > ' . $sDb->quote($start) . '
                ORDER BY catid ASC
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
			$next = $category['catid'];

			if ($last <> $next)
			{
				$last = $next;

				$categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'catid');
				$import = array(
					'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['parentid'], 0),
					'title' => $this->_convertToUtf8($category['catname'], true),
					'description' => $this->_convertToUtf8($category['catdesc'], true),
					'display_order' => $category['sort_order'],
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
					$import['category_id'] = $category['catid'];
				}

				$categoryId = $model->importXenGalleryCategory($category['catid'], $import);
				$model->logImportData('sonnb_xengallery_category', $category['catid'], $categoryId);
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
                SELECT MAX(albumid) AS max, COUNT(albumid) AS rows
                FROM ' . $prefix . 'dbtech_gallery_albums'
			);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT album.*, user.username
                FROM ' . $prefix . 'dbtech_gallery_albums AS album
                LEFT JOIN ' . $prefix . 'user AS user ON (album.userid = user.userid)
                WHERE album.albumid > ' . $sDb->quote($start) . '
                ORDER BY album.albumid ASC
            ', $options['limit']
		));

		if ($start == 0)
		{
			$albumCats = $sDb->fetchAll('
	                SELECT cat.*, image.catid
	                FROM ' . $prefix . 'dbtech_gallery_categories AS cat
	                LEFT JOIN dbtech_gallery_images AS image
	                ON (cat.catid = image.catid)
	                WHERE image.catid > 0 and image.albumid = 0
	                GROUP BY image.catid
	            ');

			if ($albumCats)
			{
				$visitor = XenForo_Visitor::getInstance();
				$categoryIdMap = $model->getCategoryIdsMapFromArray($albumCats, 'catid');

				foreach ($albumCats as $albumCat)
				{
					$importAlbumCat = array(
						'title' => $this->_convertToUtf8($albumCat['catname'], true),
						'description' => $this->_convertToUtf8($albumCat['catdesc'], true),
						'user_id' => $visitor['user_id'],
						'username' => $visitor['username'],
						'album_date' => XenForo_Application::$time,
						'album_updated_date' => XenForo_Application::$time,
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
						'category_id' => isset($categoryIdMap[$albumCat['parentid']]) ? $categoryIdMap[$albumCat['parentid']] : 0
					);

					$albumCatId = $model->importXenGalleryAlbum(0, $importAlbumCat);

					$model->logImportData('sxg_albumcat', $albumCat['catid'], $albumCatId);
				}
			}
		}

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$userIdMap = $model->getUserIdsMapFromArray($albums, 'userid');

		foreach ($albums AS $album)
		{
			$next = $album['albumid'];

			if (!isset($userIdMap[$album['userid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$album['userid']];
				$username = $this->_convertToUtf8($album['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);

				$import = array(
					'title' => $this->_convertToUtf8($album['title'], true),
					'description' => $this->_convertToUtf8($album['description'], true),
					'user_id' => $userId,
					'username' => $username,
					'album_date' => XenForo_Application::$time,
					'album_updated_date' => XenForo_Application::$time,
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
					'photo_count' => 0,
					'category_id' => 0
				);

				if ($album['private'] || $album['password'] || $album['hidden'])
				{
					$import['album_privacy'] = array(
						'allow_view' => 'none',
						'allow_view_data' => array(),
						'allow_comment' => 'none',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'none',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					);
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['albumid'];
				}

				$albumId = $model->importXenGalleryAlbum(0, $import);

				$model->logImportData('sonnb_xengallery_album', $album['albumid'], $albumId);
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
			'path' => isset($this->_config['attachmentPath']) ? trim($this->_config['attachmentPath']) : '',
			'limit' => 10,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(imageid) AS max, COUNT(imageid) AS rows
    				FROM " . $prefix . "dbtech_gallery_images
    				WHERE deleted = 0 AND filetype IN (".$sDb->quote(array_keys(sonnb_XenGallery_Model_ContentData::$typeMap)).")
    		");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$attachments = $sDb->fetchAll($sDb->limit("
    				SELECT image.*, user.username
    				FROM " . $prefix . "dbtech_gallery_images AS image
    				LEFT JOIN " . $prefix . "user AS user ON (image.userid = user.userid)
    				WHERE image.imageid > " . $sDb->quote($start) . "
    				AND image.deleted = 0 AND image.filetype IN (".$sDb->quote(array_keys(sonnb_XenGallery_Model_ContentData::$typeMap)).")
    				ORDER BY image.imageid ASC
    			", $options['limit']
		));

		if (!$attachments)
		{
			return true;
		}
		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'userid');
		$albumIdMap = $model->getAlbumIdsMapFromArray($attachments, 'albumid');
		$albumCatIdMap = $this->_getAlbumCatIdsMapFromArray($attachments, 'catid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['imageid'];

			if (!isset($userIdMap[$attachment['userid']]))
			{
				continue;
			}

			if (empty($attachment['albumid']) && empty($attachment['catid']))
			{
				continue;
			}

			if (!isset($albumIdMap[$attachment['albumid']])
					&& (empty($attachment['albumid']) && !isset($albumCatIdMap[$attachment['catid']])))
			{
				continue;
			}

			if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$attachment['filetype']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$attachment['userid']];
				$username = $this->_convertToUtf8($attachment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);

				if ($attachment['albumid'])
				{
					$albumId = $albumIdMap[$attachment['albumid']];
				}
				else
				{
					$albumId = $albumCatIdMap[$attachment['catid']];
				}

				$filePath = $this->_getDbtechFilePath($options, $attachment);
				$fileInfo = @getimagesize($filePath);

				if (!$fileInfo)
				{
					continue;
				}

				$importPhotoData = array(
					'file_size' => $attachment['filesize'] ? $attachment['filesize'] : filesize($filePath),
					'width' => $fileInfo[0],
					'height' => $fileInfo[1],
					'file_hash' => md5_file($filePath),
					'upload_date' => $attachment['dateline'],
					'unassociated' => 1,
					'extension' => $attachment['filetype']
				);

				$photoData = $model->importXenGalleryPhotoData(0, $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $attachment['imageid'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $attachment, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$import = array(
					'album_id' => $albumId,
					'content_data_id' => $photoData['content_data_id'],
					'title' => $parser->render($this->_convertToUtf8($attachment['title'], true)),
					'description' => $parser->render($this->_convertToUtf8($attachment['text'], true)),
					'user_id' => $userId,
					'username' => $username,
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'view_count' => $attachment['views'],
					'position' => $position,
					'content_state' => 'visible',
					'content_date' => $attachment['dateline'],
					'content_updated_date' => $attachment['dateline']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $attachment['imageid'];
				}

				$photoId = $model->importXenGalleryPhoto(0, $import);
				$model->logImportData('sonnb_xengallery_photo', $attachment['imageid'], $photoId);

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
    				SELECT MAX(commentid) AS max, COUNT(commentid) AS rows
    				FROM " . $prefix . "dbtech_gallery_comments
    				WHERE type = 'image'
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT comment.*, user.username
    				FROM " . $prefix . "dbtech_gallery_comments AS comment
    				LEFT JOIN " . $prefix . "user AS user ON (comment.userid = user.userid)
    				WHERE comment.commentid > " . $sDb->quote($start) . " AND comment.type = 'image'
    				ORDER BY comment.commentid ASC
    			", $options['limit']
		), $start);

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$userIdMap = $model->getUserIdsMapFromArray($comments, 'userid');
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'associd');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($comments AS $comment)
		{
			$next = $comment['commentid'];

			if (!isset($userIdMap[$comment['userid']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['associd']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['userid']];
				$username = $this->_convertToUtf8($comment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['associd']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $parser->render($this->_convertToUtf8($comment['text'], true)),
					'comment_state' => $comment['approved'] ? 'visible' : 'moderated',
					'comment_date' => $comment['date']
				);

				$commentId = $model->importXenGalleryComment(0, $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['commentid'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$attachFileOrig = $this->_getDbtechFilePath($options, $attachment);

		if (!file_exists($attachFileOrig))
		{
			return false;
		}

		return $this->_createPhotoFiles($attachFileOrig, $data);
	}

	protected function _getDbtechFilePath($options, $image)
	{
		$filePath = false;

		if ($image)
		{
			$filePath = $options['path'].$image['instanceid'];
			$path = preg_split('//', $image['userid'],  -1, PREG_SPLIT_NO_EMPTY);
			for ($m = 0; $m < count($path); $m++)
			{
				$filePath = $filePath . '/' . $path[$m];
			}
			$filePath .= '/full/'.$image['imageid'].'_'.$image['dateline'] . '.' . $image['filetype'];
		}

		return $filePath;
	}

	protected function _getAlbumCatIdsMapFromArray(array $source, $key)
	{
		$albumIds = array();
		foreach ($source AS $data)
		{
			if (is_array($key))
			{
				foreach ($key AS $_key)
				{
					$albumIds[] = $data[$_key];
				}
			}
			else
			{
				$albumIds[] = $data[$key];
			}
		}

		return $this->_importModel->getXenGalleryImportContentMap('sxg_albumcat', $albumIds);
	}
}