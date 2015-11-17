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
class sonnb_XenGallery_Importer_XenMediaGallery extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'XenMediaGallery => sonnb - XenGallery';
	}

	public function getSteps()
	{
		return array(
            'categories' => array(
                'title' => new XenForo_Phrase('sonnb_XenGallery_import_categories')
            ),
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums')
			),
			'fields' => array(
				'title' => 'Import Fields'
			),
			'contents' => array(
				'title' => 'Import Medias',
				'depends' => array('categories', 'albums', 'fields')
			),
            'albumComments' => array(
                'title' => 'Import Album\'s Comments',
                'depends' => array('albums')
            ),
            'mediaComments' => array(
                'title' => 'Import Media\'s Comments',
                'depends' => array('contents')
            )
		);
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$db = XenForo_Application::getConfig()->get('db');

			$config['db'] = array(
				'host' => $db->host,
				'port' => $db->port,
				'username' => $db->username,
				'password' => $db->password,
				'dbname' => $db->dbname
			);

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$input = array(
				'category_id' => 0
			);

			return $controller->responseView(
				'sonnb_XenGallery_ViewAdmin_Import_XenMediaGallery_Config',
				'sonnb_xengallery_import_xmg_config',
				array(
					'input' => $input,
					'categories' => $this->_getCategoryModel()->getAllCachedCategories()
				)
			);
		}
	}

	protected function _bootstrap(array $config)
	{
		@set_time_limit(0);

		$this->_config = $config;
	}

    public function stepCategories($start, array $options)
    {
        $options = array_merge(array(
            'limit' => 50,
            'processed' => 0,
            'max' => false
        ), $options);

        $model = $this->_importModel;
        $db = $this->_db;

        if ($options['max'] === false)
        {
            $data = $db->fetchRow('
	                SELECT MAX(category_id) AS max, COUNT(category_id) AS rows
	                FROM xengallery_category
				');

            $options = array_merge($options,$data);

            return array(0, $options, "Processing Albums ...");
        }

        $categories = $db->fetchAll($db->limit('
	                SELECT category.*
	                FROM xengallery_category AS category
	                WHERE category_id > ' . $this->_db->quote($start) . '
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
        foreach ($categories as $category)
        {
            $next = $category['category_id'];

            if ($last <> $next)
            {
                $last = $next;

                $categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'category_id');
                $import = array(
                    'title' => $category['category_title'],
                    'description' => $category['category_description'],
                    'parent_category_id' => $this->_mapLookUp($categoryIdMap, $category['parent_category_id'], 0),
                    'display_order' => $category['display_order'],
                    'album_count' => 0,
                    'category_breadcrumb' => 'a:0:{}',
                    'lft' => 0,
                    'rgt' => 0,
                    'depth' => 0,
                    'category_privacy' => array(
                        'post' => @unserialize($category['upload_user_groups']),
                        'view' => @unserialize($category['view_user_groups'])
                    )
                );

                if ($this->_config['retainKeys'])
                {
                    $import['category_id'] = $category['category_id'];
                }

                $albumId = $model->importXenGalleryCategory($category['category_id'], $import);

                $model->logImportData('sonnb_xengallery_category', $category['category_id'], $albumId);
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

		$model = $this->_importModel;
		$db = $this->_db;

        if ($options['max'] === false)
        {
            $data = $db->fetchRow('
                SELECT MAX(album_id) AS max, COUNT(album_id) AS rows
                FROM xengallery_album
                WHERE album_state IN (\'visible\',\'moderated\')
			');

            $options = array_merge($options,$data);

            return array(0, $options, "Processing Albums ...");
        }

        $albums = $db->fetchAll($db->limit('
                SELECT album.*
                FROM xengallery_album AS album
                WHERE album.album_id > ' . $this->_db->quote($start) . '
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

        if ($options['processed'] === 0)
        {
            //This is the first run, we will import all categories as albums.
            $visitor = XenForo_Visitor::getInstance();
            $categories = $db->fetchAll($db->limit('
	                SELECT category.*
	                FROM xengallery_category AS category
	                ORDER BY category_id ASC
	            ', $options['limit']
            ));

            foreach ($categories as $category)
            {
                $categoryIdMap = $model->getCategoryIdsMapFromArray($categories, 'category_id');
                $import = array(
                    'title' => $category['category_title'],
                    'description' => $category['category_description'],
                    'user_id' => $visitor['user_id'],
                    'username' => $visitor['username'],
                    'album_state' => 'visible',
                    'album_date' => XenForo_Application::$time,
                    'album_updated_date' => XenForo_Application::$time,
                    'album_privacy' => array(
                        'allow_view' => 'everyone',
                        'allow_view_data' => array(),
                        'allow_comment' => 'everyone',
                        'allow_comment_data' => array(),
                        'allow_add_photo' => 'none',
                        'allow_add_photo_data' => array(),
                        'allow_add_video' => 'none',
                        'allow_add_video_data' => array(),
                        'allow_download' => 'none',
                        'allow_download_data' => array()
                    ),
                    'category_id' => $this->_mapLookUp($categoryIdMap, $category['parent_category_id'], $this->_config['category_id']),
                    'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
                );

                if ($this->_config['retainKeys'])
                {
                    $import['category_id'] = $category['category_id'];
                }

                $albumId = $model->importXenGalleryAlbum($category['category_id'], $import);

                $model->logImportData('sonnb_xengallery_catalbum', $category['category_id'], $albumId);
            }
        }

        foreach ($albums as $album)
        {
            $next = $album['album_id'];

            if ($last <> $next)
            {
                $last = $next;

                $shareData = array();
                switch ($album['album_privacy'])
                {
                    case 'private':
                        $shareKey = 'none';
                        break;
                    case 'shared':
                        $shareKey = 'custom';
                        $privacy = @unserialize($album['album_share_users']);
                        foreach ($privacy AS $user)
                        {
                            $shareData[] = $user['username'];
                        }
                        break;
                    case 'members':
                        $shareKey = 'members';
                        break;
                    case 'followed':
                        $shareKey = 'followed';
                        break;
                    case 'public':
                    default:
                        $shareKey = 'everyone';
                        break;
                }

                $import = array(
                    'title' => $album['album_title'],
                    'description' => $album['album_description'],
                    'user_id' => $album['user_id'],
                    'username' => $album['username'],
                    'album_state' => $album['album_state'],
                    'album_date' => $album['album_create_date'],
                    'album_updated_date' => $album['last_update_date'],
                    'album_privacy' => array(
                        'allow_view' => $shareKey,
                        'allow_view_data' => $shareData,
                        'allow_comment' => $shareKey,
                        'allow_comment_data' => $shareData,
                        'allow_add_photo' => 'none',
                        'allow_add_photo_data' => array(),
                        'allow_add_video' => 'none',
                        'allow_add_video_data' => array(),
                        'allow_download' => 'none',
                        'allow_download_data' => array()
                    ),
                    'category_id' => $this->_config['category_id'],
                    'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
                );

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

	public function stepContents($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 10,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow('
    				SELECT MAX(media_id) AS max, COUNT(media_id) AS rows
    				FROM xengallery_media
    				WHERE media_state IN (\'visible\', \'moderated\')
			');

			$options = array_merge($options, $data);

			return array(0, $options, "Processing Medias ...");
		}

		$medias = $db->fetchAll($db->limit("
                SELECT media.*,
                    attachment.data_id, attachment_data.thumbnail_width,
					attachment_data.data_id, attachment_data.file_hash
                FROM xengallery_media AS media
					LEFT JOIN xf_attachment AS attachment ON
						(attachment.attachment_id 	= media.attachment_id)
					LEFT JOIN xf_attachment_data AS attachment_data ON
						(attachment_data.data_id = attachment.data_id)
                WHERE media.media_id > " . $db->quote($start) . "
                    AND media_state IN ('visible', 'moderated')
                ORDER BY media.media_id ASC
            ", $options['limit']
		));

		if (!$medias)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;

        /** @var XenGallery_Model_Media $mediaModel */
        $mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		$categoryAlbumIdMap = $model->getCategoryAlbumIdsMapFromArray($medias, 'category_id');
		$albumIdMap = $model->getAlbumIdsMapFromArray($medias, 'album_id');

		foreach ($medias as $media)
		{
			$next = $media['media_id'];

			if (empty($media['user_id']))
			{
				continue;
			}

			if (!empty($media['album_id']) && empty($albumIdMap[$media['album_id']]))
			{
				continue;
			}

			if (!empty($media['category_id']) && empty($categoryAlbumIdMap[$media['category_id']]))
			{
				continue;
			}

			if (!empty($media['album_id']))
			{
				$albumId = $albumIdMap[$media['album_id']];
			}
			else
			{
				$albumId = $categoryAlbumIdMap[$media['category_id']];
			}

			if ($last <> $next)
			{
				$last = $next;

				$importData = array(
					'file_size' => 0,
					'width' => 0,
					'height' => 0,
					'file_hash' => 0,
					'upload_date' => $media['media_date'],
					'unassociated' => 1
				);

				switch($media['media_type'])
				{
					case 'image_upload':
						$file = $mediaModel->getOriginalDataFilePath($media, true);
						if (!is_file($file))
						{
							continue;
						}

						$fileInfo = getimagesize($file);

						$importData['file_size'] = filesize($file);
						$importData['file_hash'] = md5($file);
						$importData['width'] = $fileInfo[0];
						$importData['height'] = $fileInfo[1];
						$importData['extension'] = sonnb_XenGallery_Model_ContentData::$extensionMap[$fileInfo[2]];
						$contentType = sonnb_XenGallery_Model_Photo::$contentType;
						break;
					case 'video_embed':
						//This is not available in XMG.
						//$importData['duration'] = $media['media_duration'];
						$contentType = sonnb_XenGallery_Model_Video::$contentType;
						break;
					default:
						continue;
						break;
				}

				$contentData = $model->importXenGalleryContentData($media['media_id'], $importData, $contentType);

				$model->logImportData('sonnb_xengallery_data', $media['media_id'], $contentData['content_data_id']);
				$success = $this->_createContentData($options, $media, $contentData, $contentType);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($contentData);

				switch ($media['media_privacy'])
				{
					case 'private':
						$shareKey = 'none';
						break;
					case 'shared':
						$shareKey = 'custom';
						break;
					case 'members':
						$shareKey = 'members';
						break;
					case 'followed':
						$shareKey = 'followed';
						break;
					case 'public':
					default:
						$shareKey = 'everyone';
						break;
				}

				$import = array(
					'album_id' => $albumId,
					'content_data_id' => $contentData['content_data_id'],
					'title' => $media['media_title'],
					'description' => $media['media_description'],
					'user_id' => $media['user_id'],
					'username' => $media['username'],
					'content_privacy' => array(
						'allow_view' => $shareKey,
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'comment_count' => 0,
					'view_count' => $media['media_view_count'],
					'content_date' => $media['media_date'],
					'content_updated_date' => $media['last_edit_date'],
					'position' => $position,
					'content_state' => $media['media_state']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $media['media_id'];
				}

				switch($media['media_type'])
				{
					case 'image_upload':
						$contentId = $model->importXenGalleryPhoto($media['media_id'], $import);
						break;
					case 'video_embed':
						if (preg_match('/\[media=(.*)\](.*)\[\/media\]/i', $media['media_tag'], $bbcodeVideo))
						{
							$import['video_type'] = $bbcodeVideo[1];
							$import['video_key'] = $bbcodeVideo[2];
						}
						else
						{
							continue;
						}

						$contentId = $model->importXenGalleryVideo($media['media_id'], $import);
						break;
				}

				//Process stream
				$contentTags = $db->fetchAll("
                    SELECT tag.*, map.*
                    FROM xengallery_content_tag AS tag
                      LEFT JOIN xengallery_content_tag_map AS map
                        ON (map.tag_id = tag.tag_id)
                    WHERE map.media_id = ?
                ", $media['media_id']);
				if (!empty($contentTags))
				{
					foreach ($contentTags as $contentTag)
					{
						$this->_db->query('
                            INSERT IGNORE INTO sonnb_xengallery_stream
                                (stream_name, content_type, content_id, user_id, username, stream_date)
                            VALUES
                                (?, ?, ?, ?, ?, ?)
                        ',
							array(
								$contentTag['tag_name'],
								$contentType,
								$contentId,
								$media['user_id'],
								$media['username'],
								XenForo_Application::$time
							));
					}
				}

				//Process user tagging
				$userTags = $db->fetchAll("
                    SELECT * FROM xengallery_user_tag
                    WHERE media_id = ?
                ", $media['media_id']);
				if (!empty($userTags))
				{
					foreach ($userTags as $userTag)
					{
						$tagData = @unserialize($userTag);
						$tagX = 0;
						$tagY = 0;

						if (!empty($tagData) && $contentType === sonnb_XenGallery_Model_Photo::$contentType)
						{
							//TODO: Calculate position
							if (isset($tagData['tag_x1']))
							{

							}

							if (isset($tagData['tag_y1']))
							{

							}
						}

						$this->_db->query('
                            INSERT IGNORE INTO sonnb_xengallery_tag
                                (content_type, content_id, user_id, username, tag_state, tagger_user_id, tagger_username, tag_date, tag_x, tag_y)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ',
							array(
								$contentType,
								$contentId,
								$userTag['user_id'],
								$userTag['username'],
								'accepted',
								$media['user_id'],
								$media['username'],
								$userTag['tag_date'],
								$tagX,
								$tagY
							));
					}
				}

				//Process fields
				$fieldValues = $db->fetchAll("
                    SELECT field_id, field_value FROM xengallery_field_value
                    WHERE media_id = ?
                ", $media['media_id']);
				if (!empty($fieldValues))
				{
					$fieldIdMap = $model->getFieldsIdsMapFromArray($fieldValues, 'field_id');
					foreach ($fieldValues as $value)
					{
						if (empty($fieldIdMap[$value['field_id']]))
						{
							continue;
						}

						$this->_db->query('
                            INSERT INTO sonnb_xengallery_field_value
                                (field_id, content_type, content_id, field_value, user_id, username)
                            VALUES
                                (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                field_value = VALUES(field_value)
                        ',
							array(
								$fieldIdMap[$value['field_id']],
								$contentType,
								$contentId,
								$value['field_value'],
								$media['user_id'],
								$media['username']
							));
					}
				}

				$model->logImportData('sonnb_xengallery_content', $media['media_id'], $contentId);

				$position++;
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepAlbumComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow("
    				SELECT MAX(comment_id) AS max, COUNT(comment_id) AS rows
    				FROM xengallery_comment
    				WHERE content_type = 'album'
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Album's Comments ...");
		}

		$comments = $db->fetchAll($db->limit("
    				SELECT comment.*
    				FROM xengallery_comment AS comment
    				WHERE comment.comment_id > " . $db->quote($start) . " AND
    				    comment.content_type = 'album' AND
    				    comment.comment_state IN ('visible','moderated')
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
		$albumIdMap = $model->getAlbumIdsMapFromArray($comments, 'content_id');

		foreach ($comments as $comment)
		{
			$next = $comment['comment_id'];

			if (empty($comment['user_id']))
			{
				continue;
			}

            if (empty($albumIdMap[$comment['content_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$import = array(
					'content_type' => sonnb_XenGallery_Model_Album::$contentType,
					'content_id' => $albumIdMap[$comment['content_id']],
					'user_id' => $comment['user_id'],
					'username' => $comment['username'],
					'message' => $comment['message'],
					'comment_state' => 'visible',
					'comment_date' => $comment['comment_date']
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

	public function stepMediaComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow("
    				SELECT MAX(comment_id) AS max, COUNT(comment_id) AS rows
    				FROM xengallery_comment
    				WHERE content_type = 'media'
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Media's Comments ...");
		}

		$comments = $db->fetchAll($db->limit("
    				SELECT comment.*, media.media_type
    				FROM xengallery_comment AS comment
    				    INNER JOIN xengallery_media AS media
    				        ON (media.media_id = comment.content_id)
    				WHERE comment.comment_id > " . $db->quote($start) . " AND
    				    comment.content_type = 'media' AND
    				    comment.comment_state IN ('visible','moderated')
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
		$contentIdMap = $model->getContentIdsMapFromArray($comments, 'content_id');

		foreach ($comments as $comment)
		{
			$next = $comment['comment_id'];

			if (empty($comment['user_id']))
			{
				continue;
			}

            if (empty($contentIdMap[$comment['content_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;

                $contentType = $this->_getContentTypeByMediaType($comment['media_type']);
                if ($contentType === false)
                {
                    continue;
                }

				$import = array(
					'content_type' => $contentType,
					'content_id' => $contentIdMap[$comment['content_id']],
					'user_id' => $comment['user_id'],
					'username' => $comment['username'],
					'message' => $comment['message'],
					'comment_state' => $comment['comment_state'],
					'comment_date' => $comment['comment_date']
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

    public function stepFields($start, array $options)
    {
        $options = array_merge(array(
            'limit' => 50,
            'processed' => 0,
            'max' => false
        ), $options);

        $db = $this->_db;
        $model = $this->_importModel;

        if ($options['max'] === false)
        {
            $data = $db->fetchRow("
    				SELECT MAX(field_id) AS max, COUNT(field_id) AS rows
    				FROM xengallery_field
			");

            $options = array_merge($options,$data);

            return array(0, $options, "Processing Fields ...");
        }

	    //TODO: check duplicate field_id in XenGallery
        $fields = $db->fetchAll($db->limit("
    				SELECT field.*, GROUP_CONCAT(field_cat.category_id SEPARATOR ',') as category
    				FROM xengallery_field AS field
    				    LEFT JOIN xengallery_field_category AS field_cat
    				        ON (field.field_id = field_cat.field_id)
    				GROUP BY field.field_id
    				ORDER BY field.field_id ASC
    			", $options['limit'], $start
        ));

        if (!$fields)
        {
            return true;
        }

        $next = 0;
        $last = 0;
        $total = 0;

	    //TODO: get category map
        foreach ($fields as $field)
        {
            $next++;

            if ($last <> $next)
            {
                $last = $next;

                $import = array(
                    'field_id' => $field['field_id'],
                    'title' => new XenForo_Phrase($this->_getXMGFieldModel()->getGalleryFieldTitlePhraseName($field['field_id'])),
                    'description' => new XenForo_Phrase($this->_getXMGFieldModel()->getGalleryFieldDescriptionPhraseName($field['field_id'])),
                    'category' => empty($field['category']) ? array() : explode(',', $field['category']),
                    'content' => array(sonnb_XenGallery_Model_Photo::$contentType, sonnb_XenGallery_Model_Video::$contentType),
                    'field_type' => $field['field_type'] === 'bbcode' ? 'textarea' : $field['field_type'],
                    'field_choices' => $field['field_choices'],
                    'required' => 0,
                    'match_type' => $field['match_type'],
                    'match_regex' => $field['match_regex'],
                    'max_length' => $field['max_length'],
                    'active' => 1,
                    'display_order' => $field['display_order']
                );

                $fieldId = $model->importXenGalleryField($field['field_id'], $import);

                $model->logImportData('sonnb_xengallery_field', $field['field_id'], $fieldId);
            }

            $total++;
        }

        $options['processed'] += $total;
        $this->_session->incrementStepImportTotal($total);

	    XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Field::$allCacheKey, false);

        return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
    }

	protected function _createContentData($options, &$attachment, $data, $contentType = null)
	{
        if ($contentType === null)
        {
            $contentType = $this->_getContentTypeByMediaType($attachment['media_type']);
            if ($contentType === false)
            {
                return false;
            }
        }

        /** @var XenGallery_Model_Media $mediaModel */
        $mediaModel = XenForo_Model::create('XenGallery_Model_Media');

        $file = $mediaModel->getOriginalDataFilePath($attachment, true);
		if (!is_file($file) && $contentType === sonnb_XenGallery_Model_Video::$contentType)
		{
			preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $attachment['media_tag'], $parts);
			$file = $mediaModel->getVideoThumbnailUrlFromParts($parts);
		}

        if (!is_file($file) || !is_readable($file))
        {
            return false;
        }

        switch($contentType)
        {
            case sonnb_XenGallery_Model_Photo::$contentType:
                return $this->_createPhotoFiles($file, $data);
                break;
            case sonnb_XenGallery_Model_Video::$contentType:
                return $this->_createVideoFiles($file, $data);
                break;
            default:
                return false;
                break;
        }
	}

    protected function _getContentTypeByMediaType($mediaType)
    {
        switch($mediaType)
        {
            case 'image_upload':
                $contentType = sonnb_XenGallery_Model_Photo::$contentType;
                break;
            case 'video_embed':
                $contentType = sonnb_XenGallery_Model_Video::$contentType;
                break;
            default:
                return false;
                break;
        }

        return $contentType;
    }

	/**
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_Category');
	}

	/**
	 * @return XenGallery_Model_Field
	 */
	protected function _getXMGFieldModel()
	{
		return XenForo_Model::create('XenGallery_Model_Field');
	}
}