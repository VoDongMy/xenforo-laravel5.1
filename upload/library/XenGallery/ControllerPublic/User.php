<?php

class XenGallery_ControllerPublic_User extends XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if ($userId)
		{
			return $this->responseReroute(__CLASS__, 'content');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery')
		);
	}

	public function actionAlbums()
	{
		$this->_getMediaHelper()->assertAlbumsAreViewable();

		$noWrapper = $this->_input->filterSingle('no_wrapper', XenForo_Input::STRING);

		$defaultOrder = 'album_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));

		$albumModel = $this->_getAlbumModel();
		$mediaModel = $this->_getMediaModel();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (!$userId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$user = $this->_getUserModel()->getUserById($userId);

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('xengallery/users/albums', $user));

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryAlbumMaxPerPage;

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
			'album_user_id' => $userId,
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
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

		$userPage = false;
		if ($userId == $visitor->user_id)
		{
			$userPage = true;
		}

		$inlineModOptions = $albumModel->prepareInlineModOptions($albums, $userPage);

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false)
		);

		$viewParams = array(
			'albums' => $albums,
			'albumPage' => true,
			'albumCount' => $albumModel->countAlbums($conditions, $fetchOptions),

			'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'order' => $order,
			'defaultOrder' => $defaultOrder,

			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'user' => $user,
			'ownMedia' => $userId == $visitor->getUserId() ? true : false,
			'noWrapper' => $noWrapper,
			'inlineModOptions' => $inlineModOptions,
			'hideFilterMenu' => true
		);

		$view = $this->responseView('XenGallery_ViewPublic_User_Albums', 'xengallery_user_albums', $viewParams);
		if ($noWrapper)
		{
			return $view;
		}
		else
		{
			return $this->_getAlbumMediaWrapper($view);
		}
	}

	public function actionContent()
	{
		$noWrapper = $this->_input->filterSingle('no_wrapper', XenForo_Input::STRING);

		$defaultOrder = 'media_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$container = $this->_input->filterSingle('container', XenForo_Input::STRING);
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY
		);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId, $userFetchOptions);

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('xengallery/users', $user));

		$mediaModel = $this->_getMediaModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'user_id' => $user['user_id'],
			'container' => $container,
			'type' => $type,
			'deleted' => XenForo_Permission::hasPermission(XenForo_Visitor::getInstance()->permissions, 'xengallery', 'viewDeleted'),
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
		);

		$fetchOptions = $this->_getMediaFetchOptions() + array(
			'order' => $order ? $order : $defaultOrder,
			'orderDirection' => 'desc',
			'page' => $page,
			'perPage' => $perPage
		);

		$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_ALBUM
			| XenGallery_Model_Media::FETCH_PRIVACY;

		$totalCount = $mediaModel->countMedia($conditions, $fetchOptions);

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		$userPage = false;
		if ($userId == $visitor->user_id)
		{
			$userPage = true;
		}

		$inlineModOptions = $mediaModel->prepareInlineModOptions($media, $userPage);

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false),
			'container' => ($container ? $container : false),
			'type' => ($type ? $type : false)
		);

		$viewParams = array(
			'canViewComments' => $this->_getCommentModel()->canViewComments(),
			'media' => $media,
			'user' => $user,

			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'order' => $order,
			'defaultOrder' => $defaultOrder,
			'container' => $container,
			'containerFilter' => $container,
			'type' => $type,
			'typeFilter' => $type,

			'mediaCount' => count($media),
			'totalCount' => $totalCount,
			'noWrapper' => $noWrapper,
			'showFilterTabs' => true,
			'inlineModOptions' => $inlineModOptions
		);

		$view = $this->responseView('XenGallery_ViewPublic_User_Media', 'xengallery_media_user', $viewParams);
		if ($noWrapper)
		{
			return $view;
		}
		else
		{
			return $this->_getSiteMediaWrapper('', $view);
		}
	}

	public function actionYourQuota()
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->user_id)
		{
			throw $this->getNoPermissionResponseException();
		}

		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$viewParams = array(
			'type' => $type,
			'uploadConstraints' => $this->_getMediaModel()->getUploadConstraints($type)
		);
		return $this->responseView('XenGallery_ViewPublic_User_YourQuota', 'xengallery_media_user_quota', $viewParams);
	}
}