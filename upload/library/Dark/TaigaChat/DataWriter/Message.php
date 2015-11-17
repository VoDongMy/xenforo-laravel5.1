<?php

class Dark_TaigaChat_DataWriter_Message extends XenForo_DataWriter
{
	
	const OPTION_IS_AUTOMATED = 'isAutomated';
	const OPTION_MAX_MESSAGE_LENGTH = 'maxMessageLength';
	
	
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$options = XenForo_Application::get('options');
		return array(
			'dark_taigachat' => array(
				'id'      	   => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'user_id'      => array('type' => self::TYPE_UINT,   'required' => true),
				'date'         => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'last_update'  => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'username'     => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'message'      => array('type' => self::TYPE_STRING, 'required' => true, 'requiredError' => 'please_enter_valid_message'),
				'activity'     => array('type' => self::TYPE_BOOLEAN,   'required' => true, 'default' => 0),
				'room_id'      => array('type' => self::TYPE_UINT,   'required' => true, 'default' => 1),
			)
		);
	}

	protected function _checkMessageValidity()
	{
		$message = $this->get('message');

		$maxLength = $this->getOption(self::OPTION_MAX_MESSAGE_LENGTH);
		
		// some leeway
		if(strpos($message, "[color") === 0)
			$maxLength += 24;
			
		if ($maxLength && utf8_strlen($message) > $maxLength)
		{
			$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $this->getOption(self::OPTION_MAX_MESSAGE_LENGTH))), 'message');
		}
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

		return array('dark_taigachat' => $this->_getModel()->getMessageById($id));
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


	/**
	 * Adds any number of responses to the poll. Blank options are ignored.
	 *
	 * @param array $responses
	 */
	public function addResponses(array $responses)
	{
		foreach ($responses AS $key => $response)
		{
			if (!is_string($response) || $response === '')
			{
				unset($responses[$key]);
			}
		}
		$this->_newResponses = array_merge($this->_newResponses, $responses);
	}

	/**
	 * Determines if the poll has new responses.
	 *
	 * @return boolean
	 */
	public function hasNewResponses()
	{
		return (count($this->_newResponses) > 0);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{		
		if ($this->isChanged('message'))
		{
			$this->_checkMessageValidity();
		}
		
		if ($this->isInsert() && !$this->isChanged('date'))
		{
			$this->set('date', XenForo_Application::$time);
		}
		
		if(!$this->isChanged('last_update')){		
			$this->set('last_update', XenForo_Application::$time);
		} 
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_getModel()->regeneratePublicHtml();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		
	}

	/**
	 * @return Dark_TaigaChat_Model_TaigaChat
	 */
	protected function _getModel()
	{
		return $this->getModelFromCache('Dark_TaigaChat_Model_TaigaChat');
	}
	
	
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::get('options');

		return array(
			self::OPTION_MAX_MESSAGE_LENGTH => $options->dark_taigachat_maxlength,
		);
	}
	
	
	public function setOption($name, $value)
	{
		if ($name === self::OPTION_IS_AUTOMATED)
		{
			if ($value)
			{
				parent::setOption(self::OPTION_MAX_MESSAGE_LENGTH, 0);
			}
		}
		else
		{
			parent::setOption($name, $value);
		}
	}

}