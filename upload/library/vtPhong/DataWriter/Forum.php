<?php
/**
* Data writer for Forums.
*
* @package XenForo_Forum
*/
class vtPhong_DataWriter_Forum extends XFCP_vtPhong_DataWriter_Forum
{
    protected function _getFields()
    {
        $field = parent::_getFields();

        $field['xf_forum']['can_add_slide'] = array('type' => self::TYPE_UINT, 'default' => 0);

        return $field;
    }

    protected function _preSave()
    {
        parent::_preSave();

        if (XenForo_Application::isRegistered('vtPhong_can_add_slide'))
        {
            $this->set('can_add_slide', XenForo_Application::get('vtPhong_can_add_slide'), 'xf_forum');
        }
    }
    
 }