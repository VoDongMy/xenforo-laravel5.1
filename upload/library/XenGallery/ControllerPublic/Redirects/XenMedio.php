<?php

class XenGallery_ControllerPublic_Redirects_XenMedio extends XenGallery_ControllerPublic_Redirects_Abstract
{
	protected function _preDispatchFirst($action)
	{
		$this->_addOnName = '[8wayRun.Com] XenMedio';
		$this->_optionKey = 'xengalleryRedirectXenMedio';
	}

	public function actionIndex()
	{
		$mediaId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		if ($mediaId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		return $this->_redirectToMediaIndex();
	}

	public function actionPlaylist()
	{
		return $this->_redirectToMediaIndex();
	}

	public function actionView()
	{
		$oldId = $this->_input->filterSingle('media_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_media');

		return $this->_redirectToMediaItem($newId);
	}

	public function actionUser()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		return $this->_redirectToUserMediaIndex($userId);
	}

	public function actionKeyword()
	{
		$keyword = $this->_input->filterSingle('keyword_text', XenForo_Input::STRING);
		$keywordId = $this->_getKeywordId($keyword);

		if (!$keywordId)
		{
			return $this->_redirectToMediaIndex();
		}

		return $this->_redirectToTagPage($keywordId);
	}

	public function actionCategory()
	{
		$oldId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$newId = $this->_getMappedId($oldId, 'xengallery_category');

		return $this->_redirectToCategoryIndex($newId);
	}
}