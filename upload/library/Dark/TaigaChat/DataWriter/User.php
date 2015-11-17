<?php
 
class Dark_TaigaChat_DataWriter_User extends XFCP_Dark_TaigaChat_DataWriter_User {
	
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_user']['taigachat_color'] = array('type' => self::TYPE_STRING, 'maxLength' => 6, 'default' => '', 'verification' => array('$this', '_verifyColor'));
		return $fields;
	}	
	
	protected function _verifyColor(&$color)
	{
		if(substr($color, 0, 1) == '#')
			$color = substr($color, 1);
		$color = strtoupper($color);
		return empty($color) || preg_match('#^[A-F0-9]{6}$#', $color);
	}
}