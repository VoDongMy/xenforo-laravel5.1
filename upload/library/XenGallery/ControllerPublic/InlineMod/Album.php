<?php

class XenGallery_ControllerPublic_InlineMod_Album extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'album';

	/**
	 * @return XenGallery_Model_InlineMod_Album
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenGallery_Model_InlineMod_Album');
	}

	/**
	 * Album deletion handler
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

			return $this->executeInlineModAction('deleteAlbum', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$albumIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Album */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canDeleteAlbum($albumIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$albumIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'albumIds' => $albumIds,
				'albumCount' => count($albumIds),
				'canHardDelete' => $handler->canDeleteAlbum($albumIds, 'hard'),
				'canSendAlert' => $handler->canSendActionAlert(),
				'redirect' => $redirect,
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_Album_Delete', 'xengallery_inline_mod_album_delete', $viewParams);
		}
	}

	/**
	 * Album edit handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		if ($this->isConfirmedPost())
		{
			$options = array(
				'input' => $this->_input->filterSingle('album', XenForo_Input::ARRAY_SIMPLE),
				'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
				'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
			);
			return $this->executeInlineModAction('editAlbum', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$albumIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Album */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canEditAlbum($albumIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$albumIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$albums = $handler->getAlbumData($albumIds, array('join' => XenGallery_Model_Album::FETCH_USER));
			$albums = $this->getModelFromCache('XenGallery_Model_Album')->prepareAlbums($albums);

			$viewParams = array(
				'albumIds' => $albumIds,
				'albumCount' => count($albumIds),
				'albums' => $albums,
				'redirect' => $redirect,
				'canSendAlert' => $handler->canSendActionAlert()
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_AlbumEdit', 'xengallery_inline_mod_album_edit', $viewParams);
		}
	}

	public function actionPrivacy()
	{
		if ($this->isConfirmedPost())
		{
			$albums = $this->_input->filterSingle('album', XenForo_Input::ARRAY_SIMPLE);
			if (!$this->getInlineModTypeModel()->changeAlbumPrivacy($albums, array(), $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}
		else // show confirmation dialog
		{
			$albumIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Album */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canChangeAlbumViewPerm($albumIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$albumIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$albums = $this->getInlineModTypeModel()->getAlbumData($albumIds, array('join' => XenGallery_Model_Album::FETCH_USER));
			$albums = $this->getModelFromCache('XenGallery_Model_Album')->prepareAlbums($albums);

			$viewParams = array(
				'albumIds' => $albumIds,
				'albumCount' => count($albumIds),
				'albums' => $albums,
				'redirect' => $redirect
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_AlbumPrivacy', 'xengallery_inline_mod_album_privacy', $viewParams);
		}
	}

	/**
	 * Undeletes the specified album.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteAlbum');
	}
}