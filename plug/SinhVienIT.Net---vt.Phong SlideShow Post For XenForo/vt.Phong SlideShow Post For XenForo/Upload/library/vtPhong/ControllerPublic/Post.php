<?php

/**
 * Controller for post-related actions.
 *
 * @package XenForo_Post
 */
class vtPhong_ControllerPublic_Post extends XFCP_vtPhong_ControllerPublic_Post
{
	/**
	 * Displays a form to edit an existing post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
	    $res = parent::actionEdit();
        
        if (isset($res->params['thread']['first_post_id']) && isset($res->params['post']['post_id']) &&
            ($res->params['thread']['first_post_id'] == $res->params['post']['post_id']))
        {
            // get all slide in post if exist
            $data = $this->_getModelSlide()->getSlides(array(
                'thread_id' => $res->params['thread']['thread_id']
            ));

            $res->params['slides'] = $data ? $data : false;
        }

        return $res;
	}

	/**
	 * Updates an existing post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$this->_assertCanEditPost($post, $thread, $forum);

        $this->_getModelSlide()->deleteSlide($thread['thread_id']);

		vtPhong_Helper::setSlideToRegistry(
            $this->_input->filterSingle('slide', XenForo_Input::ARRAY_SIMPLE)
        );

        return parent::actionSave();
	}
   
    protected function _getModelSlide()
    {
        return $this->getModelFromCache('vtPhong_Model_SlideShow');
    }
}