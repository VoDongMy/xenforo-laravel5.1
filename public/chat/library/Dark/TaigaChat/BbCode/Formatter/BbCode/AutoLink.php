<?php
  
class Dark_TaigaChat_BbCode_Formatter_BbCode_AutoLink extends XenForo_BbCode_Formatter_BbCode_AutoLink
{
	public function __construct()
	{
		parent::__construct();

		$options = XenForo_Application::get('options');

		$this->_autoEmbed = $options->autoEmbedMedia;
		$this->_autoEmbed['embedType'] = XenForo_Helper_Media::AUTO_EMBED_MEDIA_DISABLED;
		$this->_autoEmbedRemaining = ($options->messageMaxMedia ? $options->messageMaxMedia : PHP_INT_MAX);
	}
}