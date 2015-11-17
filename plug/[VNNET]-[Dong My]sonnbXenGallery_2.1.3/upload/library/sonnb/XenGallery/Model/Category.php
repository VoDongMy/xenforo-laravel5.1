<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_Model_Category extends sonnb_XenGallery_Model_Abstract
{	
	public static $allCacheKey = 'sonnb_xengallery_all_category';
	
	public function getCategoryById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['category_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getCategories($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getCategoriesByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['category_id'] = $ids;
		
		return $this->getCategories($conditions, $fetchOptions);
	}
	
	public function getCategoryByName($name, array $fetchOptions = array())
	{
		if (!$name)
		{
			return array();
		}
			
		$conditions['title'] = $name;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getCategories($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}

	public function getTopCategories()
	{
		$return = array();
		$categories = $this->getAllCachedCategories();

		if ($categories)
		{
			foreach ($categories as $_catId => $_cat)
			{
				if (!$_cat['parent_category_id'])
				{
					$return[$_catId] = $_cat;
				}
			}
		}

		return $return;
	}
	
	public function getAllCachedCategories()
	{
		$categories = XenForo_Application::getSimpleCacheData(self::$allCacheKey);
		
		if (!$categories)
		{
			$categories = $this->getCategories(array(), array('order' => 'lft'));
			
			XenForo_Application::setSimpleCacheData(self::$allCacheKey, $categories);
		}

		$categories = $this->prepareCategories($categories);
		
		return $categories;
	}
	
	public function getCategories(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCategoryConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareCategoryFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT category.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_category` AS category
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'category_id'
		);
	}
	
	public function countCategories(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCategoryConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareCategoryFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_category` AS category
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function getLatestAlbumsForCategories(array $categories, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($categories)
		{
			$albumModel = $this->_getAlbumModel();

			foreach ($categories as $categoryId => &$category)
			{
				$conditionCategoryIds = array_keys($this->getDescendantsOfCategory($category));
				$category['child_count'] = count($conditionCategoryIds);
				$conditionCategoryIds[] = $categoryId;

				$conditions = $albumModel->getPermissionBasedAlbumFetchConditions($viewingUser) + array(
					'category_id' => $conditionCategoryIds,
					'content_count' => array('>', 0)
				);
				$fetchOptions = array(
					'order' => 'album_updated_date',
					'orderDirection' => 'desc',
					'limit' => 10,
					'join' => sonnb_XenGallery_Model_Album::FETCH_USER |
						sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
					'likeUserId' => $viewingUser['user_id'],
					'watchUserId' => $viewingUser['user_id'],
					'followingUserId' => $viewingUser['user_id'],
					'coverPhoto' => true
				);

				$category['album_count'] = $albumModel->countAlbums($conditions);
				$category['albums'] = $albumModel->getAlbums($conditions, $fetchOptions);
				$category['albums'] = $albumModel->prepareAlbums($category['albums'], $fetchOptions, $viewingUser);
				$category['albums'] = $albumModel->attachCoversToAlbums($category['albums'], $fetchOptions);

				if ($category['albums'])
				{
					foreach ($category['albums'] as $albumId => $album)
					{
						if (!$album['canView'])
						{
							unset($category['albums'][$albumId]);
						}
					}
				}
			}
		}

		return $categories;
	}
	
	public function getCategoryBreadCrumbs($category, $includeSelf = true)
	{
		$breadcrumbs = array();

		if (!XenForo_Application::getOptions()->sonnbXG_disableCategory)
		{
			if (!is_array($category))
			{
				$categories = $this->getAllCachedCategories();

				if (isset($categories[$category]))
				{
					$category = $categories[$category];
				}
				else
				{
					$category = false;
				}
			}

			if ($category)
			{
				if (!isset($category['categoryBreadcrumb']))
				{
					$category['categoryBreadcrumb'] = unserialize($category['category_breadcrumb']);
				}

				foreach ($category['categoryBreadcrumb'] AS $catId => $breadcrumb)
				{
					$breadcrumbs['category_'.$catId] = array(
						'href' => XenForo_Link::buildPublicLink('full:gallery/categories', $breadcrumb),
						'value' => $breadcrumb['title']
					);
				}

				if ($includeSelf)
				{
					$breadcrumbs['category_'.$category['category_id']] = array(
						'href' => XenForo_Link::buildPublicLink('full:gallery/categories', $category),
						'value' => $category['title']
					);
				}
			}
		}

		return $breadcrumbs;
	}

	public function groupCategoriesByParent(array $categories)
	{
		$grouped = array();
		foreach ($categories AS $category)
		{
			$grouped[$category['parent_category_id']][$category['category_id']] = $category;
		}

		return $grouped;
	}

	public function rebuildCategoryStructure()
	{
		$grouped = $this->groupCategoriesByParent($this->fetchAllKeyed('
			SELECT *
			FROM sonnb_xengallery_category
			ORDER BY display_order
		', 'category_id'));

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$changes = $this->_getStructureChanges($grouped);
		foreach ($changes AS $categoryId => $change)
		{
			$db->update('sonnb_xengallery_category', $change, 'category_id = ' . $db->quote($categoryId));
		}

		XenForo_Db::commit($db);
		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Category::$allCacheKey, false);

		return $changes;
	}

	protected function _getStructureChanges(array $grouped, $parentId = 0, $depth = 0, $startPosition = 1, &$nextPosition = 0, array $breadcrumb = array())
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
			$left = $nextPosition;
			$nextPosition++;

			$thisBreadcrumb = $breadcrumb + array(
				$categoryId => array(
					'category_id' => $categoryId,
					'title' => $category['title'],
					'parent_category_id' => $category['parent_category_id'],
					'depth' => $category['depth'],
					'lft' => $category['lft'],
					'rgt' => $category['rgt'],
				)
			);

			$changes += $this->_getStructureChanges($grouped, $categoryId, $depth + 1, $nextPosition, $nextPosition, $thisBreadcrumb);

			$catChanges = array();
			if ($category['depth'] != $depth)
			{
				$catChanges['depth'] = $depth;
			}
			if ($category['lft'] != $left)
			{
				$catChanges['lft'] = $left;
			}
			if ($category['rgt'] != $nextPosition)
			{
				$catChanges['rgt'] = $nextPosition;
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

	public function getChildrenOfCategoryId($categoryId)
	{
		$categories = $this->getAllCachedCategories();

		$return = array();
		foreach ($categories as $_catId => $cat)
		{
			if ($cat['parent_category_id'] == $categoryId)
			{
				$return[$_catId] = $cat;
			}
		}

		uasort($return, array($this, '_uksort'));

		return $return;
	}

	public function getPossibleParentCategories($category)
	{
		$categories = $this->getAllCachedCategories();

		if (!is_array($category))
		{
			$category = $categories[$category];
		}

		$return = array();
		foreach ($categories as $_catId => $cat)
		{
			if ($cat['lft'] < $category['lft'] || $cat['rgt'] > $category['rgt'])
			{
				$return[$_catId] = $cat;
			}
		}

		uasort($return, array($this, '_uksort'));

		return $return;
	}

	public function getDescendantsOfCategoryIds(array $categoryIds)
	{
		$categories = $this->getAllCachedCategories();

		$ranges = array();
		foreach ($categoryIds AS $categoryId)
		{
			if (isset($categories[$categoryId]))
			{
				$category = $categories[$categoryId];
				$ranges[] = array($category['lft'], $category['rgt']);
			}
		}

		$descendants = array();
		foreach ($categories AS $category)
		{
			foreach ($ranges AS $range)
			{
				if ($category['lft'] > $range[0] && $category['lft'] < $range[1])
				{
					$descendants[$category['category_id']] = $category;
					break;
				}
			}
		}

		return $descendants;
	}

	public function getDescendantsOfCategory($category, $rgt = null)
	{
		if (is_array($category))
		{
			$lft = $category['lft'];
			$rgt = $category['rgt'];
		}
		elseif (!is_null($rgt))
		{
			$lft = intval($category);
		}
		elseif (!$category)
		{
			return $this->getAllCachedCategories();
		}
		else
		{
			$category = $this->getCategoryById($category);

			if (!$category)
			{
				return array();
			}

			$lft = $category['lft'];
			$rgt = $category['rgt'];
		}

		if ($rgt == $lft + 1)
		{
			return array();
		}

		$categories = $this->getAllCachedCategories();

		$return = array();
		foreach ($categories as $_catId => $cat)
		{
			if ($cat['lft'] > $lft && $cat['rgt'] < $rgt)
			{
				$return[$_catId] = $cat;
			}
		}

		uasort($return, array($this, '_uksort'));

		return $return;
	}

	public function deleteCategory($categoryId)
	{
		$children = $this->fetchAllKeyed('
			SELECT *
			FROM sonnb_xengallery_category
			WHERE parent_category_id = ?
			ORDER BY lft
		', 'category_id', $categoryId);

		if (!empty($children))
		{
			foreach ($children as $catId => $cat)
			{
				$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Category', XenForo_DataWriter::ERROR_SILENT);
				$dw->setExistingData($catId);
				$dw->delete();
			}
		}

		$db = $this->_getDb();
		$db->update('sonnb_xengallery_album', array('category_id' => 0), 'category_id = '. $db->quote($categoryId));
	}

	public function prepareCategory(array $category, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($category))
		{
			$this->standardizeViewingUserReference($viewingUser);

			$category['categoryBreadcrumb'] = @unserialize($category['category_breadcrumb']);
			$category['category_privacy'] = @unserialize($category['category_privacy']);

			if (empty($category['category_privacy']))
			{
				$category['category_privacy'] = array(
					'view' => array(-1),
					'post' => array(-1)
				);
			}
		}

		return $category;
	}

	public function prepareCategories(array $categories, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($categories AS $id => &$category)
		{
			$category = $this->prepareCategory($category);

			if (!$this->canViewCategory($category, $null, $viewingUser))
			{
				unset($categories[$id]);
			}
		}

		return $categories;
	}

	public function canViewCategory(array $category, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!is_array($category['category_privacy']))
		{
			$category['category_privacy'] = @unserialize($category['category_privacy']);
		}

		if (empty($category['category_privacy']['view']))
		{
			return true;
		}

		if (in_array(-1, $category['category_privacy']['view']))
		{
			return true;
		}

		if (XenForo_Template_Helper_Core::helperIsMemberOf($viewingUser, $category['category_privacy']['view']))
		{
			return true;
		}

		$errorPhraseKey = 'sonnb_xengallery_you_do_not_have_permission_to_view_this_category';
		return false;
	}

	public function canUploadToCategory(array $category, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$this->canViewCategory($category, $errorPhraseKey, $viewingUser))
		{
			return false;
		}

		if (empty($category['category_privacy']['post']))
		{
			return true;
		}

		if (in_array(-1, $category['category_privacy']['post']))
		{
			return true;
		}

		if (XenForo_Template_Helper_Core::helperIsMemberOf($viewingUser, $category['category_privacy']['post']))
		{
			return true;
		}

		$errorPhraseKey = 'sonnb_xengallery_you_do_not_have_permission_to_upload_this_category';
		return false;
	}
	
	public function prepareCategoryFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
	
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
	
			switch ($fetchOptions['order'])
			{
				case 'category_id':
				case 'title':
				case 'parent_category_id':
				case 'album_count':
				case 'lft':
				case 'rgt':
				case 'depth':
					$orderBy = 'category.' . $fetchOptions['order'];
					$orderBySecondary = ', category.display_order ASC';
					break;
				case 'display_order':
				default:
					$orderBy = 'category.display_order';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'asc')
			{
				$orderBy .= ' ASC';
			}
			else
			{
				$orderBy .= ' DESC';
			}
	
			$orderBy .= $orderBySecondary;
		}
	
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareCategoryConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
	
		if (!empty($conditions['category_id']))
		{
			if (is_array($conditions['category_id']))
			{
				$sqlConditions[] = 'category.category_id IN (' . $db->quote($conditions['category_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'category.category_id = ' . $db->quote($conditions['category_id']);
			}
		}
	
		if (isset($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'category.title IN (' . $db->quote($conditions['title']) . ')';
			}
			else
			{
				$sqlConditions[] = 'category.title = ' . $db->quote($conditions['title']);
			}
		}
	
		if (!empty($conditions['parent_category_id']))
		{
			if (is_array($conditions['parent_category_id']))
			{
				$sqlConditions[] = 'category.parent_category_id IN (' . $db->quote($conditions['parent_category_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'category.parent_category_id = ' . $db->quote($conditions['parent_category_id']);
			}
		}
	
		if (!empty($conditions['album_count']))
		{
			if (is_array($conditions['album_count']))
			{
				$sqlConditions[] = 'category.album_count IN (' . $db->quote($conditions['album_count']) . ')';
			}
			else
			{
				$sqlConditions[] = 'category.album_count = ' . $db->quote($conditions['album_count']);
			}
		}
	
		if (!empty($conditions['display_order']))
		{
			if (is_array($conditions['display_order']))
			{
				$sqlConditions[] = 'category.display_order IN (' . $db->quote($conditions['display_order']) . ')';
			}
			else
			{
				$sqlConditions[] = 'category.display_order = ' . $db->quote($conditions['display_order']);
			}
		}
	
		return $this->getConditionsForClause($sqlConditions);
	}

	protected function _uksort($a, $b)
	{
		return $a['lft'] - $b['lft'];
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}
}
