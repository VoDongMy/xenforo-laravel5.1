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
class sonnb_XenGallery_ControllerPublic_XenGallery extends sonnb_XenGallery_ControllerPublic_Abstract
{
	protected $_allowedBBCodeSizes = array(
        sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL,
        sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM,
        sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE
    );

	public function actionIndex()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$albumPerPage = $xenOptions->sonnbXG_albumPerPage;
		
		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('gallery', array('page' => $page)));

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING);

        $loadedContent = $xenOptions->sonnbXG_loadedContent;
        $loadedContentId = $xenOptions->sonnbXG_loadedContentId;

        switch($loadedContent)
        {
            case 'category':
                $action = 'index';
                if (!empty($loadedContentId))
                {
                    $this->_request->setParam('category_id', $loadedContentId);
                    $action = 'view';
                }

                return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_Category', $action);
                break;
            case 'collection':
                $action = 'index';
                if (!empty($loadedContentId))
                {
                    $this->_request->setParam('collection_id', $loadedContentId);
                    $action = 'view';
                }

                return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_Collection', $action);
                break;
            case 'photo':
                return $this->responseReroute(__CLASS__, 'new-photos');
                break;
            case 'video':
                return $this->responseReroute(__CLASS__, 'new-videos');
                break;
        }

		$this->getAlbumSort($order, $orderDirection, $defaultOrder, $defaultOrderDirection);

		$albumModel = $this->_getAlbumModel();
		$conditions = $this->_getDefaultConditions();
		$fetchElements = $this->_getAlbumFetchElements($conditions);
		
		$albumFetchConditions = $fetchElements['conditions'];
		$albumFetchOptions = $fetchElements['options'] + array(
			'perPage' => $albumPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);
		
		$totalAlbums = $albumModel->countAlbums($albumFetchConditions);

		$this->canonicalizePageNumber($page, $albumPerPage, $totalAlbums, 'gallery');
		
		$albums = $albumModel->getAlbums($albumFetchConditions, $albumFetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $albumFetchOptions);

		foreach ($albums as $albumId=>$album)
		{
			if (!$albumModel->canViewAlbum($album))
			{
				unset($albums[$albumId]);
			}
		}
		
		$albums = $albumModel->attachCoversToAlbums($albums, $albumFetchOptions);

		if ($this->_routeMatch->getResponseType() === 'rss' && $xenOptions->sonnbXG_enableRSS)
		{
			$albums = $albumModel->attachContentsToAlbums($albums);
		}

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		$topAlbums = array();
		if ($totalAlbums > 50)
		{
			//TODO: Get top albums grid
			//$topAlbums = array_slice($albums, 0, 12, true);
		}
		
		$viewParams = array(		
			'albums' => $albums,

			'displayMostViewed' => false,

			'topAlbums' => $topAlbums,

			'order' => $order,
			'orderDirection' => $orderDirection,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),

			'albumsPerPage' => $albumPerPage,
			'totalAlbums' => $totalAlbums,
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_List', 
			'sonnb_xengallery_gallery_view', 
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionNewAlbums()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$albumPerPage = $xenOptions->sonnbXG_albumPerPage;

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('gallery/new-albums', array('page' => $page)));

		$albumModel = $this->_getAlbumModel();
		$conditions = $this->_getDefaultConditions();
		$fetchElements = $this->_getAlbumFetchElements($conditions);

		$albumFetchConditions = $fetchElements['conditions'];
		$albumFetchOptions = $fetchElements['options'] + array(
			'perPage' => $albumPerPage,
			'page' => $page,
			'order' => 'album_date',
			'orderDirection' => 'desc'
		);

		$totalAlbums = $albumModel->countAlbums($albumFetchConditions);

		$this->canonicalizePageNumber($page, $albumPerPage, $totalAlbums, 'gallery/new-albums');

		$albums = $albumModel->getAlbums($albumFetchConditions, $albumFetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $albumFetchOptions);

		foreach ($albums as $albumId=>$album)
		{
			if (!$albumModel->canViewAlbum($album))
			{
				unset($albums[$albumId]);
			}
		}

		$albums = $this->_getAlbumModel()->attachCoversToAlbums($albums, $albumFetchOptions);

		$pageNavParams = $conditions;

		$viewParams = array(
			'albums' => $albums,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'albumsPerPage' => $albumPerPage,
			'totalAlbums' => $totalAlbums,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Album_FindNew',
			'sonnb_xengallery_gallery_new_albums',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionNewPhotos()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$photoPerPage = $xenOptions->sonnbXG_photoPerPage;

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/new-photos';
		if ($xenOptions->sonnbXG_loadedContent === 'photo' &&
			$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}
		else
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink($linkPrefix, array('page' => $page)));
		}

		$photoModel = $this->_getPhotoModel();

		$photoFetchConditions = $photoModel->getPermissionBasedContentFetchConditions()
		+ array(
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType
		);
		$photoFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER | sonnb_XenGallery_Model_Photo::FETCH_ALBUM |
				sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'likeUserId' => XenForo_Visitor::getUserId(),
			'followingUserId' => XenForo_Visitor::getUserId(),
			'perPage' => $photoPerPage,
			'page' => $page,
			'order' => 'content_date',
			'orderDirection' => 'desc'
		);

		$totalPhotos = $photoModel->countContents($photoFetchConditions);

		$this->canonicalizePageNumber($page, $photoPerPage, $totalPhotos, 'gallery/new-photos');

		$contents = $photoModel->getContents($photoFetchConditions, $photoFetchOptions);
		$contents = $photoModel->prepareContents($contents, $photoFetchOptions);

		foreach ($contents as $contentId => $content)
		{
			if (!$photoModel->canViewContentAndContainer($content, $content['album']))
			{
				unset($contents[$contentId]);
			}
		}

		$pageNavParams = array();

		$viewParams = array(
			'contents' => $contents,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'photosPerPage' => $photoPerPage,
			'totalPhotos' => $totalPhotos,

			'linkPrefix' => $linkPrefix,
			'isGalleryIndex' => $isGalleryIndex,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Photo_FindNew',
			'sonnb_xengallery_gallery_new_photos',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionNewVideos()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$videoPerPage = $xenOptions->sonnbXG_photoPerPage;

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/new-videos';
		if ($xenOptions->sonnbXG_loadedContent === 'video' &&
			$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}
		else
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink($linkPrefix, array('page' => $page)));
		}

		$videoModel = $this->_getVideoModel();

		$videoFetchConditions = $videoModel->getPermissionBasedContentFetchConditions()
			+ array(
				'content_type' => sonnb_XenGallery_Model_Video::$contentType
			);
		$videoFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER | sonnb_XenGallery_Model_Photo::FETCH_ALBUM |
				sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'likeUserId' => XenForo_Visitor::getUserId(),
			'followingUserId' => XenForo_Visitor::getUserId(),
			'perPage' => $videoPerPage,
			'page' => $page,
			'order' => 'content_date',
			'orderDirection' => 'desc'
		);

		$totalVideos = $videoModel->countContents($videoFetchConditions);

		$this->canonicalizePageNumber($page, $videoPerPage, $totalVideos, 'gallery/new-photos');

		$contents = $videoModel->getContents($videoFetchConditions, $videoFetchOptions);
		$contents = $videoModel->prepareContents($contents, $videoFetchOptions);

		foreach ($contents as $contentId => $content)
		{
			if (!$videoModel->canViewContentAndContainer($content, $content['album']))
			{
				unset($contents[$contentId]);
			}
		}

		$pageNavParams = array();

		$viewParams = array(
			'contents' => $contents,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'videosPerPage' => $videoPerPage,
			'totalVideos' => $totalVideos,

			'linkPrefix' => $linkPrefix,
			'isGalleryIndex' => $isGalleryIndex,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Video_FindNew',
			'sonnb_xengallery_gallery_new_videos',
			$viewParams
		);
	}

	public function actionNewComments()
	{
		//TODO:
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function actionExploreUser()
	{
		if ($this->isConfirmedPost())
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

			if (empty($username))
			{
				throw $this->_throwFriendlyNoPermission('sonnb_xengallery_please_enter_username');
			}

			/* @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');

			$user = $userModel->getUserByName($username);

			if (!$user)
			{
				throw $this->_throwFriendlyNoPermission('sonnb_xengallery_username_not_valid_please_type_correct_name');
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('gallery/authors', $user),
				''
			);
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Gallery_ExploreUser',
				'sonnb_xengallery_explore_user'
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditor()
	{
		$styleId = $this->_input->filterSingle('style', XenForo_Input::UINT);
		if ($styleId)
		{
			$this->setViewStateChange('styleId', $styleId);
		}

		$viewParams = array(
			'jQuerySource' => XenForo_Dependencies_Public::getJquerySource(),
			'jQuerySourceLocal' => XenForo_Dependencies_Public::getJquerySource(true),
			'javaScriptSource' => XenForo_Application::$javaScriptUrl
		);

		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);
		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);

		if (!in_array($type, array('album', 'content')))
		{
			$type = 'album';
		}

		if (empty($url))
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Editor_Attach',
				'sonnb_xengallery_editor_'.$type,
				$viewParams
			);
		}
		else
		{
			$id = $this->_input->filterSingle('url', XenForo_Input::UINT);
			$size = $this->_input->filterSingle('size', XenForo_Input::STRING);
			if (!in_array($size, $this->_allowedBBCodeSizes))
			{
				$size = sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM;
			}

			$message = $bbcode = '';

			if ($id)
			{
				switch ($type)
				{
					case 'content':
						$fetchOptions = array(
							'join' => sonnb_XenGallery_Model_Content::FETCH_ALBUM
						);

						$content = $this->_getContentModel()->getContentById($id, $fetchOptions);
						$content = $this->_getContentModel()->prepareContent($content, $fetchOptions);
						if (empty($content))
						{
							$message = new XenForo_Phrase('sonnb_xengallery_requested_photo_video_does_not_exist');
						}
						elseif (!$this->_getContentModel()->canViewContentAndContainer($content, $content['album'], $errorKey))
						{
							$message = new XenForo_Phrase($errorKey);
						}
						else
						{
							if (!empty($content['content_id']))
							{
								switch ($content['content_type'])
								{
									case sonnb_XenGallery_Model_Photo::$contentType:
										$bbcode = "[photo=\"$size\"]$content[content_id][/photo]";
										break;
									case sonnb_XenGallery_Model_Video::$contentType:
										$bbcode = "[video]$content[content_id][/video]";
										break;
								}
							}
						}
						break;
					case 'album':
					default:
						$album = $this->_getAlbumModel()->getAlbumById($id);
						$album = $this->_getAlbumModel()->prepareAlbum($album);

						if (empty($album))
						{
							$message = new XenForo_Phrase('sonnb_xengallery_requested_album_does_not_exist');
						}
						elseif (!$this->_getAlbumModel()->canViewAlbum($album, $errorKey))
						{
							$message = new XenForo_Phrase($errorKey);
						}
						else
						{
							if (!empty($album['album_id']))
							{
								$bbcode = "[album=\"$size\"]$album[album_id][/album]";
							}
						}
						break;
				}
			}
			else
			{
				$url = $this->_getValidUrl($url);
				$routeUrlMatch = $this->parseRouteUrl($url);

				if ($routeUrlMatch)
				{
					switch ($type)
					{
						case 'content':
							if (!empty($routeUrlMatch['params']['content_id']))
							{
								$contentId = intval($routeUrlMatch['params']['content_id']);

								$fetchOptions = array(
									'join' => sonnb_XenGallery_Model_Content::FETCH_ALBUM
								);

								$content = $this->_getContentModel()->getContentById($contentId, $fetchOptions);
								$content = $this->_getContentModel()->prepareContent($content, $fetchOptions);
								if (empty($content))
								{
									$message = new XenForo_Phrase('sonnb_xengallery_requested_photo_video_does_not_exist');
								}
								elseif (!$this->_getContentModel()->canViewContentAndContainer($content, $content['album'], $errorKey))
								{
									$message = new XenForo_Phrase($errorKey);
								}
								else
								{
									if (!empty($content['content_id']))
									{
										switch ($content['content_type'])
										{
											case sonnb_XenGallery_Model_Photo::$contentType:
												$bbcode = "[photo=\"$size\"]$content[content_id][/photo]";
												break;
											case sonnb_XenGallery_Model_Video::$contentType:
												$bbcode = "[video]$content[content_id][/video]";
												break;
										}
									}
								}
							}
							else
							{
								$message = new XenForo_Phrase('sonnb_xengallery_your_url_is_not_valid_photo_video_url');
							}
							break;
						case 'album':
							if (!empty($routeUrlMatch['params']['album_id']))
							{
								$albumId = intval($routeUrlMatch['params']['album_id']);

								$album = $this->_getAlbumModel()->getAlbumById($albumId);
								$album = $this->_getAlbumModel()->prepareAlbum($album);

								if (empty($album))
								{
									$message = new XenForo_Phrase('sonnb_xengallery_requested_album_does_not_exist');
								}
								elseif (!$this->_getAlbumModel()->canViewAlbum($album, $errorKey))
								{
									$message = new XenForo_Phrase($errorKey);
								}
								else
								{
									if (!empty($album['album_id']))
									{
										$bbcode = "[album=\"$size\"]$album[album_id][/album]";
									}
								}
							}
							else
							{
								$message = new XenForo_Phrase('sonnb_xengallery_your_url_is_not_valid_album_url');
							}
							break;
						default:
							$message = new XenForo_Phrase('sonnb_xengallery_your_url_is_invalid');
							break;
					}
				}
				else
				{
					$message = new XenForo_Phrase('sonnb_xengallery_your_url_is_invalid');
				}
			}

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Editor_Insert',
				'',
				array(
					'message' => $message,
					'bbcode' => $bbcode
				)
			);
		}
	}

	/**
	 * @param $url
	 * @return bool|string
	 */
	protected function _getValidUrl($url)
	{
		$url = trim($url);

		if (!$url)
		{
			return false;
		}

		switch ($url[0])
		{
			case '#':
			case '/':
			case ' ':
			case "\r":
			case "\n":
				return false;
		}

		if (preg_match('/\r?\n/', $url))
		{
			return false;
		}

		if (preg_match('#^(https?|ftp)://#i', $url))
		{
			return $url;
		}

		return 'http://' . $url;
	}

	/**
	 * @return array
	 */
	protected function _getDefaultConditions()
	{
		return array(
			'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL,
			'content_count' => array('>', 0)
		);
	}

	/**
	 * @param array $conditions
	 * @return array
	 */
	protected function _getAlbumFetchElements(array $conditions)
	{
		$albumModel = $this->_getAlbumModel();
		$visitor = XenForo_Visitor::getInstance();
	
		$albumFetchConditions = $conditions + $albumModel->getPermissionBasedAlbumFetchConditions();

		$albumFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER |
						sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);
	
		return array(
			'conditions' => $albumFetchConditions,
			'options' => $albumFetchOptions
		);
	}

	/**
	 * @param array $album
	 * @return array
	 */
	protected function _getAlbumSortFields(array $album)
	{
		return array('title', 'album_date', 'album_updated_date', 'view_count', 'likes', 'comment_count');
	}

	/**
	 * @param array $activities
	 * @return mixed|XenForo_Phrase
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('sonnb_xengallery_viewing_gallery');
	}
}