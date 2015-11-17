<?php

class XenGallery_ViewPublic_Media_Index extends XenForo_ViewPublic_Base
{
	public function renderHtml()
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

		$this->_params['categorySidebarHtml'] = $categorySidebarHtml;
	}
}