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
class sonnb_XenGallery_Importer_PhotoPlog extends sonnb_XenGallery_Importer_Abstract
{
	protected $_photoPlogPrefix = null;

	public static function getName()
	{
		return 'PhotoPlog 2.1.4.x (vB 3.6.x+) => sonnb - XenGallery';
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
				');

			if (!isset($settings['photoplog_upload_dir']))
			{
				return $controller->responseError('PhotoPlog was not found in selected database. Please try again.');
			}

			if (($settings['photoplog_upload_dir']) && $settings['photoplog_full_path'])
			{
				return $controller->responseView(
					'XenForo_ViewAdmin_Import_PhotoPlog_Config',
					'sonnb_xengallery_import_photoplog_config',
					array(
						'config' => $config,
						'attachmentPath' => $settings['photoplog_full_path'] . DIRECTORY_SEPARATOR . $settings['photoplog_upload_dir'],
						'retainKeys' => $config['retainKeys']
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
				'photoPlogPrefix' => '',
			));
		}

		return $controller->responseView(
			'XenForo_ViewAdmin_Import_PhotoPlog_Config',
			'sonnb_xengallery_import_photoplog_config',
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
		$config['db']['photoPlogPrefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['photoPlogPrefix']);

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
		$this->_photoPlogPrefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['photoPlogPrefix']);

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
		$prefixPhotoPlog = $this->_photoPlogPrefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(catid) AS max, COUNT(catid) AS rows
                FROM ' . $prefixPhotoPlog . 'photoplog_categories
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM ' . $prefixPhotoPlog . 'photoplog_categories
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

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($categories AS $category)
		{
			$next = $category['catid'];

			if ($last <> $next)
			{
				$last = $next;

				$categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'catid');
				$import = array(
					'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['parentid'], 0),
					'title' => $parser->render($this->_convertToUtf8($category['title'], true)),
					'description' => $parser->render($this->_convertToUtf8($category['description'], true)),
					'display_order' => $category['displayorder'],
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
		$prefixPhotoPlog = $this->_photoPlogPrefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchAll('
                SELECT cat.catid
                FROM ' . $prefixPhotoPlog . 'photoplog_categories AS cat
                LEFT JOIN ' . $prefixPhotoPlog . 'photoplog_catcounts AS count
                    ON (cat.catid = count.catid)
                WHERE count.num_uploads > 0
                GROUP BY count.catid
				ORDER BY count.catid DESC
			');

			$options['max'] = $data[0]['catid'];
			$options['rows'] = count($data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
				SELECT *
                FROM ' . $prefixPhotoPlog . 'photoplog_categories AS album
                LEFT JOIN ' . $prefixPhotoPlog . 'photoplog_catcounts AS count
                    ON (album.catid = count.catid)
                WHERE count.num_uploads > 0
                AND album.catid > ' . $sDb->quote($start) . '
                GROUP BY album.catid
                ORDER BY album.catid ASC
            ', $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$visitor = XenForo_Visitor::getInstance();
		$categoryIdMap = $model->getCategoryIdsMapFromArray($albums, 'parentid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		$photoplogOptions = array(
			'allowhtml' => 1,
			'allowsmilies' => 2,
			'allowbbcode' => 4,
			'allowimgcode' => 8,
			'allowparseurl' => 16,
			'allowcomments' => 32,
			'issearchable' => 64,
			'ismembersfolder' => 128,
			'actasdivider' => 256,
			'allowdeschtml' => 512,
			'openforsubcats' => 1024
		);

		foreach ($albums AS $album)
		{
			$next = $album['catid'];

			if ($last <> $next)
			{
				$last = $next;

				$import = array(
					'title' => $parser->render($this->_convertToUtf8($album['title'], true)),
					'description' => $parser->render($this->_convertToUtf8($album['description'], true)),
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'album_date' => $album['last_upload_dateline'] ? $album['last_upload_dateline'] : XenForo_Application::$time,
					'album_updated_date' => $album['last_upload_dateline'] ? $album['last_upload_dateline'] : XenForo_Application::$time,
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
					'category_id' => isset($categoryIdMap[$album['parentid']]) ? $categoryIdMap[$album['parentid']] : 0,
					'view_count' => $album['num_views']
				);

				if ($album['options'] ^ $photoplogOptions['allowcomments'])
				{
					$import['album_privacy']['allow_comment'] = 'none';
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['catid'];
				}

				$albumId = $model->importXenGalleryAlbum(0, $import);

				$model->logImportData('sonnb_xengallery_album', $album['catid'], $albumId);
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
		$prefixPhotoPlog = $this->_photoPlogPrefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(fileid) AS max, COUNT(fileid) AS rows
    				FROM " . $prefixPhotoPlog . "photoplog_fileuploads
    		");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$attachments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefixPhotoPlog . "photoplog_fileuploads
    				WHERE fileid > " . $sDb->quote($start) . "
    				ORDER BY fileid ASC
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
		$albumIdMap = $model->getAlbumIdsMapFromArray($attachments, 'catid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['fileid'];

			if (!isset($userIdMap[$attachment['userid']]) || !isset($albumIdMap[$attachment['catid']]))
			{
				continue;
			}

			$extension = XenForo_Helper_File::getFileExtension($attachment['filename']);

			if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$extension]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$attachment['userid']];
				$username = $this->_convertToUtf8($attachment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$albumId = $albumIdMap[$attachment['catid']];

				$filePath = $this->_getPhotoPlogFilePath($options, $attachment);
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
					'extension' => $extension
				);

				$photoData = $model->importXenGalleryPhotoData(0, $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $attachment['fileid'], $photoData['content_data_id']);
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
					'description' => $parser->render($this->_convertToUtf8($attachment['description'], true)),
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
					'content_state' => $attachment['moderate'] ? 'moderated' : 'visible',
					'content_date' => $attachment['dateline'],
					'content_updated_date' => $attachment['dateline']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $attachment['fileid'];
				}

				$photoId = $model->importXenGalleryPhoto(0, $import);
				$model->logImportData('sonnb_xengallery_photo', $attachment['fileid'], $photoId);

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
		$prefixPhotoPlog = $this->_photoPlogPrefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(commentid) AS max, COUNT(commentid) AS rows
    				FROM " . $prefixPhotoPlog . "photoplog_ratecomment
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefixPhotoPlog . "photoplog_ratecomment
    				WHERE commentid > " . $sDb->quote($start) . "
    				ORDER BY commentid ASC
    			", $options['limit']
		));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$userIdMap = $model->getUserIdsMapFromArray($comments, 'userid');
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'fileid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($comments AS $comment)
		{
			$next = $comment['commentid'];

			if (!isset($userIdMap[$comment['userid']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['fileid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['userid']];
				$username = $this->_convertToUtf8($comment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['fileid']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $parser->render($this->_convertToUtf8($comment['comment'], true)),
					'comment_state' => $comment['moderate'] ? 'moderated' : 'visible',
					'comment_date' => $comment['dateline']
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
		$attachFileOrig = $this->_getPhotoPlogFilePath($options, $attachment);

		if (!is_file($attachFileOrig) || !file_exists($attachFileOrig))
		{
			return false;
		}

		return $this->_createPhotoFiles($attachFileOrig, $data);
	}

	protected function _getPhotoPlogFilePath($options, $data)
	{
		$filePath = $options['path']. DIRECTORY_SEPARATOR . $data['userid'] . DIRECTORY_SEPARATOR . $data['filename'];

		if (!file_exists($filePath))
		{
			$filePath = $options['path']. DIRECTORY_SEPARATOR . $data['userid'] . DIRECTORY_SEPARATOR . 'large' . DIRECTORY_SEPARATOR . $data['filename'];
		}

		return $filePath;
	}
}