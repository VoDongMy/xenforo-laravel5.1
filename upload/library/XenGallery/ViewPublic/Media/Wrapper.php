<?php

class XenGallery_ViewPublic_Media_Wrapper extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if ($this->_params['collapsible'] == 'basic')
		{
			$categoriesGrouped = $this->_params['categoriesGrouped'];

			$categorySidebarHtml = '';
			$parentIds = array_keys($this->_params['categoryBreadcrumbs']);
			$parentIds = array_reverse($parentIds);
			$parentIds[] = 0;
			array_unshift($parentIds, !empty($this->_params['category']['category_id']) ? $this->_params['category']['category_id'] : 0);
			$lastParentId = !empty($this->_params['category']['category_id']) ? $this->_params['category']['category_id'] : 0;

			foreach ($parentIds AS $parentId)
			{
				if (empty($categoriesGrouped[$parentId]))
				{
					continue;
				}

				$categorySidebarHtml = $this->_renderer->createTemplateObject('xengallery_category_sidebar_list', array(
					'categories' => $categoriesGrouped[$parentId],
					'category' => $this->_params['category'],
					'childCategoryHtml' => $categorySidebarHtml,
					'showChildId' => $lastParentId
				));
				$lastParentId = $parentId;
			}

			$this->_params['categoryHtml'] = $categorySidebarHtml;
		}
		else
		{
			$this->_params['categoryHtml'] = $this->_collapsibleNavigation($this->_params['categories']);
		}
	}

	protected function _collapsibleNavigation($categories, $parentCategoryId = null)
	{
		$collapsible = $this->_params['collapsible'];
		$this->_params['loadCollapsible'] = strpos($collapsible, 'collapsible') !== false;

		$hasChildren = false;

		$outputHtml = '<ol class="categoryList%s" data-liststyle="' . $collapsible .'">%s</ol>';
		$childrenHtml = '';

		$jsClass = '';
		if ($parentCategoryId === null)
		{
			$jsClass = ' CategoryList';
		}

		foreach ($categories AS $categoryId => $category)
		{
			$expanded = '';
			if (in_array($categoryId, array_keys($this->_params['categoryBreadcrumbs'])))
			{
				$expanded = ' sapling-expanded';
			}

			if ($category['parent_category_id'] == $parentCategoryId)
			{
				$hasChildren = true;

				$selected = '';
				if (!empty($this->_params['category']['category_id']))
				{
					if ($this->_params['category']['category_id'] == $categoryId)
					{
						$selected = ' class="selected"';
						$expanded = ' sapling-expanded';
					}
				}

				$childrenHtml .= '<li class="_categoryDepth' . $category['depth'] . $expanded . '">
					<a href="' . XenForo_Link::buildPublicLink('xengallery/categories', $category) . '"' . $selected .'>'
						. XenForo_Template_Helper_Core::helperBodyText($category['category_title']) .
					'</a>';
				$childrenHtml .= $this->_collapsibleNavigation($categories, $category['category_id']);
				$childrenHtml .= '</li>';
			}
		}

		if (!$hasChildren)
		{
			$outputHtml = '';
		}

		return sprintf($outputHtml, $jsClass, $childrenHtml);
	}
}
