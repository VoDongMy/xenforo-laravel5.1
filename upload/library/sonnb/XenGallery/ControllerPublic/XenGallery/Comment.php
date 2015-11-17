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
class sonnb_XenGallery_ControllerPublic_XenGallery_Comment extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		return $this->_redirectToContainer($comment, $content, $album);
	}
	
	public function actionEdit()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		if (!$comment['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_comment');
		}
		
		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			$message = XenForo_Helper_String::autoLinkBbCode($message);
			
			$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
			$commentDw->setExistingData($comment);
			$commentDw->set('message', $message);
			$commentDw->save();

			if ($this->_noRedirect())
			{
				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Comment_EditInline',
					'',
					array(
						'comment' => $commentDw->getMergedData(),
						'content' => $content,
						'album' => $album
					)
				);
			}
			else
			{
				return $this->_redirectToContainer(
					$comment, $content, $album,
					new XenForo_Phrase('changes_saved'),
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED
				);
			}
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Comment_Edit',
				'sonnb_xengallery_comment_edit',
					
				array(
					'comment' => $comment,
					'content' => $content,
					'album' => $album,
					'breadCrumbs' => $this->_getCommentModel()->getCommentBreadCrumbs($comment, $content, $album),

                    'includeTaggerJs' => $this->_getGalleryModel()->includeJsTagger()
				)		
			);
		}
	}
	
	public function actionDelete()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		if (!$comment['canDelete'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_comment');
		}

		if ($this->_input->inRequest('undo_delete'))
		{
			return $this->responseReroute(__CLASS__, 'undo-delete');
		}
		
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
			$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);
			
			if ($hardDelete)
			{
				if (!$this->_getCommentModel()->canDeleteComment($comment, 'hard'))
				{
					return $this->responseNoPermission();
				}
			
				$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
				$commentDw->setExistingData($comment);
			
				$commentDw->setExtraData(sonnb_XenGallery_DataWriter_Comment::DATA_DELETE_REASON, $reason);
				$commentDw->delete();
			
				$message = new XenForo_Phrase('sonnb_xengallery_your_comment_has_been_deleted');
				$comment = $commentDw->getMergedData();
			}
			else
			{
				$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
				$commentDw->setExistingData($comment);
				
				$commentDw->set('comment_state', 'deleted');
				$commentDw->save();

				$commentFetchOptions = array(
					'join' => sonnb_XenGallery_Model_Comment::FETCH_USER
				);
				$message = new XenForo_Phrase('changes_saved');

				$comment = $this->_getCommentModel()->getCommentById($commentDw->get('comment_id'), $commentFetchOptions);
				$comment = $this->_getCommentModel()->prepareComment($comment, $commentFetchOptions);
			}

			if ($this->_noRedirect())
			{
				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Comment_DeleteInline',
					'',
					array(
						'comment' => $comment,
						'content' => $content,
						'album' => $album,
						'phrase' => $message->render(),
						'hardDelete' => $hardDelete
					)
				);
			}
			else
			{
				return $this->_redirectToContainer($comment, $content, $album, $message, XenForo_ControllerResponse_Redirect::SUCCESS);
			}
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Comment_Delete',
				'sonnb_xengallery_comment_delete',
					
				array(
					'comment' => $comment,
					'content' => $content,
					'album' => $album,
						
					'canHardDelete' => $this->_getCommentModel()->canDeleteComment($comment, 'hard'),	
						
					'breadCrumbs' => $this->_getCommentModel()->getCommentBreadCrumbs($comment, $content, $album)
				)		
			);
		}
	}
	
	public function actionUndoDelete()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
	
		if (!$comment['canDelete'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_comment');
		}
	
		$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
		$commentDw->setExistingData($comment);
		$commentDw->set('comment_state', 'visible');
		$commentDw->save();

		$message = new XenForo_Phrase('changes_saved');

		if ($this->_noRedirect())
		{
			$commentFetchOptions = array(
				'join' => sonnb_XenGallery_Model_Comment::FETCH_USER
			);
			$comment = $this->_getCommentModel()->getCommentById($commentDw->get('comment_id'), $commentFetchOptions);
			$comment = $this->_getCommentModel()->prepareComment($comment, $commentFetchOptions);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Comment_DeleteInline',
				'',
				array(
					'comment' => $comment,
					'content' => $content,
					'album' => $album,
					'phrase' => $message->render(),
					'hardDelete' => false
				)
			);
		}
		else
		{
			return $this->_redirectToContainer($comment, $content, $album, $message, XenForo_ControllerResponse_Redirect::SUCCESS);
		}
	}
	
	public function actionReport()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		if (!$this->_getCommentModel()->canReportComment($comment, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			$this->assertNotFlooding('report');

            /** @var XenForo_Model_Report $reportModel */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent(sonnb_XenGallery_Model_Comment::$xfContentType, $comment, $message);

			return $this->_redirectToContainer(
					$comment, $content, $album,
					new XenForo_Phrase('thank_you_for_reporting_this_message'),
					XenForo_ControllerResponse_Redirect::SUCCESS
				);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'content' => $content,
				'album' => $album,
					
				'breadCrumbs' => $this->_getCommentModel()->getCommentBreadCrumbs($comment, $content, $album)
			);

			return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Comment_Report', 
					'sonnb_xengallery_comment_report', 
					$viewParams
				);
		}
	}
	
	public function actionLike()
	{
		$visitor = XenForo_Visitor::getInstance();
		
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		if (!$this->_getCommentModel()->canLikeComment($comment, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}
		
		$likeModel = $this->_getLikeModel();
		
		$existingLike = $likeModel->getContentLikeByLikeUser(
				sonnb_XenGallery_Model_Comment::$xfContentType, 
				$comment['comment_id'], 
				XenForo_Visitor::getUserId()
			);
		
		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent(sonnb_XenGallery_Model_Comment::$xfContentType, $comment['comment_id'], $comment['user_id']);
				
				switch ($comment['content_type'])
				{
					case sonnb_XenGallery_Model_Photo::$contentType:
					case sonnb_XenGallery_Model_Video::$contentType:
						if ($visitor['user_id'] != $content['user_id'])
						{
							$this->_getWatchModel()->insertUpdateWatcherByContentId(
								$visitor,
								$comment['content_type'],
								$content['content_id']
							);
						}
						break;
					case sonnb_XenGallery_Model_Album::$contentType:
						if ($visitor['user_id'] != $album['user_id'])
						{
							$this->_getWatchModel()->insertUpdateWatcherByContentId(
								$visitor,
								$comment['content_type'],
								$album['album_id']
							);
						}
						break;
				}
			}
		
			$liked = ($existingLike ? false : true);
		
			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$comment['likeUsers'] = $latestUsers;
				$comment['likes'] += ($liked ? 1 : ($comment['likes'] ? -1: 0));
				$comment['like_date'] = ($liked ? XenForo_Application::$time : 0);
		
				$viewParams = array(
					'comment' => $comment,
					'content' => $content,
					'album' => $album,
					'liked' => $liked,
				);
		
				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Comment_LikeConfirmed', '', $viewParams
				);
			}
			else
			{
				return $this->_redirectToContainer($comment, $content, $album, null, XenForo_ControllerResponse_Redirect::SUCCESS);
			}
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'album' => $album,
				'content' => $content,
				'like' => $existingLike,
					
				'breadCrumbs' => $this->_getCommentModel()->getCommentBreadCrumbs($comment, $content, $album)
			);
		
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Comment_Like',
				'sonnb_xengallery_comment_like',
				$viewParams
			);
		}
	}
	
	public function actionLikes()
	{
		list($comment, $content, $album) = $this->_getCommentOrError();
		
		$likes = $this->_getLikeModel()->getContentLikes(sonnb_XenGallery_Model_Comment::$xfContentType, $comment['comment_id']);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_no_one_likes_this_comment_yet'));
		}
		
		$viewParams = array(
				'comment' => $comment,
				
				'album' => $album,
				'content' => $content,
		
				'breadCrumbs' => $this->_getCommentModel()->getCommentBreadCrumbs($comment, $content, $album),
		
				'likes' => $likes
		);
		
		return $this->responseView('sonnb_XenGallery_ViewPublic_Comment_Likes', 'sonnb_xengallery_comment_likes', $viewParams);
	}

	protected function _getCommentOrError($commentId = null)
	{
		if ($commentId === null)
		{
			$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
		}

		/* @var $galleryHelper sonnb_XenGallery_ControllerHelper_Gallery */
		$galleryHelper = $this->getHelper('sonnb_XenGallery_ControllerHelper_Gallery');

		return $galleryHelper->assertCommentValidAndViewable($commentId);
	}
	
	protected function _redirectToContainer(array $comment, array $content, array $album, $message = null, $type = null)
	{
        if ($type === null)
        {
		    $type = XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED;
        }
		
		switch ($comment['content_type'])
		{
			case sonnb_XenGallery_Model_Photo::$contentType:
				$redirectTarget = XenForo_Link::buildPublicLink('gallery/photos', $content). '#comment_'.$comment['comment_id'];
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$redirectTarget = XenForo_Link::buildPublicLink('gallery/videos', $content). '#comment_'.$comment['comment_id'];
				break;
            case sonnb_XenGallery_Model_Album::$contentType:
            default:
				$redirectTarget = XenForo_Link::buildPublicLink('gallery/albums', $album). '#comment_'.$comment['comment_id'];
				break;
		}
		
		return $this->responseRedirect($type, $redirectTarget, $message);
	}
}