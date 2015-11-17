<?php

class Dark_TaigaChat_DataWriter_Room extends XenForo_DataWriter
{
	
	const DATA_TITLE = 'phraseTitle';
	
	
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$options = XenForo_Application::get('options');
		return array(
			'dark_taigachat_rooms' => array(
				'id'               => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'name'             => array('type' => self::TYPE_STRING, 'required' => true),
				'key'       	   => array('type' => self::TYPE_STRING, 'required' => true),
				'group_whitelist'  => array('type' => self::TYPE_SERIALIZED, 'required' => true),
				'display_order'    => array('type' => self::TYPE_UINT, 'required' => true),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('dark_taigachat_rooms' => $this->_getModel()->getRoomDefinitionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'id = ' . $this->_db->quote($this->getExisting('id'));
	}

	protected function _preSave()
	{        
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	protected function _postSave()
	{
		$id = $this->get('id');

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($id), $titlePhrase, '', array('global_cache' => 1)
			);
			
		}
		
		$this->_getModel()->getRooms(true);
	}

	protected function _postDelete()
	{		
	}

	/**
	 * @return Dark_PostRating_Model
	 */
	protected function _getModel()
	{
		return $this->getModelFromCache('Dark_TaigaChat_Model_TaigaChat');
	}
	
	protected function _getTitlePhraseName($id)
	{
		return $this->_getModel()->getRatingTitlePhraseName($id);
	}

}