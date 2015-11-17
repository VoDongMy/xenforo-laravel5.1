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
class sonnb_XenGallery_Model_Field extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_FIELD = 0x01;

	public static $allCacheKey = 'sonnb_xengallery_all_fields';

	public function getFieldById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['field_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getFields($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getFieldsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['field_id'] = $ids;
		
		return $this->getFields($conditions, $fetchOptions);
	}

	public function getAllCachedFields()
	{
		$fields = XenForo_Application::getSimpleCacheData(self::$allCacheKey);

		if (!$fields)
		{
			$fields = $this->getFields();

			XenForo_Application::setSimpleCacheData(self::$allCacheKey, $fields);
		}

		return $fields;
	}

	public function getApplicableFieldsByContentId($contentType, $contentId = null, array $category = null, $fetchValues = true, $editMode = true)
	{
		$fields = $this->getAllCachedFields();
		$fields = $this->prepareFields($fields);

		if (empty($fields))
		{
			return array();
		}

		foreach ($fields as $key => $field)
		{
			$applicableContent = array_flip($field['content']);
			$applicableCategory = array_flip($field['category']);

			if (!isset($applicableContent[-1]) && !isset($applicableContent[$contentType]))
			{
				unset($fields[$key]);
				continue;
			}

			if ($category !== null &&
					!isset($applicableCategory[-1]) && !isset($applicableCategory[$category['category_id']]))
			{
				unset($fields[$key]);
				continue;
			}
		}

		if ($fetchValues === true)
		{
			if (empty($fields))
			{
				return array();
			}

			$fieldValues = $this->getFieldValueByFieldContentId(array_keys($fields), $contentType, $contentId);

			foreach ($fields as &$_field)
			{
				foreach ($fieldValues as $fieldValue)
				{
					if ($fieldValue['field_id'] === $_field['field_id'])
					{
						$_field['field_value'] = $fieldValue['field_value'];
					}
				}

				$_field = $this->prepareField($_field, $editMode);
			}
		}

		return $fields;
	}

	public function getFields(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareFieldConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareFieldFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT field.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_field` AS field
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'field_id'
		);
	}

	public function countFields(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareFieldConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareFieldFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_field` AS field
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function prepareField(array $field, $editMode = true, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($field))
		{
			$field['title'] = XenForo_Helper_String::censorString($field['title']);
			$field['description'] = XenForo_Helper_String::censorString($field['description']);

			$field['isMultiChoice'] = ($field['field_type'] === 'checkbox' || $field['field_type'] === 'multiselect');
			if (!empty($field['field_choices']) && !is_array($field['field_choices']))
			{
				$field['fieldChoices'] = @unserialize($field['field_choices']);
			}
			if (isset($field['field_value']))
			{
				if ($field['isMultiChoice'])
				{
					if (is_string($field['field_value']))
					{
						$field['field_value'] = @unserialize($field['field_value']);
					}
					else if (!is_array($field['field_value']))
					{
						$field['field_value'] = array();
					}
				}

                if ($field['match_type'] === 'url' && $editMode === false)
                {
                    $field['field_value'] = XenForo_Helper_String::autoLinkPlainText($field['field_value']);
                }

				$field['hasValue'] = (is_string($field['field_value']) && $field['field_value'] !== '') || (!is_string($field['field_value']) && $field['field_value']);
			}
			else
			{
				$field['hasValue'] = false;
			}

			if (!is_array($field['category']))
			{
				$field['category'] = @unserialize($field['category']);
			}

			if (!is_array($field['content']))
			{
				$field['content'] = @unserialize($field['content']);
			}
		}

		return $field;
	}

	public function prepareFields(array $fields, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (empty($fields))
		{
			return $fields;
		}

		foreach ($fields as &$field)
		{
			$field = $this->prepareField($field, $viewingUser);
		}

		return $fields;
	}

	public function getFieldTypes()
	{
		return array(
			'textbox' => array(
				'value' => 'textbox',
				'label' => new XenForo_Phrase('single_line_text_box')
			),
			'textarea' => array(
				'value' => 'textarea',
				'label' => new XenForo_Phrase('multi_line_text_box')
			),
			'select' => array(
				'value' => 'select',
				'label' => new XenForo_Phrase('drop_down_selection')
			),
			'radio' => array(
				'value' => 'radio',
				'label' => new XenForo_Phrase('radio_buttons')
			),
			'checkbox' => array(
				'value' => 'checkbox',
				'label' => new XenForo_Phrase('check_boxes')
			),
			'multiselect' => array(
				'value' => 'multiselect',
				'label' => new XenForo_Phrase('multiple_choice_drop_down_selection')
			)
		);
	}

	public function getFieldTypeMap()
	{
		return array(
			'textbox' => 'text',
			'textarea' => 'text',
			'radio' => 'single',
			'select' => 'single',
			'checkbox' => 'multiple',
			'multiselect' => 'multiple'
		);
	}

	public function getContentTypes()
	{
		return array(
			'album' => 'Album',
			'photo' => 'Photo',
			'video' => 'Video'
		);
	}

	public function getFieldBreadCrumb($field = null)
	{
		/*
		if ($field === null)
		{
			return $breadCrumbs['field'] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/fields'),
				'value' => new XenForo_Phrase('sonnb_xengallery_fields')
			);
		}

		if (!is_array($field))
		{
			$fields = $this->getAllCachedFields();

			if (!isset($fields[$field]))
			{
				return array();
			}

			$field = $fields[$field];
		}

		$breadCrumbs['field'] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/fields'),
			'value' => new XenForo_Phrase('sonnb_xengallery_fields')
		);
		$breadCrumbs['field_'.$field['field_id']] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/fields', $field),
			'value' => $field['title'],
			'field_id' => $field['field_id']
		);

		return $breadCrumbs;
		*/
	}

	public function countFieldValues($fieldId = 0, $contentType = null, $contentId = null)
	{
		$db = $this->_getDb();
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId,
			'field_id' => $fieldId
		);

		$whereConditions = $this->prepareFieldValueConditions($conditions);

		return $db->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_field_value` AS field_value
                WHERE ' . $whereConditions . '
            ');
	}

	public function getFieldValues($conditions = array(), $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareFieldValueFetchOptions($fetchOptions);
		$whereConditions = $this->prepareFieldValueConditions($conditions);

		return $this->_getDb()->fetchAll('
			SELECT field_value.*
				' . $sqlClauses['selectFields'] . '
			FROM sonnb_xengallery_field_value AS field_value
				' . $sqlClauses['joinTables'] . '
			WHERE '. $whereConditions .'
				' . $sqlClauses['orderClause'] . '
		');
	}

	public function getFieldValueByFieldContentId($fieldId, $contentType, $contentId)
	{
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId,
			'field_id' => $fieldId
		);

		return $this->getFieldValues($conditions);
	}

	public function getFieldValuesByFieldContentId($fieldId, $contentType, $contentId)
	{
		return $this->getFieldValueByFieldContentId($fieldId, $contentType, $contentId);
	}

	public function getFieldValuesByContentId($contentType, $contentId)
	{
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId
		);

		return $this->getFieldValues($conditions);
	}

	public function insertFieldValue(array $field, $contentType, $contentId, $fieldValue, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$field || !$contentType || !$contentId)
		{
			return false;
		}

		if ($field['field_type'] === 'multiselect')
		{
			$fieldValue = serialize($fieldValue);
		}

		return $this->_getDb()->insert('sonnb_xengallery_field_value', array(
			'field_id' => $field['field_id'],
			'content_id' => $contentId,
			'content_type' => $contentType,
			'field_value' => $fieldValue,
			'user_id' => $viewingUser['user_id'],
			'username' => $viewingUser['username']
		));
	}

	public function verifyFieldValue(array $field, &$value, &$error = '')
	{
		$error = false;

		switch ($field['field_type'])
		{
			case 'textbox':
				$value = preg_replace('/\r?\n/', ' ', strval($value));

			case 'textarea':
				$value = trim(strval($value));

				if ($field['max_length'] && utf8_strlen($value) > $field['max_length'])
				{
					$error = new XenForo_Phrase('please_enter_value_using_x_characters_or_fewer', array('count' => $field['max_length']));
					return false;
				}

				$matched = true;

				if ($value !== '')
				{
					switch ($field['match_type'])
					{
						case 'number':
							$matched = preg_match('/^[0-9]+(\.[0-9]+)?$/', $value);
							break;

						case 'alphanumeric':
							$matched = preg_match('/^[a-z0-9_]+$/i', $value);
							break;

						case 'email':
							$matched = Zend_Validate::is($value, 'EmailAddress');
							break;

						case 'url':
							if ($value === 'http://')
							{
								$value = '';
								break;
							}
							if (substr(strtolower($value), 0, 4) == 'www.')
							{
								$value = 'http://' . $value;
							}
							$matched = Zend_Uri::check($value);
							break;

						case 'regex':
							$matched = preg_match('#' . str_replace('#', '\#', $field['match_regex']) . '#sU', $value);
							break;
						default:
							// no matching
					}
				}

				if (!$matched)
				{
					if (!$error)
					{
						$error = new XenForo_Phrase('please_enter_value_that_matches_required_format');
					}
					return false;
				}
				break;

			case 'radio':
			case 'select':
				$choices = unserialize($field['field_choices']);
				$value = strval($value);

				if (!isset($choices[$value]))
				{
					$value = '';
				}
				break;

			case 'checkbox':
			case 'multiselect':
				$choices = unserialize($field['field_choices']);
				if (!is_array($value))
				{
					$value = array();
				}

				$newValue = array();

				foreach ($value AS $key => $choice)
				{
					$choice = strval($choice);
					if (isset($choices[$choice]))
					{
						$newValue[$choice] = $choice;
					}
				}

				$value = $newValue;
				break;
		}

		return true;
	}

	public function deleteFieldValueByFieldId($fieldId)
	{
		$db = $this->_getDb();

		$db->delete(
			'sonnb_xengallery_field_value',
			'field_id = '. $db->quote($fieldId)
		);
	}

	public function deleteFieldValueByContentId($contentType, $contentId)
	{
		$db = $this->_getDb();

		$db->delete(
			'sonnb_xengallery_field_value',
			array(
				'content_type = '. $db->quote($contentType),
				'content_id = '.$db->quote($contentId)
			)
		);
	}

	public function deleteFieldValueByFieldContentId($fieldId, $contentType, $contentId)
	{
		$db = $this->_getDb();

		$db->delete(
			'sonnb_xengallery_field_value',
			array(
				'content_type = '. $db->quote($contentType),
				'content_id = '.$db->quote($contentId),
				'field_id = '. $db->quote($fieldId)
			)
		);
	}

	public function prepareFieldValueConditions($conditions = array(), $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = "field_value.content_type IN (" . $db->quote($conditions['content_type']) . ")";
			}
			else
			{
				$sqlConditions[] = "field_value.content_type = " . $db->quote($conditions['content_type']);
			}
		}

		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = "field_value.content_id IN (" . $db->quote($conditions['content_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "field_value.content_id = " . $db->quote($conditions['content_id']);
			}
		}

		if (!empty($conditions['field_id']))
		{
			if (is_array($conditions['field_id']))
			{
				$sqlConditions[] = "field_value.field_id IN (" . $db->quote($conditions['field_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "field_value.field_id = " . $db->quote($conditions['field_id']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareFieldValueFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'content_type':
				case 'content_id':
				case 'user_id':
				case 'username':
					$orderBy = 'field_value.' . $fetchOptions['order'];
					$orderBySecondary = ', field_value.field_id DESC';
					break;
				case 'field_id':
				default:
					$orderBy = 'field_value.field_id';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}

			$orderBy .= $orderBySecondary;
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_FIELD)
			{
				$selectFields .= ',
						field.*';
				$joinTables .= '
						LEFT JOIN `sonnb_xengallery_field` AS field ON
							(field.field_id = field_value.field_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareFieldFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'field_id':
				case 'title':
				case 'description':
					$orderBy = 'field.' . $fetchOptions['order'];
					$orderBySecondary = ', field.display_order DESC';
					break;
				case 'display_order':
				default:
					$orderBy = 'field.display_order';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}
		
			$orderBy .= $orderBySecondary;
		}

		if (isset($fetchOptions['valueContent'])
				&& !empty($fetchOptions['valueContent']['content_id'])
				&& !empty($fetchOptions['valueContent']['content_type']))
		{
			$db = $this->_getDb();
			$selectFields .= ",
						field_value.field_value";
			$joinTables .= "
						LEFT JOIN `sonnb_xengallery_field_value` AS field_value ON
							(field.field_id = field_value.field_id
								AND field.content_id = '". $db->quote($fetchOptions['valueContent']['content_id']) ."'
								AND field.content_type = '". $db->quote($fetchOptions['valueContent']['content_type']) ."')";
		}
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareFieldConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['field_id']))
		{
			if (is_array($conditions['field_id']))
			{
				$sqlConditions[] = 'field.field_id IN (' . $db->quote($conditions['field_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'field.field_id = ' . $db->quote($conditions['field_id']);
			}
		}
		
		if (isset($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'field.title IN (' . $db->quote($conditions['title']) . ')';
			}
			else
			{
				$sqlConditions[] = 'field.title = ' . $db->quote($conditions['title']);
			}
		}

		if (isset($conditions['field_type']))
		{
			if (is_array($conditions['field_type']))
			{
				$sqlConditions[] = 'field.field_type IN (' . $db->quote($conditions['field_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'field.field_type = ' . $db->quote($conditions['field_type']);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
}
