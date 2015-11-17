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
class sonnb_XenGallery_ControllerPublic_InlineMod_Comment extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * @var string
	 */
	public $inlineModKey = 'sxgcomments';

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$commentIds = $this->getInlineModIds(false);

			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			$deleted = $this->_getInlineCommentModel()->deleteComments(
				$commentIds, $options, $errorPhraseKey
			);

			if (!$deleted)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		else
		{
			$commentIds = $this->getInlineModIds();

			$handler = $this->_getInlineCommentModel();
			if (!$handler->canDeleteComments($commentIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$commentIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'commentIds' => $commentIds,
				'commentCount' => count($commentIds),
				'canHardDelete' => $handler->canDeleteComments($commentIds, 'hard'),
				'redirect' => $redirect,
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_InlineMod_Comment_Delete',
				'sonnb_xengallery_inline_mod_comment_delete',
				$viewParams
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteComments');
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveComments');
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveComments');
	}

	/**
	 * @return sonnb_XenGallery_Model_InlineMod_Comment
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_InlineMod_Comment');
	}

	/**
	 * @return sonnb_XenGallery_Model_InlineMod_Comment
	 */
	protected function _getInlineCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_InlineMod_Comment');
	}
}