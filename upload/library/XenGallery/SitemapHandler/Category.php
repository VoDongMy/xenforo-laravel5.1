<?php

class XenGallery_SitemapHandler_Category extends XenGallery_SitemapHandler_Abstract
{
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();
		$categoryModel = $this->_getCategoryModel();

		if (!$mediaModel->canViewMedia($null, $viewingUser)
			|| !$categoryModel->canViewCategories($null, $viewingUser)
		)
		{
			return array();
		}

		$ids = $categoryModel->getCategoryIdsInRange($previousLast, $limit);

		$categories = $categoryModel->getCategoriesByIds($ids);
		ksort($categories);

		return $categoryModel->prepareCategories($categories);
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		return $this->_getCategoryModel()->canViewCategory($entry, $null, $viewingUser);
	}

	public function getData(array $entry)
	{
		return array(
			'loc' => XenForo_Link::buildPublicLink('canonical:xengallery/categories', $entry)
		);
	}

	public function isInterruptable()
	{
		return true;
	}

	public function getPhraseKey($key)
	{
		return 'xengallery_sitemap_categories';
	}
}