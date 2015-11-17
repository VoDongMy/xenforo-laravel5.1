<?php

/**
 * Controller for handling actions on forums.
 *
 * @package XenForo_Forum
 */
class vtPhong_ControllerPublic_Forum extends XFCP_vtPhong_ControllerPublic_Forum
{
	/**
	 * Inserts a new thread into this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddThread()
	{
        vtPhong_Helper::setSlideToRegistry(
            $this->_input->filterSingle('slide', XenForo_Input::ARRAY_SIMPLE)
        );

        return parent::actionAddThread();
	}
}