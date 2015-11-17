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
class sonnb_XenGallery_Importer_PhotopostVbGallery3 extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'PhotoPost vBGallery 3.x (vB 4.x) => sonnb - XenGallery';
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
					SELECT varname, value
					FROM ' . $this->_prefix . 'ppgal_setting
					WHERE varname=\'gallery_filedirectory\'
				');

				if (!isset($settings['gallery_filedirectory']))
				{
					return $controller->responseError('PhotoPost vBGalery was not found in provided database.');
				}

				$config['data_path'] = !empty($settings['gallery_filedirectory']) ? $settings['gallery_filedirectory'] : '';

				return $controller->responseView(
					'XenForo_ViewAdmin_Import_Ppvb_Config',
					'sonnb_xengallery_import_ppvb_config',
					array(
						'config' => $config,
						'retainKeys' => $config['retainKeys']
					));
			}

			return true;
		}
		else
		{
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
					)
				));
			}

			return $controller->responseView(
				'XenForo_ViewAdmin_Import_Ppvb_Config',
				'sonnb_xengallery_import_ppvb_config',
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

		if (!empty($config['confirm']))
		{
			if (!empty($config['data_path']))
			{
				if (!file_exists($config['data_path']))
				{
					$errors[] = new XenForo_Phrase('sonnb_xengallery_error_could_not_find_ppvb_data_directory_at_specified_path');
				}
			}
			else
			{
				$errors[] = new XenForo_Phrase('sonnb_xengallery_error_could_not_find_ppvb_data_directory_at_specified_path');
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
                FROM '.$prefix.'ppgal_categories
                WHERE catuserid = 0
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Categories ...");
		}

		$categories = $sDb->fetchAll($sDb->limit('
                SELECT *
                FROM '.$prefix.'ppgal_categories
                WHERE catid > ' . $sDb->quote($start) . ' AND catuserid = 0
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
					'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['parent'], 0),
					'title' => $this->_convertToUtf8($category['title'], true),
					'description' => $this->_convertToUtf8($category['description'], true),
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
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(catid) AS max, COUNT(catid) AS rows
                FROM '.$prefix.'ppgal_categories
                WHERE hasimages = 1
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT album.*, user.username
                FROM '.$prefix.'ppgal_categories AS album
                LEFT JOIN ' . $prefix . 'user AS user ON (album.catuserid = user.userid)
                WHERE album.catid > ' . $sDb->quote($start) . ' AND album.hasimages = 1
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
		$categoryIdMap = $model->getCategoryIdsMapFromArray($albums, 'catid');
		$userIdMap = $model->getUserIdsMapFromArray($albums, 'catuserid');
		$visitor = XenForo_Visitor::getInstance();

		$categoryOptions = array(
			'open_for_images'        => 1,
			'open_for_posts'         => 2,
			'allow_post_html'        => 4,
			'allow_post_bbcode'      => 8,
			'allow_post_smilies'     => 16,
			'allow_post_icons'       => 32,
			'allow_post_bbimgcode'   => 64,
			'allow_post_icons'       => 128,
			'allow_custom_html'      => 256,
			'allow_custom_bbcode'    => 512,
			'allow_custom_smilies'   => 1024,
			'allow_custom_bbimgcode' => 2048,
			'allow_desc_html'        => 4096,
			'allow_desc_bbcode'      => 8192,
			'allow_desc_smilies'     => 16384,
			'allow_desc_icons'       => 32768,
			'allow_desc_bbimgcode'   => 65536,
			'canhavepassword'        => 131072,
			'childpass'              => 262144,
			'allow_ratings'          => 524288,
			'allow_replies'          => 1048576,
			'allow_desc_links'       => 2097152,
			'allow_custom_links'     => 4194304
		);

		$userCategoryOptions = array(
			'allow_view'   => 1,
			'allow_upload' => 2,
			'allow_reply'  => 4,
			'allow_rating' => 8
		);

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($albums AS $album)
		{
			$next = $album['catid'];

			if ($album['catuserid'] && !isset($userIdMap[$album['catuserid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$categoryId = $this->_mapLookUp($categoryIdMap, $album['catid'], 0);

				$import = array(
					'title' => $this->_convertToUtf8($album['title'], true),
					'description' => $parser->render($this->_convertToUtf8($album['description'], true)),
					'category_id' => $categoryId,
					'album_date' => $album['lastimagedateline'] ? $album['lastimagedateline'] : XenForo_Application::$time,
					'album_updated_date' => $album['lastimagedateline'] ? $album['lastimagedateline'] : XenForo_Application::$time,
					'album_state' => 'visible',
					'cover_content_id' => 0,
					'album_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'none',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'none',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					),
					'photo_count' => 0,
					'comment_count' => 0
				);

				if ($album['password'])
				{
					$import['album_privacy']['allow_view'] = 'none';
				}

				if ($album['options'] & $categoryOptions['open_for_images'])
				{
					$import['album_privacy']['allow_add_photo'] = 'everyone';
				}

				if ($album['options'] & $categoryOptions['open_for_posts'])
				{
					$import['album_privacy']['allow_comment'] = 'everyone';
				}

				if ($album['catuserid'])
				{
					$userId = $userIdMap[$album['catuserid']];
					$username = $this->_convertToUtf8($album['username'], true);
					$username = $this->_mbTrim($username, 50, $userId);

					$import['user_id'] = $userId;
					$import['username'] = $username;

					if ($album['useroptions'] ^ $userCategoryOptions['allow_view'])
					{
						$import['album_privacy']['allow_view'] = 'none';
					}

					if ($album['useroptions'] ^ $userCategoryOptions['allow_upload'])
					{
						$import['album_privacy']['allow_download'] = 'none';
					}

					if ($album['useroptions'] ^ $userCategoryOptions['allow_reply'])
					{
						$import['album_privacy']['allow_comment'] = 'none';
					}
				}
				else
				{
					$import['user_id'] = $visitor['user_id'];
					$import['username'] = $visitor['username'];
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['catid'];
				}

				$albumId = $model->importXenGalleryAlbum($album['catid'], $import);

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
    				SELECT MAX(imageid) AS max, COUNT(imageid) AS rows
    				FROM '. $prefix .'ppgal_images
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$images = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefix . "ppgal_images
    				WHERE imageid > " . $sDb->quote($start) . "
    				ORDER BY imageid ASC
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
		$userIdMap = $model->getUserIdsMapFromArray($images, 'userid');
		$albumIdMap = $model->getAlbumIdsMapFromArray($images, 'catid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($images AS $image)
		{
			$next = $image['imageid'];

			if (!isset($userIdMap[$image['userid']]))
			{
				continue;
			}

			if (!isset($albumIdMap[$image['catid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$image['userid']];
				$username = $this->_convertToUtf8($image['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$albumId = $albumIdMap[$image['catid']];

				$filename = $image['originalname'] ? $image['originalname'] : $image['filename'];

				$file = $options['path'] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, preg_split('//', $image['userid'], -1, PREG_SPLIT_NO_EMPTY)). DIRECTORY_SEPARATOR . $filename;
				if (!file_exists($file))
				{
					continue;
				}

				if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[XenForo_Helper_File::getFileExtension($filename)]))
				{
					continue;
				}

				$importPhotoData = array(
					'file_size' => $image['originalfilesize'] ? $image['originalfilesize'] : $image['filesize'],
					'width' => $image['originalwidth'] ? $image['originalwidth'] : $image['originalwidth'],
					'height' => $image['originalheight'] ? $image['originalheight'] : $image['width'],
					'file_hash' => md5_file($file),
					'upload_date' => $image['dateline'],
					'unassociated' => 1,
					'extension' => XenForo_Helper_File::getFileExtension($filename)
				);

				$photoData = $model->importXenGalleryPhotoData($image['imageid'], $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $image['imageid'], $photoData['content_data_id']);
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
					'user_id' => $userId,
					'username' => $username,
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'view_count' => $image['views'],
					'comment_count' => 0,
					'position' => $position,
					'content_state' => $image['valid'] ? 'visible' : 'moderated',
					'content_date' => $image['dateline'],
					'content_updated_date' => $image['dateline']
				);

				if (!$image['open'])
				{
					$import['content_privacy']['allow_comment'] = 'none';
				}

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $image['imageid'];
				}

				$photoId = $model->importXenGalleryPhoto($image['imageid'], $import);
				$model->logImportData('sonnb_xengallery_photo', $image['imageid'], $photoId);

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
    				SELECT MAX(postid) AS max, COUNT(postid) AS rows
    				FROM ".$prefix."ppgal_posts
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM ".$prefix."ppgal_posts
    				WHERE postid > " . $sDb->quote($start) . "
    				ORDER BY postid ASC
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
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'imageid');

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($comments AS $comment)
		{
			$next = $comment['postid'];

			if (!isset($userIdMap[$comment['userid']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['imageid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['userid']];
				$username = $this->_convertToUtf8($comment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['imageid']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $parser->render($this->_convertToUtf8($comment['pagetext'], true)),
					'comment_state' => $comment['visible'] ? 'visible' : 'moderated',
					'comment_date' => $comment['dateline']
				);

				$commentId = $model->importXenGalleryComment($comment['postid'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['postid'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$filename = $attachment['originalname'] ? $attachment['originalname'] : $attachment['filename'];
		$file = $options['path'] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, preg_split('//', $attachment['userid'], -1, PREG_SPLIT_NO_EMPTY)). DIRECTORY_SEPARATOR . $filename;
		if (!is_file($file) || !file_exists($file))
		{
			return false;
		}

		return $this->_createPhotoFiles($file, $data);
	}
}