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
class sonnb_XenGallery_ViewPublic_Album_List extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderRss()
	{
		$xenOptions = XenForo_Application::getOptions();;

		if ($xenOptions->sonnbXG_enableRSS)
		{
			$title = new XenForo_Phrase('sonnb_xengallery');
			$title = $title->render();

			$description = new XenForo_Phrase('sonnb_xengallery_short_description', array(
				'title' => $xenOptions->boardTitle
			));
			$description = $description->render();

			$buggyXmlNamespace = (defined('LIBXML_DOTTED_VERSION') && LIBXML_DOTTED_VERSION == '2.6.24');

			$feed = new Zend_Feed_Writer_Feed();
			$feed->setEncoding('utf-8');
			$feed->setTitle($title);
			$feed->setDescription($description);
			$feed->setLink(XenForo_Link::buildPublicLink('canonical:gallery'));

			if (!$buggyXmlNamespace)
			{
				$feed->setFeedLink(XenForo_Link::buildPublicLink('canonical:gallery/index.rss'), 'rss');
			}

			$feed->setDateModified(XenForo_Application::$time);
			$feed->setLastBuildDate(XenForo_Application::$time);
			$feed->setGenerator($title);

			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text', array('view' => $this));
			$parser = new XenForo_BbCode_Parser($formatter);

			foreach ($this->_params['albums'] AS $album)
			{
				$albumDescription = $parser->render($album['description']);
				$entry = $feed->createEntry();

				if (!empty($album['contents']))
				{
					$albumDescription .= '<br /><br />';
					foreach ($album['contents'] as $content)
					{
						$albumDescription .= '<a href="'. XenForo_Link::buildPublicLink('canonical:gallery/'. $content['content_type'] .'s', $content).'"><img src="'. XenForo_Link::convertUriToAbsoluteUri($content['thumbnailUrl'], true) .'" /></a>';
					}
				}

				if ($albumDescription)
				{
					$entry->setDescription($albumDescription);
				}

				$entry->setTitle($album['title'] ? $album['title'] : $album['title'] . ' ');
				$entry->setLink(XenForo_Link::buildPublicLink('canonical:gallery/albums', $album));
				$entry->setDateCreated(new Zend_Date($album['album_date'], Zend_Date::TIMESTAMP));
				$entry->setDateModified(new Zend_Date($album['album_updated_date'], Zend_Date::TIMESTAMP));

				if (!$buggyXmlNamespace)
				{
					$entry->addAuthor(array(
						'name' => $album['username'],
						'uri' => XenForo_Link::buildPublicLink('canonical:gallery/authors', $album)
					));
					if ($album['comment_count'])
					{
						$entry->setCommentCount($album['comment_count']);
						$entry->setCommentLink(XenForo_Link::buildPublicLink('canonical:gallery/albums', $album).'#album-'.$album['album_id']);
					}
				}

				$feed->addEntry($entry);
			}

			return $feed->export('rss');
		}
	}
}