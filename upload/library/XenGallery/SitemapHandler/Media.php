<?php

class XenGallery_SitemapHandler_Media extends XenGallery_SitemapHandler_Abstract
{
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();

		if (!$mediaModel->canViewMedia($null, $viewingUser))
		{
			return array();
		}

		$ids = $mediaModel->getMediaIdsInRange($previousLast, $limit, 'all');

		if (!$ids)
		{
			return array();
		}

		$conditions = array(
			'media_id' => $ids,
			'deleted' => false,
			'privacyUserId' => $viewingUser['user_id'],
			'viewAlbums' => $albumModel->canViewAlbums($null, $viewingUser),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser)
		);

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_PRIVACY,
			'order' => 'sitemap_order'
		);

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		ksort($media);

		return $mediaModel->prepareMediaItems($media);
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		if (!$mediaModel->canViewMediaItem($entry, $null, $viewingUser))
		{
			return false;
		}

		if (!empty($entry['album_id']))
		{
			$albumModel = $this->_getAlbumModel();
			$entry = $albumModel->prepareAlbumWithPermissions($entry);

			if (!$albumModel->canViewAlbum($entry, $null, $viewingUser))
			{
				return false;
			}
		}

		if (!empty($entry['category_id']))
		{
			if (!$this->_getCategoryModel()->canViewCategory($entry, $null, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	public function getData(array $entry)
	{
		$result = array(
			'loc' => XenForo_Link::buildPublicLink('canonical:xengallery', $entry),
			'lastmod' => $entry['last_edit_date']
		);

		if (isset($entry['thumbnailUrl']))
		{
			if ($entry['media_type'] == 'image_upload')
			{
				$result['image'] = XenForo_Link::buildPublicLink('canonical:xengallery/full', $entry);
			}
			else
			{
				$result['image'] = XenForo_Link::convertUriToAbsoluteUri($entry['thumbnailUrl'], true, $this->getCanonicalPaths());
			}
		}

		return $result;
	}

	public function isInterruptable()
	{
		return true;
	}

	public function getPhraseKey($key)
	{
		return 'xengallery_sitemap_media_items';
	}
}