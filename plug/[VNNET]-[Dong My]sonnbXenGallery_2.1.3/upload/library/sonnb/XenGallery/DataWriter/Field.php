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
class sonnb_XenGallery_DataWriter_Field extends sonnb_XenGallery_DataWriter_Abstract
{
	protected $_fieldChoices = null;

	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_field' => array(
				'field_id' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 25,
					'verification' => array('$this', '_verifyFieldId'),
					'requiredError' => 'please_enter_valid_field_id'
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
					'default' => '',
					'maxLength' => 255
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 500
				),
				'category' => array(
					'type' => self::TYPE_SERIALIZED
				),
				'content' => array(
					'type' => self::TYPE_SERIALIZED
				),
				'field_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'textbox',
					'allowedValues' => array('textbox', 'textarea', 'select', 'radio', 'checkbox', 'multiselect')
				),
				'field_choices' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'required' => array(
					'type' => self::TYPE_UINT,
					'allowedValues' => array(0, 1)
				),
				'display_order' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'match_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'none',
					'allowedValues' => array('regex', 'url', 'email', 'alphanumeric', 'number', 'none')
				),
				'match_regex' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 250,
					'default' => ''
				),
				'max_length' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'active' => array(
					'type' => self::TYPE_UINT,
					'default' => 1,
					'allowedValues' => array(0, 1)
				)
			)
		);
	}

	protected function _verifyFieldId(&$id)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $id))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'field_id');
			return false;
		}

		if ($id !== $this->getExisting('field_id') && $this->_getFieldModel()->getFieldById($id))
		{
			$this->error(new XenForo_Phrase('field_ids_must_be_unique'), 'field_id');
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged('field_type'))
		{
			$typeMap = $this->_getFieldModel()->getFieldTypeMap();
			if ($typeMap[$this->get('field_type')] != $typeMap[$this->getExisting('field_type')])
			{
				$this->error(new XenForo_Phrase('you_may_not_change_field_to_different_type_after_it_has_been_created'), 'field_type');
			}
		}

		if (in_array($this->get('field_type'), array('select', 'radio', 'checkbox', 'multiselect')))
		{
			if (($this->isInsert() && !$this->_fieldChoices) || (is_array($this->_fieldChoices) && !$this->_fieldChoices))
			{
				$this->error(new XenForo_Phrase('please_enter_at_least_one_choice'), 'field_choices', false);
			}
		}
		else
		{
			$this->setFieldChoices(array());
		}

		if (strlen($this->get('title')) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	protected function _postSave()
	{
		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Field::$allCacheKey, false);
	}
	
	protected function _postDelete()
	{
		$this->_getFieldModel()->deleteFieldValueByFieldId($this->get('field_id'));

		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Field::$allCacheKey, false);
	}

	public function setFieldChoices(array $choices)
	{
		foreach ($choices AS $value => &$text)
		{
			if ($value === '')
			{
				unset($choices[$value]);
				continue;
			}

			$text = strval($text);

			if ($text === '')
			{
				$this->error(new XenForo_Phrase('please_enter_text_for_each_choice'), 'field_choices');
				return false;
			}

			if (preg_match('#[^a-z0-9_]#i', $value))
			{
				$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'field_choices');
				return false;
			}

			if (strlen($value) > 25)
			{
				$this->error(new XenForo_Phrase('please_enter_value_using_x_characters_or_fewer', array('count' => 25)));
				return false;
			}
		}

		$this->_fieldChoices = $choices;
		$this->set('field_choices', $choices);

		return true;
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'field_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_field' => $this->_getFieldModel()->getFieldById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'field_id = ' . $this->_db->quote($this->getExisting('field_id'));
	}
}