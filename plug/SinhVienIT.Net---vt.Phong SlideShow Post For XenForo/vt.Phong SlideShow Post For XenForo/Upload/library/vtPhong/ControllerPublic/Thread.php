<?php

/**
 * Controller for handling actions on threads.
 *
 * @package XenForo_Thread
 */
class vtPhong_ControllerPublic_Thread extends XFCP_vtPhong_ControllerPublic_Thread
{
	/**
	 * Displays a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
        $res = parent::actionIndex();

        $data = $this->_getModelSlide()->getSlides(array(
            'thread_id' => $this->_input->filterSingle('thread_id', XenForo_Input::UINT)
        ));

        if ($data)
        {
            $end = end($data);
            $first = reset($data);
            $res->params['slides'] = $data;
            $res->params['data_min_max']['max_id'] = $end['id'];
            $res->params['data_min_max']['min_id'] = $first['id'];
        }
        else
        {
            $res->params['slides'] = false;
        }

		return $res;
	}
    
    protected function _getModelSlide()
    {
        return $this->getModelFromCache('vtPhong_Model_SlideShow');
    }
}