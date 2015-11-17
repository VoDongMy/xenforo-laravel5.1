<?php

class XenGallery_ControllerPublic_Abstract extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Enforce view permissions for all actions in this controller
	 *
	 * @see library/XenForo/XenForo_Controller#_preDispatch($action)
	 */
	protected function _preDispatch($action)
	{
		if (XenForo_Application::isRegistered('addOns'))
		{
			$addOns = XenForo_Application::get('addOns');
			if (!empty($addOns['XenGallery']) && $addOns['XenGallery'] < 901010000)
			{
				$response = $this->responseMessage(new XenForo_Phrase('board_currently_being_upgraded'));
				throw $this->responseException($response, 503);
			}
		}

		$options = XenForo_Application::getOptions();
		if ($options->xengalleryOverrideStyle)
		{
			$this->setViewStateChange('styleId', $options->xengalleryOverrideStyle);
		}

		if ($action != 'ContentLoader')
		{
			if (!$this->_getMediaModel()->canViewMedia($error))
			{
				throw $this->getErrorOrNoPermissionResponseException($error);
			}
		}
	}

	/**
	 * Adds 'xengalleryCategory' and 'xengalleryAlbum' to the list of $containerParams if it exists in $params
	 */
	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		if (isset($controllerResponse->params['category']))
		{
			$controllerResponse->containerParams['xengalleryCategory'] = $controllerResponse->params['category'];
		}

		if (isset($controllerResponse->params['album']))
		{
			$controllerResponse->containerParams['xengalleryAlbum'] = $controllerResponse->params['album'];
		}
	}

	protected function _getSiteMediaWrapper($selected, XenForo_ControllerResponse_View $subView)
	{
		$options = XenForo_Application::getOptions();
		$collapsible = $options->xengalleryCategoryStyle;

		$categoryModel = $this->_getCategoryModel();
		$mediaModel = $this->_getMediaModel();

		$canBypassMediaPrivacy = $mediaModel->canBypassMediaPrivacy();

		if (strpos($collapsible, 'collapsible') !== false || $collapsible != 'basic')
		{
			$categories = $categoryModel->getCategoryStructure();
			if (!$canBypassMediaPrivacy)
			{
				$categories = $categoryModel->removeUnviewableCategories($categories);
			}

			$categoryList = $categoryModel->applyRecursiveCountsToGrouped($categories);
		}
		else
		{
			$categories = $categoryModel->getAllCategories();
			if (!$canBypassMediaPrivacy)
			{
				$categories = $categoryModel->removeUnviewableCategories($categories);
			}

			$categoryList = $categoryModel->applyRecursiveCountsToGrouped($categoryModel->groupCategoriesByParent($categories));
			$categories = isset($categoryList[0]) ? $categoryList[0] : array();
		}

		$category = array();
		$childCategories = array();
		if ($selected)
		{
			$category = $categoryModel->getCategoryById($selected);

			$childCategories = (isset($categoryList[$category['category_id']])
				? $categoryList[$category['category_id']]
				: array()
			);
		}

		$showCategories = false;
		if (!$categories)
		{
			if ($canBypassMediaPrivacy)
			{
				$showCategories = true;
			}
		}

		$users = array();
		if (!empty($options->xengalleryShowTopContributors['enabled']))
		{
			$users = $this->_getMediaModel()->getTopContributors($options->xengalleryShowTopContributors['limit']);
		}

		$mediaHome = false;
		if (!empty($subView->params['mediaHome']))
		{
			$mediaHome = true;
		}

		$viewParams = array(
			'selected' => $selected,
			'mediaHome' => $mediaHome,
			'category' => $category,
			'categoryBreadcrumbs' => $category ? $categoryModel->getCategoryBreadcrumb($category, false) : array(),
			'categories' => $categories,
			'collapsible' => $collapsible,
			'showCategories' => $showCategories,
			'categoriesGrouped' => $categoryList,
			'childCategories' => $childCategories,
			'topContributors' => $users ? true : false,
			'users' => $users,
			'canViewAlbums' => $this->_getAlbumModel()->canViewAlbums()
		);

		$wrapper = $this->responseView('XenGallery_ViewPublic_Media_Wrapper', 'xengallery_category_wrapper', $viewParams);
		$wrapper->subView = $subView;

		return $wrapper;
	}

	protected function _getAlbumMediaWrapper(XenForo_ControllerResponse_View $subView, array $album = array())
	{
		$options = XenForo_Application::getOptions();
		$collapsible = $options->xengalleryCategoryStyle;

		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();
		$categoryModel = $this->_getCategoryModel();

		$canBypassMediaPrivacy = $mediaModel->canBypassMediaPrivacy();

		if (strpos($collapsible, 'collapsible') !== false || $collapsible != 'basic')
		{
			$categories = $categoryModel->getCategoryStructure();
			if (!$canBypassMediaPrivacy)
			{
				$categories = $categoryModel->removeUnviewableCategories($categories);
			}

			$categoryList = $categoryModel->applyRecursiveCountsToGrouped($categories);
		}
		else
		{
			$categories = $categoryModel->getAllCategories();
			if (!$canBypassMediaPrivacy)
			{
				$categories = $categoryModel->removeUnviewableCategories($categories);
			}

			$categoryList = $categoryModel->applyRecursiveCountsToGrouped($categoryModel->groupCategoriesByParent($categories));
			$categories = isset($categoryList[0]) ? $categoryList[0] : array();
		}

		$visitor = XenForo_Visitor::getInstance();

		$recentAlbums = array();
		if ($options->xengalleryShowRecentAlbums['enabled'])
		{
			$conditions = array(
				'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
				'privacyUserId' => $visitor->user_id,
				'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()),
				'album_media_count' => '> 0',
				'is_banned' => 0
			);
			$fetchOptions = array(
				'order' => 'new',
				'orderDirection' => 'desc',
				'limit' => $options->xengalleryShowRecentAlbums['limit'],
				'join' => XenGallery_Model_Album::FETCH_PRIVACY
					| XenGallery_Model_Album::FETCH_USER
			);

			$recentAlbums = $albumModel->getAlbums($conditions, $fetchOptions);
			$recentAlbums = $albumModel->prepareAlbums($recentAlbums);
		}

		$sharedMediaSelected = false;
		$ownMediaSelected = false;
		$albumsSelected = true;
		if (!empty($subView->params['ownMedia']))
		{
			$ownMediaSelected = true;
			$albumsSelected = false;
		}

		if (!empty($subView->params['sharedMedia']))
		{
			$sharedMediaSelected  = true;
			$albumsSelected = false;
		}

		$viewParams = array(
			'recentAlbums' => $recentAlbums,
			'album' => $album,
			'categories' => $categories,
			'collapsible' => $collapsible,
			'noCategories' => $categories ? false : true,
			'categoriesGrouped' => $categoryList,
			'categoryBreadcrumbs' => array(),
			'category' => array(),
			'albumsSelected' => $albumsSelected,
			'ownMediaSelected' => $ownMediaSelected,
			'sharedMediaSelected' => $sharedMediaSelected
		);

		$wrapper = $this->responseView('XenGallery_ViewPublic_Media_Wrapper', 'xengallery_album_wrapper', $viewParams);
		$wrapper->subView = $subView;

		return $wrapper;
	}

	/**
	 * Asserts that the viewing user can upload and manage XenGallery files.
	 *
	 * @param string $hash Unique hash
	 * @param string $contentType
	 * @param array $contentData
	 */
	protected function _assertCanUploadAndManageAttachments($hash, $contentType, array $contentData)
	{
		if (!$hash)
		{
			throw $this->getNoPermissionResponseException();
		}

		$attachmentHandler = $this->_getAttachmentModel()->getAttachmentHandler($contentType);
		if (!$attachmentHandler || !$attachmentHandler->canUploadAndManageAttachments($contentData))
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	/**
	 * Gets the specified attachment or throws an error.
	 *
	 * @param integer $attachment
	 *
	 * @return array
	 */
	protected function _getAttachmentOrError($attachmentId)
	{
		$attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
		if (!$attachment)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404));
		}

		return $attachment;
	}

	protected function _getMediaFetchOptions()
	{
		$visitor = XenForo_Visitor::getInstance();

		$mediaFetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM,
			'watchUserId' => $visitor->getUserId()
		);

		if ($visitor->hasPermission('xengallery', 'viewDeleted'))
		{
			$mediaFetchOptions['join'] |= XenGallery_Model_Media::FETCH_DELETION_LOG;
		}

		return $mediaFetchOptions;
	}

	protected function _getCommentFetchOptions()
	{
		$commentFetchOptions = array(
			'join' => XenGallery_Model_Comment::FETCH_ALBUM
				| XenGallery_Model_Comment::FETCH_USER
				| XenGallery_Model_Comment::FETCH_CATEGORY
				| XenGallery_Model_Comment::FETCH_MEDIA
				| XenGallery_Model_Comment::FETCH_RATING
		);

		if (XenForo_Visitor::getInstance()->hasPermission('xengallery', 'viewDeletedComments'))
		{
			$commentFetchOptions['join'] |= XenGallery_Model_Comment::FETCH_DELETION_LOG;
		}

		return $commentFetchOptions;
	}

	protected function _logChanges(XenForo_DataWriter $dw, array $content, $action, array $additionalChanges = array(), $contentType = 'xengallery_media', $userIdKey = 'user_id')
	{
		if ($dw->isUpdate() && !empty($content[$userIdKey]) && XenForo_Visitor::getUserId() != $content[$userIdKey])
		{
			$changes = array_merge($this->_getLogChanges($dw), $additionalChanges);

			if ($changes)
			{
				XenForo_Model_Log::logModeratorAction($contentType, $content, $action, $changes);
			}
		}
	}

	protected function _getLogChanges(XenForo_DataWriter $dw)
	{
		$newData = $dw->getMergedNewData();
		$oldData = $dw->getMergedExistingData();
		$changes = array();

		foreach ($newData AS $key => $newValue)
		{
			if (isset($oldData[$key]))
			{
				$changes[$key] = $oldData[$key];
			}
		}

		return $changes;
	}

	protected function _sendAuthorAlert(array $content, $contentType, $action, array $extra = array(), $userIdKey = 'user_id')
	{
		$options = array(
			'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
			'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
		);
		$this->_getMediaModel()->sendAuthorAlert($content, $contentType, $action, $options, $extra, $userIdKey);
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
		$mediaIds = array();
		$categoryIds = array();
		$userIds = array();
		$albumIds = array();

		/** @var XenGallery_Model_Media $mediaModel */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		if (!$mediaModel->canViewMedia())
		{
			return new XenForo_Phrase('xengallery_viewing_media');
		}

		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['media_id']))
			{
				$mediaIds[$activity['params']['media_id']] = intval($activity['params']['media_id']);
			}

			if (!empty($activity['params']['album_id']))
			{
				$albumIds[$activity['params']['album_id']] = intval($activity['params']['album_id']);
			}

			if (!empty($activity['params']['category_id']))
			{
				$categoryIds[$activity['params']['category_id']] = intval($activity['params']['category_id']);
			}

			if (!empty($activity['params']['user_id']))
			{
				$userIds[$activity['params']['user_id']] = intval($activity['params']['user_id']);
			}
		}

		$mediaData = array();
		if ($mediaIds)
		{
			$visitor = XenForo_Visitor::getInstance();

			$mediaConditions = array(
				'media_id' => $mediaIds,
				'privacyUserId' => $visitor->user_id,
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()),
			);
			$mediaFetchOptions = array(
				'join' => XenGallery_Model_Media::FETCH_USER
					| XenGallery_Model_Media::FETCH_ATTACHMENT
					| XenGallery_Model_Media::FETCH_CATEGORY
					| XenGallery_Model_Media::FETCH_ALBUM
					| XenGallery_Model_Media::FETCH_PRIVACY
			);
			$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
			foreach ($media AS $item)
			{
				$mediaData[$item['media_id']] = array(
					'title' => $item['media_title'],
					'url' => XenForo_Link::buildPublicLink('xengallery', $item)
				);
			}
		}

		$albumData = array();
		if ($albumIds)
		{
			/** @var XenGallery_Model_Album $albumModel */
			$albumModel = XenForo_Model::create('XenGallery_Model_Album');

			$albumConditions = array(
				'album_id' => $albumIds,
				'privacyUserId' => XenForo_Visitor::getUserId()
			);
			$albumFetchOptions = array(
				'join' => XenGallery_Model_Album::FETCH_USER
					| XenGallery_Model_Album::FETCH_PRIVACY
			);
			$albums = $albumModel->getAlbums($albumConditions, $albumFetchOptions);
			foreach ($albums AS $album)
			{
				$albumData[$album['album_id']] = array(
					'title' => $album['album_title'],
					'url' => XenForo_Link::buildPublicLink('xengallery/albums', $album)
				);
			}
		}

		$categoryData = array();
		if ($categoryIds)
		{
			/** @var XenGallery_Model_Category $categoryModel */
			$categoryModel = XenForo_Model::create('XenGallery_Model_Category');

			$categories = $categoryModel->getCategoriesByIds($categoryIds);
			foreach ($categories AS $category)
			{
				if ($categoryModel->canViewCategory($category))
				{
					$categoryData[$category['category_id']] = array(
						'title' => $category['category_title'],
						'url' => XenForo_Link::buildPublicLink('xengallery/categories', $category)
					);
				}
			}
		}

		$userData = array();
		if ($userIds)
		{
			$userModel = XenForo_Model::create('XenForo_Model_User');

			$users = $userModel->getUsersByIds($userIds);
			foreach ($users AS $user)
			{
				$userData[$user['user_id']] = array(
					'username' => $user['username'],
					'url' => XenForo_Link::buildPublicLink('xengallery/users', $user)
				);
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$media = false;
			if (!empty($activity['params']['media_id']))
			{
				$mediaId = $activity['params']['media_id'];
				if (isset($mediaData[$mediaId]))
				{
					$media = $mediaData[$mediaId];
				}
			}

			if ($media)
			{
				$output[$key] = array(
					new XenForo_Phrase('xengallery_viewing_media'),
					$media['title'],
					$media['url'],
					''
				);
			}

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
					new XenForo_Phrase('xengallery_viewing_media_album'),
					$album['title'],
					$album['url'],
					''
				);
			}

			$category = false;
			if (!empty($activity['params']['category_id']))
			{
				$categoryId = $activity['params']['category_id'];
				if (isset($categoryData[$categoryId]))
				{
					$category = $categoryData[$categoryId];
				}
			}

			if ($category)
			{
				$output[$key] = array(
					new XenForo_Phrase('xengallery_viewing_media_category'),
					$category['title'],
					$category['url'],
					''
				);
			}

			$user = false;
			if (!empty($activity['params']['user_id']))
			{
				$userId = $activity['params']['user_id'];
				if (isset($userData[$userId]))
				{
					$user = $userData[$userId];
				}
			}

			if ($user)
			{
				$output[$key] = array(
					new XenForo_Phrase('xengallery_viewing_media_user'),
					$user['username'],
					$user['url'],
					''
				);
			}

			if (!isset($output[$key]))
			{
				$output[$key] = new XenForo_Phrase('xengallery_viewing_media');
			}
		}

		return $output;
	}

	/**
	 * @return XenGallery_ControllerHelper_Media
	 */
	protected function _getMediaHelper()
	{
		return $this->getHelper('XenGallery_ControllerHelper_Media');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}

	/**
	 * @return XenGallery_Model_AlbumWatch
	 */
	protected function _getAlbumWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_AlbumWatch');
	}

	/**
	 * @return XenGallery_Model_CategoryWatch
	 */
	protected function _getCategoryWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_CategoryWatch');
	}

	/**
	 * @return XenGallery_Model_MediaWatch
	 */
	protected function _getMediaWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_MediaWatch');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenForo_Model_Like
	 */
	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}

	/**
	 * @return XenGallery_Model_Rating
	 */
	protected function _getRatingModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Rating');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenGallery_Model_File
	 */
	protected function _getFileModel()
	{
		return $this->getModelFromCache('XenGallery_Model_File');
	}

	/**
	 * @return XenGallery_Model_UserTag
	 */
	protected function _getUserTaggingModel()
	{
		return $this->getModelFromCache('XenGallery_Model_UserTag');
	}

	/**
	 * @return XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Field');
	}

	/**
	 * @return XenForo_Model_Draft
	 */
	protected function _getDraftModel()
	{
		return $this->getModelFromCache('XenForo_Model_Draft');
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}
}