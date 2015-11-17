<?php
class vtPhong_DataWriter_DiscussionMessage_Post extends XFCP_vtPhong_DataWriter_DiscussionMessage_Post
{
    protected function _messagePostSave()
	{
	    parent::_messagePostSave();
        $threadId = $this->get('thread_id');

        // save slide
        if (XenForo_Application::isRegistered('vtPhong.slide.' . XenForo_Visitor::getUserId()))
        {
            $dataSlide = XenForo_Application::get('vtPhong.slide.' . XenForo_Visitor::getUserId());

            foreach ($dataSlide AS $item)
            {
                $dwSlide = $this->_getDataWriterSlideShow();
                $dwSlide->bulkSet(array(
                    'thread_id' => $threadId,
                    'url_slide' => $item['url_slide'],
                    'title_slide' => $item['title_slide'],
                    'des_slide' => $item['des_slide']
                ));
                $dwSlide->save();
            }
        }
	}
    
    protected function _getDataWriterSlideShow()
    {
        return XenForo_DataWriter::create('vtPhong_DataWriter_SlideShow');
    }
}