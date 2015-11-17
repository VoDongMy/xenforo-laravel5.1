<?php

class XenGallery_ControllerPublic_Watched extends XFCP_XenGallery_ControllerPublic_Watched
{
	protected function _preDispatch($action)
	{
		if ($action == 'Media' || $action == 'Categories' || $action == 'Albums')
		{
			$options = XenForo_Application::getOptions();
			if ($options->xengalleryOverrideStyle)
			{
				$this->setViewStateChange('styleId', $options->xengalleryOverrideStyle);
			}
		}

		return parent::_preDispatch($action);
	}

	public function actionAlbumsAll()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('watched/albums')
		);
	}
	
	public function actionAlbums()
	{
		$this->_routeMatch->setSections('xengallery');

		$albumWatchModel = $this->_getAlbumWatchModel();
		$albumModel = $this->_getAlbumModel();

		if (!$albumModel->canWatchAlbum())
		{
			throw $this->getErrorOrNoPermissionResponseException();
		}

		$visitor = XenForo_Visitor::getInstance();

		$defaultOrder = 'album_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$albums = array();
		$conditions = array();
		$fetchOptions = array();

		$albumIds = $albumWatchModel->getUserAlbumWatchByUser($visitor['user_id']);
		if ($albumIds)
		{
			$conditions = array(
				'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
				'privacyUserId' => $visitor->user_id,
				'viewCategoryIds' => $this->getModelFromCache('XenGallery_Model_Media')->getViewableCategoriesForVisitor(),
				'album_id' => array_keys($albumIds)
			);
			$fetchOptions = array(
				'order' => $order ? $order : $defaultOrder,
				'orderDirection' => 'desc',
				'page' => $page,
				'perPage' => $perPage,
				'join' => XenGallery_Model_Album::FETCH_PRIVACY
					| XenGallery_Model_Album::FETCH_USER
			);
			$albums = $albumModel->getAlbums($conditions, $fetchOptions);
			$albums = $albumModel->prepareAlbums($albums);

			foreach ($albums AS $albumId => &$album)
			{
				$album = array_merge($albumIds[$albumId], $album);
			}
		}

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false)
		);

		$viewParams = array(
			'albums' => $albums,
			'albumCount' => $albums ? $albumModel->countAlbums($conditions, $fetchOptions) : 0,

			'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'order' => $order,
			'defaultOrder' => $defaultOrder,

			'page' => $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'watchPage' => true,
			'hideFilterMenu' => true
		);

		return $this->responseView('XenGallery_ViewPublic_Watched_Albums', 'xengallery_watch_albums', $viewParams);
	}

	public function actionAlbumsUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'album_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getAlbumWatchModel()->getUserAlbumWatchByAlbumIds(XenForo_Visitor::getUserId(), $input['album_ids']);
		foreach ($watch AS $albumWatch)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumWatch');
			$dw->setExistingData($albumWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('send_email', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('send_email', 0);
					$dw->save();
					break;

				case 'alert':
					$dw->set('send_alert', 1);
					$dw->save();
					break;

				case 'no_alert':
					$dw->set('send_alert', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/albums'))
		);
	}

	public function actionMediaAll()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('watched/media')
		);
	}

	public function actionMedia()
	{
		$this->_routeMatch->setSections('xengallery');

		$mediaWatchModel = $this->_getMediaWatchModel();
		$mediaModel = $this->_getMediaModel();

		if (!$mediaModel->canWatchMedia())
		{
			throw $this->getErrorOrNoPermissionResponseException();
		}

		$visitor = XenForo_Visitor::getInstance();

		$defaultOrder = 'media_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$container = $this->_input->filterSingle('container', XenForo_Input::STRING);
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$media = array();
		$conditions = array();
		$fetchOptions = array();

		$mediaIds = $mediaWatchModel->getUserMediaWatchByUser($visitor['user_id']);
		if ($mediaIds)
		{
			$conditions = array(
				'deleted' => $mediaModel->canViewDeletedMedia(),
				'privacyUserId' => $visitor->user_id,
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor(),
				'media_id' => array_keys($mediaIds),
				'container' => $container,
				'type' => $type
			);
			$fetchOptions = array(
				'order' => $order ? $order : $defaultOrder,
				'orderDirection' => 'desc',
				'page' => $page,
				'perPage' => $perPage,
				'join' => XenGallery_Model_Media::FETCH_PRIVACY
					| XenGallery_Model_Media::FETCH_USER
					| XenGallery_Model_Media::FETCH_ATTACHMENT
					| XenGallery_Model_Media::FETCH_CATEGORY
					| XenGallery_Model_Media::FETCH_ALBUM
			);
			$media = $mediaModel->getMedia($conditions, $fetchOptions);
			foreach ($media AS $mediaId => &$_media)
			{
				$_media = array_merge($mediaIds[$mediaId], $_media);
				$_media = $mediaModel->prepareMedia($_media);
			}
		}

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false),
			'container' => ($container ? $container : false),
			'type' => ($type ? $type : false)
		);

		$viewParams = array(
			'media' => $media,
			'mediaCount' => $media ? $mediaModel->countMedia($conditions, $fetchOptions) : 0,

			'canViewRatings' => $mediaModel->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'order' => $order,
			'defaultOrder' => $defaultOrder,
			'container' => $container,
			'containerFilter' => $container,
			'type' => $type,
			'typeFilter' => $type,

			'page' => $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'watchPage' => true,
			'showFilterTabs' => true
		);

		return $this->responseView('XenGallery_ViewPublic_Watched_Media', 'xengallery_watch_media', $viewParams);
	}

	public function actionMediaUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'media_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getMediaWatchModel()->getUserMediaWatchByMediaIds(XenForo_Visitor::getUserId(), $input['media_ids']);
		foreach ($watch AS $mediaWatch)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_MediaWatch');
			$dw->setExistingData($mediaWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('send_email', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('send_email', 0);
					$dw->save();
					break;

				case 'alert':
					$dw->set('send_alert', 1);
					$dw->save();
					break;

				case 'no_alert':
					$dw->set('send_alert', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/media'))
		);
	}

	public function actionCategoriesAll()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('watched/categories')
		);
	}

	public function actionCategories()
	{
		$this->_routeMatch->setSections('xengallery');

		$categoryWatchModel = $this->_getGalleryCategoryWatchModel();
		$categoryModel = $this->_getCategoryModel();

		if (!$categoryModel->canWatchCategory())
		{
			throw $this->getErrorOrNoPermissionResponseException();
		}

		$visitor = XenForo_Visitor::getInstance();

		$categoryList = $categoryModel->groupCategoriesByParent(
			$categoryModel->getViewableCategories()
		);
		$categoryList = $categoryModel->applyRecursiveCountsToGrouped($categoryList);

		$categories = array();
		$categoryIds = $categoryWatchModel->getUserCategoryWatchByUser($visitor['user_id']);
		if ($categoryIds)
		{
			$categories = $categoryModel->getCategoriesByIds($categoryIds);
			$categories = $categoryModel->prepareCategories($categories);

			foreach ($categories AS $categoryId => &$category)
			{
				if (isset($categoryIds[$categoryId]))
				{
					$category = array_merge($categoryIds[$categoryId], $category);
					$category['recursive_count'] = $categoryList[$category['parent_category_id']][$category['category_id']]['category_media_count'];
				}
				else
				{
					unset($categories[$categoryId]);
				}
			}
		}

		$viewParams = array(
			'categories' => $categories,
			'watchPage' => true
		);

		return $this->responseView('XenGallery_ViewPublic_Watched_Categories', 'xengallery_watch_categories', $viewParams);
	}

	public function actionCategoriesUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'category_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getGalleryCategoryWatchModel()->getUserCategoryWatchByCategoryIds(XenForo_Visitor::getUserId(), $input['category_ids']);
		foreach ($watch AS $categoryWatch)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_CategoryWatch');
			$dw->setExistingData($categoryWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('send_email', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('send_email', 0);
					$dw->save();
					break;

				case 'alert':
					$dw->set('send_alert', 1);
					$dw->save();
					break;

				case 'no_alert':
					$dw->set('send_alert', 0);
					$dw->save();
					break;

				case 'include_children':
					$dw->set('include_children', 1);
					$dw->save();
					break;

				case 'no_include_children':
					$dw->set('include_children', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/categories'))
		);
	}

	protected function _takeEmailAction(array $user, $action, $type, $id)
	{
		if ($type == '' || $type == 'xengallery_media')
		{
			if ($id)
			{
				$this->_getMediaWatchModel()->setMediaWatchState($user['user_id'], $id, $action);
			}
			else
			{
				$this->_getMediaWatchModel()->setMediaWatchStateForAll($user['user_id'], $action);
			}
		}

		if ($type == '' || $type == 'xengallery_album')
		{
			if ($id)
			{
				$this->_getAlbumWatchModel()->setAlbumWatchState($user['user_id'], $id, $action);
			}
			else
			{
				$this->_getAlbumWatchModel()->setAlbumWatchStateForAll($user['user_id'], $action);
			}
		}

		return parent::_takeEmailAction($user, $action, $type, $id);
	}

	protected function _getEmailActionConfirmPhrase(array $user, $action, $type, $id)
	{
		if ($type == 'xengallery_media')
		{
			if ($id)
			{
				return new XenForo_Phrase('xengallery_you_sure_want_update_notification_settings_for_one_media_item');
			}
			else
			{
				return new XenForo_Phrase('xengallery_you_sure_want_update_notification_settings_for_all_media');
			}
		}

		if ($type == 'xengallery_category')
		{
			if ($id)
			{
				return new XenForo_Phrase('xengallery_you_sure_want_update_notification_settings_for_one_category');
			}
			else
			{
				return new XenForo_Phrase('xengallery_you_sure_want_to_update_notification_settings_for_all_categories');
			}
		}

		if ($type == 'xengallery_album')
		{
			if ($id)
			{
				return new XenForo_Phrase('xengallery_you_sure_want_update_notification_settings_for_one_album');
			}
			else
			{
				return new XenForo_Phrase('xengallery_you_sure_want_to_update_notification_settings_for_all_albums');
			}
		}

		return parent::_getEmailActionConfirmPhrase($user, $action, $type, $id);
	}

	/**
	 * @return XenGallery_Model_AlbumWatch
	 */
	protected function _getAlbumWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_AlbumWatch');
	}

	/**
	 * @return XenGallery_Model_MediaWatch
	 */
	protected function _getMediaWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_MediaWatch');
	}

	/**
	 * @return XenGallery_Model_CategoryWatch
	 */
	protected function _getGalleryCategoryWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_CategoryWatch');
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}
}