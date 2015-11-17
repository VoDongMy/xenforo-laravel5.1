<?php

class XenGallery_ControllerPublic_InlineMod_Media extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'media';

	/**
	 * @return XenGallery_Model_InlineMod_Media
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenGallery_Model_InlineMod_Media');
	}

	/**
	 * Media deletion handler
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

			return $this->executeInlineModAction('deleteMedia', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$mediaIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Media */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canDeleteMedia($mediaIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$mediaIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'mediaIds' => $mediaIds,
				'mediaCount' => count($mediaIds),
				'canHardDelete' => $handler->canDeleteMedia($mediaIds, 'hard'),
				'canSendAlert' => $handler->canSendActionAlert(),
				'redirect' => $redirect,
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_Media_Delete', 'xengallery_inline_mod_media_delete', $viewParams);
		}
	}

	/**
	 * Media remove watermark handler
	 */
	public function actionRemoveWatermark()
	{
		if ($this->isConfirmedPost())
		{
			$media = $this->_input->filterSingle('media', XenForo_Input::ARRAY_SIMPLE);

			if (!$this->getInlineModTypeModel()->removeWatermarkFromImage($media, array(), $errorPhraseKey))
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
			$mediaIds = $this->getInlineModIds();

			$redirect = $this->getDynamicRedirect();

			if (!$mediaIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			/* @var $handler XenGallery_Model_InlineMod_Media */
			$handler = $this->getInlineModTypeModel();

			$media = $handler->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT | XenGallery_Model_Media::FETCH_USER));
			$media = $this->getModelFromCache('XenGallery_Model_Media')->prepareMediaItems($media);

			foreach ($media AS $mediaId => $item)
			{
				if (!$handler->canRemoveWatermarkData($item))
				{
					unset ($media[$mediaId]);
				}
			}

			if (!$media)
			{
				$this->clearCookie();

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'mediaIds' => array_keys($media),
				'mediaCount' => count($media),
				'media' => $media,
				'redirect' => $redirect,
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_RemoveWatermark', 'xengallery_inline_mod_media_remove_watermark', $viewParams);
		}
	}

	/**
	 * Media add watermark handler
	 */
	public function actionAddWatermark()
	{
		if ($this->isConfirmedPost())
		{
			$media = $this->_input->filterSingle('media', XenForo_Input::ARRAY_SIMPLE);

			if (!$this->getInlineModTypeModel()->addWatermarkToImage($media, array(), $errorPhraseKey))
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
			$mediaIds = $this->getInlineModIds();

			$redirect = $this->getDynamicRedirect();

			if (!$mediaIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			/* @var $handler XenGallery_Model_InlineMod_Media */
			$handler = $this->getInlineModTypeModel();

			$media = $handler->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT | XenGallery_Model_Media::FETCH_USER));
			$media = $this->getModelFromCache('XenGallery_Model_Media')->prepareMediaItems($media);

			foreach ($media AS $mediaId => $item)
			{
				if (!$handler->canAddWatermarkData($item))
				{
					unset ($media[$mediaId]);
				}
			}

			if (!$media)
			{
				$this->clearCookie();

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'mediaIds' => array_keys($media),
				'mediaCount' => count($media),
				'media' => $media,
				'redirect' => $redirect,
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_AddWatermark', 'xengallery_inline_mod_media_add_watermark', $viewParams);
		}
	}

	/**
	 * Media edit handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		if ($this->isConfirmedPost())
		{
			$options = array(
				'input' => $this->_input->filterSingle('media', XenForo_Input::ARRAY_SIMPLE),
				'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
				'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
			);
			return $this->executeInlineModAction('editMedia', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$mediaIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Media */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canEditMedia($mediaIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$mediaIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$media = $this->getInlineModTypeModel()->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT | XenGallery_Model_Media::FETCH_USER));
			$media = $this->getModelFromCache('XenGallery_Model_Media')->prepareMediaItems($media);

			/** @var $categoryModel XenGallery_Model_Category */
			$categoryModel = $this->getModelFromCache('XenGallery_Model_Category');

			$categories = $categoryModel->getCategoryStructure();
			$categories = $categoryModel->prepareCategories($categories);

			$albumConditions = array(
				'album_user_id' => XenForo_Visitor::getUserId()
			);

			$albums = $this->getModelFromCache('XenGallery_Model_Album')->getAlbums($albumConditions);

			$viewParams = array(
				'mediaIds' => $mediaIds,
				'mediaCount' => count($mediaIds),
				'media' => $media,
				'redirect' => $redirect,
				'categories' => $categories,
				'albums' => $albums,
				'canSendAlert' => $handler->canSendActionAlert()
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_MediaEdit', 'xengallery_inline_mod_media_edit', $viewParams);
		}
	}

	/**
	 * Media move handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		/** @var $mediaModel XenGallery_Model_Media */
		$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

		/** @var $albumModel XenGallery_Model_Album */
		$albumModel = $this->getModelFromCache('XenGallery_Model_Album');

		$moveToAnyAlbum = $mediaModel->canMoveMediaToAnyAlbum();
		$canCreateAlbums = $albumModel->canCreateAlbum();

		if ($this->isConfirmedPost())
		{
			/** @var $mediaHelper XenGallery_ControllerHelper_Media */
			$mediaHelper = $this->getHelper('XenGallery_ControllerHelper_Media');

			$media = $this->_input->filterSingle('mediaids', XenForo_Input::ARRAY_SIMPLE);

			$album = array();
			$category = array();

			$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
			if ($albumId)
			{
				$album = $mediaHelper->assertAlbumValidAndViewable($albumId);
				if ($album['album_user_id'] != XenForo_Visitor::getUserId() && !$moveToAnyAlbum)
				{
					throw $this->getNoPermissionResponseException();
				}
			}

			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
			if ($categoryId)
			{
				$category = $mediaHelper->assertCategoryValidAndViewable($categoryId);
			}

			if (!$category && !$album)
			{
				if ($canCreateAlbums)
				{
					$visitor = XenForo_Visitor::getInstance();

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
				}
			}

			$media = $this->getInlineModTypeModel()->getMediaData($media);

			$options = array(
				'album' => $album,
				'category' => $category
			);

			if (!$this->getInlineModTypeModel()->moveMedia($media, $options, $errorPhraseKey))
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
			/** @var $categoryModel XenGallery_Model_Category */
			$categoryModel = $this->getModelFromCache('XenGallery_Model_Category');

			/** @var $albumModel XenGallery_Model_Album */
			$albumModel = $this->getModelFromCache('XenGallery_Model_Album');

			$mediaIds = $this->getInlineModIds();

			/* @var $handler XenGallery_Model_InlineMod_Media */
			$handler = $this->getInlineModTypeModel();
			if (!$handler->canMoveMedia($mediaIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$mediaIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$media = $this->getInlineModTypeModel()->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT | XenGallery_Model_Media::FETCH_USER));
			$media = $this->getModelFromCache('XenGallery_Model_Media')->prepareMediaItems($media);

			$categories = $categoryModel->getCategoryStructure();
			$categories = $categoryModel->prepareCategories($categories);

			$albumConditions = array(
				'album_user_id' => XenForo_Visitor::getUserId()
			);
			$albums = $albumModel->getAlbums($albumConditions);

			$viewParams = array(
				'mediaIds' => $mediaIds,
				'mediaCount' => count($mediaIds),
				'media' => $media,
				'redirect' => $redirect,
				'categories' => $categories,
				'albums' => $albums,
				'moveToAnyAlbum' => $moveToAnyAlbum,
				'canCreateAlbums' => $canCreateAlbums
			);

			return $this->responseView('XenGallery_ViewPublic_InlineMod_MediaMove', 'xengallery_inline_mod_media_move', $viewParams);
		}
	}

	/**
	 * Undeletes the specified media.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteMedia');
	}

	/**
	 * Approves the specified media.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveMedia');
	}

	/**
	 * Unapproves the specified media.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveMedia');
	}
}