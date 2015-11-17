<?php

/**
 * Controller for XenForo Media Gallery in the Admin CP
 */
class XenGallery_ControllerAdmin_Media extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('manageXenGallery');
	}

	public function actionIndex()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xengallery/categories')
		);
	}

	public function actionRebuilds()
	{
		return $this->responseView(
			'XenGallery_ViewAdmin_Rebuild_List',
			'xengallery_tools_rebuild',
			array('success' => $this->_input->filterSingle('success', XenForo_Input::BOOLEAN)),
			array('title' => new XenForo_Phrase('xengallery_gallery_rebuilds'))
		);
	}

	public function actionTriggerDeferred()
	{
		$this->_assertPostOnly();
		$this->assertAdminPermission('rebuildCache');

		$input = $this->_input->filter(array(
			'cache' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,
		));

		if ($input['cache'])
		{
			$obj = XenForo_Deferred_Abstract::create($input['cache']);
			if ($obj)
			{
				XenForo_Application::defer($input['cache'], $input['options'], 'Rebuild' . $input['cache'], true);
			}
		}

		$this->_request->setParam('redirect',
			XenForo_Link::buildAdminLink('xengallery/rebuilds', false, array('success' => 1))
		);

		return $this->responseReroute('XenForo_ControllerAdmin_Tools', 'runDeferred');
	}

	/**
	 * Lists all categories.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCategory()
	{
		$viewParams = array(
			'categories' => $this->_getCategoryModel()->getCategoryStructure()
		);
		
		return $this->responseView('XenGallery_ViewAdmin_Category_List', 'xengallery_category_list', $viewParams);
	}

	/**
	 * Gets the category add/edit form response.
	 *
	 * @param array $category
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getCategoryAddEditResponse(array $category)
	{
		$fieldModel = $this->_getFieldModel();
		$categories = $this->_getCategoryModel()->getCategoryStructure();

		$userGroups = $this->_getUserGroupModel()->getAllUserGroups();
		if (!empty($category['category_id']))
		{
			$addSelUserGroupIds = @unserialize($category['upload_user_groups']);
			if (!$addSelUserGroupIds)
			{
				$addSelUserGroupIds = array();
			}

			$addAllUserGroups = false;
			if (in_array(-1, $addSelUserGroupIds))
			{
				$addAllUserGroups = true;
				$addSelUserGroupIds = array_keys($userGroups);
			}

			$viewSelUserGroupIds = @unserialize($category['view_user_groups']);
			if (!$viewSelUserGroupIds)
			{
				$viewSelUserGroupIds = array();
			}

			$viewAllUserGroups = false;
			if (in_array(-1, $viewSelUserGroupIds))
			{
				$viewAllUserGroups = true;
				$viewSelUserGroupIds = array_keys($userGroups);
			}
			
			$allowedTypes = @unserialize($category['allowed_types']);
			if (!$allowedTypes)
			{
				$allowedTypes = array();
			}

			$allMediaTypes = false;
			if (in_array('all', $allowedTypes))
			{
				$allMediaTypes = true;
				$allowedTypes = array('image_upload', 'video_upload', 'video_embed');
			}

			$selectedFields = $fieldModel->getFieldIdsInCategory($category['category_id']);
		}
		else
		{
			$addAllUserGroups = true;
			$addSelUserGroupIds = array_keys($userGroups);

			$viewAllUserGroups = true;
			$viewSelUserGroupIds = array_keys($userGroups);
			
			$allMediaTypes = true;
			$allowedTypes = array('image_upload', 'video_upload', 'video_embed');

			$selectedFields = array();
		}

		$fields = $fieldModel->prepareGalleryFields($fieldModel->getGalleryFields());

		$viewParams = array(
			'category' => $category,
			'categories' => $categories,
			
			'userGroups' => $userGroups,

			'addAllUserGroups' => $addAllUserGroups,
			'addSelUserGroupIds' => $addSelUserGroupIds,

			'viewAllUserGroups' => $viewAllUserGroups,
			'viewSelUserGroupIds' => $viewSelUserGroupIds,
			
			'allMediaTypes' => $allMediaTypes,
			'allowedTypes' => $allowedTypes,

			'fieldsGrouped' => $fieldModel->groupGalleryFields($fields),
			'fieldGroups' => $fieldModel->getGalleryFieldGroups(),
			'selectedFields' => $selectedFields
		);
		return $this->responseView('XenGallery_ViewAdmin_Category_Edit', 'xengallery_category_edit', $viewParams);
	}

	/**
	 * Displays a form to create a new category.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCategoryAdd()
	{
		$parentCategoryId = $this->_input->filterSingle('parent', XenForo_Input::UINT);
		
		return $this->_getCategoryAddEditResponse(array(
			'display_order' => 1,
			'parent_category_id' => $parentCategoryId,
			'min_tags' => 0
		));
	}

	/**
	 * Displays a form to edit an existing category.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCategoryEdit()
	{
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$category = $this->_getCategoryOrError($categoryId);

		return $this->_getCategoryAddEditResponse($category);
	}

	/**
	 * Updates an existing media site or inserts a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCategorySave()
	{
		$this->_assertPostOnly();

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		
		$dwInput = $this->_input->filter(array(
			'category_title' => XenForo_Input::STRING,
			'category_description' => XenForo_Input::STRING,
			'parent_category_id' => XenForo_Input::UINT,			
			'display_order' => XenForo_Input::UINT,
			'min_tags' => XenForo_Input::UINT,
			'user_group_type_add' => XenForo_Input::STRING,
			'add_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'user_group_type_view' => XenForo_Input::STRING,
			'view_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'usable_media_type' => XenForo_Input::STRING,
			'media_type_checkboxes' => array(XenForo_Input::STRING, 'array' => true)
		));

		$input = $this->_input->filter(array(
			'available_fields' => array(XenForo_Input::STRING, 'array' => true)
		));
		
		if ($dwInput['user_group_type_add'] == 'all')
		{
			$addAllowedGroupIds = array(-1); // -1 is a sentinel for all groups
		}
		else
		{
			$addAllowedGroupIds = $dwInput['add_user_group_ids'];
		}

		$allViewUserGroups = false;
		if ($dwInput['user_group_type_view'] == 'all')
		{
			$allViewUserGroups = true;
			$viewAllowedGroupIds = array(-1); // -1 is a sentinel for all groups
		}
		else
		{
			$viewAllowedGroupIds = $dwInput['view_user_group_ids'];
		}
		
		if ($dwInput['usable_media_type'] == 'all')
		{
			$allowedMediaTypes = array('all');
		}
		else
		{
			$allowedMediaTypes = $dwInput['media_type_checkboxes'];
		}
		
		$dwInput['upload_user_groups'] = $addAllowedGroupIds;
		$dwInput['view_user_groups'] = $viewAllowedGroupIds;
		$dwInput['allowed_types'] = $allowedMediaTypes;
		
		unset($dwInput['add_user_group_ids']);
		unset($dwInput['user_group_type_add']);
		unset($dwInput['view_user_group_ids']);
		unset($dwInput['user_group_type_view']);
		unset($dwInput['media_type_checkboxes']);
		unset($dwInput['usable_media_type']);

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Category');
		if ($categoryId)
		{
			$dw->setExistingData($categoryId);
		}
		$dw->setExtraData(XenGallery_DataWriter_Category::DATA_FIELD_IDS, $input['available_fields']);
		$dw->bulkSet($dwInput);
		$dw->save();

		$categoryId = $dw->get('category_id');

		if ($allViewUserGroups)
		{
			$userGroups = $this->_getUserGroupModel()->getAllUserGroups();
			$viewAllowedGroupIds = array_keys($userGroups);
		}

		$db = XenForo_Application::getDb();
		$db->delete('xengallery_category_map', 'category_id = ' . $db->quote($categoryId));

		foreach ($viewAllowedGroupIds AS $userGroupId)
		{
			$db->query("
				INSERT IGNORE INTO xengallery_category_map
					(category_id, view_user_group_id)
				VALUES
					($categoryId, $userGroupId)
			");
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xengallery/categories') . $this->getLastHash($categoryId)
		);
	}

	/**
	 * Deletes the specified category
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCategoryDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenGallery_DataWriter_Category', 'category_id',
				XenForo_Link::buildAdminLink('xengallery/categories')
			);
		}
		else // show confirmation dialog
		{
			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
			
			$viewParams = array(
				'category' => $this->_getCategoryOrError($categoryId)
			);
			return $this->responseView('XenGallery_ViewAdmin_Category_Delete', 'xengallery_category_delete', $viewParams);
		}
	}
	
	public function actionCategoryDisplayOrder()
	{
		if ($this->isConfirmedPost())
		{
			$displayOrders = $this->_input->filterSingle('category', XenForo_Input::ARRAY_SIMPLE);
			
			foreach ($displayOrders AS $key => $displayOrder)
			{
				$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Category');
				
				$dw->setExistingData($key);
				$dw->set('display_order', $displayOrder);
				
				$dw->save();
			}
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('xengallery/categories')
			);
		}
		else
		{
			$viewParams = array(
				'categories' => $this->_getCategoryModel()->getCategoryStructure()
			);
			
			return $this->responseView('XenGallery_ViewAdmin_Category_DisplayOrder', 'xengallery_category_display_order', $viewParams);
		}
	}

	/**
	 * Gets the specified record or errors.
	 *
	 * @param string $categoryId
	 *
	 * @return array
	 */
	protected function _getCategoryOrError($categoryId)
	{
		$category = $this->_getCategoryModel()->getCategoryById($categoryId);
		if (!$category)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_category_not_found'), 404));
		}

		return $category;
	}

	public function actionPermission()
	{
		$this->assertAdminPermission('userGroup');

		if ($this->_input->filterSingle('user_group_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'permission-edit');
		}

		$viewParams = array(
			'userGroups' => $this->getModelFromCache('XenForo_Model_UserGroup')->getAllUserGroups()
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserGroupList', 'xengallery_permission_user_group_list', $viewParams);
	}

	public function actionPermissionEdit()
	{
		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissionModel = $this->_getPermissionModel();

		$permissions = $permissionModel->getUserCollectionPermissionsForInterface($userGroup['user_group_id']);

		$userPermissions = array(
			'xengalleryMediaPermissions' => $permissions['xengalleryMediaPermissions'],
			'xengalleryAlbumPermissions' => $permissions['xengalleryAlbumPermissions'],
			'xengalleryCategoryPermissions' => $permissions['xengalleryCategoryPermissions'],
			'xengalleryWatermarkPermissions' => $permissions['xengalleryWatermarkPermissions'],
			'xengalleryCommentPermissions' => $permissions['xengalleryCommentPermissions'],
			'xengalleryUserTaggingPermissions' => $permissions['xengalleryUserTaggingPermissions'],
			'xengalleryGeneralMediaQuotas' => $permissions['xengalleryGeneralMediaQuotas'],
			'xengalleryImageMediaQuotas' => $permissions['xengalleryImageMediaQuotas']
		);

		$moderatorPermissions = array(
			'xengalleryMediaModeratorPermissions' => $permissions['xengalleryMediaModeratorPermissions'],
			'xengalleryAlbumModeratorPermissions' => $permissions['xengalleryAlbumModeratorPermissions'],
			'xengalleryWatermarkModeratorPermissions' => $permissions['xengalleryWatermarkModeratorPermissions'],
			'xengalleryCommentModeratorPermissions' => $permissions['xengalleryCommentModeratorPermissions'],
		);

		$viewParams = array(
			'userGroup' => $userGroup,
			'userPermissions' => $userPermissions,
			'moderatorPermissions' => $moderatorPermissions,
			'permissionChoices' => $permissionModel->getPermissionChoices('userGroup', false)
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserGroupEdit', 'xengallery_permission_user_group_edit', $viewParams);
	}

	/**
	 * Updates permissions for a particular user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionSave()
	{
		$this->_assertPostOnly();

		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($permissions, $userGroupId, 0);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xengallery/permissions') . $this->getLastHash($userGroupId)
		);
	}

	/**
	 * Gets a valid user group record or raises a controller response exception.
	 *
	 * @param integer $userGroupId
	 *
	 * @return array
	 */
	protected function _getValidUserGroupOrError($userGroupId)
	{
		$userGroup = $this->_getUserGroupModel()->getUserGroupById($userGroupId);
		if (!$userGroup)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_group_not_found'), 404));
		}

		return $userGroup;
	}

	public function actionOption()
	{
		$optionModel = $this->_getOptionModel();

		$fetchOptions = array('join' => XenForo_Model_Option::FETCH_ADDON);

		$group = $this->_getOptionGroupOrError('XenGallery', $fetchOptions);
		$groups = $optionModel->getOptionGroupList($fetchOptions);
		$options = $optionModel->getOptionsInGroup($group['group_id'], $fetchOptions);

		$canEdit = $optionModel->canEditOptionAndGroupDefinitions();

		$viewParams = array(
			'group' => $group,
			'groups' => $optionModel->prepareOptionGroups($groups, false),
			'preparedOptions' => $optionModel->prepareOptions($options, false),
			'canEditGroup' => $canEdit,
			'canEditOptionDefinition' => $canEdit
		);

		return $this->responseView('XenForo_ViewAdmin_Option_ListOptions', 'xengallery_option_list', $viewParams);
	}

	/**
	 * Gets the specified option group or throws an exception.
	 *
	 * @param integer $groupId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	protected function _getOptionGroupOrError($groupId, array $fetchOptions = array())
	{
		$info = $this->_getOptionModel()->getOptionGroupById($groupId, $fetchOptions);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_group_not_found'), 404));
		}

		if (!empty($fetchOptions['join']) && $fetchOptions['join'] & XenForo_Model_Option::FETCH_ADDON)
		{
			if ($this->getModelFromCache('XenForo_Model_AddOn')->isAddOnDisabled($info))
			{
				throw $this->responseException($this->responseError(
					new XenForo_Phrase('option_group_belongs_to_disabled_addon', array(
						'addon' => $info['addon_title'],
						'link' => XenForo_Link::buildAdminLink('add-ons', $info)
					))
				));
			}
		}

		return $this->_getOptionModel()->prepareOptionGroup($info);
	}

	/**
	 * Gets the specified option or throws an exception.
	 *
	 * @param integer $optionId
	 *
	 * @return array
	 */
	protected function _getOptionOrError($optionId)
	{
		$info = $this->_getOptionModel()->getOptionById($optionId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_option_not_found'), 404));
		}

		return $this->_getOptionModel()->prepareOption($info);
	}

	public function actionField()
	{
		$fieldModel = $this->_getFieldModel();

		$fields = $fieldModel->prepareGalleryFields($fieldModel->getGalleryFields());

		$viewParams = array(
			'fieldsGrouped' => $fieldModel->groupGalleryFields($fields),
			'fieldCount' => count($fields),
			'fieldGroups' => $fieldModel->getGalleryFieldGroups(),
			'fieldTypes' => $fieldModel->getGalleryFieldTypes()
		);


		return $this->responseView('XenGallery_ViewAdmin_Field_List', 'xengallery_field_list', $viewParams);
	}

	public function actionFieldAdd()
	{
		return $this->_getFieldAddEditResponse(array(
			'field_id' => null,
			'display_group' => 'below_media',
			'display_order' => 1,
			'field_type' => 'textbox',
			'field_choices' => '',
			'match_type' => 'none',
			'match_regex' => '',
			'match_callback_class' => '',
			'match_callback_method' => '',
			'max_length' => 0,
			'display_template' => ''
		));
	}

	public function actionFieldEdit()
	{
		$field = $this->_getFieldOrError($this->_input->filterSingle('field_id', XenForo_Input::STRING));
		return $this->_getFieldAddEditResponse($field);
	}

	protected function _getFieldAddEditResponse(array $field)
	{
		$fieldModel = $this->_getFieldModel();

		$typeMap = $fieldModel->getGalleryFieldTypeMap();
		$validFieldTypes = $fieldModel->getGalleryFieldTypes();

		if (!empty($field['field_id']))
		{
			$selCategoryIds = $this->_getFieldModel()->getCategoryAssociationsByField($field['field_id']);

			$masterTitle = $fieldModel->getGalleryFieldMasterTitlePhraseValue($field['field_id']);
			$masterDescription = $fieldModel->getGalleryFieldMasterDescriptionPhraseValue($field['field_id']);

			$existingType = $typeMap[$field['field_type']];
			foreach ($validFieldTypes AS $typeId => $type)
			{
				if ($typeMap[$typeId] != $existingType)
				{
					unset($validFieldTypes[$typeId]);
				}
			}
		}
		else
		{
			$selCategoryIds = array();
			$masterTitle = '';
			$masterDescription = '';
			$existingType = false;
		}

		if (!$selCategoryIds)
		{
			$selCategoryIds = array(0);
		}

		$viewParams = array(
			'field' => $field,
			'masterTitle' => $masterTitle,
			'masterDescription' => $masterDescription,
			'masterFieldChoices' => $fieldModel->getGalleryFieldChoices($field['field_id'], $field['field_choices'], true),

			'fieldGroups' => $fieldModel->getGalleryFieldGroups(),
			'validFieldTypes' => $validFieldTypes,
			'fieldTypeMap' => $typeMap,
			'existingType' => $existingType,

			'categories' => $this->_getCategoryModel()->getCategoryStructure(),
			'selCategoryIds' => $selCategoryIds,
		);

		return $this->responseView('XenGallery_ViewAdmin_Field_Edit', 'xengallery_field_edit', $viewParams);
	}

	public function actionFieldSave()
	{
		$fieldId = $this->_input->filterSingle('field_id', XenForo_Input::STRING);

		$newFieldId = $this->_input->filterSingle('new_field_id', XenForo_Input::STRING);
		$dwInput = $this->_input->filter(array(
			'display_group' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'field_type' => XenForo_Input::STRING,
			'match_type' => XenForo_Input::STRING,
			'match_regex' => XenForo_Input::STRING,
			'match_callback_class' => XenForo_Input::STRING,
			'match_callback_method' => XenForo_Input::STRING,
			'max_length' => XenForo_Input::UINT,
			'album_use' => XenForo_Input::UINT,
			'display_add_media' => XenForo_Input::BOOLEAN,
			'required' => XenForo_Input::BOOLEAN,
			'display_template' => XenForo_Input::STRING
		));
		$categoryIds = $this->_input->filterSingle('category_ids', XenForo_Input::UINT, array('array' => true));

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Field');
		if ($fieldId)
		{
			$dw->setExistingData($fieldId);
		}
		else
		{
			$dw->set('field_id', $newFieldId);
		}

		$dw->bulkSet($dwInput);

		$dw->setExtraData(XenGallery_DataWriter_Field::DATA_CATEGORY_IDS, $categoryIds);

		$dw->setExtraData(
			XenGallery_DataWriter_Field::DATA_TITLE,
			$this->_input->filterSingle('title', XenForo_Input::STRING)
		);
		$dw->setExtraData(
			XenGallery_DataWriter_Field::DATA_DESCRIPTION,
			$this->_input->filterSingle('description', XenForo_Input::STRING)
		);

		$fieldChoices = $this->_input->filterSingle('field_choice', XenForo_Input::STRING, array('array' => true));
		$fieldChoicesText = $this->_input->filterSingle('field_choice_text', XenForo_Input::STRING, array('array' => true));
		$fieldChoicesCombined = array();
		foreach ($fieldChoices AS $key => $choice)
		{
			if (isset($fieldChoicesText[$key]))
			{
				$fieldChoicesCombined[$choice] = $fieldChoicesText[$key];
			}
		}

		$dw->setFieldChoices($fieldChoicesCombined);

		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xengallery/fields') . $this->getLastHash($dw->get('field_id'))
		);
	}

	public function actionFieldDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenGallery_DataWriter_Field', 'field_id',
				XenForo_Link::buildAdminLink('xengallery/fields')
			);
		}
		else
		{
			$field = $this->_getFieldOrError($this->_input->filterSingle('field_id', XenForo_Input::STRING));

			$viewParams = array(
				'field' => $field
			);

			return $this->responseView('XenGallery_ViewAdmin_Field_Delete', 'xengallery_field_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified field or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getFieldOrError($id)
	{
		$field = $this->getRecordOrError(
			$id, $this->_getFieldModel(), 'getGalleryFieldById',
			'requested_field_not_found'
		);

		return $this->_getFieldModel()->prepareGalleryField($field);
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}

	/**
	 * Gets the permission model.
	 *
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
	
	/**
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}

	/**
	 * Lazy load the option model.
	 *
	 * @return XenForo_Model_Option
	 */
	protected function _getOptionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Option');
	}

	/**
	 * @return XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Field');
	}
}