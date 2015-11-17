<?php

class XenGallery_Model_Category extends XenForo_Model
{
	public function getCategoryById($categoryId)
	{
		return $this->_getDb()->fetchRow('
			SELECT category.*,
				IF(category_watch.user_id IS NULL, 0, 1) AS category_is_watched
			FROM xengallery_category AS category
			LEFT JOIN xengallery_category_watch AS category_watch
				ON (category_watch.category_id = category.category_id
				AND category_watch.user_id = ?)
			WHERE category.category_id = ?
		', array(XenForo_Visitor::getUserId(), $categoryId));
	}

	public function getCategoriesByIds(array $categoryIds)
	{
		if (!$categoryIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_category
			WHERE category_id IN (' . $this->_getDb()->quote($categoryIds) . ')
		', 'category_id');
	}

	public function getCategoryIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT category_id
			FROM xengallery_category
			WHERE category_id > ?
			ORDER BY category_id
		', $limit), $start);
	}

	public function setMediaPrivacyByCategoryId($categoryId, $privacy = 'category')
	{
		$db = $this->_getDb();

		$mediaIds = $this->_getDb()->fetchCol('
			SELECT media_id
			FROM xengallery_media
			WHERE category_id = ?
		', $categoryId);

		return $db->update('xengallery_media', array('media_privacy' => $privacy), 'media_id IN (' . $db->quote($mediaIds) . ')');
	}

	public function getAllCategories()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_category
			ORDER BY display_order
		', 'category_id');
	}

	public function getViewableCategories(array $viewingUser = array())
	{
		$categories = $this->getAllCategories();

		$viewingUser = $this->standardizeViewingUserReference($viewingUser);
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			return $categories;
		}

		foreach ($categories AS $categoryId => $category)
		{
			if (!$this->canViewCategory($category, $null, $viewingUser))
			{
				unset ($categories[$categoryId]);
			}
		}

		return $categories;
	}

	public function canViewCategories(&$errorPhraseKey = '', array $viewingUser = array())
	{
		$viewingUser = $this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			return true;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewCategories'))
		{
			$errorPhraseKey = 'xengallery_no_view_this_category_permission';
			return false;
		}

		return true;
	}

	public function removeUnviewableCategories(array $categories, array $viewingUser = array())
	{
		foreach ($categories AS $key => $category)
		{
			if (!$this->canViewCategory($category, $null, $viewingUser))
			{
				unset ($categories[$key]);
			}
		}

		return $categories;
	}

	public function canViewCategory(array $category, &$errorPhraseKey = '', array $viewingUser = array())
	{
		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$viewingUser = $this->standardizeViewingUserReference($viewingUser);

		if (!$this->canViewCategories($errorPhraseKey, $viewingUser))
		{
			return false;
		}

		$category['view_user_groups'] = @unserialize($category['view_user_groups']);
		if (!$category['view_user_groups'])
		{
			$category['view_user_groups'] = array();
		}

		if (in_array('-1', $category['view_user_groups']))
		{
			return true;
		}

		if (!$userModel->isMemberOfUserGroup($viewingUser, $category['view_user_groups']))
		{
			$errorPhraseKey = 'xengallery_no_view_this_category_permission';
			return false;
		}

		return true;
	}

	public function canWatchCategory(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return ($viewingUser['user_id'] ? true : false);
	}

	public function getCategoryCount()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_category
		');
	}
	
	public function getChildCategoriesForParent($parentCategoryId)
	{
		return $this->_getDb()->fetchCol('
			SELECT category_id
			FROM xengallery_category
			WHERE parent_category_id = ?
		', $parentCategoryId);
	}
	
	/**
	 * Group category entries by their parent.
	 * Code based on Admin Navigation Categories by XenForo Ltd.
	 *
	 * @param array $categories List of category entries to group
	 *
	 * @return array [parent category id][category id] => info
	 */
	public function groupCategoriesByParent(array $categories)
	{
		$output = array(
			'0' => array()
		);		
		foreach ($categories AS $category)
		{
			$output[$category['parent_category_id']][$category['category_id']] = $category;
		}

		return $output;
	}

	/**
	 * Get the categories list in the correct display order. This can be processed
	 * linearly with depth markers to visually represent the tree.
	 * Code based on Admin Navigation Categories by XenForo Ltd.
	 *
	 * @param array|null $categories Category entries; if null, grabbed automatically
	 * @param integer $root Root node to traverse from
	 * @param integer $depth Depth to start at
	 *
	 * @return array [categories id] => info, with depth key set
	 */
	public function getCategoryStructure(array $categories = null, $root = 0, $depth = 0)
	{
		if (!is_array($categories))
		{
			$categories = $this->groupCategoriesByParent($this->getAllCategories());
		}

		if (!isset($categories[$root]))
		{
			return array();
		}

		$output = array();
		foreach ($categories[$root] AS $category)
		{
			$category['depth'] = $depth;
			$output[$category['category_id']] = $category;

			$output += $this->getCategoryStructure($categories, $category['category_id'], $depth + 1);
		}

		return $output;
	}

	public function applyRecursiveCountsToGrouped(array $grouped, $parentCategoryId = 0)
	{
		if (!isset($grouped[$parentCategoryId]))
		{
			return array();
		}

		$this->_applyRecursiveCountsToGrouped($grouped, $parentCategoryId);
		return $grouped;
	}

	protected function _applyRecursiveCountsToGrouped(array &$grouped, $parentCategoryId)
	{
		$output = array(
			'category_media_count' => 0
		);

		foreach ($grouped[$parentCategoryId] AS $categoryId => &$category)
		{
			if (isset($grouped[$categoryId]))
			{
				$childCounts = $this->_applyRecursiveCountsToGrouped($grouped, $categoryId);

				$category['category_media_count'] += $childCounts['category_media_count'];
			}

			$output['category_media_count'] += $category['category_media_count'];
		}

		return $output;
	}

	public function canAddMediaToCategory(array $category, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$userGroups = @unserialize($category['upload_user_groups']);
		if (!is_array($userGroups))
		{
			return false;
		}

		if (in_array('-1', $userGroups))
		{
			return true;
		}

		if ($this->getModelFromCache('XenForo_Model_User')->isMemberOfUserGroup($viewingUser, $userGroups))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_category';
		return false;
	}

	public function prepareCategory(array $category, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$category['canAddMedia'] = $this->canAddMediaToCategory($category, $viewingUser);

		$allowedTypes = @unserialize($category['allowed_types']);
		if (!is_array($allowedTypes))
		{
			return false;
		}

		$category['canUploadImage'] = (in_array('image_upload', $allowedTypes) || in_array('all', $allowedTypes));
		$category['canUploadVideo'] = (in_array('video_upload', $allowedTypes) || in_array('all', $allowedTypes));
		$category['canEmbedVideo'] = (in_array('video_embed', $allowedTypes) || in_array('all', $allowedTypes));

		$mediaSites = XenForo_Application::getOptions()->xengalleryMediaSites;
		if (!count($mediaSites))
		{
			$category['canEmbedVideo'] = false;
		}

		$category['categoryFieldCache'] = @unserialize($category['field_cache']);
		if (!is_array($category['categoryFieldCache']))
		{
			$category['categoryFieldCache'] = array();
		}

		return $category;
	}
	
	public function prepareCategories(array $categories, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($categories AS &$category)
		{
			$category = $this->prepareCategory($category, $viewingUser);
		}
		
		return $categories;
	}
	
	public function getCategoryBreadcrumb(array $container, $includeSelf = true)
	{
		if (!empty($container['album_id']))
		{
			$albumUser = array(
				'user_id' => $container['album_user_id'],
				'username' => $container['album_username']
			);

			return array(
				'albums' => array(
					'href' => XenForo_Link::buildPublicLink('full:xengallery/albums'),
					'value' => new XenForo_Phrase('xengallery_albums')
				),
				'useralbums' => array(
					'href' => XenForo_Link::buildPublicLink('full:xengallery/users/albums', $albumUser),
					'value' => $albumUser['username']
				),
				'album' => array(
					'href' => XenForo_Link::buildPublicLink('full:xengallery/albums', $container),
					'value' => $container['album_title']
				)
			);
		}
		
		$breadcrumbs = array();

		if (!isset($container['categoryBreadcrumb']))
		{
			$container['categoryBreadcrumb'] = @unserialize($container['category_breadcrumb']);
		}

		if (!$container['categoryBreadcrumb'])
		{
			$container['categoryBreadcrumb'] = array();
		}

		foreach ($container['categoryBreadcrumb'] AS $catId => $breadcrumb)
		{
			$breadcrumbs[$catId] = array(
				'href' => XenForo_Link::buildPublicLink('full:xengallery/categories', $breadcrumb),
				'value' => $breadcrumb['category_title']
			);
		}

		if ($includeSelf)
		{
			$breadcrumbs[$container['category_id']] = array(
				'href' => XenForo_Link::buildPublicLink('full:xengallery/categories', $container),
				'value' => $container['category_title']
			);
		}

		return $breadcrumbs;
	}	
	
	
	public function rebuildCategoryStructure()
	{
		$grouped = $this->groupCategoriesByParent($this->getAllCategories());

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$changes = $this->_getStructureChanges($grouped);
		foreach ($changes AS $categoryId => $changes)
		{
			$db->update('xengallery_category', $changes, 'category_id = ' . $db->quote($categoryId));
		}

		XenForo_Db::commit($db);

		return $changes;
	}

	protected function _getStructureChanges(array $grouped, $parentId = 0, $depth = 0,
		$startPosition = 1, &$nextPosition = 0, array $breadcrumb = array()
	)
	{
		$nextPosition = $startPosition;

		if (!isset($grouped[$parentId]))
		{
			return array();
		}

		$changes = array();
		$serializedBreadcrumb = serialize($breadcrumb);

		foreach ($grouped[$parentId] AS $categoryId => $category)
		{
			$nextPosition++;

			$thisBreadcrumb = $breadcrumb + array(
				$categoryId => array(
					'category_id' => $categoryId,
					'category_title' => $category['category_title'],
					'parent_category_id' => $category['parent_category_id'],
					'depth' => $category['depth']
				)
			);

			$changes += $this->_getStructureChanges(
				$grouped, $categoryId, $depth + 1, $nextPosition, $nextPosition, $thisBreadcrumb
			);

			$catChanges = array();
			if ($category['depth'] != $depth)
			{
				$catChanges['depth'] = $depth;
			}
			if ($category['category_breadcrumb'] != $serializedBreadcrumb)
			{
				$catChanges['category_breadcrumb'] = $serializedBreadcrumb;
			}

			if ($catChanges)
			{
				$changes[$categoryId] = $catChanges;
			}

			$nextPosition++;
		}

		return $changes;
	}	
}