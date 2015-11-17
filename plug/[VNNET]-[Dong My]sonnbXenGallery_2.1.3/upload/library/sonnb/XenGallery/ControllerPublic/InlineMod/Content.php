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
class sonnb_XenGallery_ControllerPublic_InlineMod_Content extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * @var string
	 */
	public $inlineModKey = 'sxgcontents';

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$contentIds = $this->getInlineModIds(false);

			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			$deleted = $this->_getInlineContentModel()->deleteContents(
				$contentIds, $options, $errorPhraseKey
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
			$contentIds = $this->getInlineModIds();

			$handler = $this->_getInlineContentModel();
			if (!$handler->canDeleteContents($contentIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$contentIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'contentIds' => $contentIds,
				'contentCount' => count($contentIds),
				'canHardDelete' => $handler->canDeleteContents($contentIds, 'hard'),
				'redirect' => $redirect,
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_InlineMod_Content_Delete',
				'sonnb_xengallery_inline_mod_content_delete',
				$viewParams
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function actionMove()
	{
		if ($this->isConfirmedPost())
		{
			$contentIds = $this->getInlineModIds(false);
			$options = array();

			$input = $this->_input->filter(array(
				'album_id' => XenForo_Input::UINT
			));

			$album = $this->_getAlbumModel()->getAlbumById($input['album_id']);

			if (empty($input['album_id']) || !$album)
			{
				$this->responseNoPermission();
			}

			if (!$this->_getInlineContentModel()->moveContents($contentIds, $input['album_id'], $options, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('gallery/albums', $album)
			);
		}
		else
		{
			$contentIds = $this->getInlineModIds();

			$handler = $this->_getInlineContentModel();
			if (!$handler->canMoveContents($contentIds, 0, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$contentIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$firstContent = $this->_getContentModel()->getContentById(reset($contentIds));

			$viewParams = array(
				'contentIds' => $contentIds,
				'contentCount' => count($contentIds),
				'firstContent' => $firstContent,
				'albums' => $this->_getAlbumModel()->getAlbums(),
				'redirect' => $redirect
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_InlineMod_Content_Move',
				'sonnb_xengallery_inline_mod_content_move',
				$viewParams
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteContents');
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveContents');
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveContents');
	}

	/**
	 * @return sonnb_XenGallery_Model_InlineMod_Content
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_InlineMod_Content');
	}

	/**
	 * @return sonnb_XenGallery_Model_InlineMod_Content
	 */
	protected function _getInlineContentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_InlineMod_Content');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Content
	 */
	protected function _getContentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Content');
	}
}