<?php

class XenGallery_ControllerPublic_InlineMod_Comment extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'comment';

	/**
	 * @return XenGallery_Model_InlineMod_Comment
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenGallery_Model_InlineMod_Comment');
	}

	/**
	 * Comment deletion handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING),
				'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
				'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
			);

			return $this->executeInlineModAction('deleteComment', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$commentIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Comment */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canDeleteComment($commentIds, 'soft', $errorPhraseKey))
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
				'canHardDelete' => $handler->canDeleteComment($commentIds, 'hard'),
				'canSendAlert' => $handler->canSendActionAlert(),
				'redirect' => $redirect,
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_Comment_Delete', 'xengallery_inline_mod_comment_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified comment.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteComment');
	}

	/**
	 * Approves the specified comment.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveComment');
	}

	/**
	 * Unapproves the specified Comment.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveComment');
	}
}