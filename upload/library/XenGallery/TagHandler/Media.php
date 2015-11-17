<?php

class XenGallery_TagHandler_Media extends XenForo_TagHandler_Abstract
{
	/**
	 * @var XenGallery_Model_Media
	 */
	protected $_mediaModel = null;

	public function getPermissionsFromContext(array $context, array $parentContext = null)
	{
		// Context could be some previously fetched permissions, media, album or category...

		if (isset($context['tagger_permissions']))
		{
			return $context['tagger_permissions'];
		}
		else if (isset($context['media_id']))
		{
			$media = $context;
			$container = $parentContext;
		}
		else
		{
			$media = null;
			$container = $context;
		}

		if (!$container || (empty($container['album_id']) && empty($container['category_id'])))
		{
			throw new Exception("Context must be a media item and an album/category or just an album/category");
		}

		$visitor = XenForo_Visitor::getInstance();

		if ($media)
		{
			if ($media['user_id'] == $visitor['user_id']
				&& XenForo_Permission::hasPermission($visitor['permissions'], 'xengallery', 'manageOthersTagsOwnMedia')
			)
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = XenForo_Permission::hasPermission($visitor['permissions'], 'xengallery', 'manageAnyTag');
			}
		}
		else
		{
			$removeOthers = false;
		}

		return array(
			'edit' => $this->_getMediaModel()->canEditTags($media),
			'removeOthers' => $removeOthers,
			'minTotal' => isset($container['min_tags'])
				? $container['min_tags']
				: XenForo_Application::getOptions()->xengalleryAlbumMinTags
		);
	}

	public function getBasicContent($id)
	{
		return $this->_getMediaModel()->getMediaById($id);
	}

	public function getContentDate(array $content)
	{
		return $content['media_date'];
	}

	public function getContentVisibility(array $content)
	{
		return $content['media_state'] == 'visible';
	}

	public function updateContentTagCache(array $content, array $cache)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
		$dw->setExistingData($content['media_id']);
		$dw->set('tags', $cache);
		$dw->save();
	}

	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$mediaModel = $this->_getMediaModel();

		$conditions = array(
			'media_id' => $ids,
			'privacyUserId' => $viewingUser['user_id'],
			'viewAlbums' => XenForo_Model::create('XenGallery_Model_Album')->canViewAlbums($null, $viewingUser),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser)
		);
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_PRIVACY
		);

		return $mediaModel->getMedia($conditions, $fetchOptions);
	}

	public function canViewResult(array $result, array $viewingUser)
	{
		return $this->_getMediaModel()->canViewMediaItem(
			$result, $null, $viewingUser
		);
	}

	public function prepareResult(array $result, array $viewingUser)
	{
		return $this->_getMediaModel()->prepareMedia($result);
	}

	public function renderResult(XenForo_View $view, array $result)
	{
		return $view->createTemplateObject('xengallery_search_result_media', array(
			'item' => $result,
			'search' => true
		));
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		if (!$this->_mediaModel)
		{
			$this->_mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		}

		return $this->_mediaModel;
	}
}