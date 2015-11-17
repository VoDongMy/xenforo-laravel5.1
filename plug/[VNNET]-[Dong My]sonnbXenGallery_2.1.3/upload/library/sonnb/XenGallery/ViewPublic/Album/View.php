<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_ViewPublic_Album_View extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderHtml()
	{
		$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this));
		$parser = new XenForo_BbCode_Parser($bbCodeFormatter);

		$this->_params['album']['descriptionHtml'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $this->_params['album']['description']);

		if (!empty($this->_params['album']['comments']))
		{
			foreach ($this->_params['album']['comments'] as &$comment)
			{
				$comment['message'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $comment['message']);
			}
		}

		if (!empty($this->_params['fields']))
		{
			$this->_params['fields'] = sonnb_XenGallery_ViewPublic_Helper::addFieldsValueHtml($this, $this->_params['fields']);
		}
	}

	public function renderRss()
	{
		if (XenForo_Application::getOptions()->sonnbXG_enableRSS)
		{
			$album = $this->_params['album'];
			$title = $album['title'];
			$description = $album['description']. ' ';

			$buggyXmlNamespace = (defined('LIBXML_DOTTED_VERSION') && LIBXML_DOTTED_VERSION == '2.6.24');

			$feed = new Zend_Feed_Writer_Feed();
			$feed->setEncoding('utf-8');
			$feed->setTitle($title);
			if ($description)
			{
				$feed->setDescription($description);
			}
			$feed->setLink(XenForo_Link::buildPublicLink('canonical:gallery/albums', $album));

			if (!$buggyXmlNamespace)
			{
				$feed->setFeedLink(XenForo_Link::buildPublicLink('canonical:gallery/albums/index.rss', $album), 'rss');
			}

			$feed->setDateModified(XenForo_Application::$time);
			$feed->setLastBuildDate(XenForo_Application::$time);
			$feed->setGenerator($title);

			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text', array('view' => $this));
			$parser = new XenForo_BbCode_Parser($formatter);

			foreach ($this->_params['contents'] AS $content)
			{
				$photoDescription = $parser->render($content['description']);
				$entry = $feed->createEntry();

				$photoDescription .= '<br /><br />';
				$photoDescription .= '<a href="'. XenForo_Link::buildPublicLink('canonical:gallery/'. $content['content_type'] .'s', $content).'"><img src="'. XenForo_Link::convertUriToAbsoluteUri($content['thumbnailUrl'], true) .'" /></a>';

				if ($photoDescription)
				{
					$entry->setDescription($photoDescription);
				}

				$entry->setTitle(' ');
				$entry->setLink(XenForo_Link::buildPublicLink('canonical:gallery/photos', $content));
				$entry->setDateCreated(new Zend_Date($content['content_date'], Zend_Date::TIMESTAMP));
				$entry->setDateModified(new Zend_Date($content['content_updated_date'], Zend_Date::TIMESTAMP));

				if (!$buggyXmlNamespace)
				{
					$entry->addAuthor(array(
						'name' => $content['username'],
						'uri' => XenForo_Link::buildPublicLink('canonical:gallery/authors', $content)
					));
					if ($content['comment_count'])
					{
						$entry->setCommentCount($content['comment_count']);
						$entry->setCommentLink(XenForo_Link::buildPublicLink('canonical:gallery/'. $content['content_type'] .'s', $content).'#content-'.$content['content_id']);
					}
				}

				$feed->addEntry($entry);
			}

			return $feed->export('rss');
		}
	}
}