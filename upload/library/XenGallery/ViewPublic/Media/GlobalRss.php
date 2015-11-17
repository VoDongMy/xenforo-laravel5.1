<?php

class XenGallery_ViewPublic_Media_GlobalRss extends XenForo_ViewPublic_Base
{
	public function renderRss()
	{
		$options = XenForo_Application::getOptions();
		$title = new XenForo_Phrase('xengallery_media');
		$description = new XenForo_Phrase('xengallery_check_out_all_media_from_x', array('board' => $options->boardTitle));

		$buggyXmlNamespace = (defined('LIBXML_DOTTED_VERSION') && LIBXML_DOTTED_VERSION == '2.6.24');

		$feed = new Zend_Feed_Writer_Feed();
		$feed->setEncoding('utf-8');
		$feed->setTitle($title->render());
		$feed->setDescription($description->render());
		$feed->setLink(XenForo_Link::buildPublicLink('canonical:xengallery'));
		if (!$buggyXmlNamespace)
		{
			$feed->setFeedLink(XenForo_Link::buildPublicLink('canonical:xengallery.rss'), 'rss');
		}
		$feed->setDateModified(XenForo_Application::$time);
		$feed->setLastBuildDate(XenForo_Application::$time);
		$feed->setGenerator($title->render());

		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		foreach ($this->_params['media'] AS $media)
		{
			$entry = $feed->createEntry();
			$entry->setTitle($media['media_title'] ? $media['media_title'] : $media['media_title'] . ' ');
			$entry->setLink(XenForo_Link::buildPublicLink('canonical:xengallery', $media));
			$entry->setDateCreated(new Zend_Date($media['media_date'], Zend_Date::TIMESTAMP));
			$entry->setDateModified(new Zend_Date($media['last_edit_date'], Zend_Date::TIMESTAMP));
			if (!empty($media['media_description']))
			{
				$entry->setDescription($media['media_description']);
			}

			XenGallery_ViewPublic_Helper_VideoHtml::addVideoHtml($media, $bbCodeParser);
			$content = $this->_renderer->createTemplateObject('xengallery_rss_content', array('media' => $media));

			$entry->setContent($content->render());

			if (!$buggyXmlNamespace)
			{
				$entry->addAuthor(array(
					'name' => $media['username'],
					'email' => 'invalid@example.com',
					'uri' => XenForo_Link::buildPublicLink('canonical:xengallery/users', $media)
				));
				if ($media['comment_count'])
				{
					$entry->setCommentCount($media['comment_count']);
				}
			}

			$feed->addEntry($entry);
		}

		return $feed->export('rss');
	}
}