<?php
class vtPhong_ControllerAdmin_Forum extends XFCP_vtPhong_ControllerAdmin_Forum
{
    public function actionSave()
    {
        $hasUp = $this->_input->filterSingle('can_add_slide', XenForo_Input::UINT);
        XenForo_Application::set('vtPhong_can_add_slide', $hasUp);

        return parent::actionSave();
    }
}