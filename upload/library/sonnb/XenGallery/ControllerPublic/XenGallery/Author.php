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
class sonnb_XenGallery_ControllerPublic_XenGallery_Author extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$user = $this->_getAuthorOrError();

		$albumModel = $this->_getAlbumModel();
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$albumPerPage = $xenOptions->sonnbXG_albumPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('gallery/authors', $user+array('page' => $page))
		);

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultAlbumSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$conditions = $this->_getDefaultAlbumConditions($user);
		$fetchElements = $this->_getAlbumFetchElements($conditions);

		$albumFetchConditions = $fetchElements['conditions'];
		$albumFetchOptions = $fetchElements['options'] + array(
			'perPage' => $albumPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalAlbums = $albumModel->countAlbums($albumFetchConditions, $albumFetchOptions);

		$this->canonicalizePageNumber($page, $albumPerPage, $totalAlbums, 'gallery/authors', $user);

		$albums = $albumModel->getAlbums($albumFetchConditions, $albumFetchOptions);
		$albums = $albumModel->prepareAlbums($albums);

		foreach ($albums as $_albumId => $_album)
		{
			if (!$albumModel->canViewAlbum($_album))
			{
				unset($albums[$_albumId]);
			}
		}

		$albums = $albumModel->attachCoversToAlbums($albums, $albumFetchOptions);

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		$viewParams = array(
			'user' => $user,

			'albums' => $albums,

			'order' => $order,
			'orderDirection' => $orderDirection,
			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'albumsPerPage' => $albumPerPage,
			'totalAlbums' => $totalAlbums,

			'canManageCover' => $this->_getGalleryModel()->canManageCover($user)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Author_Album',
			'sonnb_xengallery_author_albums',
			$viewParams
		);
	}

	public function actionPhotos()
	{
		$profileCall = $this->_input->inRequest('profile');
        $user = $this->_getAuthorOrError();;

		$photoModel = $this->_getPhotoModel();
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$photosPerPage = $xenOptions->sonnbXG_photoPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('gallery/authors/photos', $user+array('page' => $page))
		);

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultPhotoSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$conditions = $this->_getDefaultPhotoConditions($user);
		$fetchElements = $this->_getPhotoFetchElements($conditions);

		$photoFetchConditions = $fetchElements['conditions'];
		$photoFetchOptions = $fetchElements['options'] + array(
			'perPage' => $photosPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalPhotos = $photoModel->countContents($photoFetchConditions, $photoFetchOptions);

		$this->canonicalizePageNumber($page, $photosPerPage, $totalPhotos, 'gallery/authors/photos', $user);

		$photos = $photoModel->getContents($photoFetchConditions, $photoFetchOptions);
		$photos = $photoModel->prepareContents($photos, $photoFetchOptions);

		foreach ($photos as $_photoId => $_photo)
		{
			if (!$photoModel->canViewContentAndContainer($_photo, $_photo['album']))
			{
				unset($photos[$_photoId]);
			}
		}

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		$inlineModOptions = $this->_getContentModel()->getInlineModeration();

		$viewParams = array(
			'user' => $user,
			'inlineModOptions' => $inlineModOptions,

			'photos' => $photos,

			'profileCall' => $profileCall,

			'order' => $order,
			'orderDirection' => $orderDirection,
			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'photosPerPage' => $photosPerPage,
			'totalPhotos' => $totalPhotos,

			'canManageCover' => $this->_getGalleryModel()->canManageCover($user)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Author_Photo',
			'sonnb_xengallery_author_photos',
			$viewParams
		);
	}

	public function actionVideos()
	{
        $user = $this->_getAuthorOrError();

		$videoModel = $this->_getVideoModel();
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$videosPerPage = $xenOptions->sonnbXG_photoPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('gallery/authors/videos', $user+array('page' => $page))
		);

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultVideoSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$conditions = $this->_getDefaultVideoConditions($user);
		$fetchElements = $this->_getVideoFetchElements($conditions);

		$videoFetchConditions = $fetchElements['conditions'];
		$videoFetchOptions = $fetchElements['options'] + array(
			'perPage' => $videosPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalVideos = $videoModel->countContents($videoFetchConditions, $videoFetchOptions);

		$this->canonicalizePageNumber($page, $videosPerPage, $totalVideos, 'gallery/authors/videos', $user);

		$videos = $videoModel->getContents($videoFetchConditions, $videoFetchOptions);
		$videos = $videoModel->prepareContents($videos, $videoFetchOptions);

		foreach ($videos as $_videoId => $_video)
		{
			if (!$videoModel->canViewContentAndContainer($_video, $_video['album']))
			{
				unset($videos[$_videoId]);
			}
		}

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		$inlineModOptions = $this->_getContentModel()->getInlineModeration();

		$viewParams = array(
			'user' => $user,
			'inlineModOptions' => $inlineModOptions,

			'videos' => $videos,

			'order' => $order,
			'orderDirection' => $orderDirection,
			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'videosPerPage' => $videosPerPage,
			'totalVideos' => $totalVideos,

			'canManageCover' => $this->_getGalleryModel()->canManageCover($user)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Author_Video',
			'sonnb_xengallery_author_videos',
			$viewParams
		);
	}

	public function actionTags()
	{
        $user = $this->_getAuthorOrError();

		$tagModel = $this->_getTagModel();
		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$photosPerPage = $xenOptions->sonnbXG_photoPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('gallery/authors/tags', $user+array('page' => $page))
		);

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultTagSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$conditions = $this->_getDefaultTagConditions($user);
		$fetchElements = $this->_getTagFetchElements($conditions);

		$tagFetchConditions = $fetchElements['conditions'];
		$tagFetchOptions = $fetchElements['options'] + array(
			'perPage' => $photosPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalTags = $tagModel->countTags($tagFetchConditions, $tagFetchOptions);

		$this->canonicalizePageNumber($page, $photosPerPage, $totalTags, 'gallery/authors/tags', $user);

		$tags = $tagModel->getTags($tagFetchConditions, $tagFetchOptions);

		$photos = $pageNavParams = array();

		if (!empty($tags))
		{
			$photoIds = array();
			foreach ($tags as $_tag)
			{
				$photoIds[] = $_tag['content_id'];
			}

			$handler = sonnb_XenGallery_ContentHandler_Abstract::create(sonnb_XenGallery_Model_Photo::$contentType);
			$photos = $handler->getContentsByIds($photoIds);

			foreach ($photos as $_photoId => $_photo)
			{
				if (!$handler->canViewContent($_photo))
				{
					unset($photos[$_photoId]);
				}
			}

			$pageNavParams = array();
			$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
			$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);
		}

		$viewParams = array(
			'user' => $user,

			'photos' => $photos,
			'tags' => $tags,

			'order' => $order,
			'orderDirection' => $orderDirection,
			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'tagsPerPage' => $photosPerPage,
			'totalTags' => $totalTags,

			'canManageCover' => $this->_getGalleryModel()->canManageCover($user)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Author_Tag',
			'sonnb_xengallery_author_tags',
			$viewParams
		);
	}

	public function actionCoverUpload()
	{
		$this->_assertPostOnly();

		$cover = XenForo_Upload::getUploadedFile('cover');

        $user = $this->_getAuthorOrError();

		if (!$this->_getGalleryModel()->canManageCover($user))
		{
			throw $this->_throwFriendlyNoPermission();
		}

		$input = $this->_input->filter(array(
			'crop_x' => XenForo_Input::UINT,
			'crop_y' => XenForo_Input::UINT,

			'width' => XenForo_Input::UINT,
			'height' => XenForo_Input::UINT,

			'delete' => XenForo_Input::UINT
		));

		if ($cover)
		{
			$oldCover = $this->_getGalleryModel()->getAuthorCoverFile($user);
			$return = $this->_getGalleryModel()->uploadAuthorCover($cover, $user['user_id']);

			if ($return)
			{
				if (isset($user['sonnb_xengallery_cover']['bdattachmentstore_engine']))
				{
					$engine = $user['sonnb_xengallery_cover']['bdattachmentstore_engine'];
					$options = $user['sonnb_xengallery_cover']['bdattachmentstore_options'];
					$keepLocalCopy = !empty($options['keepLocalCopy']);

					if (empty($engine) || (!empty($engine) && $keepLocalCopy))
					{
						$oldLocalCover = $this->_getGalleryModel()->getAuthorCoverFile($user, true);
						@unlink($oldLocalCover);
					}

					if (!empty($engine))
					{
						$this->_bdAttachmentStore_getFileModel()->deleteFile($engine, $options, $oldCover);
					}
				}
				else
				{
					$oldLocalCover = $this->_getGalleryModel()->getAuthorCoverFile($user, true);
					@unlink($oldLocalCover);
				}
			}
		}
		elseif ($input['delete'])
		{
			$return = $this->_getGalleryModel()->deleteAuthorCover($user);
		}
		elseif ($input['crop_x'] || $input['crop_y'] || $input['height'])
		{
			$return = $this->_getGalleryModel()->cropAuthorCover($user, $input['crop_x'], $input['crop_y'], $input['width'], $input['height']);
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Author_CoverUpload',
				'sonnb_xengallery_cover_upload',
				array(
					'cover' => "",
					'url' => "",
					'message' => ""
				)
			);
		}

		if ($return === false)
		{
			throw $this->_throwFriendlyNoPermission('unexpected_error_occurred');
		}
		else
		{
			$user['sonnb_xengallery_cover'] = $return;
		}

		$message = new XenForo_Phrase('sonnb_xengallery_your_cover_has_been_saved');

		if ($this->_noRedirect())
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Author_CoverUpload',
				'sonnb_xengallery_cover_upload',
				array(
					'cover' => $user['sonnb_xengallery_cover'],
					'url' => $this->_getGalleryModel()->getAuthorCoverUrl($user),
					'message' => $message
				)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('gallery/authors'),
				$message
			);
		}
	}

	protected function _getDefaultAlbumConditions(array $user)
	{
		$conditions = array(
			'user_id' => $user['user_id']
		);

		return $conditions;
	}

	protected function _getDefaultPhotoConditions(array $user)
	{
		$conditions = array(
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
			'user_id' => $user['user_id']
		);

		return $conditions;
	}

	protected function _getDefaultVideoConditions(array $user)
	{
		$conditions = array(
			'content_type' => sonnb_XenGallery_Model_Video::$contentType,
			'user_id' => $user['user_id']
		);

		return $conditions;
	}

	protected function _getDefaultTagConditions(array $user)
	{
		$conditions = array(
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
			'user_id' => $user['user_id']
		);

		return $conditions;
	}

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

	protected function _getPhotoFetchElements(array $conditions)
	{
		$photoModel = $this->_getPhotoModel();
		$visitor = XenForo_Visitor::getInstance();

		$photoFetchConditions = $conditions + $photoModel->getPermissionBasedContentFetchConditions();
		$photoFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER
						| sonnb_XenGallery_Model_Photo::FETCH_DATA
						| sonnb_XenGallery_Model_Photo::FETCH_ALBUM,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);

		return array(
			'conditions' => $photoFetchConditions,
			'options' => $photoFetchOptions
		);
	}

	protected function _getVideoFetchElements(array $conditions)
	{
		$videoModel = $this->_getVideoModel();
		$visitor = XenForo_Visitor::getInstance();

		$videoFetchConditions = $conditions + $videoModel->getPermissionBasedContentFetchConditions();
		$videoFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_USER
						| sonnb_XenGallery_Model_Video::FETCH_DATA
						| sonnb_XenGallery_Model_Video::FETCH_ALBUM,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);

		return array(
			'conditions' => $videoFetchConditions,
			'options' => $videoFetchOptions
		);
	}

	protected function _getTagFetchElements(array $conditions)
	{
		$tagFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Tag::FETCH_USER,
			'tag_state' => 'accepted'
		);

		return array(
			'conditions' => $conditions,
			'options' => $tagFetchOptions
		);
	}

    protected function _getAuthorOrError($userId = null)
    {
        if ($userId === null)
        {
            $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        }

        if (!$userId)
        {
            $userId = XenForo_Visitor::getUserId();
        }

        $user = $this->getHelper('UserProfile')->getUserOrError($userId);
        $user = $this->getModelFromCache('XenForo_Model_User')->prepareUser($user);

        return $user;
    }

	protected function _getDefaultAlbumSort()
	{
		return array('album_date',  'desc');
	}

	protected function _getDefaultPhotoSort()
	{
		return array('content_date',  'desc');
	}

	protected function _getDefaultVideoSort()
	{
		return array('content_date',  'desc');
	}

	protected function _getDefaultTagSort()
	{
		return array('tag_date',  'desc');
	}

	protected function _getAlbumSortFields(array $album)
	{
		return array('title', 'album_date', 'album_updated_date', 'view_count', 'likes', 'comment_count');
	}

	protected function _getContentSortFields(array $content)
	{
		return array('position', 'content_date', 'content_updated_date', 'view_count', 'likes', 'comment_count');
	}
}