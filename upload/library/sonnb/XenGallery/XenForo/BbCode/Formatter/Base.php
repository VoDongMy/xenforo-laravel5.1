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
class sonnb_XenGallery_XenForo_BbCode_Formatter_Base extends XFCP_sonnb_XenGallery_XenForo_BbCode_Formatter_Base
{
	public function getTags()
	{
		$tags = parent::getTags();

		$tags['photo'] = array(
			//'hasOption' => true,
			//'optionRegex' => '/^[a-z]+$/',
			'callback' => array($this, 'renderTagXenPhoto'),
			'plainChildren' => true,
		);

		$tags['video'] = array(
			//'hasOption' => true,
			//'optionRegex' => '/^[a-z]+$/',
			'callback' => array($this, 'renderTagXenVideo'),
			'plainChildren' => true,
		);

		$tags['album'] = array(
			//'hasOption' => true,
			//'optionRegex' => '/^[a-z]+$/',
			'callback' => array($this, 'renderTagXenAlbum'),
			'plainChildren' => true,
		);

		return $tags;
	}

	public function preLoadTemplates(XenForo_View $view)
	{
		parent::preLoadTemplates($view);

		$view->preLoadTemplate('sonnb_xengallery_bbcode_photo');
		$view->preLoadTemplate('sonnb_xengallery_bbcode_album');
	}

	public function renderTagXenPhoto(array $tag, array $rendererStates)
	{
		$photoId = trim($this->stringifyTree($tag['children']));
		$photoId = intval($photoId);
		$photoModel = $this->_getPhotoModel();
		$galleryModel = $this->_getGalleryModel();

		if (!$photoId || !$galleryModel->canViewGallery())
		{
			return '';
		}

		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA |
						sonnb_XenGallery_Model_Photo::FETCH_USER |
						sonnb_XenGallery_Model_Photo::FETCH_ALBUM,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id']
		);

		$photo = $photoModel->getContentByContentId(sonnb_XenGallery_Model_Photo::$contentType, $photoId, $fetchOptions);
		$photo = $photoModel->prepareContent($photo, $fetchOptions);

		if (!$photo || !$photoModel->canViewContentAndContainer($photo, $photo['album'], $errorPhraseKey))
		{
			return '';
		}

		switch ($tag['option'])
		{
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL:
				$thumbnail = $photo['thumbnailSmall'];
				break;
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE:
				$thumbnail = $photo['contentUrl'];
				break;
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM:
			default:
				$thumbnail = $photo['thumbnailUrl'];
				break;
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject(
				'sonnb_xengallery_bbcode_photo',
				array(
					'content' => $photo,
					'tag' => $tag,
					'thumbnail' => $thumbnail
				)
			);

			return $template->render();
		}
		else
		{
			$template = '<a style="display: inline-block;" href="%s" title="%s"><img style="display: block; padding: 4px; border: 1px solid #CCC; background-color: #FFF; margin: 5px 0px;" src="%s" /></a>';

			return sprintf(
				$template,
				XenForo_Link::buildPublicLink('gallery/photos', $photo),
				$photo['description'],
				$thumbnail
			);
		}
	}

	public function renderTagXenVideo(array $tag, array $rendererStates)
	{
		$videoId = trim($this->stringifyTree($tag['children']));
		$videoId = intval($videoId);
		$videoModel = $this->_getVideoModel();
		$galleryModel = $this->_getGalleryModel();

		if (!$videoId || !$galleryModel->canViewGallery())
		{
			return '';
		}

		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_DATA |
				sonnb_XenGallery_Model_Video::FETCH_USER |
				sonnb_XenGallery_Model_Video::FETCH_ALBUM |
				sonnb_XenGallery_Model_Video::FETCH_VIDEO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id']
		);

		$video = $videoModel->getContentByContentId(sonnb_XenGallery_Model_Video::$contentType, $videoId, $fetchOptions);
		$video = $videoModel->prepareContent($video, $fetchOptions);

		if (!$video || !$videoModel->canViewContentAndContainer($video, $video['album'], $errorPhraseKey))
		{
			return '';
		}

		if (!empty($video['video_key']) || !$this->_view)
		{
			if (!empty($video['video_key']))
			{
				$mediaKey = $video['video_key'];
				if (preg_match('#[&?"\'<>\r\n]#', $mediaKey) || strpos($mediaKey, '..') !== false)
				{
					return '';
				}

				$mediaSiteId = strtolower($video['video_type']);
				if ($mediaSiteId == 'youtube')
				{
					$mediaKey = str_replace('/', '', $mediaKey);
				}
				if (isset($this->_mediaSites[$mediaSiteId]))
				{
					$embedHtml = $this->_getMediaSiteHtmlFromCallback($mediaKey, $this->_mediaSites[$mediaSiteId]);

					if (!$embedHtml)
					{
						$embedHtml = $this->_mediaSites[$mediaSiteId]['embed_html'];
						$embedHtml = str_replace('{$id}', urlencode($mediaKey), $embedHtml);
					}

					return $embedHtml;
				}
				else
				{
					return '';
				}
			}
		}
		else
		{
			$template = $this->_view->createTemplateObject(
				'sonnb_xengallery_bbcode_video',
				array(
					'content' => $video,
					'tag' => $tag
				)
			);

			return $template->render();
		}
	}

	public function renderTagXenAlbum(array $tag, array $rendererStates)
	{
		$albumId = trim($this->stringifyTree($tag['children']));
		$albumId = intval($albumId);
		$albumModel = $this->_getAlbumModel();
		$galleryModel = $this->_getGalleryModel();

		if (!$albumId || !$galleryModel->canViewGallery())
		{
			return '';
		}

		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO |
				sonnb_XenGallery_Model_Photo::FETCH_USER,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id']
		);

		$album = $albumModel->getAlbumById($albumId, $fetchOptions);
		$album = $albumModel->prepareAlbum($album, $fetchOptions);

		if (!$album || !$albumModel->canViewAlbum($album, $errorPhraseKey) || !$album['content_count'])
		{
			return '';
		}

		$album = $albumModel->attachCoverToAlbum($album, $fetchOptions);

		$thumbnail = false;

		if (!empty($album['cover']))
		{
			switch ($tag['option'])
			{
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL:
					$thumbnail = $album['cover']['thumbnailSmall'];
					break;
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE:
					$thumbnail = $album['cover']['contentUrl'];
					break;
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM:
				default:
					$thumbnail = $album['cover']['thumbnailUrl'];
					break;
			}
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject(
				'sonnb_xengallery_bbcode_album',
				array(
					'album' => $album,
					'tag' => $tag,
					'thumbnail' => $thumbnail
				)
			);

			return $template->render();
		}
		else
		{
			$templateImage = "<a style='display: inline-block;' class='thumbnail' href='%s' title='%s'><img src='%s' /></a>";
			$templateTitle = '<div class="detail"><div class="title"><a href="%s">%s</a></div><span class="caption muted"><a class="username" href="%s">%s</a></span><div class="description muted">%s</div>';
			$output = '<div class="bbcodeAlbum"><div class="clearfix"><div class="title">';

			$output .= sprintf($templateImage, XenForo_Link::buildPublicLink('gallery/albums', $album), $album['title'], $thumbnail);

			$output .= '</span></div></div>';

			$output .= sprintf($templateTitle, XenForo_Link::buildPublicLink('gallery/albums', $album), $album['title'], XenForo_Link::buildPublicLink('members', $album), $album['username'], $album['description']);

			$output .= '</div>';

			return $output;
		}
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_Photo');
	}

	/**
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_Video');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return XenForo_Model::create('sonnb_XenGallery_Model_Gallery');
	}
}