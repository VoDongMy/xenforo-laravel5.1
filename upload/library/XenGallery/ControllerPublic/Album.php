<?php

class XenGallery_ControllerPublic_Album extends XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$this->_getMediaHelper()->assertAlbumsAreViewable();

		$visitor = XenForo_Visitor::getInstance();

		$defaultOrder = 'album_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));

		$albumModel = $this->_getAlbumModel();
		$mediaModel = $this->_getMediaModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		if ($albumId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryAlbumMaxPerPage;

		$conditions = array(
			'deleted' => $albumModel->canViewDeletedAlbums(),
			'privacyUserId' => $visitor->user_id,
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

		$albumCount = $albumModel->countAlbums($conditions, $fetchOptions);

		$this->canonicalizePageNumber($page, $perPage, $albumCount, 'xengallery/albums');
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xengallery/albums', '', array('page' => $page))
		);

		$inlineModOptions = $albumModel->prepareInlineModOptions($albums);

		$ignoredNames = array();
		foreach ($albums AS $album)
		{
			if (!empty($album['isIgnored']))
			{
				$ignoredNames[] = $album['username'];
			}
		}
		$ignoredNames = array_unique($ignoredNames);

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false)
		);

		$viewParams = array(
			'albums' => $albums,
			'albumPage' => true,

			'canCreateAlbum' => $albumModel->canCreateAlbum(),
			'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'order' => $order,
			'defaultOrder' => $defaultOrder,

			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'albumCount' => $albumCount,
			'inlineModOptions' => $inlineModOptions,
			'ignoredNames' => $ignoredNames,
			'hideFilterMenu' => true
		);

		return $this->_getAlbumMediaWrapper(
			$this->responseView('XenGallery_ViewPublic_Media_Album', 'xengallery_album_index', $viewParams)
		);
	}

	public function actionView()
	{
		$albumModel = $this->_getAlbumModel();
		$mediaModel = $this->_getMediaModel();
		$commentModel = $this->_getCommentModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $this->_getMediaHelper()->assertAlbumValidAndViewable($albumId);
		$album = $albumModel->prepareAlbum($album);

		$defaultOrder = 'media_date';
		if ($album['album_default_order'] && $album['album_media_count'] > 1)
		{
			$defaultOrder = 'custom';
		}
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));

		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$albumModel->logAlbumView($albumId);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$conditions = array(
			'album_id' => $albumId,
			'deleted' => XenForo_Permission::hasPermission(XenForo_Visitor::getInstance()->permissions, 'xengallery', 'viewDeleted'),
			'type' => $type
		);

		$fetchOptions = $this->_getMediaFetchOptions() + array(
			'order' => $order ? $order : $defaultOrder,
			'page' => $page,
			'perPage' => $perPage
		);

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		$userPage = false;
		$visitor = XenForo_Visitor::getInstance();
		if ($visitor->user_id == $album['album_user_id'])
		{
			$userPage = true;
		}

		$inlineModOptions = $mediaModel->prepareInlineModOptions($media, $userPage);

		$ignoredNames = array();
		foreach ($media AS $item)
		{
			if (!empty($item['isIgnored']))
			{
				$ignoredNames[] = $item['username'];
			}
		}
		$ignoredNames = array_unique($ignoredNames);

		$likeModel = $this->_getLikeModel();
		$existingLike = $likeModel->getContentLikeByLikeUser('xengallery_album', $albumId, XenForo_Visitor::getUserId());

		$liked = ($existingLike ? true : false);

		$album['likeUsers'] = isset($album['album_like_users']) ? unserialize($album['album_like_users']) : false;
		$album['like_date'] = ($liked ? XenForo_Application::$time : 0);
		$album['likes'] = $album['album_likes'];

		$mediaCount = $mediaModel->countMedia($conditions);

		$this->canonicalizePageNumber($page, $perPage, $mediaCount, 'xengallery/albums', $album);
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xengallery/albums', $album, array('page' => $page))
		);

		$album = $albumModel->prepareAlbumWithPermissions($album);
		$canAddMediaToAlbum = $albumModel->canAddMediaToAlbum($album);

		$commentPage = $this->_input->filterSingle('commentpage', XenForo_Input::UINT);
		$commentsPerPage = XenForo_Application::getOptions()->xengalleryMaxCommentsPerPage;

		$moderated = $commentModel->canViewUnapprovedComment();
		if (!$moderated)
		{
			$moderated = XenForo_Visitor::getUserId();
		}

		$commentConditions = array(
			'album_id' => $albumId,
			'deleted' => $commentModel->canViewDeletedComment(),
			'moderated' => $moderated
		);

		$commentFetchOptions = $this->_getCommentFetchOptions() + array(
			'page' => $commentPage,
			'perPage' => $commentsPerPage,
			'order' => 'comment_date',
			'orderDirection' => 'ASC',
			'content_type' => 'album',
		);

		$comments = $commentModel->getComments($commentConditions, $commentFetchOptions);
		$comments = $commentModel->prepareComments($comments);

		$commentsInlineModOptions = $commentModel->prepareInlineModOptions($comments);

		$commentsIgnoredNames = array();
		foreach ($comments AS $comment)
		{
			if (!empty($comment['isIgnored']))
			{
				$commentsIgnoredNames[] = $comment['username'];
			}
		}
		$commentsIgnoredNames = array_unique($commentsIgnoredNames);

		$commentCount = $commentModel->countComments($commentConditions, $commentFetchOptions);

		$date = 0;
		if ($comments)
		{
			$date = $commentModel->getLatestDate(array(
				'content_id' => $albumId,
				'content_type' => 'album'
			));
		}

		$linkParams = array(
			'order' => ($order != $defaultOrder ? $order : false),
			'type' => ($type ? $type : false)
		);
		if ($commentPage)
		{
			$linkParams['commentpage'] = $commentPage;
		}
		if ($page)
		{
			$linkParams['page'] = $page;
		}

		$commentLinkParams = $albumLinkParams = $linkParams;

		unset ($albumLinkParams['page']);
		unset ($commentLinkParams['commentpage']);

		$session = XenForo_Application::getSession();
		$requiresTranscode = $session->get('xfmgVideoRequiresTranscode');
		if ($requiresTranscode)
		{
			$session->remove('xfmgVideoRequiresTranscode');
		}

		$userModel = $this->_getUserModel();

		$viewParams = array(
			'album' => $album,

			'comments' => $comments,
			'commentCount' => $commentCount,
			'contentId' => $albumId,
			'contentType' => 'album',
			'content' => $album,
			'date' => $date,

			'ignoredNames' => $ignoredNames,
			'commentIgnoredNames' => $commentsIgnoredNames,

			'inlineModOptions' => $inlineModOptions,
			'commentInlineModOptions' => $commentsInlineModOptions,

			'draft' => $this->_getDraftModel()->getDraftByUserKey('album-' . $albumId, $visitor->getUserId()),

			'canWatchAlbum' => $albumModel->canWatchAlbum(),
			'canAddMedia' => $canAddMediaToAlbum,
			'canLikeAlbum' => $albumModel->canLikeAlbum($album),
			'canRateAlbum' => $albumModel->canRateAlbum($album),
			'canViewRatings' => $mediaModel->canViewRatings(),
			'canViewComments' => $commentModel->canViewComments(),
			'canEditAlbum' => $albumModel->canEditAlbum($album),
			'canDeleteAlbum' => $albumModel->canDeleteAlbum($album),
			'canChangeViewPermission' => $albumModel->canChangeAlbumViewPerm($album),
			'canChangeShareUsers' => $albumModel->canShareAlbum($album),
			'canChangeCustomOrder' => $albumModel->canChangeCustomOrder($album),
			'canChangeThumbnail' => $albumModel->canChangeThumbnail($album),
			'canWarn' => $albumModel->canWarnAlbum($album),
			'canViewWarnings' => $this->getModelFromCache('XenForo_Model_User')->canViewWarnings(),
			'canViewIps' => $userModel->canViewIps(),
			'canReport' => $visitor['user_id'] ? true : false,
			'canCleanSpam' => (XenForo_Permission::hasPermission($visitor->permissions, 'general', 'cleanSpam') && $userModel->couldBeSpammer($album)),
			'canAddComment' => $commentModel->canAddComment(),

			'media' => $media,
			'mediaCount' => $mediaCount,

			'order' => $order,
			'defaultOrder' => $defaultOrder,
			'type' => $type,
			'typeFilter' => $type,

			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'pageLink' => 'xengallery/albums',

			'commentPage' => $commentPage <= 1 ? '' : $commentPage,
			'commentsPerPage' => $commentsPerPage,

			'pageNavParams' => $linkParams,
			'albumLinkParams' => $albumLinkParams,
			'commentLinkParams' => $commentLinkParams,

			'requiresTranscode' => $requiresTranscode
		);

		return $this->responseView('XenGallery_ViewPublic_Album_View', 'xengallery_album_view', $viewParams);
	}

	public function actionEdit()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		if (!$albumId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();

		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
		$mediaHelper->assertCanEditAlbum($album);

		if ($this->isConfirmedPost())
		{
			$data = $this->_input->filter(array(
				'album_title' => XenForo_Input::STRING,
				'album_description' => XenForo_Input::STRING
			));

			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExistingData($albumId);

			$albumWriter->bulkSet($data);

			$albumWriter->save();

			$this->_sendAuthorAlert($album, 'xengallery_album', 'edit', array(), 'album_user_id');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album),
				new XenForo_Phrase('xengallery_album_has_been_updated')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Edit', 'xengallery_album_edit', $viewParams);
		}
	}

	public function actionDelete()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		if (!$albumId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$mediaHelper = $this->_getMediaHelper();

		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
		$mediaHelper->assertCanDeleteAlbum($album, $deleteType);

		if ($this->isConfirmedPost())
		{
			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExistingData($albumId);

			$reason = '';

			if ($hardDelete)
			{
				$albumWriter->delete();
			}
			else
			{
				$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);

				$albumWriter->setExtraData(XenGallery_DataWriter_Album::DATA_DELETE_REASON, $reason);
				$albumWriter->set('album_state', 'deleted');
				$albumWriter->save();
			}

			$this->_sendAuthorAlert($album, 'xengallery_album', 'delete', array(), 'album_user_id');
			$this->_logChanges($albumWriter, $album, 'delete_' . $deleteType, array('reason' => $reason), 'xengallery_album', 'album_user_id');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums'),
				new XenForo_Phrase('xengallery_album_deleted_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'canHardDelete' => $this->_getAlbumModel()->canDeleteAlbum($album, 'hard')
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Delete', 'xengallery_album_delete', $viewParams);
		}
	}

	public function actionUndelete()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		if (!$albumId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();
		$albumModel = $this->_getAlbumModel();

		$album = $albumModel->getAlbumById($albumId);

		if ($album['album_state'] != 'deleted')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery', $album)
			);
		}

		$mediaHelper->assertCanDeleteAlbum($album);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$writer->setExistingData($album['album_id']);

			$writer->set('album_state', 'visible');
			$writer->save();

			$this->_logChanges($writer, $album, 'undelete', array(), 'xengallery_album', 'album_user_id');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album)
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Undelete', 'xengallery_album_undelete', $viewParams);
		}
	}

	public function actionLike()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		$mediaHelper->assertCanLikeAlbum($album);

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('xengallery_album', $albumId, XenForo_Visitor::getUserId());

		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent('xengallery_album', $albumId, $album['album_user_id']);
			}

			$liked = ($existingLike ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$album['likeUsers'] = $latestUsers;
				$album['album_likes'] += ($liked ? 1 : -1);
				$album['likes'] = $album['album_likes'];
				$album['like_date'] = ($liked ? XenForo_Application::$time : 0);

				$viewParams = array(
					'album' => $album,
					'liked' => $liked,
					'inline' => $this->_input->filterSingle('inline', XenForo_Input::BOOLEAN)
				);

				return $this->responseView('XenGallery_ViewPublic_Album_LikeConfirmed', '', $viewParams);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery/albums', $album)
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
				'like' => $existingLike
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Like', 'xengallery_album_like', $viewParams);
		}
	}

	public function actionLikes()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album =  $mediaHelper->assertAlbumValidAndViewable($albumId);

		$likes = $this->_getLikeModel()->getContentLikes('xengallery_album', $albumId);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('xengallery_no_one_has_liked_this_album_yet'));
		}

		$viewParams = array(
			'album' => $album,
			'likes' => $likes,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Likes', 'xengallery_album_likes', $viewParams);
	}

	public function actionRate()
	{
		$albumModel = $this->_getAlbumModel();
		$commentModel = $this->_getCommentModel();

		$mediaHelper = $this->_getMediaHelper();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		$mediaHelper->assertCanRateAlbum($album);

		$visitor = XenForo_Visitor::getInstance();

		$input = $this->_input->filter(array(
			'rating' => XenForo_Input::UINT
		));

		$canAddComment = $commentModel->canAddComment();
		$commentRequired = XenForo_Application::getOptions()->xengalleryRequireComment;

		$existing = $this->_getRatingModel()->getRatingByContentAndUser($albumId, 'album', $visitor['user_id']);

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
				'content_id' => $album['album_id'],
				'content_type' => 'album',
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
					'content_id' => $albumId,
					'content_type' => 'album',
					'message' => $input['message'],
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'rating_id' => $ratingDw->get('rating_id')
				);

				$commentDw->bulkSet($data);
				$commentDw->save();
			}

			$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$newRating = $albumModel->getRatingAverage($albumDw->get('album_rating_sum'), $albumDw->get('album_rating_count'), true);
			$hintText = new XenForo_Phrase('x_votes', array('count' => $albumDw->get('album_rating_count')));

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
				'album' => $album,
				'rating' => $input['rating'],
				'existing' => ($existing ? $existing : false),

				'commentRequired' => $commentRequired,
				'canAddComment' => $canAddComment
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Rate', 'xengallery_album_rate', $viewParams);
		}
	}

	public function actionRatings()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album =  $mediaHelper->assertAlbumValidAndViewable($albumId);

		$conditions = array(
			'album_id' => $albumId
		);

		$fetchOptions = array(
			'join' => XenGallery_Model_Rating::FETCH_USER
		);

		$ratings = $this->_getRatingModel()->getRatings($conditions, $fetchOptions);

		$viewParams = array(
			'album' => $album,
			'ratings' => $ratings
		);

		return $this->responseView('XenGallery_ViewPublic_Album_Ratings', 'xengallery_album_ratings', $viewParams);
	}

	public function actionIp()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $this->_getMediaHelper()->assertAlbumValidAndViewable($albumId);

		if (!$this->_getUserModel()->canViewIps($error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		$ipInfo = $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($album);

		if (empty($ipInfo['contentIp']))
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'album' => $album,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
			'ipInfo' => $ipInfo
		);

		return $this->responseView('XenGallery_ViewPublic_Album_Ip', 'xengallery_album_ip', $viewParams);
	}

	public function actionReport()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		if ($this->isConfirmedPost())
		{
			$reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$reportMessage)
			{
				return $this->responseError(new XenForo_Phrase('xengallery_please_enter_reason_for_reporting_this_album'));
			}

			$this->assertNotFlooding('report');

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('xengallery_album', $album, $reportMessage);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album),
				new XenForo_Phrase('xengallery_thank_you_for_reporting_this_album')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album)
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Report', 'xengallery_album_report', $viewParams);
		}
	}

	public function actionMarkViewed()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);

		$album = array();
		if ($albumId)
		{
			$album = $this->_getMediaHelper()->assertAlbumValidAndViewable($albumId);
		}

		if ($this->isConfirmedPost())
		{
			$mediaModel = $this->_getMediaModel();

			$visitor = XenForo_Visitor::getInstance();

			$fetchOptions = array(
				'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
			);
			if ($albumId)
			{
				$fetchOptions['album_id'] = $albumId;
			}

			$mediaIds = $mediaModel->getUnviewedMediaIds($visitor->getUserId(), $fetchOptions);
			if (sizeof($mediaIds))
			{
				$media = $mediaModel->getMediaByIds($mediaIds);

				foreach ($media AS $item)
				{
					$mediaModel->markMediaViewed($item);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirectIfNot(XenForo_Link::buildPublicLink('find-new'), XenForo_Link::buildPublicLink('find-new/media'))
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album
			);

			return $this->responseView('XenGallery_ViewPublic_Media_MarkViewed', 'xengallery_mark_viewed', $viewParams);
		}
	}

	public function actionShared()
	{
		$this->_assertRegistrationRequired();

		$defaultOrder = 'album_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));

		$albumModel = $this->_getAlbumModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryAlbumMaxPerPage;

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'deleted' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewDeleted'),
			'shared_user_id' => $visitor->user_id,
			'privacyUserId' => $visitor->user_id,
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums')
		);
		$fetchOptions = array(
			'order' => $order ? $order : $defaultOrder,
			'orderDirection' => 'desc',
			'page' => $page,
			'perPage' => $perPage,
			'join' => XenGallery_Model_Album::FETCH_PRIVACY
				| XenGallery_Model_Album::FETCH_USER
		);

		$albums = $albumModel->getSharedAlbums($conditions, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums);

		$albumCount = $albumModel->countAlbums($conditions, $fetchOptions);

		$inlineModOptions = $albumModel->prepareInlineModOptions($albums);

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false)
		);

		$viewParams = array(
			'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),

			'albums' => $albums,
			'albumPage' => true,
			'albumCount' => $albumCount,

			'sharedMedia' => true,
			'inlineModOptions' => $inlineModOptions,

			'order' => $order,
			'defaultOrder' => $defaultOrder,

			'page' => $page,
			'perPage' => $perPage,
			'pageNavParams' => $pageNavParams,

			'hideFilterMenu' => true
		);

		return $this->_getAlbumMediaWrapper(
			$this->responseView('XenGallery_ViewPublic_Album_Shared', 'xengallery_album_shared', $viewParams)
		);
	}

	public function actionPrivacyDescription()
	{
		$privacyType = $this->_input->filterSingle('privacy_type', XenForo_Input::STRING);
		$phraseType = $this->_input->filterSingle('phrase_type', XenForo_Input::STRING);

		$descPhraseType = '';
		if ($phraseType == 'add')
		{
			$descPhraseType .= "_$phraseType";
		}

		$descPhrase = new XenForo_Phrase('xengallery_' . $privacyType . $descPhraseType . '_explain');

		return $this->responseView('XenGallery_ViewPublic_Album_PrivacyDescription', '', array(
			'descPhrase' => $descPhrase
		));
	}

	public function actionPermissions()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		if (!$albumId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();

		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
		$mediaHelper->assertcanChangeAlbumViewPerm($album);

		$albumModel = $this->_getAlbumModel();
		$album = $albumModel->prepareAlbumWithPermissions($album);

		$canChangeAddPermission = $albumModel->canChangeAlbumAddPerm($album);

		if ($this->isConfirmedPost())
		{
			$userModel = $this->_getUserModel();

			$viewMedia = $this->_input->filterSingle('view_media', XenForo_Input::STRING);
			$addMedia = $this->_input->filterSingle('add_media', XenForo_Input::STRING);

			if (!$canChangeAddPermission && ($addMedia != $album['albumPermissions']['add']['access_type']))
			{
				$addMedia = $album['albumPermissions']['add']['access_type'];
			}

			$viewUsers = array();
			if ($viewMedia == 'shared')
			{
				$viewUsernames = $this->_input->filterSingle('view_users', XenForo_Input::STRING);
				$viewUsernames = explode(',', $viewUsernames);

				$viewUsers = $userModel->getUsersByNames($viewUsernames, array('join' => XenForo_Model_User::FETCH_USER_FULL), $notFound);
				if ($notFound)
				{
					return $this->responseError(new XenForo_Phrase('xengallery_following_members_not_found_when_setting_view_permissions_x', array('members' => implode(', ', $notFound))));
				}
				$notFound = false;

				// prevent sharing with the owner
				if (isset($viewUsers[$album['album_user_id']]))
				{
					if (sizeof($viewUsers) == 1)
					{
						return $this->responseError(new XenForo_Phrase('xengallery_album_owner_cannot_share_with_themselves'));
					}
					unset($viewUsers[$album['album_user_id']]);
				}

				if (empty($viewUsers))
				{
					return $this->responseError(new XenForo_Phrase('xengallery_please_specify_one_or_more_members_to_share'));
				}

				$viewUsers = $albumModel->prepareViewShareUsers($viewUsers, $album);
			}

			$addUsers = array();
			if ($addMedia == 'shared')
			{
				$addUsernames = $this->_input->filterSingle('add_users', XenForo_Input::STRING);
				$addUsernames = explode(',', $addUsernames);

				$addUsers = $userModel->getUsersByNames($addUsernames, array('join' => XenForo_Model_User::FETCH_USER_FULL), $notFound);
				if ($notFound)
				{
					return $this->responseError(new XenForo_Phrase('xengallery_following_members_not_found_when_setting_add_permissions_x', array('members' => implode(', ', $notFound))));
				}

				// prevent sharing with the owner
				if (isset($addUsers[$album['album_user_id']]))
				{
					if (sizeof($addUsers) == 1)
					{
						return $this->responseError(new XenForo_Phrase('xengallery_album_owner_cannot_share_with_themselves'));
					}
					unset($addUsers[$album['album_user_id']]);
				}

				if (empty($addUsers))
				{
					return $this->responseError(new XenForo_Phrase('xengallery_please_specify_one_or_more_members_to_share'));
				}

				$addUsers = $albumModel->prepareAddShareUsers($addUsers, $album);

				// If add user cannot view, allow them to view.
				foreach ($addUsers AS $userId => $user)
				{
					if ($viewUsers && !isset($viewUsers[$userId]))
					{
						$viewUsers[$userId] = $user;
					}
				}
			}

			$albumViewData = array(
				'album_id' => $albumId,
				'permission' => 'view',
				'access_type' => $viewMedia,
				'share_users' => $viewUsers ? $viewUsers : @unserialize($album['albumPermissions']['view']['share_users'])
			);
			$albumModel->writeAlbumPermission($albumViewData, $album);

			$albumAddData = array(
				'album_id' => $albumId,
				'permission' => 'add',
				'access_type' => $addMedia,
				'share_users' => $addUsers ? $addUsers : @unserialize($album['albumPermissions']['add']['share_users'])
			);
			$albumModel->writeAlbumPermission($albumAddData, $album);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album),
				new XenForo_Phrase('xengallery_album_privacy_changed_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
				'canChangeAddPermission' => $canChangeAddPermission
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Permissions', 'xengallery_album_permissions', $viewParams);
		}
	}

	public function actionCreate()
	{
		$options = XenForo_Application::getOptions();

		$visitor = XenForo_Visitor::getInstance();
		$albumModel = $this->_getAlbumModel();

		$permConditions = array(
			'album_user_id' => $visitor->user_id
		);

		$canChangeViewPermission = $albumModel->canChangeAlbumViewPerm($permConditions);
		$canChangeAddPermission = $albumModel->canChangeAlbumAddPerm($permConditions);

		if ($this->isConfirmedPost())
		{
			$albumInput = $this->_input->filter(array(
				'album_title' => XenForo_Input::STRING,
				'album_description' => XenForo_Input::STRING
			));

			$albumInput = $albumInput + array(
				'album_user_id' => $visitor->user_id,
				'album_username' => $visitor->username
			);

			$viewMedia = $this->_input->filterSingle('view_media', XenForo_Input::STRING);
			$addMedia = $this->_input->filterSingle('add_media', XenForo_Input::STRING);

			if (!$canChangeViewPermission && ($viewMedia != $options->xengalleryAlbumViewPerm))
			{
				$viewMedia = $options->xengalleryAlbumViewPerm;
			}

			if (!$canChangeAddPermission && ($addMedia != $options->xengalleryAlbumAddPerm))
			{
				$addMedia = $options->xengalleryAlbumAddPerm;
			}

			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExtraData(XenGallery_DataWriter_Album::DATA_ACCESS_TYPE, $viewMedia);
			$albumWriter->setExtraData(XenGallery_DataWriter_Album::DATA_ACCESS_TYPE_ADD, $addMedia);

			$albumWriter->bulkSet($albumInput);
			$albumWriter->save();
			$album = $albumWriter->getMergedData();

			$userModel = $this->_getUserModel();

			$viewUsers = array();
			if ($viewMedia == 'shared')
			{
				$viewUsernames = $this->_input->filterSingle('view_users', XenForo_Input::STRING);
				$viewUsernames = explode(',', $viewUsernames);

				$viewUsers = $userModel->getUsersByNames($viewUsernames, array('join' => XenForo_Model_User::FETCH_USER_FULL));

				// prevent sharing with the owner
				if (isset($viewUsers[$album['album_user_id']]))
				{
					unset($viewUsers[$album['album_user_id']]);
				}

				$viewUsers = $albumModel->prepareViewShareUsers($viewUsers, $album);
			}

			$addUsers = array();
			if ($addMedia == 'shared')
			{
				$addUsernames = $this->_input->filterSingle('add_users', XenForo_Input::STRING);
				$addUsernames = explode(',', $addUsernames);

				$addUsers = $userModel->getUsersByNames($addUsernames, array('join' => XenForo_Model_User::FETCH_USER_FULL));

				// prevent sharing with the owner
				if (isset($addUsers[$album['album_user_id']]))
				{
					unset($addUsers[$album['album_user_id']]);
				}

				$addUsers = $albumModel->prepareAddShareUsers($addUsers, $album);

				// If add user cannot view, allow them to view.
				foreach ($addUsers AS $userId => $user)
				{
					if ($viewUsers && !isset($viewUsers[$userId]))
					{
						$viewUsers[$userId] = $user;
					}
				}
			}

			$albumViewData = array(
				'album_id' => $album['album_id'],
				'permission' => 'view',
				'access_type' => $viewMedia,
				'share_users' => $viewUsers
			);
			$albumModel->writeAlbumPermission($albumViewData, $album);

			$albumAddData = array(
				'album_id' => $album['album_id'],
				'permission' => 'add',
				'access_type' => $addMedia,
				'share_users' => $addUsers
			);
			$albumModel->writeAlbumPermission($albumAddData, $album);

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

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/add', '', array('album_id' => $album['album_id'])),
				new XenForo_Phrase('xengallery_album_has_been_created_successfully')
			);
		}
		else
		{

			$viewParams = array(
				'canChangeViewPermission' => $canChangeViewPermission,
				'canChangeAddPermission' => $canChangeAddPermission
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Create', 'xengallery_album_create', $viewParams);
		}
	}

	public function actionCustomOrder()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumModel = $this->_getAlbumModel();
		$mediaModel = $this->_getMediaModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
		$album = $albumModel->prepareAlbum($album);

		$mediaHelper->assertCanChangeAlbumOrder($album);

		if ($this->isConfirmedPost())
		{
			$mediaIds = $this->_input->filterSingle('mediaId', XenForo_Input::UINT, array('array' => true));
			$mediaIds = array_flip($mediaIds);

			$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumDw->setExistingData($albumId);

			$albumDw->set('album_default_order', 'custom');

			if ($albumDw->save())
			{
				$mediaModel->setMediaPosition($mediaIds);
			}

			$this->_logChanges($albumDw, $album, 'custom_order', array(), 'xengallery_album', 'album_user_id');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album)
			);
		}
		else
		{
			$order = 'new';
			if ($album['album_default_order'] && $album['album_media_count'] > 1)
			{
				$order = $album['album_default_order'];
			}

			$mediaConditions = array(
				'album_id' => $albumId
			);
			$mediaFetchOptions = array(
				'order' => $order,
				'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
					| XenGallery_Model_Media::FETCH_ALBUM
					| XenGallery_Model_Media::FETCH_CATEGORY
			);
			$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
			$media = $mediaModel->prepareMediaItems($media);

			$viewParams = array(
				'album' => $album,
				'media' => $media,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album)
			);

			return $this->responseView('XenGallery_ViewPublic_Album_CustomOrder', 'xengallery_album_custom_order', $viewParams);
		}
	}

	public function actionCustomOrderDelete()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));

		$mediaHelper = $this->_getMediaHelper();

		$albumModel = $this->_getAlbumModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
		$album = $albumModel->prepareAlbum($album);

		$mediaHelper->assertCanChangeAlbumOrder($album);

		$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');

		$albumDw->setExistingData($album);
		$albumDw->set('album_default_order', '');

		$albumDw->save();

		$this->_logChanges($albumDw, $album, 'custom_order_delete', array(), 'xengallery_album', 'album_user_id');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery/albums', $album)
		);
	}

	public function actionThumbnail()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumModel = $this->_getAlbumModel();
		$mediaModel = $this->_getMediaModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		$mediaHelper->assertCanChangeAlbumThumbnail($album);

		if ($this->isConfirmedPost())
		{
			$mediaIds = $this->_input->filterSingle('mediaId', XenForo_Input::UINT, array('array' => true));
			if (!$mediaIds)
			{
				return $this->responseError(new XenForo_Phrase('xengallery_you_must_select_at_least_one_image_as_a_thumbnail'));
			}

			$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumDw->setExistingData($albumId);

			$media = $this->_getMediaModel()->getMediaForAlbumCacheByMediaIds($mediaIds);

			$orderedMedia = array();
			foreach ($mediaIds AS $mediaId)
			{
				$orderedMedia[$mediaId] = $media[$mediaId];
			}

			$albumDw->bulkSet(array(
				'last_update_date' => XenForo_Application::$time,
				'media_cache' => serialize($orderedMedia),
				'manual_media_cache' => 1,
				'album_thumbnail_date' => 0
			));

			$albumDw->save();

			$this->_logChanges($albumDw, $album, 'thumbnail_add', array(), 'xengallery_album', 'album_user_id');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album)
			);
		}
		else
		{
			$mediaConditions = array(
				'album_id' => $albumId
			);
			$mediaFetchOptions = array(
				'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
					| XenGallery_Model_Media::FETCH_ALBUM
					| XenGallery_Model_Media::FETCH_CATEGORY
			);
			$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
			$media = $mediaModel->prepareMediaItems($media);
			$media = $albumModel->prepareAlbumThumbs($media, $album);

			$viewParams = array(
				'album' => $album,
				'media' => $media,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Thumbnail', 'xengallery_album_thumbnail', $viewParams);
		}
	}

	public function actionThumbnailUpload()
	{
		$mediaHelper = $this->_getMediaHelper();

		$albumModel = $this->_getAlbumModel();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		$mediaHelper->assertCanChangeAlbumThumbnail($album);

		if ($this->isConfirmedPost())
		{
			$thumbnail = XenForo_Upload::getUploadedFile('thumbnail');
			$delete = $this->_input->filterSingle('delete', XenForo_Input::BOOLEAN);

			if ($thumbnail)
			{
				$albumModel->uploadAlbumThumbnail($thumbnail, $album['album_id']);

				if (XenForo_Visitor::getUserId() != $album['album_user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_album', $album, 'thumbnail_add');
				}
			}
			else if ($delete)
			{
				$albumModel->deleteAlbumThumbnail($album['album_id']);

				if (XenForo_Visitor::getUserId() != $album['album_user_id'])
				{
					XenForo_Model_Log::logModeratorAction('xengallery_album', $album, 'thumbnail_remove');
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album)
			);
		}
		else
		{
			$viewParams = array(
				'album' => $albumModel->prepareAlbum($album),
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
			);

			return $this->responseView('XenGallery_ViewPublic_Album_ThumbnailUpload', 'xengallery_album_thumbnail_upload', $viewParams);
		}
	}

	public function actionThumbnailRandom()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('t', XenForo_Input::STRING));

		$mediaHelper = $this->_getMediaHelper();

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		$mediaHelper->assertCanChangeAlbumThumbnail($album);

		$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
		$albumDw->setExistingData($album);

		$albumDw->bulkSet(array(
			'last_update_date' => XenForo_Application::$time,
			'media_cache' => serialize($this->_getMediaModel()->getMediaForAlbumCache($albumId)),
			'manual_media_cache' => 0,
			'album_thumbnail_date' => 0
		));
		$albumDw->save();

		if (XenForo_Visitor::getUserId() != $album['album_user_id'])
		{
			XenForo_Model_Log::logModeratorAction('xengallery_album', $album, 'thumbnail_add');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery/albums', $album)
		);
	}

	public function actionWatch()
	{
		$mediaHelper = $this->_getMediaHelper();
		$albumModel = $this->_getAlbumModel();

		if (!$albumModel->canWatchAlbum())
		{
			return $this->responseNoPermission();
		}

		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$album = $mediaHelper->assertAlbumValidAndViewable($albumId);

		/** @var $albumWatchModel XenGallery_Model_AlbumWatch */
		$albumWatchModel = $this->getModelFromCache('XenGallery_Model_AlbumWatch');

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

			$albumWatchModel->setAlbumWatchState(
				XenForo_Visitor::getUserId(), $albumId,
				$notifyOn, $sendAlert, $sendEmail
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/albums', $album),
				null,
				array('linkPhrase' => ($notifyOn != 'delete' ? new XenForo_Phrase('xengallery_unwatch_album') : new XenForo_Phrase('xengallery_watch_album')))
			);
		}
		else
		{
			$albumWatch = $albumWatchModel->getUserAlbumWatchByAlbumId(
				XenForo_Visitor::getUserId(), $albumId
			);

			$viewParams = array(
				'album' => $album,
				'albumWatch' => $albumWatch,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($album),
			);

			return $this->responseView('XenGallery_ViewPublic_Album_Watch', 'xengallery_album_watch', $viewParams);
		}
	}
}