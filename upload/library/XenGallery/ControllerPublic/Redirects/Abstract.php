<?php

abstract class XenGallery_ControllerPublic_Redirects_Abstract extends XenForo_ControllerPublic_Abstract
{
	protected $_importLogTable = '';
	protected $_addOnName = '';
	protected $_optionKey = '';

	protected function _preDispatch($action)
	{
		$option = XenForo_Application::getOptions()->get($this->_optionKey);
		$this->_importLogTable = $option['log'];

		if (!$this->_importLogTable || !$this->_getMediaGalleryImportersModel($this->_importLogTable))
		{
			$response = $this->responseMessage(new XenForo_Phrase('xengallery_import_log_not_found_for_add_on_x', array('addon' => $this->_addOnName)));
			throw $this->responseException($response);
		}
	}

	protected function _redirectToMediaIndex()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery')
		);
	}

	protected function _redirectToAlbumIndex($albumId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery/albums', array('album_id' => $albumId))
		);
	}

	protected function _redirectToCategoryIndex($categoryId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery/categories', array('category_id' => $categoryId))
		);
	}

	protected function _redirectToUserAlbumIndex($userId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery/users/albums', array('album_user_id' => $userId))
		);
	}

	protected function _redirectToUserMediaIndex($userId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery/users', array('user_id' => $userId))
		);
	}

	protected function _redirectToFullMedia($mediaId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery/full', array('media_id' => $mediaId))
		);
	}

	protected function _redirectToMediaItem($mediaId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('xengallery', array('media_id' => $mediaId))
		);
	}

	protected function _redirectToTagPage($tagId)
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('tags', array('tag_url' => $tagId))
		);
	}

	protected function _getMappedId($id, $type, $default = null)
	{
		define('IMPORT_LOG_TABLE', $this->_importLogTable);

		$ids = $this->_getImportModel()->getImportContentMap($type, $id);
		return ($ids ? reset($ids) : $default);
	}

	protected function _getKeywordId($keyword)
	{
		$db = XenForo_Application::getDb();

		$keywordId = $db->fetchOne('
			SELECT tag_url
			FROM xf_tag
			WHERE tag = ?
		', $keyword);

		if (!$keywordId)
		{
			return false;
		}

		return $keywordId;
	}

	/**
	 * @return XenForo_Model_Import
	 */
	protected function _getImportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Import');
	}

	/**
	 * @return XenGallery_Model_Importers
	 */
	protected function _getMediaGalleryImportersModel()
	{
		return XenForo_Model::create('XenGallery_Model_Importers');
	}
}