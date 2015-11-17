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
class sonnb_XenGallery_ControllerAdmin_Category extends sonnb_XenGallery_ControllerAdmin_Abstract
{
	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'categories' => $this->_getCategoryModel()->getAllCachedCategories()
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Category_List',
			'sonnb_xengallery_category_list',
			$viewParams
		);
	}

	/**
	 * @param array $category
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getCategoryAddEditResponse(array $category)
	{
		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		$userGroups = $this->getModelFromCache('XenForo_Model_UserGroup')->getAllUserGroups();

		if (!is_array($category['category_privacy']) && !empty($category['category_privacy']))
		{
			$category['category_privacy'] = @unserialize($category['category_privacy']);
		}

		if (empty($category['category_privacy'])
				|| !isset($category['category_privacy']['post'])
				|| !isset($category['category_privacy']['view']))
		{
			$category['category_privacy'] = array(
				'view' => array(-1),
				'post' => array(-1)
			);
		}

		$selUserGroupIdsPost = $category['category_privacy']['post'];
		$selUserGroupIdsView = $category['category_privacy']['view'];

		if (in_array(-1, $selUserGroupIdsView))
		{
			$allUserGroupsView = true;
			$selUserGroupIdsView = array_keys($userGroups);
		}
		else
		{
			$allUserGroupsView = false;
		}

		if (in_array(-1, $selUserGroupIdsPost))
		{
			$allUserGroupsPost = true;
			$selUserGroupIdsPost = array_keys($userGroups);
		}
		else
		{
			$allUserGroupsPost = false;
		}

		$viewParams = array(
			'category' => $category,
			'categories' => $categories,

			'userGroups' => $userGroups,

			'allUserGroupsView' => $allUserGroupsView,
			'selUserGroupIdsView' => $selUserGroupIdsView,

			'allUserGroupsPost' => $allUserGroupsPost,
			'selUserGroupIdsPost' => $selUserGroupIdsPost,
		);
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Category_Edit',
			'sonnb_xengallery_category_edit',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		return $this->_getCategoryAddEditResponse(array(
			'display_order' => 1,
			'category_id' => 0,
			'title' => '',
			'description' => '',
			'parent_category_id' => 0,
			'depth' => 0,
			'category_privacy' => array(
				'view' => array(-1),
				'post' => array(-1)
			)
		));
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionEdit()
	{
		$category = $this->_getCategoryOrError();

		return $this->_getCategoryAddEditResponse($category);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'parent_category_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT
		));

		$input = $this->_input->filter(array(
			'view_user_group_ids' => XenForo_Input::STRING,
			'postable_user_group_type' => XenForo_Input::STRING,

			'view_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'post_user_group_ids' => array(XenForo_Input::UINT, 'array' => true)
		));

		if ($input['view_user_group_ids'] === 'all')
		{
			$allowedGroupIdsView = array(-1);
		}
		else
		{
			$allowedGroupIdsView = $input['view_user_group_ids'];
		}

		if ($input['postable_user_group_type'] === 'all')
		{
			$allowedGroupIdsPost = array(-1);
		}
		else
		{
			$allowedGroupIdsPost = $input['post_user_group_ids'];
		}

		$dwInput['category_privacy'] = array(
			'view' => $allowedGroupIdsView,
			'post' => $allowedGroupIdsPost
		);

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Category');
		if ($categoryId)
		{
			$dw->setExistingData($categoryId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('gallery/categories') . $this->getLastHash($dw->get('category_id'))
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
			
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Category');
			$dw->setExistingData($categoryId);
			$dw->delete();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				XenForo_Link::buildAdminLink('gallery/categories')
			);
		}
		else
		{
			$viewParams = array(
				'category' => $this->_getCategoryOrError()
			);
			
			return $this->responseView(
					'sonnb_XenGallery_ViewAdmin_Category_Delete', 
					'sonnb_xengallery_category_delete', 
					$viewParams
				);
		}
	}

	/**
	 * @param null $id
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _getCategoryOrError($id = null)
	{
		if ($id === null)
		{
			$id = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		}

		$info = $this->_getCategoryModel()->getCategoryById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_category_not_found'), 404));
		}

		$info = $this->_getCategoryModel()->prepareCategory($info);

		return $info;
	}
}