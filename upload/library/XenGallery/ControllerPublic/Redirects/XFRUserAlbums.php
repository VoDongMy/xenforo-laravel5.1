<?php

class XenGallery_ControllerPublic_Redirects_XFRUserAlbums extends XenGallery_ControllerPublic_Redirects_Abstract
{
	protected function _preDispatchFirst($action)
	{
		$this->_addOnName = '[xfr] User Albums';
		$this->_optionKey = 'xengalleryRedirectXFRUA';
	}

	public function actionIndex()
	{
		return $this->_redirectToMediaIndex();
	}

	public function actionView()
	{
		$oldId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_album');
		if (strstr($newId, 'category_'))
		{
			$newId = str_replace('category_', '', $newId);
			return $this->_redirectToCategoryIndex($newId);
		}

		return $this->_redirectToAlbumIndex($newId);
	}

	public function actionOwn()
	{
		$visitor = XenForo_Visitor::getInstance();
		$this->_request->setParam('user_id', $visitor->user_id);

		return $this->responseReroute(__CLASS__, 'list');
	}

	public function actionList()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		return $this->_redirectToUserAlbumIndex($userId);
	}

	public function actionStandalone()
	{
		$oldId = $this->_input->filterSingle('image_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_media');

		return $this->_redirectToFullMedia($newId);
	}

	public function actionViewImage()
	{
		$oldId = $this->_input->filterSingle('image_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_media');

		return $this->_redirectToFullMedia($newId);
	}

	public function actionShowImage()
	{
		$oldId = $this->_input->filterSingle('image_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_media');

		return $this->_redirectToFullMedia($newId);
	}

	public function actionImagebox()
	{
		$oldId = $this->_input->filterSingle('image_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_media');

		return $this->_redirectToFullMedia($newId);
	}
}