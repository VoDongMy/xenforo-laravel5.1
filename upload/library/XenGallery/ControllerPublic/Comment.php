<?php

class XenGallery_ControllerPublic_Comment extends XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$commentModel = $this->_getCommentModel();
		$mediaHelper = $this->_getMediaHelper();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		$fetchOptions = array(
			'join' =>	XenGallery_Model_Comment::FETCH_USER
				| XenGallery_Model_Comment::FETCH_MEDIA
				| XenGallery_Model_Comment::FETCH_CATEGORY
		);

		$redirect = 'xengallery';
		$comment = $commentModel->getCommentById($commentId, $fetchOptions);
		if ($comment['content_type'] == 'media')
		{
			$content = $mediaHelper->assertMediaValidAndViewable($comment['content_id']);
			$pageParam = 'page';
		}
		else
		{
			$content = $mediaHelper->assertAlbumValidAndViewable($comment['content_id']);
			$redirect .= '/albums';
			$pageParam = 'commentpage';
		}

		$moderated = $commentModel->canViewUnapprovedComment();
		if (!$moderated)
		{
			$moderated = XenForo_Visitor::getUserId();
		}

		$conditions = array(
			'content_id' => $comment['content_id'],
			'content_type' => $comment['content_type'],
			'deleted' => $commentModel->canViewDeletedComment(),
			'moderated' => $moderated,
			'comment_id_lt' => $commentId
		);
		$commentsBefore = $commentModel->countComments($conditions);

		$commentsPerPage = XenForo_Application::getOptions()->xengalleryMaxCommentsPerPage;
		$page = floor($commentsBefore / $commentsPerPage) + 1;

		$linkParams = array();
		if ($page > 1)
		{
			$linkParams = array($pageParam => $page);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink($redirect, $content, $linkParams) . '#comment-' . $commentId
		);
	}

	public function actionEdit()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();

		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);
		$mediaHelper->assertCanEditComment($comment);

		$viewParams = array(
			'comment' => $comment,
			'content' => $content,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true),
			'commentEditor' => true
		);

		return $this->responseView('XenGallery_ViewPublic_Media_CommentEdit', 'xengallery_comment_edit', $viewParams);
	}

	public function actionEditInline()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		$mediaHelper = $this->_getMediaHelper();

		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);
		$mediaHelper->assertCanEditComment($comment);

		$viewParams = array(
			'comment' => $comment,
			'content' => $content,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_CommentEditInline', 'xengallery_comment_edit_inline', $viewParams);
	}

	public function actionDelete()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		if (!$commentId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$mediaHelper = $this->_getMediaHelper();
		$commentModel = $this->_getCommentModel();

		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);
		$mediaHelper->assertCanDeleteComment($comment, $deleteType);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
			$writer->setExistingData($comment['comment_id']);

			$reason = '';

			if ($hardDelete)
			{
				$writer->delete();
			}
			else
			{
				$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);

				$writer->setExtraData(XenGallery_DataWriter_Comment::DATA_DELETE_REASON, $reason);
				$writer->set('comment_state', 'deleted');
				$writer->save();
			}

			$this->_sendAuthorAlert($comment, 'xengallery_comment', 'delete', array(
				'content' => $content,
				'comment' => $comment
			));

			$this->_logChanges($writer, $comment, 'delete_' . $deleteType, array('reason' => $reason), 'xengallery_comment');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(XenForo_Link::buildPublicLink('xengallery/comments', $comment))
			);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'content' => $content,
				'canHardDelete' => $commentModel->canDeleteComment($comment, 'hard'),
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true)
			);

			return $this->responseView('XenGallery_ViewPublic_Media_CommentDelete', 'xengallery_comment_delete', $viewParams);
		}
	}

	public function actionUndelete()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		if (!$commentId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();
		$comment = $mediaHelper->assertCommentValidAndViewable($commentId);

		if ($comment['comment_state'] != 'deleted')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery/comments', $comment)
			);
		}

		$mediaHelper->assertCanDeleteComment($comment);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
			$writer->setExistingData($comment['comment_id']);

			$writer->set('comment_state', 'visible');
			$writer->save();

			$this->_logChanges($writer, $comment, 'undelete', array(), 'xengallery_comment');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(XenForo_Link::buildPublicLink('xengallery/comments', $comment))
			);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment
			);

			return $this->responseView('XenGallery_ViewPublic_Media_CommentUndelete', 'xengallery_comment_undelete', $viewParams);
		}
	}

	public function actionIp()
	{
		$mediaHelper = $this->_getMediaHelper();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);

		if (!$this->_getUserModel()->canViewIps($error))
		{
			return $this->getErrorOrNoPermissionResponseException($error);
		}

		$ipInfo = $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($comment);

		if (empty($ipInfo['contentIp']))
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'comment' => $comment,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true),
			'ipInfo' => $ipInfo
		);

		return $this->responseView('XenGallery_ViewPublic_Media_CommentIp', 'xengallery_comment_ip', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationRequired();

		$commentModel = $this->_getCommentModel();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		$fetchOptions = array(
			'join' =>	XenGallery_Model_Comment::FETCH_MEDIA
				| XenGallery_Model_Comment::FETCH_ALBUM_CONTENT
				| XenGallery_Model_Comment::FETCH_USER
		);

		list ($comment, $content) = $this->_getMediaHelper()->assertCommentAndContentValidAndViewable($commentId, $fetchOptions);

		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');

		$writer->setExistingData($commentId);
		$writer->set('message', $input['message']);

		$writer->save();

		$this->_sendAuthorAlert($comment, 'xengallery_comment', 'edit', array(
			'content' => $content,
			'comment' => $comment
		));

		$this->_logChanges($writer, $comment, 'edit', array(), 'xengallery_comment');

		if ($this->_noRedirect())
		{
			if ($commentId)
			{
				$comment = $commentModel->prepareComments($comment);
				$comment['message'] = $input['message'];

				if ($comment['rating_id'])
				{
					$rating = $this->_getRatingModel()->getRatingById($comment['rating_id']);
					if ($rating)
					{
						$comment['rating'] = $rating['rating'];
					}
					else
					{
						$comment['rating'] = 0;
					}
				}
			}

			$visitor = XenForo_Visitor::getInstance();
			$viewParams = array(
				'comment' => $comment,
				'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
				'canViewIps' => XenForo_Permission::hasPermission($visitor->permissions, 'general', 'viewIps'),
				'canViewWarnings' => $this->getModelFromCache('XenForo_Model_User')->canViewWarnings(),
				'canReport' => $visitor['user_id'] ? true : false
			);

			return $this->responseView(
				'XenGallery_ViewPublic_Media_Save_CommentListItem',
				'',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xengallery/comments', $comment),
			new XenForo_Phrase('xengallery_comment_updated_successfully')
		);
	}

	public function actionShow()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		$comment = $this->_getMediaHelper()->assertCommentValidAndViewable($commentId);
		$comment = $this->_getCommentModel()->prepareComments($comment);

		$visitor = XenForo_Visitor::getInstance();

		$viewParams = array(
			'comment' => $comment,
			'commentId' => $commentId,
			'canViewRatings' => $this->_getMediaModel()->canViewRatings(),
			'canViewIps' => XenForo_Permission::hasPermission($visitor->permissions, 'general', 'viewIps'),
			'canViewWarnings' => $this->getModelFromCache('XenForo_Model_User')->canViewWarnings(),
			'canReport' => $visitor->user_id ? true : false
		);

		return $this->responseView(
			'XenGallery_ViewPublic_Media_ShowComment',
			'',
			$viewParams
		);
	}

	public function actionLike()
	{
		$mediaHelper = $this->_getMediaHelper();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);

		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);
		$mediaHelper->assertCanLikeComment($comment);

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('xengallery_comment', $commentId, XenForo_Visitor::getUserId());

		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent('xengallery_comment', $commentId, $comment['user_id']);
			}

			$liked = ($existingLike ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$comment['likeUsers'] = $latestUsers;
				$comment['likes'] += ($liked ? 1 : -1);
				$comment['like_date'] = ($liked ? XenForo_Application::$time : 0);

				$viewParams = array(
					'comment' => $comment,
					'liked' => $liked
				);

				return $this->responseView('XenGallery_ViewPublic_Media_LikeConfirmedComment', '', $viewParams);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('xengallery/comments', $comment)
				);
			}
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true),
				'like' => $existingLike
			);

			return $this->responseView('XenGallery_ViewPublic_Comment_Like', 'xengallery_comment_like', $viewParams);
		}
	}

	public function actionLikes()
	{
		$mediaHelper = $this->_getMediaHelper();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		$comment =  $mediaHelper->assertCommentValidAndViewable($commentId);

		$likes = $this->_getLikeModel()->getContentLikes('xengallery_comment', $commentId);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('xengallery_no_one_has_liked_this_comment_yet'));
		}

		$viewParams = array(
			'comment' => $comment,
			'likes' => $likes,
			'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($comment)
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Ratings', 'xengallery_comment_likes', $viewParams);
	}

	public function actionReport()
	{
		$mediaHelper = $this->_getMediaHelper();

		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		list ($comment, $content) = $mediaHelper->assertCommentAndContentValidAndViewable($commentId);

		if ($this->isConfirmedPost())
		{
			$reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$reportMessage)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			$this->assertNotFlooding('report');

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('xengallery_comment', $comment, $reportMessage);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery', $comment),
				new XenForo_Phrase('xengallery_thank_you_for_reporting_this_comment')
			);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($content, true)
			);

			return $this->responseView('XenGallery_ViewPublic_Media_CommentReport', 'xengallery_comment_report', $viewParams);
		}
	}
}