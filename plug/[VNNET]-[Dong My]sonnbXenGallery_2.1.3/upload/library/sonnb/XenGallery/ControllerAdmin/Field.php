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
class sonnb_XenGallery_ControllerAdmin_Field extends sonnb_XenGallery_ControllerAdmin_Abstract
{
	/**
	 * @param $action
	 */
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('xengalleryManager');
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		$fields = $this->_getFieldModel()->getFields();

		$viewParams = array(
			'fields' => $fields,
			'fieldCount' => count($fields)
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Field_List',
			'sonnb_xengallery_field_list',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionView()
	{
		//TODO: List all values within this field
	}

	/**
	 * @param array $field
	 * @return XenForo_ControllerResponse_View
	 */
	protected function AddEdit(array $field)
	{
		$fieldModel = $this->_getFieldModel();

		$typeMap = $fieldModel->getFieldTypeMap();
		$validFieldTypes = $fieldModel->getFieldTypes();

		if (!empty($field['field_id']))
		{
			$existingType = $typeMap[$field['field_type']];
			$fieldChoices = $field['fieldChoices'];

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
			$existingType = false;
			$fieldChoices = array();
		}

		$categories = $this->_getCategoryModel()->getCategories();
		$contentTypes = $fieldModel->getContentTypes();

		$viewParams = array(
			'field' => $field,
			'validFieldTypes' => $validFieldTypes,
			'fieldChoices' => $fieldChoices,

			'existingType' => $existingType,
			'categories' => $categories,
			'contentTypes' => $contentTypes
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Field_Edit',
			'sonnb_xengallery_field_edit',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		return $this->AddEdit(array(
			'field_id' => 0,
			'title' => '',
			'description' => '',
			'category' => array(),
			'content' => array(),
			'field_choices' => array(),
			'field_type' => 'textbox',
			'required' => false,
			'display_order' => 1,
			'match_type' => 'none',
			'match_regex' => '',
			'max_length' => 0,
			'active' => true
		));
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionEdit()
	{
		$field = $this->_getFieldOrError();

		return $this->AddEdit($field);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$fieldId = $this->_input->filterSingle('field_id', XenForo_Input::STRING);

		$newFieldId = $this->_input->filterSingle('new_field_id', XenForo_Input::STRING);
		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,

			'field_type' => XenForo_Input::STRING,
			'match_type' => XenForo_Input::STRING,
			'match_regex' => XenForo_Input::STRING,

			'display_order' => XenForo_Input::UINT,
			'required' => XenForo_Input::UINT,
			'max_length' => XenForo_Input::UINT,

			'category' => XenForo_Input::ARRAY_SIMPLE,
			'content' => XenForo_Input::ARRAY_SIMPLE,
		));

		/* @var sonnb_XenGallery_DataWriter_Field $dw */
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Field');
		if ($fieldId)
		{
			$dw->setExistingData($fieldId);
		}
		else
		{
			$dw->set('field_id', $newFieldId);
		}
		$dw->bulkSet($dwInput);

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
			XenForo_Link::buildAdminLink('gallery/fields') . $this->getLastHash($dw->get('field_id'))
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$fieldId = $this->_input->filterSingle('field_id', XenForo_Input::STRING);
			
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Field');
			$dw->setExistingData($fieldId);
			$dw->delete();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				XenForo_Link::buildAdminLink('gallery/fields')
			);
		}
		else
		{
			$viewParams = array(
				'field' => $this->_getFieldOrError()
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Field_Delete',
				'sonnb_xengallery_field_delete',
				$viewParams
			);
		}
	}

	/**
	 * @param null $id
	 * @return mixed
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _getFieldOrError($id = null)
	{
		if ($id === null)
		{
			$id = $this->_input->filterSingle('field_id', XenForo_Input::STRING);
		}

		$info = $this->_getFieldModel()->getFieldById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('sonnb_xengallery_requested_field_not_found'), 404));
		}

		return $this->_getFieldModel()->prepareField($info);
	}
}