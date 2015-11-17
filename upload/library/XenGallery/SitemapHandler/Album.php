<?php

class XenGallery_SitemapHandler_Album extends XenGallery_SitemapHandler_Abstract
{
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();

		if (!$mediaModel->canViewMedia($null, $viewingUser)
			|| !$albumModel->canViewAlbums($null, $viewingUser)
		)
		{
			return array();
		}

		$ids = $albumModel->getAlbumIdsInRange($previousLast, $limit);

		$albums = $albumModel->getAlbumsByIds($ids);
		ksort($albums);

		return $albumModel->prepareAlbums($albums);
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		$albumModel = $this->_getAlbumModel();

		return $albumModel->canViewAlbum(
			$albumModel->prepareAlbumWithPermissions($entry),
			$null, $viewingUser, true
		);
	}

	public function getData(array $entry)
	{
		$result = array(
			'loc' => XenForo_Link::buildPublicLink('canonical:xengallery/albums', $entry),
			'lastmod' => $entry['last_update_date']
		);

		if (isset($entry['mediaCache']['placeholder']))
		{
			$result['image'] = XenForo_Link::convertUriToAbsoluteUri($entry['mediaCache']['placeholder'], true, $this->getCanonicalPaths());
		}

		return $result;
	}

	public function isInterruptable()
	{
		return true;
	}

	public function getPhraseKey($key)
	{
		return 'xengallery_sitemap_albums';
	}
}