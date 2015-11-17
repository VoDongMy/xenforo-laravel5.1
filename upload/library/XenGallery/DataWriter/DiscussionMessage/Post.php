<?php

class XenGallery_DataWriter_DiscussionMessage_Post extends XFCP_XenGallery_DataWriter_DiscussionMessage_Post
{
	/**
	 * Check that the contents of the message are valid, based on length, images, etc.
	 */
	protected function _checkMessageValidity()
	{
		$maxImages = $this->getOption(self::OPTION_MAX_IMAGES);

		if ($maxImages && empty($this->_errors['message']))
		{
			$message = $this->get('message');

			/** @var $formatter XenForo_BbCode_Formatter_BbCode_Filter */
			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Filter');
			$parser = XenForo_BbCode_Parser::create($formatter);
			$parser->render($message);

			if ($formatter->getTagTally('img') + $formatter->getTagTally('gallery') > $maxImages)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_images', array('count' => $maxImages)), 'message');
			}
		}
	}
}