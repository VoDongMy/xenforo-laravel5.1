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
class sonnb_XenGallery_ControllerPublic_XenGallery_Album extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$albumModel = $this->_getAlbumModel();
		$visitor = XenForo_Visitor::getInstance();
		$source = $this->_input->filterSingle('_source', XenForo_Input::STRING);
		
		$album = $this->_getAlbumOrError();

		if ($album['cover_content_id'] && $this->_noRedirect())
		{
			$this->_request->setParam('content_id', $album['cover_content_id']);
			$this->_request->setParam('_source', '');

			return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_Photo', 'index');
		}

		$this->canonicalizeRequestUrl($this->_buildLink('gallery/albums', $album));

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING);

		$this->getContentSort($order, $orderDirection, $defaultOrder, $defaultOrderDirection);
		
		$contentPerPage = $xenOptions->sonnbXG_photoPerPage;
		$commentOnLoad = $xenOptions->sonnbXG_commentPerPage;
		
		$contentFetchCondition = $this->_getContentModel()->getPermissionBasedContentFetchConditions();
		$contentFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Content::FETCH_DATA
				| sonnb_XenGallery_Model_Content::FETCH_PHOTO
				| sonnb_XenGallery_Model_Content::FETCH_VIDEO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id'],
			'perPage' => $contentPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalContents = $this->_getContentModel()->countContentsByAlbumId($album['album_id'], $contentFetchCondition, $contentFetchOptions);
		$contents = $this->_getContentModel()->getContentsByAlbumId($album['album_id'], $contentFetchCondition, $contentFetchOptions);
		$contents = $this->_getContentModel()->prepareContents($contents, $contentFetchOptions);

		if (!empty($contents))
		{
			$album['contents'] = array_slice($contents, 0, 4);
		}

		foreach ($contents as $_contentId => $_content)
		{
			if (!$_content['canView'])
			{
				unset($contents[$_contentId]);
			}
		}

		if ($commentOnLoad > 0)
		{
			$commentConditions = array(
				'content_id' => $album['album_id'],
				'content_type' => sonnb_XenGallery_Model_Album::$contentType
			)+$this->_getCommentModel()->getPermissionBasedCommentFetchConditions();
			$commentFetchOptions = array(
				'join' => sonnb_XenGallery_Model_Comment::FETCH_USER,
				'likeUserId' => $visitor['user_id'],
				'limit' => $commentOnLoad,
				'order' => 'comment_date',
				'orderDirection' => 'desc'
			);
			$album['comments'] = $this->_getCommentModel()->getComments($commentConditions, $commentFetchOptions);
			$album['comments'] = $this->_getCommentModel()->prepareComments($album['comments'], $commentFetchOptions);
			$album['comments'] = array_reverse($album['comments'], true);
		}
		
		if (!empty($album['comments']))
		{
			$firstShownComment = reset($album['comments']);
			$firstShownCommentDate = $firstShownComment['comment_date'];
			$lastShownComment = end($album['comments']);
			$lastShownCommentDate = $lastShownComment['comment_date'];
		}
		else
		{
			$firstShownCommentDate = 0;
			$lastShownCommentDate = 0;
		}

		/*
		 * Get page params for default sort, just for future use.
		 */
		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);
		
		$albumModel->logAlbumView($album['album_id']);

		$inlineModOptions = $this->_getContentModel()->getInlineModeration();

		$category = null;
		if (isset($categories[$album['category_id']]))
		{
			$category = $categories[$album['category_id']];
		}
		$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
			sonnb_XenGallery_Model_Album::$contentType,
			$album['album_id'],
			$category,
			true,
			false
		);

		$viewParams = array(		
			'album' => $album,
			'category' => $category,
			'fields' => $fields,

			'inlineModOptions' => $inlineModOptions,

			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album, true),

			'contents' => $contents,

			'order' => $order,
			'orderDirection' => $orderDirection,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'photosPerPage' => $contentPerPage,
			'totalPhotos' => $totalContents,

			'commentOnLoad' => $commentOnLoad,
			'firstShownCommentDate' => $firstShownCommentDate,
			'lastShownCommentDate' => $lastShownCommentDate,
			'commentShownCount' => $album['comment_count'] > $commentOnLoad ? $commentOnLoad : $album['comment_count'],

			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),

            'includeTaggerJs' => $this->_getGalleryModel()->includeJsTagger()
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_View', 
			'sonnb_xengallery_album_view', 
			$viewParams
		);
	}
	
	public function actionCreate()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertCanCreateMoreAlbum();
		$this->_assertIosDevices();

		$xenOptions = XenForo_Application::getOptions();

		$categoryId = 0;
		if (!$xenOptions->sonnbXG_disableCategory)
		{
			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

			if ($categoryId)
			{
				$category = $this->_getCategoryModel()->getCategoryById($categoryId);
				$category = $this->_getCategoryModel()->prepareCategory($category);

				if (!$category)
				{
					throw $this->_throwFriendlyNoPermission('sonnb_xengallery_requested_category_could_not_be_found');
				}

				if (!$this->_getCategoryModel()->canUploadToCategory($category, $errorPhaseKey))
				{
					throw $this->_throwFriendlyNoPermission($errorPhaseKey);
				}
			}
		}

		$albumPrivacy = array(
			'allow_view' => $xenOptions->sonnbXG_albumPrivacyView,
			'allow_comment' => $xenOptions->sonnbXG_albumPrivacyComment,
			'allow_download' => $xenOptions->sonnbXG_albumPrivacyDownload,
			'allow_add_photo' => $xenOptions->sonnbXG_albumPrivacyAdd,
			'allow_add_video' => $xenOptions->sonnbXG_albumPrivacyAddVideo
		);

		$visitor = XenForo_Visitor::getInstance();
		if (!empty($visitor['xengallery']))
		{
			if (isset($visitor['xengallery']['album_allow_view']))
			{
				$albumPrivacy['allow_view'] = $visitor['xengallery']['album_allow_view'];
			}
			if (isset($visitor['xengallery']['album_allow_comment']))
			{
				$albumPrivacy['allow_comment'] = $visitor['xengallery']['album_allow_comment'];
			}
			if (isset($visitor['xengallery']['album_allow_download']))
			{
				$albumPrivacy['allow_download'] = $visitor['xengallery']['album_allow_download'];
			}
			if (isset($visitor['xengallery']['album_allow_add_photo']))
			{
				$albumPrivacy['allow_add_photo'] = $visitor['xengallery']['album_allow_add_photo'];
			}
			if (isset($visitor['xengallery']['album_allow_add_video']))
			{
				$albumPrivacy['allow_add_video'] = $visitor['xengallery']['album_allow_add_video'];
			}
		}

		$album = array(
			'album_id' => 0,
			'title' => '',
			'description' => '',
			'album_state' => 'visible',
			'category_id' => $categoryId,
			'collection_id' => 0,
			'album_location' => '',
			'cover_content_id' => 0,
			
			'album_privacy' => $albumPrivacy
		);
		
		return $this->EditCreate($album);
	}
	
	public function actionEdit()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_album');
		}

		if (!empty($album['albumStreams']))
		{
			$album['stream_name'] = implode(', ', $album['albumStreams']);
		}
		
		return $this->EditCreate($album);
	}
	
	public function EditCreate(array $album)
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();

		$xenOptions = XenForo_Application::getOptions();
		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		
		$contentDataParams = array(
			'hash' => md5(uniqid('', true)),
			'album_id' => $album['album_id']
		);
		
		$breadCrumbs = array();
		$contents = array();
		$totalPhotos = 0;

		$contentPerPage = $xenOptions->sonnbXG_photoPerPage;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));

		if ($album['album_id'])
		{
			$totalPhotos = $album['content_count'];
			$breadCrumbs = $this->_getAlbumModel()->getAlbumBreadCrumbs($album);

			$conditions = $this->_getContentModel()->getPermissionBasedContentFetchConditions();
			$contentFetchOptions = array(
				'join' => sonnb_XenGallery_Model_Content::FETCH_DATA
							| sonnb_XenGallery_Model_Content::FETCH_PHOTO
							| sonnb_XenGallery_Model_Content::FETCH_VIDEO,
				'page' => $page,
				'perPage' => $contentPerPage
			);

			$contents = $this->_getContentModel()->getContentsByAlbumId($album['album_id'], $conditions, $contentFetchOptions);
			$contents = $this->_getContentModel()->prepareContents($contents, $contentFetchOptions);

			foreach ($contents as $contentId => $content)
			{
				if (!$this->_getContentModel()->canViewContent($content))
				{
					unset($contents[$contentId]);
				}
			}

			if (!empty($album['tagUsers']))
			{
				foreach ($album['tagUsers'] as $user)
				{
					$album['album_people'][] = $user['username'];
				}
				
				$album['album_people'] = implode(',', $album['album_people']);
			}
			
			if (!empty($album['album_privacy']['allow_view_data']))
			{
				$album['allow_view_username'] = array();
				foreach ($album['album_privacy']['allow_view_data'] as $user)
				{
					$album['allow_view_username'][] = $user['username'];
				}
				
				$album['allow_view_username'] = implode(',', $album['allow_view_username']);
			}
			
			if (!empty($album['album_privacy']['allow_comment_data']))
			{
				$album['allow_comment_username'] = array();
				foreach ($album['album_privacy']['allow_comment_data'] as $user)
				{
					$album['allow_comment_username'][] = $user['username'];
				}
				
				$album['allow_comment_username'] = implode(',', $album['allow_comment_username']);
			}

			if (!empty($album['album_privacy']['allow_download_data']))
			{
				$album['allow_download_username'] = array();
				foreach ($album['album_privacy']['allow_download_data'] as $user)
				{
					$album['allow_download_username'][] = $user['username'];
				}

				$album['allow_download_username'] = implode(',', $album['allow_download_username']);
			}
			
			if (!empty($album['album_privacy']['allow_add_photo_data']))
			{
				$album['allow_add_photo_username'] = array();
				foreach ($album['album_privacy']['allow_add_photo_data'] as $user)
				{
					$album['allow_add_photo_username'][] = $user['username'];
				}
				
				$album['allow_add_photo_username'] = implode(',', $album['allow_add_photo_username']);
			}

			if (!empty($album['album_privacy']['allow_add_video_data']))
			{
				$album['allow_add_video_username'] = array();
				foreach ($album['album_privacy']['allow_add_video_data'] as $user)
				{
					$album['allow_add_video_username'][] = $user['username'];
				}

				$album['allow_add_video_username'] = implode(',', $album['allow_add_video_username']);
			}
		}

		$category = null;
		if (isset($categories[$album['category_id']]))
		{
			$category = $categories[$album['category_id']];
		}
		if (!empty($album['album_id']))
		{
			$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
				sonnb_XenGallery_Model_Album::$contentType,
				$album['album_id'],
				$category
			);
		}
		else
		{
			$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
				sonnb_XenGallery_Model_Album::$contentType,
				null,
				$category,
				false
			);
		}

		$viewParams = array(
			'album' => $album,
			'fields' => $fields,
				
			'contents' => $contents,
				
			'breadCrumbs' => $breadCrumbs,
				
			'contentDataParams' => $contentDataParams,
			'photoDataConstraints' => $this->_getPhotoModel()->getPhotoDataConstraints($xenOptions->sonnbXG_enableResize ? true: false),

			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
			'disableLocation' => $xenOptions->sonnb_XG_disableLocation,
				
			'categories' => $categories,

			'photoPrivacy' => $this->getContentDefaultPrivacy(),
			'videoPrivacy' => $this->getContentDefaultPrivacy(sonnb_XenGallery_Model_Video::$contentType),

			'page' => $page,
			'perPage' => $contentPerPage,
			'pageNavParams' => array(),
			'totalPhotos' => $totalPhotos
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_Edit',
			'sonnb_xengallery_album_edit',
			$viewParams
		);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();

		$xenOptions = XenForo_Application::getOptions();
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$visitor = XenForo_Visitor::getInstance();
		$session = XenForo_Application::getSession();
		
		if (!$albumId)
		{
			$this->_assertCanCreateMoreAlbum();
		}
		else
		{
			$album = $this->_getAlbumOrError(null, true);
			
			if (!$this->_getAlbumModel()->canEditAlbum($album, $errorPhraseKey))
			{
				$this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}
		}
		
		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'category_id' => XenForo_Input::UINT
		));

		$coverContentType = $this->_input->filterSingle('cover_content_type', XenForo_Input::STRING);
		$coverContentData = $this->_input->filterSingle('cover_content_id', XenForo_Input::STRING);
		$coverContentDataType = substr($coverContentData, 0, 1);
		$coverContentDataId = intval(substr($coverContentData, 1));

		if (!$xenOptions->sonnbXG_disableCategory)
		{
			if ($input['category_id'] && $albumId)
			{
				$category = $this->_getCategoryModel()->getCategoryById($input['category_id']);
				$category = $this->_getCategoryModel()->prepareCategory($category);

				if (!$category)
				{
					throw $this->_throwFriendlyNoPermission('sonnb_xengallery_requested_category_could_not_be_found');
				}

				if (!$this->_getCategoryModel()->canUploadToCategory($category, $errorPhaseKey))
				{
					throw $this->_throwFriendlyNoPermission($errorPhaseKey);
				}
			}
		}
		else
		{
			unset($input['category_id']);
		}
		
		$basicPrivacy = $this->_input->filter(array(				
			'allow_view' => XenForo_Input::STRING,
			'allow_comment' => XenForo_Input::STRING,
			'allow_download' => XenForo_Input::STRING,
			'allow_add_photo' => XenForo_Input::STRING,
			'allow_add_video' => XenForo_Input::STRING
		));
		
		$privacy = $this->_input->filter(array(
			'allow_view_username' => XenForo_Input::STRING,
			'allow_comment_username' => XenForo_Input::STRING,
			'allow_download_username' => XenForo_Input::STRING,
			'allow_add_photo_username' => XenForo_Input::STRING,
			'allow_add_video_username' => XenForo_Input::STRING
		));
		
		$input['description'] = $this->getHelper('Editor')->getMessageText('description', $this->_input);

		if ($albumId || (!$albumId && !$xenOptions->sonnbXG_disableCreationUpload))
		{
			$contentData = $this->_input->filter(array(
				'content_data_hash' => XenForo_Input::STRING,

				'content_title' => array(XenForo_Input::STRING, array('array' => true)),
				'content_description' => array(XenForo_Input::STRING, array('array' => true)),
				'content_people' => array(XenForo_Input::STRING, array('array' => true)),

				'video_key' => array(XenForo_Input::STRING, array('array' => true)),
				'video_type' => array(XenForo_Input::STRING, array('array' => true)),

				'content_type' => array(XenForo_Input::STRING, array('array' => true))
			));

			if (!$xenOptions->sonnb_XG_disableLocation)
			{
				$input['album_location'] = $this->_input->filterSingle('album_location', XenForo_Input::STRING);

				$contentData['content_location'] = $this->_input->filterSingle('content_location', XenForo_Input::STRING, array('array' => true));
				$contentData['location_lat'] = $this->_input->filterSingle('location_lat', XenForo_Input::STRING, array('array' => true));
				$contentData['location_lng'] = $this->_input->filterSingle('location_lng', XenForo_Input::STRING, array('array' => true));
			}
		}

		$streams = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);
		$deletes = $this->_input->filterSingle('delete', array('array' => true, XenForo_Input::UINT));
		$deletes = array_filter($deletes);
		$deletes = array_flip($deletes);

		$photoPrivacy = $videoPrivacy = array();
		if ($xenOptions->sonnbXG_showPrivacyOnUpload && $this->_input->inRequest('photo_allow_view'))
		{
			$photoPrivacy = $this->_input->filter(array(
		          'photo_allow_view' => XenForo_Input::STRING,
		          'photo_allow_comment' => XenForo_Input::STRING,
		          'photo_allow_view_username' => XenForo_Input::STRING,
		          'photo_allow_comment_username' => XenForo_Input::STRING
		     ));
			$videoPrivacy = $this->_input->filter(array(
	              'video_allow_view' => XenForo_Input::STRING,
	              'video_allow_comment' => XenForo_Input::STRING,
	              'video_allow_view_username' => XenForo_Input::STRING,
	              'video_allow_comment_username' => XenForo_Input::STRING
	         ));
		}

		/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
		$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');

		if (!empty($album))
		{
			$albumDw->setExistingData($album);

			if (!empty($album['cover']))
			{
				if (empty($coverContentDataId) &&
					!isset($deletes[$album['cover']['content_data_id']]))
				{
					$coverContentDataId = false;
				}
				elseif (!empty($coverContentDataId) && isset($deletes[$album['cover']['content_data_id']]))
				{
					$coverContentDataId = 0;
				}
			}

			if ($coverContentDataId !== false)
			{
				if ($coverContentDataType === 'c')
				{
					$albumDw->set('cover_content_id', $coverContentDataId);
				}
				elseif ($coverContentDataType === 'd')
				{
					$albumDw->setExtraData(sonnb_XenGallery_DataWriter_Album::COVER_CONTENT_DATA_ID, $coverContentDataId);
				}

				$albumDw->set('cover_content_type', $coverContentType);
			}
		}
		else
		{
			if (isset($contentData['content_data_hash'])
					&& $session->get($contentData['content_data_hash']))
			{
				$albumDw->setExistingData($session->get($contentData['content_data_hash']));
			}
			else
			{
				$albumDw->bulkSet(array(
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username']
				));

				if (!empty($coverContentDataId))
				{
					$albumDw->setExtraData(sonnb_XenGallery_DataWriter_Content::COVER_CONTENT_DATA_ID, $coverContentDataId);
					$albumDw->set('cover_content_type', $coverContentType);
				}
			}
		}

        $albumDw->setExtraData(sonnb_XenGallery_DataWriter_Album::CHECK_CATEGORY, true);

		$albumDw->bulkSet($input);
		$albumDw->set('album_privacy', $basicPrivacy);
		$albumDw->insertCustomPrivacy($privacy);

		if ($streams)
		{
			$albumDw->setExtraData(sonnb_XenGallery_DataWriter_Album::DATA_ALBUM_STREAMS, $streams);
		}

		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$albumDw->setCustomFields($customFields);

		$albumDw->preSave();

		if ($errors = $albumDw->getErrors())
		{
			return $this->responseError($errors);
		}
		
		$albumDw->save();

		if (isset($contentData['content_data_hash'])
				&& !$albumId && !$xenOptions->sonnbXG_disableCreationUpload)
		{
			$session->set($contentData['content_data_hash'], $albumDw->get('album_id'));
		}

		if ($albumId || (!$albumId && !$xenOptions->sonnbXG_disableCreationUpload))
		{
			$albumDw->insertContents($contentData, $deletes, $photoPrivacy, $videoPrivacy);
		}

		if ($errors = $albumDw->getErrors())
		{
			return $this->responseError($errors);
		}

		if (isset($contentData['content_data_hash']))
		{
			$session->remove($contentData['content_data_hash']);
		}

		$album = $albumDw->getMergedData();

		$tagInput = $this->_input->filterSingle('album_with', XenForo_Input::STRING);
		$this->_getTagModel()->addTagUsers(
			$tagInput,
			sonnb_XenGallery_Model_Album::$contentType,
			$album['album_id'],
			true
		);
		
		if (!$xenOptions->sonnb_XG_disableLocation && $albumDw->isChanged('album_location'))
		{
			$locationLatlng = $this->_input->filter(array(
				'album_location_lat' => XenForo_Input::STRING,
				'album_location_lng' => XenForo_Input::STRING,
				'album_location' => XenForo_Input::STRING
			));
			
			$this->_getLocationModel()->insertLocation(sonnb_XenGallery_Model_Album::$contentType, $album['album_id'], array(
				'location_lat' => $locationLatlng['album_location_lat'],
				'location_lng' => $locationLatlng['album_location_lng'],
				'location_name' => $locationLatlng['album_location']		
			));
		}
		
		if ($albumId)
		{
			$message = new XenForo_Phrase('changes_saved');
		}
		else
		{
			$message = new XenForo_Phrase('sonnb_xengallery_your_album_has_been_created');
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS, 
			$this->_buildLink('gallery/albums', $album),
			$message
		);
	}
	
	public function actionAddPhoto()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canAddPhoto'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_add_photos_to_this_album');
		}

		$xenOptions = XenForo_Application::getOptions();
		$this->_assertCanAddMorePhoto($album);
		
		if ($this->_request->isPost())
		{
			$photoData = $this->_input->filter(array(
				'content_data_hash' => XenForo_Input::STRING,

				'content_title' => array(XenForo_Input::STRING, array('array' => true)),
				'content_description' => array(XenForo_Input::STRING, array('array' => true)),
				'content_people' => array(XenForo_Input::STRING, array('array' => true))
			));

			$deletes = $this->_input->filterSingle('delete', array('array' => true, XenForo_Input::UINT));
			$deletes = array_filter($deletes);
			$deletes = array_flip($deletes);

			$privacy = array();
			if ($xenOptions->sonnbXG_showPrivacyOnUpload && $this->_input->inRequest('photo_allow_view'))
			{
				$privacy = $this->_input->filter(array(
	                  'photo_allow_view' => XenForo_Input::STRING,
	                  'photo_allow_comment' => XenForo_Input::STRING,
	                  'photo_allow_view_username' => XenForo_Input::STRING,
	                  'photo_allow_comment_username' => XenForo_Input::STRING
	            ));
			}

			if (!$xenOptions->sonnb_XG_disableLocation)
			{
				$photoData['content_location'] = $this->_input->filterSingle('content_location', XenForo_Input::STRING, array('array' => true));
				$photoData['location_lat'] = $this->_input->filterSingle('location_lat', XenForo_Input::STRING, array('array' => true));
				$photoData['location_lng'] = $this->_input->filterSingle('location_lng', XenForo_Input::STRING, array('array' => true));
			}

			/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$albumDw->setExistingData($album);
			$albumDw->insertPhotos($photoData, $photoData['content_data_hash'], $deletes, $privacy);
			$albumDw->save();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('sonnb_xengallery_your_photos_have_been_added')
			);
		}
		else
		{
			$categories = $this->_getCategoryModel()->getAllCachedCategories();
			
			$contentDataParams = array(
				'hash' => md5(uniqid('', true)),
				'album_id' => $album['album_id']
			);

			$breadCrumbs = $this->_getAlbumModel()->getAlbumBreadCrumbs($album);
			
			$viewParams = array(
				'album' => $album,

				'breadCrumbs' => $breadCrumbs,

				'contentDataParams' => $contentDataParams,
				'contentDataConstraints' => $this->_getPhotoModel()->getPhotoDataConstraints($xenOptions->sonnbXG_enableResize ? true: false),

				'categories' => $categories,

				'photoPrivacy' => $this->getContentDefaultPrivacy()
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_AddPhoto',
				'sonnb_xengallery_album_add_photo',
				$viewParams
			);
		}
	}

	public function actionAddVideo()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();
		$this->_assertCanEmbedVideos();

		$album = $this->_getAlbumOrError();

		if (!$album['canAddVideo'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_add_videos_to_this_album');
		}

		$xenOptions = XenForo_Application::getOptions();
		$this->_assertCanAddMoreVideo($album);

		if ($this->_request->isPost())
		{
			$videoData = $this->_input->filter(array(
				'content_data_hash' => XenForo_Input::STRING,

				'content_title' => array(XenForo_Input::STRING, array('array' => true)),
				'content_description' => array(XenForo_Input::STRING, array('array' => true)),
				'content_people' => array(XenForo_Input::STRING, array('array' => true)),

				'video_key' => array(XenForo_Input::STRING, array('array' => true)),
				'video_type' => array(XenForo_Input::STRING, array('array' => true))
			));

			$deletes = $this->_input->filterSingle('delete', array('array' => true, XenForo_Input::UINT));
			$deletes = array_filter($deletes);
			$deletes = array_flip($deletes);

			$privacy = array();
			if ($xenOptions->sonnbXG_showPrivacyOnUpload && $this->_input->inRequest('video_allow_view'))
			{
				$privacy = $this->_input->filter(array(
			          'video_allow_view' => XenForo_Input::STRING,
			          'video_allow_comment' => XenForo_Input::STRING,
			          'video_allow_view_username' => XenForo_Input::STRING,
			          'video_allow_comment_username' => XenForo_Input::STRING
			    ));
			}

			if (!$xenOptions->sonnb_XG_disableLocation)
			{
				$videoData['content_location'] = $this->_input->filterSingle('content_location', XenForo_Input::STRING, array('array' => true));
				$videoData['location_lat'] = $this->_input->filterSingle('location_lat', XenForo_Input::STRING, array('array' => true));
				$videoData['location_lng'] = $this->_input->filterSingle('location_lng', XenForo_Input::STRING, array('array' => true));
			}

			/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$albumDw->setExistingData($album);
			$albumDw->insertVideos($videoData, $videoData['content_data_hash'], $deletes, $privacy);
			$albumDw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('sonnb_xengallery_your_videos_have_been_added')
			);
		}
		else
		{
			$categories = $this->_getCategoryModel()->getAllCachedCategories();

			$contentDataParams = array(
				'hash' => md5(uniqid('', true)),
				'album_id' => $album['album_id']
			);

			$breadCrumbs = $this->_getAlbumModel()->getAlbumBreadCrumbs($album);

			$viewParams = array(
				'album' => $album,

				'breadCrumbs' => $breadCrumbs,

				'contentDataParams' => $contentDataParams,

				'categories' => $categories,

				'videoPrivacy' => $this->getContentDefaultPrivacy(sonnb_XenGallery_Model_Video::$contentType),
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_AddVideo',
				'sonnb_xengallery_album_add_video',
				$viewParams
			);
		}
	}

	public function actionStreamAdd()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();

		if (!$album['canEdit'])
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_you_do_not_have_permission_to_edit_streams_this_album'), 403);
		}

		$streams = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);
		$streams = explode(',', $streams);
		$streams = array_filter($streams, 'utf8_trim');
		$streams = array_filter($streams);
		$streams = array_unique($streams);

		if ($streams)
		{
			$streamProcessed = $this->_getStreamModel()->publishStream(
				sonnb_XenGallery_Model_Album::$contentType,
				$album['album_id'],
				$streams
			);

			if ($streamProcessed === false)
			{

			}
			elseif ($streamProcessed === -1)
			{
				return $this->responseError(
					new XenForo_Phrase(
						'sonnb_xengallery_you_are_allowed_to_add_x_streams_to_a_single_photo_album',
						array(
							'limit' => $this->_getGalleryModel()->getMaximumStreamCount()
						)
					), 403);
			}
			else
			{
				$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
				$dw->setExistingData($album);

				if ($album['albumStreams'])
				{
					$album['albumStreams'] = array_merge($album['albumStreams'], $streamProcessed);
				}
				else
				{
					$album['albumStreams'] = $streamProcessed;
				}

				$dw->set('album_streams', $album['albumStreams']);
				$dw->save();
			}
		}

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $album,

				'newStreams' =>is_array($streamProcessed) ? $streamProcessed : array()
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_StreamAdd',
				'sonnb_xengallery_album_stream_add',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_buildLink('gallery/albums', $album),
			new XenForo_Phrase('sonnb_xengallery_streams_have_been_added')
		);
	}

	public function actionStreamDelete()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();

		if (!$album['canEdit'])
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_you_do_not_have_permission_to_delete_streams_of_this_album'), 403);
		}

		$stream = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);
		$contentType = sonnb_XenGallery_Model_Album::$contentType;
		$contentId = $album['album_id'];

		$return = $this->_getStreamModel()->removeStream($contentType, $contentId,$stream);
		if (!$stream || !$return)
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_invalid_stream_name_specified'), 404);
		}

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
		$dw->setExistingData($album);

		if ($album['albumStreams'])
		{
			foreach ($album['albumStreams'] as $_index => $_stream)
			{
				if ($_stream === $return)
				{
					unset($album['albumStreams'][$_index]);
				}
			}

			$dw->set('album_streams', $album['albumStreams']);
			$dw->save();
		}

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $album,

				'stream' => $return
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_StreamDelete',
				'sonnb_xengallery_album_stream_delete',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_buildLink('gallery/albums', $album),
			new XenForo_Phrase('sonnb_xengallery_stream_has_been_removed')
		);
	}

	public function actionShare()
	{
		$album = $this->_getAlbumOrError();

		$viewParams = array(
			'album' => $album,

			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_Share',
			'sonnb_xengallery_album_share',
			$viewParams
		);
	}
	
	public function actionTag()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_tag_people_to_this_album');
		}
		
		if ($this->_request->isPost())
		{
			$tagInput = $this->_input->filterSingle('album_with', XenForo_Input::STRING);

			$tag = $this->_getTagModel()->addTagUsers(
				$tagInput,
				sonnb_XenGallery_Model_Album::$contentType,
				$album['album_id']
			);

			if ($tag === false)
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_usernames_are_not_valid_or_tagged'));
			}

			if ($this->_noRedirect())
			{
				$album['tagUsers'] = $this->_getTagModel()->getTagsByContentId(
					sonnb_XenGallery_Model_Album::$contentType,
					$album['album_id'],
					array(
						'tag_state' => 'accepted'
					)
				);

				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Album_Tag',
					'',
					array(
						'album' => $album,
						'message' => new XenForo_Phrase('sonnb_xengallery_users_were_tagged_might_need_approval')
					)
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_buildLink('gallery/albums', $album),
					new XenForo_Phrase('changes_saved')
				);
			}
		}
		else
		{
			if (!empty($album['tagUsers']))
			{
				foreach ($album['tagUsers'] as $user)
				{
					$album['album_people'][] = $user['username'];
				}
			
				$album['album_people'] = implode(',', $album['album_people']);
			}
			
			$viewParams = array(
				'album' => $album,

				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);
				
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Tag',
				'sonnb_xengallery_album_edit_tag',
				$viewParams
			);
		}	
	}
	
	public function actionTags()
	{
		$album = $this->_getAlbumOrError();
		
		$tags = $this->_getTagModel()->getTagsByContentId(sonnb_XenGallery_Model_Album::$contentType, $album['album_id']);
		if (!$tags)
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_no_one_was_tagged_in_this_album_yet');
		}
		
		$viewParams = array(
			'album' => $album,
			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album),
			'tags' => $tags
		);
		
		return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Tags', 
				'sonnb_xengallery_album_tags', 
				$viewParams
			);
	}
	
	public function actionPrivacy()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_privacy_of_this_album');
		}
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(						
				'allow_view' => XenForo_Input::STRING,
				'allow_comment' => XenForo_Input::STRING,
				'allow_add_photo' => XenForo_Input::STRING,
				'allow_add_video' => XenForo_Input::STRING,
				'allow_download' => XenForo_Input::STRING
			));
				
			$privacy = $this->_input->filter(array(
				'allow_view_username' => XenForo_Input::STRING,
				'allow_comment_username' => XenForo_Input::STRING,
				'allow_add_photo_username' => XenForo_Input::STRING,
				'allow_add_video_username' => XenForo_Input::STRING,
				'allow_download_username' => XenForo_Input::STRING
			));

			/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$albumDw->setExistingData($album);

			$albumDw->set('album_privacy', $input);
			$albumDw->insertCustomPrivacy($privacy);
			$albumDw->save();
			
			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_buildLink('gallery/albums', $album),
					new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			if (!empty($album['album_privacy']['allow_view_data']))
			{
				$album['allow_view_username'] = array();
				foreach ($album['album_privacy']['allow_view_data'] as $user)
				{
					$album['allow_view_username'][] = $user['username'];
				}
					
				$album['allow_view_username'] = implode(',', $album['allow_view_username']);
			}
		
			if (!empty($album['album_privacy']['allow_comment_data']))
			{
				$album['allow_comment_username'] = array();
				foreach ($album['album_privacy']['allow_comment_data'] as $user)
				{
					$album['allow_comment_username'][] = $user['username'];
				}
					
				$album['allow_comment_username'] = implode(',', $album['allow_comment_username']);
			}

			if (!empty($album['album_privacy']['allow_add_photo_data']))
			{
				$album['allow_add_photo_username'] = array();
				foreach ($album['album_privacy']['allow_add_photo_data'] as $user)
				{
					$album['allow_add_photo_username'][] = $user['username'];
				}

				$album['allow_add_photo_username'] = implode(',', $album['allow_add_photo_username']);
			}
		
			if (!empty($album['album_privacy']['allow_add_video_data']))
			{
				$album['allow_add_video_username'] = array();
				foreach ($album['album_privacy']['allow_add_video_data'] as $user)
				{
					$album['allow_add_video_username'][] = $user['username'];
				}
					
				$album['allow_add_video_username'] = implode(',', $album['allow_add_video_username']);
			}

			if (!empty($album['album_privacy']['allow_download_data']))
			{
				$album['allow_download_username'] = array();
				foreach ($album['album_privacy']['allow_download_data'] as $user)
				{
					$album['allow_download_username'][] = $user['username'];
				}

				$album['allow_download_username'] = implode(',', $album['allow_download_username']);
			}
				
			$viewParams = array(
				'album' => $album,
	
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);
		
			return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Album_Privacy',
					'sonnb_xengallery_album_edit_privacy',
					$viewParams
			);
		}
	}
	
	public function actionArrange()
	{
		//TODO: Arrange album's content
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_album');
		}	

		/*
		$position = $this->_input->filterSingle('position', XenForo_Input::STRING);
		$position = str_replace('photo[]=', '', $position);
		$position = explode('&', $position);
		
		$this->_getContentModel()->arrangePhotos($position);
		
		if ($this->_noRedirect())
		{
			return $this->responseMessage('done');
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/albums', $album)
			);
		}
		*/

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->_buildLink('gallery/albums', $album)
		);
	}
	
	public function actionLocation()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canEdit'] || XenForo_Application::getOptions()->sonnb_XG_disableLocation)
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_location_of_this_album');
		}
		
		if ($this->_request->isPost())
		{
			$locationLatlng = $this->_input->filter(array(
				'location_lat' => XenForo_Input::FLOAT,
				'location_lng' => XenForo_Input::FLOAT
			));
			$location = $this->_input->filterSingle('album_location', XenForo_Input::STRING);
			$location = trim($location);
			$locationLatlng['location_name'] = $location;
			
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$albumDw->setExistingData($album);
			$albumDw->set('album_location', $location);
			$albumDw->save();

			if ($albumDw->isChanged('album_location'))
			{
				$this->_getLocationModel()->insertLocation(sonnb_XenGallery_Model_Album::$contentType, $album['album_id'], $locationLatlng);
			}
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Location',
				'sonnb_xengallery_album_edit_location',
				$viewParams
			);
		}
	}
	
	public function actionReport()
	{	
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();

		if (!$this->_getAlbumModel()->canReportAlbum($album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				throw $this->_throwFriendlyNoPermission('please_enter_reason_for_reporting_this_message');
			}

			$this->assertNotFlooding('report');

			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent(sonnb_XenGallery_Model_Album::$xfContentType, $album, $message);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('thank_you_for_reporting_this_message')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album),
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Report',
				'sonnb_xengallery_album_report',
				$viewParams
			);
		}
	}

	public function actionCollectionEdit()
	{
		$this->_assertRegistrationRequired();

		if (XenForo_Application::getOptions()->sonnbXG_disableCollection)
		{
			return $this->responseNoPermission();
		}

		$album = $this->_getAlbumOrError();

		if (!$this->_getCollectionModel()->canAddToCollection($album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::UINT);

			if (!$collectionId)
			{
				throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_need_to_specify_a_collection');
			}

			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$dw->setExistingData($album);
			$dw->set('collection_id', $collectionId);
			$dw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,

				'collections' => $this->_getCollectionModel()->getAllCachedCollections(),

				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_CollectionEdit',
				'sonnb_xengallery_album_collection_edit',
				$viewParams
			);
		}
	}

	public function actionCollectionRemove()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();

		if (empty($album['collection_id']) || !$this->_getCollectionModel()->canRemoveFromCollection($album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$dw->setExistingData($album);
			$dw->set('collection_id', 0);
			$dw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/albums', $album),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$collections = $this->_getCollectionModel()->getAllCachedCollections();

			$viewParams = array(
				'album' => $album,
				'collections' => $collections,
				'collection' => isset($collections[$album['collection_id']]) ? $collections[$album['collection_id']] : array(),
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_CollectionRemove',
				'sonnb_xengallery_album_collection_remove',
				$viewParams
			);
		}
	}
	
	public function actionDelete()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		
		if (!$album['canDelete'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_album');
		}

		if ($this->_input->inRequest('undo_delete'))
		{
			return $this->responseReroute(__CLASS__, 'undo-delete');
		}
		
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
			$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);
			
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
			$albumDw->setExistingData($album);
			
			if ($hardDelete)
			{
				if (!$this->_getAlbumModel()->canDeleteAlbum($album, 'hard'))
				{
					return $this->responseNoPermission();	
				}
				
				$albumDw->setExtraData(sonnb_XenGallery_DataWriter_Album::DATA_DELETE_REASON, $reason);
				$albumDw->delete();

				$target = $this->_buildLink('gallery');
				$message = new XenForo_Phrase('sonnb_xengallery_your_album_has_been_deleted');
			}
			else
			{
				$albumDw->set('album_state', 'deleted');
				$albumDw->save();

				$target = $this->_buildLink('gallery');

				if ($this->_getAlbumModel()->canViewDeletedAlbum($album))
				{
					$target = $this->_buildLink('gallery/albums', $album);
				}

				$message = new XenForo_Phrase('changes_saved');
			}
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$target,
				$message
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'canHardDelete' => 	$this->_getAlbumModel()->canDeleteAlbum($album, 'hard'),
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);
			
			return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Album_Delete',
					'sonnb_xengallery_album_delete',
					$viewParams
			); 
		}
	}

	public function actionUndoDelete()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();

		if (!$album['canDelete'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_album');
		}

		$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
		$albumDw->setExistingData($album);
		$albumDw->set('album_state', 'visible');
		$albumDw->save();
		$message = new XenForo_Phrase('changes_saved');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_buildLink('gallery/albums', $album),
			$message
		);
	}
	
	public function actionComment()
	{
		$this->_assertRegistrationRequired();

		$album = $this->_getAlbumOrError();
		$visitor = XenForo_Visitor::getInstance();
		
		if (!$album['canComment'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_leave_a_comment_here');
		}
		
		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			$message = XenForo_Helper_String::autoLinkBbCode($message);

			$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
			
			$commentDw->bulkSet(array(
				'user_id' => $visitor['user_id'],
				'username' => $visitor['username'],
				'content_type' => sonnb_XenGallery_Model_Album::$contentType,
				'content_id' => $album['album_id'],
				'message' => $message	
			));
			
			$commentDw->setExtraData(sonnb_XenGallery_DataWriter_Comment::DATA_CONTENT, $album);
			
			$commentDw->preSave();

			if (!$commentDw->hasErrors())
			{
				$this->assertNotFlooding('post');
			}

			$commentDw->save();
			
			if ($this->_noRedirect())
			{
				$lastShown = $this->_input->filterSingle('after', XenForo_Input::UINT);

				$commentConditions = array(
					'comment_date' => array('>', $lastShown),
					'content_type' => sonnb_XenGallery_Model_Album::$contentType,
					'content_id' => $album['album_id']
				);
				$commentFetch = array('join' => sonnb_XenGallery_Model_Comment::FETCH_USER);
				$comments = $this->_getCommentModel()->getComments($commentConditions, $commentFetch);
				$comments = $this->_getCommentModel()->prepareComments($comments, $commentFetch);
			
				$viewParams = array(
					'comments' => $comments,
					'album' => $album
				);
			
				return $this->responseView('sonnb_XenGallery_ViewPublic_Album_Comment', '', $viewParams);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS, 
					$this->_buildLink('gallery/albums', $album),
					new XenForo_Phrase('changes_saved')
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_CommentPost',
				'sonnb_xengallery_album_comment_post',
				$viewParams
			);
		}
	}
	
	public function actionComments()
	{
		$album = $this->_getAlbumOrError();
		
		$beforeDate = $this->_input->filterSingle('before', XenForo_Input::UINT);
		
		$commentModel = $this->_getCommentModel();
		
		$conditions = array(
			'content_type' => sonnb_XenGallery_Model_Album::$contentType,
			'content_id' => $album['album_id']
		);

		if ($beforeDate)
		{
			$conditions['comment_date'] =  array('<', $beforeDate);
		}

		//Load next 20 older comments
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Comment::FETCH_USER, 
			'limit' => 20,
			'order' => 'comment_date'
		);
		
		$comments = $commentModel->getComments($conditions, $fetchOptions);
		
		if (!$comments)
		{
			return $this->responseMessage(new XenForo_Phrase('no_comments_to_display'));
		}
		
		$comments = $commentModel->prepareComments($comments, $fetchOptions);
		$comments = array_reverse($comments, true);
		
		$firstCommentShown = reset($comments);
		$lastCommentShown = end($comments);
		$remainCommentCount = $commentModel->countComments($conditions) - count($comments);
		
		$viewParams = array(
			'comments' => $comments,

			'album' => $album,
			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album),

			'remainCommentCount' => $remainCommentCount,
			'commentShownCount' => $album['comment_count'] - $remainCommentCount,

			'firstCommentShown' => $firstCommentShown,
			'lastCommentShown' => $lastCommentShown
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_Comments', 
			'sonnb_xengallery_album_comments', 
			$viewParams
		);
	}
	
	public function actionLike()
	{
		$this->_assertRegistrationRequired();

		$visitor = XenForo_Visitor::getInstance();
		$album = $this->_getAlbumOrError();
		
		if (!$this->_getAlbumModel()->canLikeAlbum($album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}
	
		$likeModel = $this->_getLikeModel();
	
		$existingLike = $likeModel->getContentLikeByLikeUser(
				sonnb_XenGallery_Model_Album::$xfContentType, $album['album_id'], $visitor->getUserId());
		
		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent(sonnb_XenGallery_Model_Album::$xfContentType, $album['album_id'], $album['user_id']);
				
				if ($visitor['user_id'] != $album['user_id'])
				{
					$this->_getWatchModel()->insertUpdateWatcherByContentId(
						$visitor,
						sonnb_XenGallery_Model_Album::$contentType,
						$album['album_id']
					);
				}
			}
	
			$liked = ($existingLike ? false : true);
	
			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$album['likeUsers'] = $latestUsers;
				$album['likes'] += ($liked ? 1 : ($album['likes'] ? -1: 0));
				$album['like_date'] = ($liked ? XenForo_Application::$time : 0);
	
				$viewParams = array(
					'album' => $album,
					'liked' => $liked,
				);
	
				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Album_LikeConfirmed', '', $viewParams
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_buildLink('gallery/albums', $album)
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'like' => $existingLike,
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);
	
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Like',
				'sonnb_xengallery_album_like',
				$viewParams
			);
		}
	}
	
	public function actionLikes()
	{
		$album = $this->_getAlbumOrError();
		
		$likes = $this->_getLikeModel()->getContentLikes(sonnb_XenGallery_Model_Album::$xfContentType, $album['album_id']);
		if (!$likes)
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_no_one_likes_this_album_yet');
		}
		
		$viewParams = array(
			'album' => $album,
			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album),
			'likes' => $likes
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_Likes',
			'sonnb_xengallery_album_likes',
			$viewParams
		);
	}

	public function actionWatch()
	{
		$this->_assertRegistrationRequired();
		$visitor = XenForo_Visitor::getInstance();

		$album = $this->_getAlbumOrError();

		if (!$this->_getAlbumModel()->canWatchAlbum($album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		$watchModel = $this->_getWatchModel();

		$existingWatch = $watchModel->getWatchByUserIdContentId(
			$visitor->getUserId(),
			sonnb_XenGallery_Model_Album::$contentType,
			$album['album_id']
		);

		if ($this->_request->isPost())
		{
			if ($existingWatch)
			{
				$latestUsers = $watchModel->unwatchContent($existingWatch);
			}
			else
			{
				$latestUsers = $watchModel->watchContent(sonnb_XenGallery_Model_Album::$contentType, $album['album_id']);
			}

			$watched = ($existingWatch ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$photo['watch_date'] = ($watched ? XenForo_Application::$time : 0);

				$viewParams = array(
					'album' => $album,
					'watched' => $watched,
				);

				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Album_WatchConfirmed', '', $viewParams
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_buildLink('gallery/albums', $album)
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'watch' => $existingWatch,
				'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Album_Watch',
				'sonnb_xengallery_album_watch',
				$viewParams
			);
		}
	}

	public function actionDownload()
	{

		//TODO: allow download whole album
	}

    public function actionOwner()
    {
        $album = $this->_getAlbumOrError();

        if (!$this->_getAlbumModel()->canEditAnyAlbum())
        {
            throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_change_owner_this_album');
        }

        if ($this->_request->isPost())
        {
            $username = $this->_input->filterSingle('username', XenForo_Input::STRING);

            $user = $this->_getUserModel()->getUserByName($username);
            if (empty($user))
            {
                throw $this->_throwFriendlyNoPermission('requested_user_not_found');
            }

            $dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
            $dw->setExistingData($album);
            $dw->set('user_id', $user['user_id']);
            $dw->set('username', $user['username']);
            $dw->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                $this->_buildLink('gallery/albums', $album),
                new XenForo_Phrase('changes_saved')
            );
        }
        else
        {
            $viewParams = array(
                'album' => $album,
                'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album)
            );

            return $this->responseView(
                'sonnb_XenGallery_ViewPublic_Album_Owner',
                'sonnb_xengallery_album_owner',
                $viewParams
            );
        }
    }

    public function actionHash()
    {
        //TODO: allow to create private hash to access this album and bypass the privacy
    }
	
	protected function _getDefaultConditions()
	{
		$conditions = array(
			
		);

		return $conditions;
	}
	
	protected function _getAlbumFetchElements(array $conditions = array())
	{
		$albumModel = $this->_getAlbumModel();
		$visitor = XenForo_Visitor::getInstance();
	
		$albumFetchConditions = $conditions + $albumModel->getPermissionBasedAlbumFetchConditions();

		$albumFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER | sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);
	
		return array(
			'conditions' => $albumFetchConditions,
			'options' => $albumFetchOptions
		);
	}

	public function getContentDefaultPrivacy($contentType = null)
	{
		if ($contentType === null)
		{
			$contentType = sonnb_XenGallery_Model_Photo::$contentType;
		}

		return array(
			'allow_view' => $this->_getContentModel()->getDefaultPrivacy($contentType, 'view'),
			'allow_view_data' => array(),
			'allow_comment' => $this->_getContentModel()->getDefaultPrivacy($contentType, 'comment'),
			'allow_comment_data' => array()
		);
	}

	protected function _getAlbumOrError($albumId = null, $fetchCover = false)
	{
		if ($albumId === null)
		{
			$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		}

		$fetchElements = $this->_getAlbumFetchElements();

		/* @var $galleryHelper sonnb_XenGallery_ControllerHelper_Gallery */
		$galleryHelper = $this->getHelper('sonnb_XenGallery_ControllerHelper_Gallery');

		$album = $galleryHelper->assertAlbumValidAndViewable($albumId, $fetchElements['options']);

		if ($fetchCover)
		{
			$album = $this->_getAlbumModel()->attachCoverToAlbum($album, $fetchElements['options']);
		}

		return $album;
	}
	
	protected function _assertCanCreateMoreAlbum()
	{
		$visitor = XenForo_Visitor::getInstance();
		
		$currentAlbumCount = $this->_getAlbumModel()->countAlbumsByUserId($visitor['user_id']);
		$maximumAlbumAllowed = $this->_getAlbumModel()->getUserMaximumAllowedAlbumCount();
		
		if ($maximumAlbumAllowed > 0 && $currentAlbumCount >= $maximumAlbumAllowed)
		{
			throw $this->_throwFriendlyNoPermission(
						new XenForo_Phrase(
							'sonnb_xengallery_you_reach_maximum_album_allowed',
							array(
								'limit' => $maximumAlbumAllowed
							)
						)
				);
		}
	}
	
	protected function _assertCanAddMorePhoto(array $album)
	{
		$maximumPhotoInAlbum = $this->_getPhotoModel()->getPhotoCountLimit();
	
		if ($maximumPhotoInAlbum > 0 && $album['photo_count'] >= $maximumPhotoInAlbum)
		{
			throw $this->_throwFriendlyNoPermission(
						new XenForo_Phrase(
							'sonnb_xengallery_you_reach_maximum_photo_in_album_allowed',
							array(
							     'count' => $maximumPhotoInAlbum
							)
						)
				);
		}
	}

	protected function _assertCanAddMoreVideo(array $album)
	{
		$maximumVideoInAlbum = $this->_getVideoModel()->getVideoCountLimit();

		if ($maximumVideoInAlbum > 0 && $album['video_count'] >= $maximumVideoInAlbum)
		{
			throw $this->_throwFriendlyNoPermission(
					new XenForo_Phrase(
						'sonnb_xengallery_you_reach_maximum_video_in_album_allowed',
						array(
						     'count' => $maximumVideoInAlbum
						)
					)
				);
		}
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
		$xenOptions = XenForo_Application::getOptions();

		if ($xenOptions->sonnbXG_showActivity)
		{
			$albumIds = array();
			foreach ($activities AS $activity)
			{
				if (!empty($activity['params']['album_id']))
				{
					$albumIds[$activity['params']['album_id']] = intval($activity['params']['album_id']);
				}
			}

			$albumData = array();

			if ($albumIds)
			{
				/* @var $albumModel sonnb_XenGallery_Model_Album */
				$albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');

				$albums = $albumModel->getAlbumsByIds($albumIds);
				$albums = $albumModel->prepareAlbums($albums);

				foreach ($albums AS $album)
				{
					if ($albumModel->canViewAlbum($album))
					{
						$album['title'] = XenForo_Helper_String::censorString($album['title']);

						$albumData[$album['album_id']] = array(
							'title' => $album['title'],
							'url' => XenForo_Link::buildPublicLink('gallery/albums', $album),
							'user' => $album['username']
						);
					}
				}
			}

			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$album = false;
				if (!empty($activity['params']['album_id']))
				{
					$albumId = $activity['params']['album_id'];
					if (isset($albumData[$albumId]))
					{
						$album = $albumData[$albumId];
					}
				}

				if ($album)
				{
					$output[$key] = array(
						' ',
						new XenForo_Phrase(
							'sonnb_xengallery_viewing_album_x_by_y',
							array(
								'title' => $album['title'],
								'link' => $album['url'],
								'user' => $album['user']
							)
						),
						$album['url'],
						''
					);
				}
				else
				{
					$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_album');
				}
			}
		}
		else
		{
			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_album');
			}
		}

		return $output;
	}
}