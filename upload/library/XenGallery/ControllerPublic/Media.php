<?php

class XenGallery_ControllerPublic_Media extends XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$defaultOrder = 'media_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$container = $this->_input->filterSingle('container', XenForo_Input::STRING);
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if ($mediaId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			return $this->getGlobalMediaRss();
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$visitor = XenForo_Visitor::getInstance();

		$mediaModel = $this->_getMediaModel();

		$conditions = array(
			'deleted' => $mediaModel->canViewDeletedMedia(),
			'container' => $container,
			'type' => $type,
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => $this->_getAlbumModel()->canViewAlbums(),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()),
			'newerThan' => $mediaModel->getMediaHomeCutOff()
		);
		$fetchOptions = $this->_getMediaFetchOptions() + array(
			'order' => $order ? $order : $defaultOrder,
			'orderDirection' => 'desc',
			'page' => $page,
			'perPage' => $perPage
		);

		$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_PRIVACY;

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		$inlineModOptions = $mediaModel->prepareInlineModOptions($media);

		$ignoredNames = array();
		foreach ($media AS $item)
		{
			if (!empty($item['isIgnored']))
			{
				$ignoredNames[] = $item['username'];
			}
		}

		$mediaCount = $mediaModel->countMedia($conditions, $fetchOptions);

		$this->canonicalizePageNumber($page, $perPage, $mediaCount, 'xengallery');
		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('xengallery', null, array('page' => $page)));

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false),
			'container' => ($container ? $container : false),
			'type' => ($type ? $type : false)
		);

		$viewParams = array(
			'canAddMedia' => $mediaModel->canAddMedia(),
			'canViewRatings' => $mediaModel->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'media' => $media,
			'mediaCount' => $mediaCount,

			'mediaHome' => true,
			'userPage' => false,

			'ignoredNames' => array_unique($ignoredNames),

			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'order' => $order,
			'defaultOrder' => $defaultOrder,
			'container' => $container,
			'containerFilter' => $container,
			'type' => $type,
			'typeFilter' => $type,

			'time' => XenForo_Application::$time,
			'showFilterTabs' => true,
			'inlineModOptions' => $inlineModOptions
		);

		return $this->_getSiteMediaWrapper('',
			$this->responseView('XenGallery_ViewPublic_Media_HomeCategory', 'xengallery_media_index', $viewParams)
		);
	}

	/**
	 * Gets the data for the global media RSS feed.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function getGlobalMediaRss()
	{
		$mediaModel = $this->_getMediaModel();
		$visitor = XenForo_Visitor::getInstance()->toArray();

		$perPage = max(1, XenForo_Application::getOptions()->xengalleryMediaMaxPerPage);

		$conditions = array(
			'type' => 'all',
			'privacyUserId' => $visitor['user_id'],
			'viewAlbums' => XenForo_Permission::hasPermission($visitor['permissions'], 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor)
		);
		$fetchOptions = $this->_getMediaFetchOptions() + array(
			'order' => 'media_date',
			'orderDirection' => 'desc',
			'limit' => $perPage * 3
		);

		$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_PRIVACY;

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		foreach ($media AS $key => &$item)
		{
			if ($item['album_id'] > 0)
			{
				$albumModel = $this->_getAlbumModel();

				$item = $albumModel->prepareAlbumWithPermissions($item);
				if (!$albumModel->canViewAlbum($item, $null, $visitor))
				{
					unset ($media[$key]);
				}
			}

			if ($item['category_id'] > 0)
			{
				if (!$this->_getCategoryModel()->canViewCategory($item, $null, $visitor))
				{
					unset ($media[$key]);
				}
			}
		}
		$media = array_slice($media, 0, $perPage, true);
		$media = $mediaModel->prepareMediaItems($media);

		$viewParams = array(
			'media' => $media,
		);
		return $this->responseView('XenGallery_ViewPublic_Media_GlobalRss', '', $viewParams);
	}

	public function actionView()
	{
		$mediaModel = $this->_getMediaModel();
		$taggingModel = $this->_getUserTaggingModel();
		$commentModel = $this->_getCommentModel();
		$watermarkModel = $this->_getWatermarkModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();
		$fetchOptions = $this->_getMediaFetchOptions();
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId, $fetchOptions);

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('xengallery', $media));

		$media = $mediaModel->prepareMediaCustomFields($media);
		$media = $mediaModel->prepareMediaExifData($media);

		$mediaModel->markMediaViewed($media);
		$mediaModel->logMediaView($media['media_id']);

		$tags = $taggingModel->getAllTagsByMediaId($mediaId);
		$tags = $taggingModel->prepareTags($tags);

		$media['tags'] = $tags;

		$taggedUserIds = array();
		foreach ($tags AS $tag)
		{
			$taggedUserIds[] = $tag['user_id'];
		}

		$taggedUsers = $this->_getUserModel()->getUsersByIds($taggedUserIds);

		$tags = $taggingModel->mergeTagsWithUsers($tags, $taggedUsers);

		$likeModel = $this->_getLikeModel();
		$existingLike = $likeModel->getContentLikeByLikeUser('xengallery_media', $mediaId, XenForo_Visitor::getUserId());

		$liked = ($existingLike ? true : false);

		$media['likeUsers'] = isset($media['like_users']) ? unserialize($media['like_users']) : false;
		$media['like_date'] = ($liked ? XenForo_Application::$time : 0);

		$containerType = $media['album_id'] > 0 ? 'album' : 'category';

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
		);
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_PRIVACY
				| XenGallery_Model_Media::FETCH_ALBUM
		);

		$customOrder = ($media['album_default_order'] == 'custom' ? true : false);

		$prevMedia = $mediaModel->getNextPrevMedia($customOrder ? $media['position'] : $mediaId, $media[$containerType . '_id'], $containerType, 'prev', 1, $conditions, $fetchOptions, $customOrder);
		$nextMedia = $mediaModel->getNextPrevMedia($customOrder ? $media['position'] : $mediaId, $media[$containerType . '_id'], $containerType, 'next', 1, $conditions, $fetchOptions, $customOrder);

		$commentPage = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$commentsPerPage = XenForo_Application::getOptions()->xengalleryMaxCommentsPerPage;

		$moderated = $commentModel->canViewUnapprovedComment();
		if (!$moderated)
		{
			$moderated = XenForo_Visitor::getUserId();
		}

		$commentConditions = array(
			'media_id' => $mediaId,
			'deleted' => $commentModel->canViewDeletedComment(),
			'moderated' => $moderated
		);

		$commentFetchOptions = $this->_getCommentFetchOptions() + array(
			'page' => $commentPage,
			'perPage' => $commentsPerPage,
			'order' => 'comment_date',
			'orderDirection' => 'ASC',
			'content_type' => 'media',
		);

		$comments = $commentModel->getComments($commentConditions, $commentFetchOptions);
		$comments = $commentModel->prepareComments($comments);

		$inlineModOptions = $commentModel->prepareInlineModOptions($comments);

		$commentIgnoredNames = array();
		foreach ($comments AS $comment)
		{
			if (!empty($comment['isIgnored']))
			{
				$commentIgnoredNames[] = $comment['username'];
			}
		}
		$commentIgnoredNames = array_unique($commentIgnoredNames);

		$commentCount = $commentModel->countComments($commentConditions, $commentFetchOptions);

		$this->canonicalizePageNumber($commentPage, $commentsPerPage, $commentCount, 'xengallery', $media);

		$date = 0;
		if ($comments)
		{
			$date = $commentModel->getLatestDate(array(
				'content_id' => $mediaId,
				'content_type' => 'media'
			));
		}

		$linkParams = array();
		if ($commentPage)
		{
			$linkParams['page'] = $commentPage;
		}

		$viewParams = array(
			'media' => $media,
			'prev' => array_shift($prevMedia),
			'next' => array_shift($nextMedia),

			'comments' => $comments,
			'commentCount' => $commentCount,
			'contentId' => $mediaId,
			'contentType' => 'media',
			'content' => $media,
			'date' => $date,

			'commentIgnoredNames' => $commentIgnoredNames,
			'commentInlineModOptions' => $inlineModOptions,
			'draft' => $this->_getDraftModel()->getDraftByUserKey('media-' . $mediaId, $visitor->getUserId()),

			'liked' => $liked,

			'canViewTags' => $mediaModel->canViewTags(),
			'tags' => $tags,

			'canDownloadMedia' => $mediaModel->canDownloadMedia($media),
			'canSetAvatar' => $mediaModel->canSetAvatar($media),
			'canCropMedia' => $mediaModel->canCropMedia($media),
			'canTagMedia' => $mediaModel->canTagMedia($media),
			'canDeleteTag' => $mediaModel->canDeleteTag($media),
			'canAddWatermark' => $watermarkModel->canAddWatermark($media),
			'canRemoveWatermark' => $watermarkModel->canRemoveWatermark($media),
			'canRotateMedia' => $mediaModel->canRotateMedia($media),
			'canFlipMedia' => $mediaModel->canFlipMedia($media),
			'canEditMedia' => $mediaModel->canEditMedia($media),
			'canMoveMedia' => $mediaModel->canMoveMedia($media),
			'canDeleteMedia' => $mediaModel->canDeleteMedia($media),

			'canChangeThumbnail' => $mediaModel->canChangeThumbnail($media),
			'canWatchMedia' => $mediaModel->canWatchMedia(),
			'canApproveUnapprove' => $mediaModel->canApproveMedia($media) || $mediaModel->canUnapproveMedia($media),
			'canLikeMedia' => $mediaModel->canLikeMedia($media),
			'canViewRatings' => $mediaModel->canViewRatings(),
			'canRateMedia' => $mediaModel->canRateMedia($media),
			'canReport' => $visitor['user_id'] ? true : false,
			'canWarn' => $mediaModel->canWarnMediaItem($media),
			'canViewWarnings' => $this->getModelFromCache('XenForo_Model_User')->canViewWarnings(),
			'canViewIps' => XenForo_Permission::hasPermission($visitor->permissions, 'general', 'viewIps'),
			'canCleanSpam' => (XenForo_Permission::hasPermission($visitor->permissions, 'general', 'cleanSpam') && $this->_getUserModel()->couldBeSpammer($media)),
			'canViewComments' => $commentModel->canViewComments(),
			'canAddComment' => $commentModel->canAddComment(),
			'canEditTags' => $mediaModel->canEditTags($media),

			'commentPage' => $commentPage <= 1 ? '' : $commentPage,
			'commentsPerPage' => $commentsPerPage,
			'pageLink' => 'xengallery',

			'linkParams' => $linkParams,

			'fieldCacheType' => $media['category_id'] ? 'categoryFieldCache' : 'albumFieldCache',
			'fieldsCache' => $this->_getFieldModel()->getGalleryFieldCache(),

			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media, true),
			'externalThumbnailUrl' => isset($media['thumbnailUrl']) ? XenForo_Link::convertUriToAbsoluteUri($media['thumbnailUrl'], true) : ''
		);

		return $this->responseView('XenGallery_ViewPublic_Media_View', 'xengallery_media_view', $viewParams);
	}

	public function actionSaveDraft()
	{
		$this->_assertPostOnly();

		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddComment();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$key = 'media-' . $media['media_id'];

		$comment = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);

		if (!strlen($comment) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $this->_getDraftModel()->deleteDraft($key) || $forceDelete;
		}
		else
		{
			$this->_getDraftModel()->saveDraft($key, $comment);
			$draftSaved = true;
			$draftDeleted = false;
		}

		$viewParams = array(
			'comment' => $comment,
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XenGallery_ViewPublic_Media_SaveDraft', '', $viewParams);
	}

	public function actionWatch()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		if (!$mediaModel->canWatchMedia())
		{
			return $this->responseNoPermission();
		}

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		/** @var $mediaWatchModel XenGallery_Model_MediaWatch */
		$mediaWatchModel = $this->getModelFromCache('XenGallery_Model_MediaWatch');

		if ($this->isConfirmedPost())
		{
			if ($this->_input->filterSingle('stop', XenForo_Input::STRING))
			{
				$notifyOn = 'delete';
			}
			else
			{
				$notifyOn = $this->_input->filterSingle('notify_on', XenForo_Input::STRING);
			}

			$sendAlert = $this->_input->filterSingle('send_alert', XenForo_Input::BOOLEAN);
			$sendEmail = $this->_input->filterSingle('send_email', XenForo_Input::BOOLEAN);

			$mediaWatchModel->setMediaWatchState(
				XenForo_Visitor::getUserId(), $mediaId,
				$notifyOn, $sendAlert, $sendEmail
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media),
				null,
				array('linkPhrase' => ($notifyOn != 'delete' ? new XenForo_Phrase('xengallery_unwatch_media') : new XenForo_Phrase('xengallery_watch_media')))
			);
		}
		else
		{
			$mediaWatch = $mediaWatchModel->getUserMediaWatchByMediaId(
				XenForo_Visitor::getUserId(), $mediaId
			);

			$viewParams = array(
				'media' => $media,
				'mediaWatch' => $mediaWatch,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media),
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Watch', 'xengallery_media_watch', $viewParams);
		}
	}

	public function actionTags()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		if (!$mediaModel->canEditTags($media, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		/** @var XenForo_Model_Tag $tagModel */
		$tagModel = $this->getModelFromCache('XenForo_Model_Tag');
		$tagger = $tagModel->getTagger('xengallery_media');
		$tagger->setContent($media['media_id'])->setPermissionsFromContext($media, $media);

		$editTags = $tagModel->getTagListForEdit('xengallery_media', $media['media_id'], $tagger->getPermission('removeOthers'));

		if ($this->isConfirmedPost())
		{
			$tags = $this->_input->filterSingle('tags', XenForo_Input::STRING);
			if ($editTags['uneditable'])
			{
				// this is mostly a sanity check; this should be ignored
				$tags .= (strlen($tags) ? ', ' : '') . implode(', ', $editTags['uneditable']);
			}
			$tagger->setTags($tagModel->splitTags($tags));

			$errors = $tagger->getErrors();
			if ($errors)
			{
				return $this->responseError($errors);
			}

			$cache = $tagger->save();

			if ($this->_noRedirect())
			{
				$view = $this->responseView('', 'helper_tag_list', array(
					'tags' => $cache,
					'editUrl' => XenForo_Link::buildPublicLink('xengallery/tags', $media)
				));
				$view->jsonParams = array(
					'isTagList' => true,
					'redirect' => XenForo_Link::buildPublicLink('xengallery', $media)
				);
				return $view;
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery', $media)
				);
			}
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'tags' => $editTags,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media)
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Tags', 'xengallery_media_tags', $viewParams);
		}
	}

	public function actionFetch()
	{
		$mediaModel = $this->_getMediaModel();
		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$lastMediaId = $this->_input->filterSingle('last_media_id', XenForo_Input::UINT);

		if ($lastMediaId)
		{
			$mediaId = $lastMediaId;
		}

		$fetchOptions = $this->_getMediaFetchOptions();

		$media = $mediaHelper->assertMediaValidAndViewable($mediaId, $fetchOptions);

		$containerType = $media['category_id'] ? 'category' : 'album';
		$containerId = $containerType == 'category' ? $media['category_id'] : $media['album_id'];

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'deleted' => $mediaModel->canViewDeletedMedia(),
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
		);
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_PRIVACY
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_USER
		);

		$limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		$direction = $this->_input->filterSingle('direction', XenForo_Input::STRING);

		$prevMedia = array();
		$nextMedia = array();

		$customOrder = ($media['album_default_order'] == 'custom' ? true : false);

		if (!$direction || $direction == 'prev')
		{
			$prevMedia = $mediaModel->getNextPrevMedia($customOrder ? $media['position'] : $media['media_id'], $containerId, $containerType, 'prev', $limit ? $limit : 0, $conditions, $fetchOptions, $customOrder);
		}

		if (!$direction || $direction == 'next')
		{
			$nextMedia = $mediaModel->getNextPrevMedia($customOrder ? $media['position'] : $media['media_id'], $containerId, $containerType, 'next', $limit ? $limit : 0, $conditions, $fetchOptions, $customOrder);
		}

		$viewParams = array(
			'prevMedia' => array_reverse($mediaModel->prepareMediaItems($prevMedia)),
			'noMorePrev' => (count($prevMedia) < $limit),
			'nextMedia' => $mediaModel->prepareMediaItems($nextMedia),
			'noMoreNext' => (count($nextMedia) < $limit)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Fetch', '', $viewParams);
	}

	public function actionAdd()
	{
		$this->_getMediaHelper()->assertCanAddMedia();
		$albumModel = $this->_getAlbumModel();

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $this->_input->filterSingle('album', XenForo_Input::STRING);

		$canCreateAlbums = ($albumModel->canCreateAlbum() && $albumModel->canViewAlbums());
		$canAddMediaToAlbum = ($albumModel->canAddMediaToAlbum() && $albumModel->canViewAlbums());

		$categoryModel = $this->_getCategoryModel();

		$categories = $categoryModel->getCategoryStructure(
			$categoryModel->groupCategoriesByParent($categoryModel->getViewableCategories())
		);
		$categories = $categoryModel->prepareCategories($categories);

		$canAddMediaToCategory = false;
		foreach ($categories AS $category)
		{
			if ($category['canAddMedia'])
			{
				$canAddMediaToCategory = true;
				break;
			}
		}

		$viewParams = array(
			'categoryId' => $categoryId,
			'categories' => $categories,
			'canAddMediaToCategory' => $canAddMediaToCategory,
			'canCreateAlbums' => $canCreateAlbums,
			'canAddMediaToAlbum' => $canAddMediaToAlbum,
			'canEditTags' => $this->_getMediaModel()->canEditTags(),
			'albumId' => $albumId,
			'album' => $album
		);

		if ($canAddMediaToAlbum)
		{
			$albumConditions = array(
				'add_user_id' => XenForo_Visitor::getUserId()
			);
			$albumFetchOptions = array(
				'order' => 'media_count'
			);

			$albums = $albumModel->getAlbumsByAddPermission($albumConditions, $albumFetchOptions);
			list ($users, $groupedAlbums) = $albumModel->groupAlbumsByUser($albums);

			$viewParams = array_merge($viewParams, array(
				'groupedAlbums' => $groupedAlbums,
				'users' => $users,
				'canChangeViewPermission' => $albumModel->canChangeAlbumViewPerm($albumConditions)
			));
		}

		return $this->_getSiteMediaWrapper('',
			$this->responseView('XenGallery_ViewPublic_Media_Add', 'xengallery_media_add', $viewParams)
		);
	}

	public function actionSetAll()
	{
		$category = array();

		if ($categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT))
		{
			$categoryModel = $this->_getCategoryModel();
			$category = $categoryModel->getCategoryById($categoryId);
			$category = $categoryModel->prepareCategory($category);
		}

		$minTags = $category ? $category['min_tags'] : XenForo_Application::getOptions()->xengalleryAlbumMinTags;

		$viewParams = array(
			'type' => $this->_input->filterSingle('type', XenForo_Input::STRING),
			'canEditTags' => $this->_getMediaModel()->canEditTags(),
			'minTags' => $minTags,
		);
		return $this->responseView('XenGallery_ViewPublic_Media_SetAll', 'xengallery_media_set_all', $viewParams);
	}

	public function actionEdit()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if (!$mediaId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);
		$mediaHelper->assertCanEditMedia($media);
		$media = $mediaModel->prepareMedia($media);

		if ($media['album_id'])
		{
			$albumModel = $this->_getAlbumModel();

			$album = $albumModel->getAlbumById($media['album_id']);

			if ($album)
			{
				$album = $albumModel->prepareAlbum($album);
				$fieldCache = $album['albumFieldCache'];
			}
			else
			{
				$album = array(
					'canUploadImage' => $albumModel->canUploadImage(),
					'canUploadVideo' => $albumModel->canUploadVideo(),
					'canEmbedVideo' => $albumModel->canEmbedVideo()
				);
			}

			$container = $album;
			$containerType = 'album';

			$albumConditions = array(
				'album_user_id' => XenForo_Visitor::getUserId()
			);

			$containers = $albumModel->getAlbums($albumConditions);
		}
		else
		{
			$categoryModel = $this->_getCategoryModel();

			$category = $mediaHelper->assertCategoryValidAndViewable($media['category_id']);
			$category = $categoryModel->prepareCategory($category);

			$fieldCache = $category['categoryFieldCache'];

			$container = $category;
			$containerType = 'category';

			$containers = $categoryModel->getCategoryStructure();
		}

		$media['mediaType'] = $media['media_type'];

		/** @var XenForo_Model_Tag $tagModel */
		$tagModel = $this->getModelFromCache('XenForo_Model_Tag');
		$tagger = $tagModel->getTagger('xengallery_media');
		$tagger->setContent($media['media_id'])->setPermissionsFromContext($media, $media);

		$editTags = $tagModel->getTagListForEdit('xengallery_media', $media['media_id'], $tagger->getPermission('removeOthers'));

		$fieldModel = $this->_getFieldModel();
		$customFields = $fieldModel->prepareGalleryFields($fieldModel->getGalleryFields(array(), array('valueMediaId' => $mediaId)), true);

		$shownFields = array();
		foreach ($fieldCache AS $fields)
		{
			foreach ($fields AS $fieldId)
			{
				if (isset($customFields[$fieldId]))
				{
					$shownFields[$customFields[$fieldId]['display_group']][$fieldId] = $customFields[$fieldId];
				}
			}
		}

		$viewParams = array(
			'media' => $media,
			'editTags' => $editTags,

			'container' => $container,
			'containerType' => $containerType,
			'containers' => $containers,
			'containerBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($container),

			'canEditUrl' => $mediaModel->canEditEmbedUrl($media),
			'canMoveMedia' => $mediaModel->canMoveMedia($media),
			'canDeleteMedia' => $mediaModel->canDeleteMedia($media),
			'canEditTags' => $mediaModel->canEditTags($media),

			'minTags' => isset($container['min_tags']) ? $container['min_tags'] : XenForo_Application::getOptions()->xengalleryAlbumMinTags,

			'customFields' => $shownFields
		);

		if ($media['category_id'])
		{
			return $this->_getSiteMediaWrapper($media['category_id'],
				$this->responseView('XenGallery_ViewPublic_Media_Edit', 'xengallery_media_edit', $viewParams)
			);
		}
		else
		{
			return $this->_getAlbumMediaWrapper(
				$this->responseView('XenGallery_ViewPublic_Media_Edit', 'xengallery_media_edit', $viewParams)
			);
		}
	}

	public function actionMove()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if (!$mediaId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();

		$mediaModel = $this->_getMediaModel();
		$categoryModel = $this->_getCategoryModel();
		$albumModel = $this->_getAlbumModel();

		$media = $mediaHelper->assertMediaValidAndMovable($mediaId);
		$media = $mediaModel->prepareMedia($media);

		$moveToAnyAlbum = $mediaModel->canMoveMediaToAnyAlbum($media, $error);
		$canCreateAlbums = $albumModel->canCreateAlbum();

		if ($this->isConfirmedPost())
		{
			$redirectMessage = new XenForo_Phrase('xengallery_your_media_has_been_moved_successfully');

			$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
			if ($albumId)
			{
				$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
				if ($album['album_user_id'] != XenForo_Visitor::getUserId() && !$moveToAnyAlbum)
				{
					throw $this->getErrorOrNoPermissionResponseException($error);
				}
			}

			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
			if ($categoryId)
			{
				$category = $mediaHelper->assertCategoryValidAndViewable($categoryId);
			}

			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($media);

			$moved = false;
			if ($albumId || $categoryId)
			{
				if ($albumId)
				{
					$mediaWriter->bulkSet(array(
						'album_id' => $album['album_id'],
						'category_id' => 0,
						'media_privacy' => $album['access_type']
					));
				}
				else
				{
					$mediaWriter->bulkSet(array(
						'category_id' => $category['category_id'],
						'album_id' => 0,
						'media_privacy' => 'category'
					));
				}
				$moved = $mediaWriter->save();
			}
			else
			{
				$visitor = XenForo_Visitor::getInstance();

				if ($canCreateAlbums)
				{
					$albumInput = $this->_input->filter(array(
						'album_title' => XenForo_Input::STRING,
						'album_description' => XenForo_Input::STRING
					));

					$albumInput = $albumInput + array(
						'album_user_id' => $visitor->user_id,
						'album_username' => $visitor->username
					);

					$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');

					$albumWriter->setExtraData(
						XenGallery_DataWriter_Album::DATA_ACCESS_TYPE,
						$this->_input->filterSingle('album_privacy', XenForo_Input::STRING)
					);

					$albumWriter->bulkSet($albumInput);
					$albumWriter->save();
					$album = $albumModel->getAlbumById($albumWriter->get('album_id'));

					if ($albumModel->canWatchAlbum() && $visitor->xengallery_default_album_watch_state)
					{
						$albumWatchModel = $this->_getAlbumWatchModel();

						$notifyOn = 'media_comment';
						$sendAlert = true;
						$sendEmail = false;

						if ($visitor->xengallery_default_album_watch_state == 'watch_email')
						{
							$sendEmail = true;
						}

						$albumWatchModel->setAlbumWatchState($visitor->user_id, $albumWriter->get('album_id'), $notifyOn, $sendAlert, $sendEmail);
					}

					$mediaWriter->bulkSet(array(
						'album_id' => $album['album_id'],
						'category_id' => 0,
						'media_privacy' => $album['access_type']
					));
					$moved = $mediaWriter->save();

					$redirectMessage .= ' ' . new XenForo_Phrase('xengallery_and_your_album_has_been_created');
				}
			}

			if ($categoryId)
			{
				$container = 'category';
				$containerTitle = $category['category_title'];
			}
			else
			{
				$container = 'album';
				$containerTitle = $album['album_title'];
			}

			$this->_logChanges($mediaWriter, $media, 'move_to_' . $container, array('title' => $containerTitle));

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media),
				$moved ? $redirectMessage : ''
			);
		}
		else
		{
			$categories = $categoryModel->getCategoryStructure();
			$categories = $categoryModel->prepareCategories($categories);

			$albumConditions = array(
				'album_user_id' => XenForo_Visitor::getUserId()
			);
			$albums = $albumModel->getAlbums($albumConditions);

			$viewParams = array(
				'media' => $media,
				'categories' => $categories,
				'albums' => $albums,
				'canCreateAlbums' => $canCreateAlbums,
				'canChangeViewPermission' => $albumModel->canChangeAlbumViewPerm($albumConditions),
				'moveToAnyAlbum' => $moveToAnyAlbum
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Move', 'xengallery_media_move', $viewParams);
		}
	}

	public function actionDelete()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if (!$mediaId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		$media = $mediaModel->getMediaById($mediaId, $this->_getMediaFetchOptions());
		$media = $mediaModel->prepareMedia($media);
		$mediaHelper->assertCanDeleteMedia($media, $deleteType);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$writer->setExistingData($media['media_id']);

			$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);

			if ($hardDelete)
			{
				$writer->delete();
			}
			else
			{
				$writer->setExtraData(XenGallery_DataWriter_Media::DATA_DELETE_REASON, $reason);
				$writer->set('media_state', 'deleted');
				$writer->save();
			}

			$this->_sendAuthorAlert($media, 'xengallery_media', 'delete');
			$this->_logChanges($writer, $media, 'delete_' . $deleteType, array('reason' => $reason));

			$redirectLink = '';
			if ($media['album_id'])
			{
				$redirectLink = XenForo_Link::buildPublicLink('xengallery/albums', $media);
			}
			else if ($media['category_id'])
			{
				$redirectLink = XenForo_Link::buildPublicLink('xengallery/categories', $media);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirectLink,
				new XenForo_Phrase('xengallery_media_deleted_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'canHardDelete' => $mediaModel->canDeleteMedia($media, 'hard'),
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media)
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Delete', 'xengallery_media_delete', $viewParams);
		}
	}

	public function actionUndelete()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if (!$mediaId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		$media = $mediaModel->getMediaById($mediaId, $this->_getMediaFetchOptions());

		if ($media['media_state'] != 'deleted')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery', $media)
			);
		}

		$mediaHelper->assertCanDeleteMedia($media);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$writer->setExistingData($media['media_id']);

			$writer->set('media_state', 'visible');
			$writer->save();

			$this->_logChanges($writer, $media, 'undelete');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media)
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Undelete', 'xengallery_media_undelete', $viewParams);
		}
	}

	public function actionIp()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if (!$this->_getUserModel()->canViewIps($error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		$media['ip_id'] = $this->_getMediaModel()->getIpIdFromMediaId($mediaId);

		$ipInfo = $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($media);

		if (empty($ipInfo['contentIp']))
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'media' => $media,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media),
			'ipInfo' => $ipInfo
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Ip', 'xengallery_media_ip', $viewParams);
	}

	public function actionReport()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		if ($this->isConfirmedPost())
		{
			$reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$reportMessage)
			{
				return $this->responseError(new XenForo_Phrase('xengallery_please_enter_reason_for_reporting_this_media'));
			}

			$this->assertNotFlooding('report');

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('xengallery_media', $media, $reportMessage);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media),
				new XenForo_Phrase('xengallery_thank_you_for_reporting_this_media')
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media)
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Report', 'xengallery_media_report', $viewParams);
		}
	}

	public function actionApprove()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);

		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if (!$this->_getMediaModel()->canApproveMedia($media, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
		$dw->setExistingData($media['media_id']);
		$dw->set('media_state', 'visible');
		$dw->save();

		$this->_logChanges($dw, $media, 'approve');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media)
		);
	}

	public function actionUnapprove()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);

		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if (!$this->_getMediaModel()->canUnapproveMedia($media, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
		$dw->setExistingData($media['media_id']);
		$dw->set('media_state', 'moderated');
		$dw->save();

		$this->_logChanges($dw, $media, 'unapprove');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media)
		);
	}

	public function actionApproveTag()
	{
		$visitor = XenForo_Visitor::getInstance();

		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));
		$tagId = $this->_input->filterSingle('tag', XenForo_Input::UINT);

		$taggingModel = $this->_getUserTaggingModel();

		$tag = $taggingModel->getTagById($tagId);

		if (!$tag)
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_requested_tag_could_not_be_found'));
		}

		if ($visitor->user_id != $tag['user_id'])
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_you_cannot_approve_this_tag_request'));
		}

		if ($tag['tag_state'] != 'pending')
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_tag_request_has_already_been_processed'));
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_UserTag');
		$dw->setExistingData($tag);
		$dw->set('tag_state', 'approved');
		$dw->set('tag_state_date', XenForo_Application::$time);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect()
		);
	}

	public function actionRejectTag()
	{
		$visitor = XenForo_Visitor::getInstance();

		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));
		$tagId = $this->_input->filterSingle('tag', XenForo_Input::UINT);

		$taggingModel = $this->_getUserTaggingModel();

		$tag = $taggingModel->getTagById($tagId);

		if (!$tag)
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_requested_tag_could_not_be_found'));
		}

		if ($visitor->user_id != $tag['user_id'])
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_you_cannot_approve_this_tag_request'));
		}

		if ($tag['tag_state'] != 'pending')
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_tag_request_has_already_been_processed'));
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_UserTag');
		$dw->setExistingData($tag);
		$dw->set('tag_state', 'rejected');
		$dw->set('tag_state_date', XenForo_Application::$time);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect()
		);
	}

	public function actionFull()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if ($this->_input->filterSingle('lightbox', XenForo_Input::BOOLEAN))
		{
			$mediaModel = $this->_getMediaModel();
			$mediaModel->markMediaViewed($media);
			$mediaModel->logMediaView($media['media_id']);
		}

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xengallery/full', $media)
		);

		if ($media['media_type'] == 'video_embed' || $media['media_type'] == 'video_upload')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery', $media)
			);
		}

		$this->_request->setParam('attachment_id', $media['attachment_id']);
		$this->_request->setParam('no_canonical', 1);

		return $this->responseReroute('XenForo_ControllerPublic_Attachment', 'index');
	}

	public function actionDownload()
	{
		$mediaModel = $this->_getMediaModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);
		$media = $mediaModel->prepareMedia($media);

		if (!$mediaModel->canDownloadMedia($media, $error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		if ($media['media_type'] == 'video_embed')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery', $media)
			);
		}

		$filePath = $mediaModel->getAttachmentDataFilePath($media);

		if (!file_exists($filePath) || !is_readable($filePath))
		{
			return $this->responseError(new XenForo_Phrase('xengallery_media_cannot_be_downloaded'));
		}

		$this->_routeMatch->setResponseType('raw');

		$viewParams = array(
			'media' => $media,
			'mediaFile' => $filePath
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Download', '', $viewParams);
	}

	public function actionRotate()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$clockwise = $this->_input->filterSingle('clockwise', XenForo_Input::UINT);

		$rotation = 90;
		if ($clockwise)
		{
			$rotation = -90;
		}

		$mediaModel = $this->_getMediaModel();
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if (!$mediaModel->canRotateMedia($media, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$imageInfo = $mediaModel->rotateMedia($media, $rotation);
		if ($imageInfo)
		{
			$mediaModel->rebuildThumbnail($media, $imageInfo, true, $media['thumbnail_date']);

			if (XenForo_Visitor::getUserId() != $media['user_id'])
			{
				XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'rotate');
			}
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('xengallery_media_cannot_be_rotated'));
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media),
			new XenForo_Phrase('xengallery_image_rotated_successfully')
		);
	}

	public function actionFlip()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$vertical = $this->_input->filterSingle('vertical', XenForo_Input::UINT);

		$direction = 'horizontal';
		if ($vertical)
		{
			$direction = 'vertical';
		}

		$mediaModel = $this->_getMediaModel();
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		if (!$mediaModel->canFlipMedia($media, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$imageInfo = $mediaModel->flipMedia($media, $direction);
		if ($imageInfo)
		{
			$mediaModel->rebuildThumbnail($media, $imageInfo, true, $media['thumbnail_date']);

			if (XenForo_Visitor::getUserId() != $media['user_id'])
			{
				XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'flip');
			}
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('xengallery_media_cannot_be_flipped'));
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media),
			new XenForo_Phrase('xengallery_image_rotated_successfully')
		);
	}

	public function actionCropConfirm()
	{
		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$mediaHelper->assertCanCropMedia($media);

		$cropData = $this->_input->filter(array(
			'crop_x1' => XenForo_Input::FLOAT,
			'crop_y1' => XenForo_Input::FLOAT,
			'crop_x2' => XenForo_Input::FLOAT,
			'crop_y2' => XenForo_Input::FLOAT,
			'crop_width' => XenForo_Input::FLOAT,
			'crop_height' => XenForo_Input::FLOAT,
			'crop_multiplier' => XenForo_Input::FLOAT
		));

		$viewParams = array(
			'media' => $media,
			'cropData' => $cropData
		);

		return $this->responseView('XenGallery_ViewPublic_Media_CropConfirm', 'xengallery_media_crop_confirm', $viewParams);
	}

	public function actionCrop()
	{
		$this->_assertPostOnly();

		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$mediaHelper->assertCanCropMedia($media);
		$mediaModel = $this->_getMediaModel();

		$cropData = $this->_input->filterSingle('crop_data', XenForo_Input::FLOAT, array('array' => true));
		$multiplier = $cropData['crop_multiplier'];
		if (!$multiplier)
		{
			throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_an_unexpected_error_occurred_during_cropping'));
		}

		foreach ($cropData AS $key => &$cropDataItem)
		{
			if ($key == 'crop_multiplier')
			{
				continue;
			}

			$cropDataItem = $cropDataItem / $multiplier;
		}

		$imageInfo = $mediaModel->cropMedia($media, $cropData);
		if ($imageInfo)
		{
			$mediaModel->rebuildThumbnail($media, $imageInfo, true, $media['thumbnail_date']);

			if (XenForo_Visitor::getUserId() != $media['user_id'])
			{
				XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'crop');
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media)
		);
	}

	public function actionTagInput()
	{
		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$tagSelfOnly = $mediaHelper->assertCanTagMedia($media);

		$tagData = $this->_input->filter(array(
			'tag_x1' => XenForo_Input::FLOAT,
			'tag_y1' => XenForo_Input::FLOAT,
			'tag_x2' => XenForo_Input::FLOAT,
			'tag_y2' => XenForo_Input::FLOAT,
			'tag_width' => XenForo_Input::FLOAT,
			'tag_height' => XenForo_Input::FLOAT,
			'tag_multiplier' => XenForo_Input::FLOAT
		));

		$imageCss = array(
			'width' => round($media['width'] * $tagData['tag_multiplier'], 2),
			'height' => round($media['height'] * $tagData['tag_multiplier'], 2),
			'margin-left' => round($tagData['tag_x1'], 2),
			'margin-top' => round($tagData['tag_y1'], 2)
		);

		$viewParams = array(
			'media' => $media,
			'tagData' => $tagData,
			'imageCss' => $imageCss,
			'tagSelfOnly' => $tagSelfOnly === 'self' ? true : false,
			'tagNeedsApproval' => $this->_getUserTaggingModel()->getTagInsertState() == 'pending'
		);

		return $this->responseView('XenGallery_ViewPublic_Media_TagUserInput', 'xengallery_media_tag_user_input', $viewParams);
	}

	public function actionTag()
	{
		$this->_assertPostOnly();

		$userModel = $this->_getUserModel();
		$taggingModel = $this->_getUserTaggingModel();

		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$tagSelfOnly = $mediaHelper->assertCanTagMedia($media);

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

		if ($tagSelfOnly === 'self')
		{
			if ($username != XenForo_Visitor::getInstance()->username)
			{
				throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_you_can_only_tag_yourself'));
			}
		}

		$visitor = XenForo_Visitor::getInstance();
		$user = $userModel->getUserByName($username, array('join' => XenForo_Model_User::FETCH_USER_FULL));
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$tagData = array(
			'media_id' => $mediaId,
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'tag_by_user_id' => $visitor->user_id,
			'tag_by_username' => $visitor->username,
			'tag_state' => $taggingModel->getTagInsertState($user, $visitor->toArray())
		);

		$tagData['tag_data'] = $this->_input->filterSingle('tag_data', XenForo_Input::FLOAT, array('array' => true));
		$multiplier = $tagData['tag_data']['tag_multiplier'];

		foreach ($tagData['tag_data'] AS $key => &$tagDataItem)
		{
			if ($key == 'tag_multiplier' || !$multiplier)
			{
				continue;
			}

			$tagDataItem = $tagDataItem / $multiplier;
		}

		if (!$taggingModel->getTagByMediaAndUserId($mediaId, $user['user_id'], 'approved'))
		{
			$tagWriter = XenForo_DataWriter::create('XenGallery_DataWriter_UserTag');

			$tagWriter->bulkSet($tagData);
			$tagWriter->save();
		}
		else
		{
			if ($tagData['tag_state'] == 'approved')
			{
				throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_you_have_already_tagged_this_user_in_this_media'));
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media)
		);
	}

	public function actionTagDelete()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);

		$tagId = $this->_input->filterSingle('tag_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();

		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);
		$tag = $this->_getUserTaggingModel()->getTagById($tagId);

		$mediaHelper->assertCanDeleteTag($media, $tag);

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_UserTag');
		$dw->setExistingData($tag);
		$dw->delete();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery', $media)
		);
	}

	public function actionAddWatermark()
	{
		$mediaHelper = $this->_getMediaHelper();
		$watermarkModel = $this->_getWatermarkModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		if (!$watermarkModel->canAddWatermark($media, $error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		if ($this->isConfirmedPost())
		{
			$watermarked = $watermarkModel->addWatermarkToImage($media);
			if ($watermarked)
			{
				$this->_getMediaModel()->rebuildThumbnail($media, $watermarked, true, $media['thumbnail_date']);

				if (XenForo_Visitor::getUserId() != $media['user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'watermark_add');
				}

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery', $media),
					new XenForo_Phrase('xengallery_watermark_add_successfully')
				);
			}
			else
			{
				throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_watermark_could_not_be_added'));
			}
		}
		else
		{
			$viewParams = array(
				'media' => $media
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_AddWatermark',
				'xengallery_media_add_watermark',
				$viewParams
			);
		}
	}

	public function actionRemoveWatermark()
	{
		$mediaHelper = $this->_getMediaHelper();
		$watermarkModel = $this->_getWatermarkModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		if (!$watermarkModel->canRemoveWatermark($media, $error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		if ($this->isConfirmedPost())
		{
			$removed = $watermarkModel->removeWatermarkFromImage($media);
			if ($removed)
			{
				if (XenForo_Visitor::getUserId() != $media['user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'watermark_remove');
				}

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery', $media),
					new XenForo_Phrase('xengallery_watermark_removed_successfully')
				);
			}
			else
			{
				throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_watermark_could_not_be_removed'));
			}
		}
		else
		{
			$viewParams = array(
				'media' => $media
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_RemoveWatermark',
				'xengallery_media_remove_watermark',
				$viewParams
			);
		}
	}

	public function actionAvatar()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaModel = $this->_getMediaModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		if ($this->isConfirmedPost())
		{
			if (!XenForo_Visitor::getInstance()->canUploadAvatar())
			{
				return $this->responseNoPermission();
			}

			if (!$mediaModel->canSetAvatar($media, $error))
			{
				throw $this->getErrorOrNoPermissionResponseException($error);
			}

			/* @var $avatarModel XenForo_Model_Avatar */
			$avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');

			$visitor = XenForo_Visitor::getInstance();

			$filePath = $mediaModel->getOriginalDataFilePath($media, true);
			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');

			$success = @copy ($filePath, $newTempFile);
			if ($success)
			{
				$avatarModel->insertAvatar($newTempFile, $visitor->user_id, $visitor->getPermissions());
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media),
				new XenForo_Phrase('xengallery_avatar_set_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_Avatar',
				'xengallery_media_avatar',
				$viewParams
			);
		}
	}

	/**
	 * Displays a form to like a media item.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLike()
	{
		$mediaModel = $this->_getMediaModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);
		$media = $mediaModel->prepareMedia($media);

		$this->_getMediaHelper()->assertCanLikeMedia($media);

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('xengallery_media', $mediaId, XenForo_Visitor::getUserId());

		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent('xengallery_media', $mediaId, $media['user_id']);
			}

			$liked = ($existingLike ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$media['likeUsers'] = $latestUsers;
				$media['likes'] += ($liked ? 1 : -1);
				$media['like_date'] = ($liked ? XenForo_Application::$time : 0);

				$viewParams = array(
					'media' => $media,
					'liked' => $liked,
					'inline' => $this->_input->filterSingle('inline', XenForo_Input::BOOLEAN)
				);

				return $this->responseView('XenGallery_ViewPublic_Media_LikeConfirmed', '', $viewParams);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery', $media)
				);
			}
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media),
				'like' => $existingLike
			);

			return $this->responseView('XenGallery_ViewPublic_Media_Like', 'xengallery_media_like', $viewParams);
		}
	}

	public function actionLikes()
	{
		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media =  $mediaHelper->assertMediaValidAndViewable($mediaId);

		$likes = $this->_getLikeModel()->getContentLikes('xengallery_media', $mediaId);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('xengallery_no_one_has_liked_this_media_yet'));
		}

		$viewParams = array(
			'media' => $media,
			'likes' => $likes,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Ratings', 'xengallery_media_likes', $viewParams);
	}

	public function actionRate()
	{
		$mediaModel = $this->_getMediaModel();
		$categoryModel = $this->_getCategoryModel();
		$commentModel = $this->_getCommentModel();

		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);

		$categoryBreadcrumbs = $categoryModel->getCategoryBreadcrumb($media);

		$mediaHelper->assertCanRateMedia($media);

		$visitor = XenForo_Visitor::getInstance();

		$input = $this->_input->filter(array(
			'rating' => XenForo_Input::UINT
		));

		$canAddComment = $commentModel->canAddComment();
		$commentRequired = XenForo_Application::getOptions()->xengalleryRequireComment;

		$existing = $this->_getRatingModel()->getRatingByContentAndUser($mediaId, 'media', $visitor['user_id']);

		$comment = '';
		if ($existing)
		{
			$comment = $this->_getCommentModel()->getCommentByRatingId($existing['rating_id']);
		}

		if ($this->isConfirmedPost())
		{
			$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
			$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

			if ($canAddComment && $commentRequired && !$input['message'])
			{
				return $this->responseError(new XenForo_Phrase('xengallery_a_comment_is_required_with_this_rating'));
			}

			$ratingData = array(
				'content_id' => $media['media_id'],
				'content_type' => 'media',
				'user_id' => $visitor->user_id,
				'username' => $visitor->username,
				'rating' => $input['rating']
			);

			$ratingDw = XenForo_DataWriter::create('XenGallery_DataWriter_Rating');
			$ratingDw->bulkSet($ratingData);

			if ($existing)
			{
				$deleteDw = XenForo_DataWriter::create('XenGallery_DataWriter_Rating');
				$deleteDw->setExistingData($existing, true);
				$deleteDw->delete();

				if ($comment)
				{
					$existingCommentDw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
					$existingCommentDw->setExistingData($comment);
					$existingCommentDw->set('rating_id', 0);
				}
			}

			$ratingDw->save();

			if ($input['message'])
			{
				$commentDw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');

				$data = array(
					'content_id' => $mediaId,
					'content_type' => 'media',
					'message' => $input['message'],
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'rating_id' => $ratingDw->get('rating_id')
				);

				$commentDw->bulkSet($data);
				$commentDw->save();
			}

			$mediaDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$newRating = $mediaModel->getRatingAverage($mediaDw->get('rating_sum'), $mediaDw->get('rating_count'), true);
			$hintText = new XenForo_Phrase('x_votes', array('count' => $mediaDw->get('rating_count')));

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(),
				new XenForo_Phrase('your_rating_has_been_recorded'),
				array(
					'newRating' => $newRating,
					'hintText' => $hintText
				)
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'rating' => $input['rating'],
				'existing' => ($existing ? $existing : false),

				'commentRequired' => $commentRequired,
				'canAddComment' => $canAddComment,

				'categoryBreadcrumbs' => $categoryBreadcrumbs,
			);

			if ($comment)
			{
				$viewParams += array(
					'message' => $comment['message']
				);
			}

			return $this->responseView('XenGallery_ViewPublic_Media_Rate', 'xengallery_media_rate', $viewParams);
		}
	}

	public function actionRatings()
	{
		$mediaHelper = $this->_getMediaHelper();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media =  $mediaHelper->assertMediaValidAndViewable($mediaId);

		$conditions = array(
			'media_id' => $mediaId
		);

		$fetchOptions = array(
			'join' => XenGallery_Model_Rating::FETCH_USER
		);

		$ratings = $this->_getRatingModel()->getRatings($conditions, $fetchOptions);

		$viewParams = array(
			'media' => $media,
			'ratings' => $ratings,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Ratings', 'xengallery_media_ratings', $viewParams);
	}

	public function actionContentLoader()
	{
		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();
		$categoryModel = $this->_getCategoryModel();
		$commentsModel = $this->_getCommentModel();

		$items = $this->_input->filterSingle('items', XenForo_Input::ARRAY_SIMPLE);

		$albumIds = array();
		$albums = array();

		$mediaIds = array();
		$mediaArray = array();

		foreach ($items AS $item)
		{
			if ($item['type'] == 'album')
			{
				$albumIds[] = intval($item['id']);
			}

			if ($item['type'] == 'media')
			{
				$mediaIds[] = intval($item['id']);
			}
		}

		$visitor = XenForo_Visitor::getInstance();

		if ($albumIds)
		{
			$albumConditions = array(
				'privacyUserId' => $visitor->user_id,
				'album_id' => $albumIds
			);
			$albumFetchOptions = array(
				'join' => XenGallery_Model_Album::FETCH_PRIVACY
					| XenGallery_Model_Album::FETCH_USER
			);

			$albums = $albumModel->getAlbums($albumConditions, $albumFetchOptions);
			$albums = $albumModel->prepareAlbums($albums);
		}

		if ($mediaIds)
		{
			$mediaConditions = array(
				'media_id' => $mediaIds,
				'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
				'privacyUserId' => $visitor->user_id,
				'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
			);
			$mediaFetchOptions = array(
				'join' => XenGallery_Model_Media::FETCH_USER
					| XenGallery_Model_Media::FETCH_ALBUM
					| XenGallery_Model_Media::FETCH_CATEGORY
					| XenGallery_Model_Media::FETCH_PRIVACY
					| XenGallery_Model_Media::FETCH_ATTACHMENT
			);

			$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
			$media = $mediaModel->prepareMediaItems($media);

			foreach ($media AS $mediaId => $item)
			{
				$canViewMedia = $mediaModel->canViewMediaItem($item);

				$canViewAlbum = true;
				if ($item['album_id'] > 0)
				{
					$item = $albumModel->prepareAlbumWithPermissions($item);
					$canViewAlbum = $albumModel->canViewAlbum($item);
				}

				$canViewCategory = true;
				if ($item['category_id'] > 0)
				{
					$canViewCategory = $categoryModel->canViewCategory($item);
				}

				if (!$canViewMedia || !$canViewAlbum || !$canViewCategory)
				{
					unset ($media[$mediaId]);
				}
			}

			$commentConditions = array(
				'media_id' => $mediaIds,
				'comment_state' => 'visible'
			);
			$commentFetchOptions = array(
				'limit' => 10,
				'order' => 'comment_date',
				'join' => XenGallery_Model_Comment::FETCH_USER
			);

			$comments = $commentsModel->getComments($commentConditions, $commentFetchOptions);

			$groupedComments = array();
			foreach ($comments AS $commentId => &$comment)
			{
				$comment = $commentsModel->prepareComments($comment);
				$comment['message'] = XenForo_Helper_String::bbCodeStrip($comment['message']);
				$groupedComments[$comment['content_id']][$commentId] = $comment;
			}

			foreach ($media AS &$_media)
			{
				if (!empty($groupedComments[$_media['media_id']]))
				{
					$_media['comments'] = $groupedComments[$_media['media_id']];
				}

				$_media = $mediaModel->prepareMediaExifData($_media);
			}

			$mediaArray = array();
			foreach ($mediaIds AS $mediaId)
			{
				if (isset($media[$mediaId]))
				{
					$mediaArray[] = $media[$mediaId];
				}
			}
		}

		$viewParams = array(
			'albums' => array(),
			'media' => $mediaArray
		);

		return $this->responseView('XenGallery_ViewPublic_Media_BbCode', '', $viewParams);
	}

	public function actionEditorBrowser()
	{
		$lastPage = $this->_input->filterSingle('last_page', XenForo_Input::UINT);
		$page = $lastPage + 1;

		$mediaModel = $this->_getMediaModel();
		$visitor = XenForo_Visitor::getInstance();

		$albums = array();

		$mediaConditions = array(
			'user_id' => $visitor->user_id,
			'media_state' => 'visible'
		);
		$mediaFetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_ALBUM,
			'order' => 'new',
			'page' => $page,
			'perPage' => $perPage = 50
		);

		$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		$mediaCount = $mediaModel->countMedia($mediaConditions, $mediaFetchOptions);


		$viewParams = array(
			'albums' => $albums,
			'media' => $media,
			'page' => $page,
			'isLastPage' => ($page * $perPage > $mediaCount)
		);

		$templateName = 'xengallery_browser_editor';
		if ($page > 1)
		{
			$templateName .= '_content';
		}

		return $this->responseView('XenGallery_ViewPublic_Media_EditorBrowser', $templateName, $viewParams);
	}

	public function actionThumbnail()
	{
		$mediaModel = $this->_getMediaModel();

		$mediaSiteOptions = XenForo_Application::getOptions()->xengalleryMediaThumbs;

		$mediaSite = $this->_input->filterSingle('id', XenForo_Input::STRING);
		if (!$mediaSite)
		{
			return $this->responseView(
				'XenGallery_ViewPublic_Media_Thumbnail',
				'',
				array()
			);
		}

		$parts = explode('.', $mediaSite, 2);

		$videoThumbnail = $mediaModel->getVideoThumbnailFromParts($parts);
		if (file_exists($videoThumbnail) && is_readable($videoThumbnail))
		{
			$this->getRouteMatch()->setResponseType('raw');

			$viewParams = array(
				'thumbnailPath' => $videoThumbnail
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_Thumbnail',
				'',
				$viewParams
			);
		}
		else
		{
			$mediaSite = false;
			if (!empty($mediaSiteOptions[$parts[0]]))
			{
				$mediaSite = $mediaSiteOptions[$parts[0]];
			}

			if (strpos($mediaSite, '_'))
			{
				if (class_exists($mediaSite))
				{
					$thumbnailObj = XenGallery_Thumbnail_Abstract::create($mediaSite);
					$thumbnailPath = $thumbnailObj->getThumbnailUrl($parts[1]);
				}
				else
				{
					$thumbnailPath = $mediaSite;
				}
			}
			else
			{
				$thumbnailPath = $mediaSite;
			}

			if (strpos($thumbnailPath, '{$id}'))
			{
				$this->_thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $parts[0];
				XenForo_Helper_File::createDirectory($this->_thumbnailPath, true);

				$thumbnailUrl = str_replace('{$id}', $parts[1], $thumbnailPath);

				$thumbnailPath = XenGallery_Thumbnail_Abstract::saveThumbnailFromUrl($parts[0], $parts[1], $thumbnailUrl);
			}

			if (!$thumbnailPath)
			{
				$thumbnailPath = XenForo_Template_Helper_Core::callHelper('dummy', array(
					'', '', '', true
				));
			}
		}

		$this->getRouteMatch()->setResponseType('raw');

		$viewParams = array(
			'thumbnailPath' => $thumbnailPath
		);

		return $this->responseView(
			'XenGallery_ViewPublic_Media_Thumbnail',
			'',
			$viewParams
		);
	}

	public function actionThumbnailChange()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if (!$mediaId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();

		$mediaModel = $this->_getMediaModel();

		$media = $mediaHelper->assertMediaValidAndViewable($mediaId);
		$media = $mediaModel->prepareMedia($media);

		$mediaHelper->assertCanChangeMediaThumbnail($media);

		if ($this->isConfirmedPost())
		{
			$thumbnail = XenForo_Upload::getUploadedFile('thumbnail');
			$delete = $this->_input->filterSingle('delete', XenForo_Input::BOOLEAN);

			if ($thumbnail)
			{
				$mediaModel->uploadMediaThumbnail($thumbnail, $media);

				if (XenForo_Visitor::getUserId() != $media['user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'thumbnail_add');
				}
			}
			else if ($delete)
			{
				$mediaModel->deleteMediaThumbnail($media);

				if (XenForo_Visitor::getUserId() != $media['user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_media', $media, 'thumbnail_remove');
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $media)
			);
		}
		else
		{
			$viewParams = array(
				'media' => $media,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($media),
			);

			return $this->responseView('XenGallery_ViewPublic_Media_ThumbnailUpload', 'xengallery_media_thumbnail_upload', $viewParams);
		}
	}

	public function actionPreview()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $this->_getMediaHelper()->assertMediaValidAndViewable($mediaId);

		$viewParams = array(
			'media' => $media
		);

		return $this->responseView(
			'XenGallery_ViewPublic_Media_Preview',
			'xengallery_media_preview',
			$viewParams,
			array('containerTemplate' =>
				'xengallery_page_container'
			)
		);
	}

	public function actionPreviewVideo()
	{
		$embedUrl = $this->_input->filterSingle('embed_url', XenForo_Input::STRING);

		$mediaTag = XenForo_Helper_Media::convertMediaLinkToEmbedHtml($embedUrl);
		if (!$mediaTag)
		{
			return $this->responseView('XenGallery_ViewPublic_Media_PreviewVideo', '', array('throwError' => true, 'notValid' => true));
		}

		preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $mediaTag, $parts);

		$allowedSites = XenForo_Application::getOptions()->xengalleryMediaSites;
		if (!in_array($parts[1], $allowedSites))
		{
			return $this->responseView('XenGallery_ViewPublic_Media_PreviewVideo', '', array('throwError' => true, 'notAllowed' => true));
		}

		$mediaModel = $this->_getMediaModel();

		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$media = $mediaModel->getMediaById($mediaId);

		$media['mediaSite'] = "$parts[1].$parts[2]";
		$media['attachment_id'] = uniqid();

		$videoThumbnail = $mediaModel->getVideoThumbnailUrlFromParts($parts);
		$media['thumbnailUrl'] = $videoThumbnail;

		if (!$videoThumbnail)
		{
			$media['noThumb'] = true;
		}

		$containerType = $this->_input->filterSingle('container_type', XenForo_Input::STRING);
		$containerId = $this->_input->filterSingle('container_id', XenForo_Input::UINT);

		$category = array();

		if ($containerType == 'category')
		{
			$categoryModel = $this->_getCategoryModel();
			$category = $categoryModel->getCategoryById($containerId);
			$category = $categoryModel->prepareCategory($category);

			$fieldCache = $category['categoryFieldCache'];
		}
		else
		{
			$albumModel = $this->_getAlbumModel();
			$album = $albumModel->getAlbumByIdSimple($containerId);
			$album = $albumModel->prepareAlbum($album);

			$fieldCache = $album['albumFieldCache'];
		}

		$fieldModel = $this->_getFieldModel();
		$customFields = $fieldModel->prepareGalleryFields($fieldModel->getGalleryFields(array('display_add_media' => true)), true);

		$hasRequiredFields = false;
		$shownFields = array();
		foreach ($fieldCache AS $fields)
		{
			foreach ($fields AS $fieldId)
			{
				if (isset($customFields[$fieldId]))
				{
					$shownFields[$customFields[$fieldId]['display_group']][$fieldId] = $customFields[$fieldId];
					if ($customFields[$fieldId]['required'])
					{
						$hasRequiredFields = true;
					}
				}
			}
		}

		$minTags = $category ? $category['min_tags'] : XenForo_Application::getOptions()->xengalleryAlbumMinTags;

		$viewParams = array(
			'mediaTag' => $mediaTag,
			'uniqueId' => $media['attachment_id'],
			'embedUrl' => $embedUrl,
			'media' => $media,
			'isEditable' => true,
			'canEditTags' => $mediaModel->canEditTags(),
			'minTags' => $minTags,
			'customFields' => $shownFields,
			'requiredInput' => ($minTags || $hasRequiredFields)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_PreviewVideo', 'xengallery_media_add_item', $viewParams);
	}

	public function actionLoadEditForm()
	{
		$mediaModel = $this->_getMediaModel();
		$mediaHelper = $this->_getMediaHelper();

		$mediaHelper->assertCanAddMedia();

		$containerType = $this->_input->filterSingle('container_type', XenForo_Input::STRING);
		$containerId = $this->_input->filterSingle('container_id', XenForo_Input::UINT);

		$album = array();
		$category = array();

		if ($containerType == 'album')
		{
			$albumModel = $this->_getAlbumModel();

			$album = $albumModel->getAlbumById($containerId);

			if ($album)
			{
				$album = $albumModel->prepareAlbum($album);
				$album = $albumModel->prepareAlbumWithPermissions($album);

				if (!$albumModel->canAddMediaToAlbum($album, $error))
				{
					throw $this->getErrorOrNoPermissionResponseException($error);
				}
			}
			else
			{
				$album = array(
					'canUploadImage' => $albumModel->canUploadImage(),
					'canUploadVideo' => $albumModel->canUploadVideo(),
					'canEmbedVideo' => $albumModel->canEmbedVideo()
				);
			}

			$container = $album;
		}
		else
		{
			$categoryModel = $this->_getCategoryModel();

			$category = $mediaHelper->assertCategoryValidAndViewable($containerId);
			$category = $categoryModel->prepareCategory($category);

			if (!$categoryModel->canAddMediaToCategory($category, $error))
			{
				throw $this->getErrorOrNoPermissionResponseException($error);
			}

			$container = $category;
		}

		$mediaSites = $this->getModelFromCache('XenForo_Model_BbCode')->getAllBbCodeMediaSites();
		$allowedSites = XenForo_Application::getOptions()->xengalleryMediaSites;

		foreach ($mediaSites AS $key => $mediaSite)
		{
			if (!in_array($mediaSite['media_site_id'], $allowedSites))
			{
				unset ($mediaSites[$key]);
			}
		}

		$linkParams = array();
		if ($category)
		{
			$linkParams['category_id'] = $category['category_id'];
		}

		$viewParams = array(
			'album' => $album,
			'category' => $category,

			'mediaSites' => $mediaSites,

			'imageUploadParams' => $mediaModel->getAttachmentParams($container),
			'videoUploadParams' => $mediaModel->getAttachmentParams($container, 'video_upload'),
			'imageUploadConstraints' => $mediaModel->getUploadConstraints(),
			'videoUploadConstraints' => $mediaModel->getUploadConstraints('video_upload'),

			'canUploadImage' => $container['canUploadImage'],
			'canUploadVideo' => $container['canUploadVideo'],
			'canEmbedVideo' => $container['canEmbedVideo'],

			'canEditTags' => $mediaModel->canEditTags(),
			'setAllLinkParams' => $linkParams
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Add', 'xengallery_media_add_form', $viewParams);
	}

	public function actionLoadUserAlbums()
	{
		$albumModel = $this->_getAlbumModel();
		$userModel = $this->_getUserModel();

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		$user = $userModel->getUserByName($username, array('join' => XenForo_Model_User::FETCH_USER_FULL));

		$albumConditions = array(
			'album_user_id' => $user['user_id']
		);
		$albums = $albumModel->getAlbums($albumConditions);
		foreach ($albums AS $key => $album)
		{
			$album = $albumModel->prepareAlbumWithPermissions($album);
			if (!$albumModel->canViewAlbum($album))
			{
				unset ($albums[$key]);
			}
		}

		$viewParams = array(
			'user' => $user,
			'albums' => $albums
		);

		return $this->responseView('XenGallery_ViewPublic_Media_LoadUserAlbums', '', $viewParams);
	}

	public function actionAddComment()
	{
		$this->_assertPostOnly();

		$this->_assertRegistrationRequired();

		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();
		$commentModel = $this->_getCommentModel();

		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddComment();

		$visitor = XenForo_Visitor::getInstance()->toArray();

		$this->assertNotFlooding('actionAddComment');

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		$input = $this->_input->filter(array(
			'content_id' => XenForo_Input::UINT,
			'content_type' => XenForo_Input::STRING
		));

		$input['comment_state'] = $commentModel->getCommentInsertState();

		if ($input['content_type'] == 'media')
		{
			$content = $mediaHelper->assertMediaValidAndViewable($input['content_id']);
			if ($mediaModel->canWatchMedia() && $visitor['xengallery_default_media_watch_state'])
			{
				$mediaWatchModel = $this->_getMediaWatchModel();

				$notifyOn = 'comment';
				$sendAlert = true;
				$sendEmail = false;

				if ($visitor['xengallery_default_media_watch_state'] == 'watch_email')
				{
					$sendEmail = true;
				}

				$mediaWatchModel->setMediaWatchState($visitor['user_id'], $input['content_id'], $notifyOn, $sendAlert, $sendEmail);
			}
			$redirectLink = XenForo_Link::buildPublicLink('xengallery', $content);
		}
		else
		{
			$content = $mediaHelper->assertAlbumValidAndViewable($input['content_id']);
			if ($albumModel->canWatchAlbum() && $visitor['xengallery_default_album_watch_state'])
			{
				$albumWatchModel = $this->_getAlbumWatchModel();

				$notifyOn = 'media_comment';
				$sendAlert = true;
				$sendEmail = false;

				if ($visitor['xengallery_default_album_watch_state'] == 'watch_email')
				{
					$sendEmail = true;
				}

				$albumWatchModel->setAlbumWatchState($visitor['user_id'], $input['content_id'], $notifyOn, $sendAlert, $sendEmail);
			}
			$redirectLink = XenForo_Link::buildPublicLink('xengallery/albums', $content);
		}

		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');

		if ($commentId)
		{
			$writer->setExistingData($commentId);
			$writer->set('user_id', $writer->get('user_id'));
			$writer->set('message', $input['message']);
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();

			$writer->setOption(XenGallery_DataWriter_Comment::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));
			$writer->set('content_id', $input['content_id']);
			$writer->set('content_type', $input['content_type']);
			$writer->set('user_id', $visitor->user_id);
			$writer->set('username', $visitor->username);
			$writer->set('message', $input['message']);
			$writer->set('comment_state', $input['comment_state']);
		}

		$writer->save();

		// only run this code if the action has been loaded via XenForo.ajax()
		if ($this->_noRedirect())
		{
			$commentModel = $this->_getCommentModel();

			$date = $this->_input->filterSingle('date', XenForo_Input::UINT);

			$comments = $commentModel->getCommentsNewerThan($date, $input['content_id'], $input['content_type']);
			$comments = $commentModel->prepareComments($comments);
			$inlineModOptions = $commentModel->prepareInlineModOptions($comments);

			$date = $commentModel->getLatestDate($input);

			$viewParams = array(
				'comments' => $comments,
				'inlineModOptions' => $inlineModOptions,
				'pageLink' => isset($content['media_id']) ? 'xengallery' : 'xengallery/albums',
				'date' => $date,
				'content' => $content,
				'canViewRatings' => $mediaModel->canViewRatings(),
				'canViewIps' => $this->_getUserModel()->canViewIps(),
				'canReport' => $visitor['user_id'] ? true : false,
				'canAddComment' => $commentModel->canAddComment()
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_LatestComments',
				'xengallery_comments_load',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirectLink
		);
	}

	protected function _saveEditedMediaProcess(array $inputArray, array $otherInput, &$redirect = null)
	{
		$mediaModel = $this->_getMediaModel();

		foreach ($inputArray AS $input)
		{
			$media = $mediaModel->getMediaById($otherInput['media_id'], array(
				'join' => XenGallery_Model_Media::FETCH_ALBUM
					| XenGallery_Model_Media::FETCH_CATEGORY
			));
			$this->_getMediaHelper()->assertCanEditMedia($media);

			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($media);

			$tagger = null;

			if ($mediaModel->canEditTags($media))
			{
				/** @var XenForo_Model_Tag $tagModel */
				$tagModel = $this->getModelFromCache('XenForo_Model_Tag');
				$tagger = $tagModel->getTagger('xengallery_media');

				$tagger->setContent($media['media_id'])->setPermissionsFromContext($media, $media);

				$editTags = $tagModel->getTagListForEdit('xengallery_media', $media['media_id'], $tagger->getPermission('removeOthers'));

				if ($editTags['uneditable'])
				{
					// this is mostly a sanity check; this should be ignored
					$input['tags'] .= (strlen($input['tags']) ? ', ' : '') . implode(', ', $editTags['uneditable']);
				}
				$tagger->setTags($tagModel->splitTags($input['tags']));
				$mediaWriter->mergeErrors($tagger->getErrors());

				unset($input['tags']);
			}

			$data = array_merge(array(
				'media_title' => '',
				'media_description' => '',
				'media_embed_url' => '',
				'media_tag' => ''
			), $input);

			if (!empty($data['media_embed_url_original']))
			{
				if ($data['media_embed_url'] && $mediaModel->canEditEmbedUrl($media))
				{
					$data['media_tag'] = XenForo_Helper_Media::convertMediaLinkToEmbedHtml($data['media_embed_url']);
				}
				else
				{
					$data['media_embed_url'] = $data['media_embed_url_original'];
				}
			}
			unset($data['media_embed_url_original'], $data['custom_fields']);

			$mediaWriter->bulkSet($data);
			$mediaWriter->save();

			if ($tagger)
			{
				$tagger->save();
			}

			$this->_updateCustomFields($input, $mediaWriter->getMergedData());
			$this->_sendAuthorAlert($media, 'xengallery_media', 'edit');
			$this->_logChanges($mediaWriter, $media, 'edit');

			$redirect = XenForo_Link::buildPublicLink('xengallery', $mediaWriter->getMergedData());
		}
	}

	public function actionSaveMedia()
	{
		$this->_assertPostOnly();

		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddMedia();

		$mediaModel = $this->_getMediaModel();

		$imageUploads = $this->_input->filterSingle('media_image_upload', XenForo_Input::BOOLEAN);
		$videoUploads = $this->_input->filterSingle('media_video_upload', XenForo_Input::BOOLEAN);
		$videoEmbeds = $this->_input->filterSingle('media_video_embed', XenForo_Input::BOOLEAN);

		$containerType = $this->_input->filterSingle('container_type', XenForo_Input::STRING);
		$containerId = $this->_input->filterSingle('container_id', XenForo_Input::UINT);

		$visitor = XenForo_Visitor::getInstance();

		XenForo_Db::beginTransaction();

		$album = array();
		$category = array();

		$redirectMessage = new XenForo_Phrase('xengallery_media_has_been_saved');
		if ($containerType == 'category')
		{
			$categoryModel = $this->_getCategoryModel();

			$category = $mediaHelper->assertCategoryValidAndViewable($containerId);

			if (!$categoryModel->canAddMediaToCategory($category, $error))
			{
				throw $this->getErrorOrNoPermissionResponseException($error);
			}

			if ($categoryModel->canWatchCategory() && $visitor->xengallery_default_category_watch_state)
			{
				$categoryWatchModel = $this->_getCategoryWatchModel();

				$notifyOn = 'media';
				$sendAlert = true;
				$sendEmail = false;
				$includeChildren = true;

				if ($visitor->xengallery_default_category_watch_state == 'watch_email')
				{
					$sendEmail = true;
				}

				$categoryWatchModel->setCategoryWatchState($visitor->user_id, $category['category_id'], $notifyOn, $sendAlert, $sendEmail, $includeChildren);
			}

			$redirect = XenForo_Link::buildPublicLink('xengallery/categories', $category);
		}
		else
		{
			$album = $mediaHelper->assertAlbumValidAndViewable($containerId);

			if (!$this->_getAlbumModel()->canAddMediaToAlbum($album, $error))
			{
				throw $this->getErrorOrNoPermissionResponseException($error);
			}

			$redirect = XenForo_Link::buildPublicLink('xengallery/albums', $album);
		}

		$otherInput = $this->_input->filter(array(
			'media_id' => XenForo_Input::UINT,
			'image_upload_hash' => XenForo_Input::STRING,
			'video_upload_hash' => XenForo_Input::STRING
		));

		if ($imageUploads)
		{
			$imageUploadInput = $this->_input->filterSingle('image_upload', XenForo_Input::ARRAY_SIMPLE);
			if (!empty($otherInput['media_id']))
			{
				$this->_saveEditedMediaProcess($imageUploadInput, $otherInput, $redirect);
			}
			else
			{
				$imageUploadInput['media_type'] = 'image_upload';

				$imageUploadInput['media_state'] = $mediaModel->getMediaInsertState();
				if ($imageUploadInput['media_state'] == 'moderated')
				{
					$redirectMessage = new XenForo_Phrase('xengallery_media_has_been_saved_awaiting_approval');
				}

				$attachmentModel = $this->_getAttachmentModel();
				$attachments = $attachmentModel->getAttachmentsByTempHash($otherInput['image_upload_hash']);
				$attachments = $mediaModel->prepareMediaItems($attachments);

				$this->_associateAttachmentsAndMedia($attachments, $imageUploadInput, $album ? $album : $category);
			}
		}

		if ($videoUploads)
		{
			$videoUploadInput = $this->_input->filterSingle('video_upload', XenForo_Input::ARRAY_SIMPLE);
			if (!empty($otherInput['media_id']))
			{
				$this->_saveEditedMediaProcess($videoUploadInput, $otherInput, $redirect);
			}
			else
			{
				$videoUploadInput['media_type'] = 'video_upload';

				$videoUploadInput['media_state'] = $mediaModel->getMediaInsertState();
				if ($videoUploadInput['media_state'] == 'moderated')
				{
					$redirectMessage = new XenForo_Phrase('xengallery_media_has_been_saved_awaiting_approval');
				}

				$attachmentModel = $this->_getAttachmentModel();

				$attachments = $attachmentModel->getAttachmentsByTempHash($otherInput['video_upload_hash']);
				$attachments = $mediaModel->prepareMediaItems($attachments);

				$requireTranscode = $this->_associateAttachmentsAndMedia($attachments, $videoUploadInput, $album ? $album : $category);
				if ($requireTranscode)
				{
					XenForo_Application::getSession()->set('xfmgVideoRequiresTranscode', true);
				}
			}
		}

		if ($videoEmbeds)
		{
			$videoEmbedInput = $this->_input->filterSingle('video_embed', XenForo_Input::ARRAY_SIMPLE);
			if (!empty($otherInput['media_id']))
			{
				$this->_saveEditedMediaProcess($videoEmbedInput, $otherInput, $redirect);
			}
			else
			{
				$mediaType = 'video_embed';

				$mediaState = $mediaModel->getMediaInsertState();
				if ($mediaState == 'moderated')
				{
					$redirectMessage = new XenForo_Phrase('xengallery_media_has_been_saved_awaiting_approval');
				}

				$container = $album ? $album : $category;
				foreach ($videoEmbedInput AS $videoEmbed)
				{
					$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

					$tagger = null;

					if ($mediaModel->canEditTags())
					{
						$tagModel = $this->getModelFromCache('XenForo_Model_Tag');

						/** @var XenForo_TagHandler_Tagger $tagger */
						$tagger = $tagModel->getTagger('xengallery_media');
						$tagger->setPermissionsFromContext($container)
							->setTags($tagModel->splitTags($videoEmbed['tags']));

						$mediaWriter->mergeErrors($tagger->getErrors());
					}

					$mediaWriter->bulkSet(array(
						'media_title' => $videoEmbed['media_title'],
						'media_description' => $videoEmbed['media_description'],
						'media_type' => $mediaType,
						'media_tag' => $videoEmbed['media_tag'],
						'media_embed_url' => $videoEmbed['media_embed_url'],
						'media_state' => $mediaState,
						'attachment_id' => 0,
						'user_id' => $visitor->user_id,
						'username' => $visitor->username,
						'album_id' => !empty($container['album_id']) ? $container['album_id'] : 0,
						'category_id' => !empty($container['category_id']) ? $container['category_id'] : 0
					));

					$mediaWriter->save();

					$media = $mediaWriter->getMergedData();

					if ($tagger)
					{
						$tagger->setContent($media['media_id'], true)->save();
					}

					$this->_updateCustomFields($videoEmbed, $media);
				}
			}
		}

		XenForo_Db::commit();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirect, $redirectMessage
		);
	}

	protected function _associateAttachmentsAndMedia(array $attachments, array $input, array $container)
	{
		$mediaModel = $this->_getMediaModel();

		$visitor = XenForo_Visitor::getInstance();

		$requiresTranscode = false;

		foreach ($attachments AS $attachmentId => $attachment)
		{
			if (!isset($input[$attachmentId]))
			{
				continue;
			}

			$attachInput = array_merge(array(
				'media_title' => '',
				'media_description' => '',
				'tags' => '',
				'custom_fields' => array()
			), $input[$attachmentId]);

			$mediaData = array(
				'media_title' => $attachInput['media_title'],
				'media_description' => $attachInput['media_description'],
				'media_type' => $input['media_type'],
				'media_state' => $input['media_state'],
				'attachment_id' => $attachmentId,
				'user_id' => $visitor->user_id,
				'username' => $visitor->username,
				'album_id' => !empty($container['album_id']) ? $container['album_id'] : 0,
				'category_id' => !empty($container['category_id']) ? $container['category_id'] : 0
			);

			if ($input['media_type'] == 'video_upload' && $mediaModel->requiresTranscoding($attachment))
			{
				$customFields = array();
				$customFieldsShown = array();

				$customFieldsData = $this->_updateCustomFields($attachInput, array(), true);
				if ($customFieldsData)
				{
					list($customFields, $customFieldsShown) = $customFieldsData;
				}

				$transcodeData = array(
					'attachmentId' => $attachmentId,
					'media' => $mediaData,
					'tags' => $attachInput['tags'],
					'customFields' => $customFieldsData ? $customFields : array(),
					'customFieldsShown' => $customFieldsData ? $customFieldsShown : array()
				);

				$attachmentFile = $mediaModel->getAttachmentDataFilePath($attachment);

				// We check for tag errors and permissions here, now, so nothing will prevent the transcoding finishing later.
				if ($mediaModel->canEditTags())
				{
					$tagModel = XenForo_Model::create('XenForo_Model_Tag');

					/** @var XenForo_TagHandler_Tagger $tagger */
					$tagger = $tagModel->getTagger('xengallery_media');
					$tagger->setPermissionsFromContext($container)
						->setTags($tagModel->splitTags($attachInput['tags']));

					if ($tagger->getErrors())
					{
						throw $this->responseException($this->responseError($tagger->getErrors()));
					}

					$transcodeData['tagger_permissions'] = $tagger->getPermissions();
				}
				else
				{
					unset($transcodeData['tags']);
				}

				$video = new XenGallery_Helper_Video($attachmentFile);
				$video->queueTranscode($transcodeData);

				$requiresTranscode = true;
			}
			else
			{
				$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

				$tagger = null;

				if ($mediaModel->canEditTags())
				{
					$tagModel = $this->getModelFromCache('XenForo_Model_Tag');

					/** @var XenForo_TagHandler_Tagger $tagger */
					$tagger = $tagModel->getTagger('xengallery_media');
					$tagger->setPermissionsFromContext($container)
						->setTags($tagModel->splitTags($attachInput['tags']));

					$mediaWriter->mergeErrors($tagger->getErrors());
				}

				$mediaWriter->bulkSet($mediaData);
				$mediaWriter->save();

				$media = $mediaWriter->getMergedData();

				if ($tagger)
				{
					$tagger->setContent($media['media_id'], true)->save();
				}

				$this->_updateCustomFields($attachInput, $media);

				$mediaModel->updateAttachmentData($attachmentId, $media['media_id']);
				$mediaModel->updateExifData($attachment, $media);

				$mediaWriter->updateUserMediaQuota();

				if ($media['media_type'] == 'image_upload')
				{
					$watermarkModel = $this->_getWatermarkModel();

					$options = XenForo_Application::getOptions();
					if ($options->get('xengalleryEnableWatermarking') == 'enabled' && !$watermarkModel->canBypassWatermark())
					{
						$media = array_merge($media, $attachment);
						$imageInfo = $watermarkModel->addWatermarkToImage($media);

						if ($imageInfo)
						{
							$mediaModel->rebuildThumbnail($media, $imageInfo);
						}
					}
				}
			}
		}

		return $requiresTranscode; // Does not require transcoding
	}

	protected function _updateCustomFields(array $input, array $media, $prepareOnly = false)
	{
		if (empty($input['custom_fields']))
		{
			return;
		}

		$values = $input['custom_fields'];
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));

		$fieldModel = $this->getModelFromCache('XenGallery_Model_Field');
		$fields = $fieldModel->getGalleryFields();

		$customFields = array();
		foreach ($customFieldsShown AS $key)
		{
			if (!isset($fields[$key]))
			{
				continue;
			}

			if (isset($values[$key]))
			{
				$customFields[$key] = $values[$key];
			}

			$field = $fields[$key];

			if (isset($values[$key]))
			{
				$customFields[$key] = $values[$key];
			}
			else if ($field['field_type'] == 'bbcode' && isset($values[$key . '_html']))
			{
				$messageTextHtml = strval($values[$key . '_html']);

				if ($this->_input->filterSingle('_xfRteFailed', XenForo_Input::UINT))
				{
					// actually, the RTE failed to load, so just treat this as BB code
					$customFields[$key] = $messageTextHtml;
				}
				else if ($messageTextHtml !== '')
				{
					$customFields[$key] = $this->getHelper('Editor')->convertEditorHtmlToBbCode($messageTextHtml, $this->_input);
				}
				else
				{
					$customFields[$key] = '';
				}
			}
		}

		if ($prepareOnly)
		{
			return array($customFields, $customFieldsShown);
		}

		// Have to call the Media DW again.
		$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
		$mediaWriter->setExistingData($media['media_id']);

		$mediaWriter->setCustomFields($customFields, $customFieldsShown);

		$mediaWriter->save();
	}
}