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
class sonnb_XenGallery_Model_VideoData extends sonnb_XenGallery_Model_ContentData
{
	protected static $_siteApi = array(
		'youtube' => "https://gdata.youtube.com/feeds/api/videos/{video_key}?v=2&alt=json",
		'dailymotion' => "https://api.dailymotion.com/video/{video_key}?fields=title,description,thumbnail_large_url,duration",
		'facebook' => "https://graph.facebook.com/{video_key}/picture",
		'liveleak' => "http://www.liveleak.com/view?i={video_key}&ajax=1",
		'metacafe' => "http://www.metacafe.com/api/item/{video_key}/",
		'vimeo' => "http://vimeo.com/api/v2/video/{video_key}.json"
	);

	protected function _getDefaultImage($tempFile)
	{
		$success = @copy(sonnb_XenGallery_Model_VideoData::$videoNoThumbnail, $tempFile);

		return $success;
	}

	public function insertEmbedVideoData($data, $extra = array())
	{
		$mediumThumbFile = false;
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

		if (empty($data['thumbnailUrl']))
		{
			$this->_getDefaultImage($tempFile);
		}
		else
		{
			try
			{
				$client = XenForo_Helper_Http::getClient($data['thumbnailUrl']);
				$response = $client->request('GET');

				if ($response->isSuccessful())
				{
					@file_put_contents($tempFile, $response->getBody());
				}
				else
				{
					$this->_getDefaultImage($tempFile);
				}
			}
			catch (Exception $e)
			{
				$this->_getDefaultImage($tempFile);
			}
		}

		$tempFileInfo = @getimagesize($tempFile);

		if (empty($tempFileInfo))
		{
			$this->_getDefaultImage($tempFile);
			$tempFileInfo = @getimagesize($tempFile);
		}

		if (!empty($tempFileInfo))
		{
			$dimensions = array();

			$smallThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$mediumThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$largeThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

			$smallSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_SMALL);
			$mediumSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_MEDIUM);
			$largeSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_LARGE);

			if ($smallThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($tempFile, $tempFileInfo[2]);
				if ($image)
				{
					if ($image->thumbnail($smallSize*2, $smallSize*2))
					{
						$x = floor(($image->getWidth() - $smallSize) /2);
						$y = floor(($image->getHeight() - $smallSize) /2);
						$image->crop($x, $y, $smallSize, $smallSize);
						$image->output(IMAGETYPE_JPEG, $smallThumbFile, 100);

						$dimensions['small_width'] = $image->getWidth();
						$dimensions['small_height'] = $image->getHeight();
					}
					else
					{
						copy($tempFile, $smallThumbFile);

						$dimensions['small_width'] = $image->getWidth();
						$dimensions['small_height'] = $image->getHeight();
					}

					unset($image);
				}
			}

			if ($mediumThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($tempFile, $tempFileInfo[2]);
				if ($image)
				{
					$realWidth = $tempFileInfo[0];
					$realHeight = $tempFileInfo[1];

					$resizeWidth = $mediumSize;
					$resizeHeight = $mediumSize*2;

					if ($realWidth*($resizeHeight/$realHeight) < $mediumSize)
					{
						$resizeHeight = $realHeight*($resizeWidth/$realWidth);
					}

					if ($image->thumbnail($resizeWidth, $resizeHeight))
					{
						$image->output(IMAGETYPE_JPEG, $mediumThumbFile, 100);

						$dimensions['medium_width'] = $image->getWidth();
						$dimensions['medium_height'] = $image->getHeight();
					}
					else
					{
						copy($tempFile, $mediumThumbFile);

						$dimensions['medium_width'] = $image->getWidth();
						$dimensions['medium_height'] = $image->getHeight();
					}

					unset($image);
				}
			}

			if ($largeThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($tempFile, $tempFileInfo[2]);
				if ($image)
				{
					if ($image->thumbnail($largeSize, $largeSize))
					{
						$image->output(IMAGETYPE_JPEG, $largeThumbFile, 100);

						$dimensions['large_width'] = $image->getWidth();
						$dimensions['large_height'] = $image->getHeight();
					}
					else
					{
						copy($tempFile, $largeThumbFile);

						$dimensions['large_width'] = $image->getWidth();
						$dimensions['large_height'] = $image->getHeight();
					}

					unset($image);
				}
			}
		}

		try
		{
			$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
			$dataDw->bulkSet($extra);
			$dataDw->bulkSet(array(
				'duration' => isset($data['duration']) ? $data['duration'] : 0,
				'unassociated' => 1,
				'extension' => '',
				'file_size' => 0,
				'file_hash' => '0'
			));
			if (isset($dimensions))
			{
				$dataDw->set('extension', sonnb_XenGallery_Model_ContentData::$extensionMap[IMAGETYPE_JPEG]);
				$dataDw->bulkSet($dimensions);

				if ($smallThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_SMALL_THUMB_FILE, $smallThumbFile);
				}
				if ($mediumThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_MEDIUM_THUMB_FILE, $mediumThumbFile);
				}
				if ($largeThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_LARGE_THUMB_FILE, $largeThumbFile);
				}
			}
			$dataDw->save();

			$videoData = $dataDw->getMergedData();

			if ($smallThumbFile)
			{
				@unlink($smallThumbFile);
			}
			if ($mediumThumbFile)
			{
				@unlink($mediumThumbFile);
			}
			if ($largeThumbFile)
			{
				@unlink($largeThumbFile);
			}

			return array_merge($videoData, $data);
		}
		catch (Exception $e)
		{
			if ($smallThumbFile)
			{
				@unlink($smallThumbFile);
			}
			if ($mediumThumbFile)
			{
				@unlink($mediumThumbFile);
			}
			if ($largeThumbFile)
			{
				@unlink($largeThumbFile);
			}

			throw $e;
		}

		return false;
	}

	public function parseMediaUrl($url)
	{
		foreach ($this->_getAllBbCodeMediaSites() AS $siteId => $site)
		{
			foreach ($site['regexes'] AS $regex)
			{
				if (preg_match($regex, $url, $matches))
				{
					if (!$mediaId = $this->_getMediaKeyFromCallback($url, $matches['id'], $site))
					{
						$mediaId = $matches['id'];
					}

					return array_merge(array(
						'video_type' => $siteId,
						'video_key' => $mediaId
					), $this->getEmbedVideoData($siteId, $mediaId));
				}
			}
		}

		return false;
	}

	public function getEmbedVideoData($type, $key)
	{
		if (!isset(self::$_siteApi[$type]))
		{
			return array();
		}

		$url = str_replace("{video_key}", $key, self::$_siteApi[$type]);

		if ($type === 'facebook')
		{
			return array(
				'title' => '',
				'thumbnailUrl' => $url,
				'description' => '',
				'duration' => 0
			);
		}
		elseif ($type === 'metacafe')
		{
			$key = explode('/', $key);
			$key = $key[0];
			$url = str_replace("{video_key}", $key, self::$_siteApi[$type]);
		}

		try
		{
			$client = XenForo_Helper_Http::getClient($url);
			$response = $client->request('GET');

			$function = "_".$type;

			$return =  $this->$function($response->getBody());

			if (isset($return['title']))
			{
				$return['title'] = strip_tags($return['title']);
			}

			if (isset($return['description']))
			{
				$return['description'] = strip_tags($return['description']);
			}

			return $return;
		}
		catch(Exception $e)
		{
			return array();
		}
	}

	protected function _youtube($data)
	{
		$data = @json_decode($data, true);

		$return = array();
		if (!empty($data))
		{
			$return['title'] = isset($data['entry']['media$group']['media$title']) ? $data['entry']['media$group']['media$title']['$t'] : '';
			$return['thumbnailUrl'] = isset($data['entry']['media$group']['media$thumbnail'][2]) ? $data['entry']['media$group']['media$thumbnail'][2]['url'] : '';
			$return['description'] = isset($data['entry']['media$group']['media$description']) ? $data['entry']['media$group']['media$description']['$t'] : '';
			$return['duration'] = isset($data['entry']['media$group']['yt$duration']) ? $data['entry']['media$group']['yt$duration']['seconds'] : 0;
		}

		return $return;
	}

	protected function _dailymotion($data)
	{
		$data = @json_decode($data, true);

		$return = array();
		if (!empty($data))
		{
			$return['title'] = isset($data['title']) ? $data['title'] : '';
			$return['thumbnailUrl'] = isset($data['thumbnail_large_url']) ? $data['thumbnail_large_url'] : '';
			$return['description'] = isset($data['description']) ? $data['description'] : '';
			$return['duration'] = isset($data['duration']) ? $data['duration'] : 0;
		}

		return $return;
	}

	protected function _liveleak($data)
	{
		$return = array();
		if (!empty($data))
		{
			if (preg_match('#<span[^>]*?\bclass="section_title"[^>]*+>(.*)<\/span>#i', $data, $matchTitle))
			{
				$return['title'] = $matchTitle[1];
			}
			else
			{
				$return['title'] = '';
			}

			if (preg_match('#<div[^>]*?\bid="body_text"[^>]*+>(.*)<\/div>#i', $data, $matchDescription))
			{
				$return['description'] = $matchDescription[1];
			}
			else
			{
				$return['description'] = '';
			}

			if (preg_match('#image:(.*?)"(.*?)"#i', $data, $matchThumbnail))
			{
				$return['thumbnailUrl'] = $matchThumbnail[2];
			}
			else
			{
				$return['thumbnailUrl'] = '';
			}

			$return['duration'] = 0;
		}

		return $return;
	}

	protected function _metacafe($data)
	{
		$return = array();
		if (!empty($data))
		{
			$xml = new SimpleXMLElement($data);
			$title = $xml->xpath( "/rss/channel/item/media:title" );
			$description = $xml->xpath( "/rss/channel/item/media:description" );
			$thumbnailUrl = $xml->xpath( "/rss/channel/item/media:thumbnail/@url" );
			$duration = $xml->xpath( "/rss/channel/item/media:content/@duration" );

			$return['title'] = (string) $title[0];
			$return['description'] = (string) $description[0];
			$return['thumbnailUrl'] = (string) $thumbnailUrl[0]['url'];
			$return['duration'] = intval($duration[0]['duration']);
		}

		return $return;
	}

	protected function _vimeo($data)
	{
		$data = @json_decode($data, true);

		$return = array();
		if (!empty($data))
		{
			$return['title'] = isset($data[0]['title']) ? $data[0]['title'] : '';
			$return['thumbnailUrl'] = isset($data[0]['thumbnail_large']) ? $data[0]['thumbnail_large'] : '';
			$return['description'] = isset($data[0]['description']) ? $data[0]['description'] : '';
			$return['duration'] = isset($data[0]['duration']) ? $data[0]['duration'] : 0;
		}

		return $return;
	}

	protected function _getMediaKeyFromCallback($url, $matchedId, array $site)
	{
		if (!empty($site['match_callback_class']) && !empty($site['match_callback_method']))
		{
			$class = $site['match_callback_class'];
			$method = $site['match_callback_method'];

			if (XenForo_Application::autoload($class) && method_exists($class, $method))
			{
				return call_user_func_array(array($class, $method), array($url, $matchedId, $site));
			}
		}

		return false;
	}

	protected function _getAllBbCodeMediaSites()
	{
		$bbCodeModel = $this->_getBbCodeModel();

		$bbCodeMediaSites = $bbCodeModel->getAllBbCodeMediaSites();

		foreach ($bbCodeMediaSites AS $siteId => &$site)
		{
			$site['regexes'] = $bbCodeModel->convertMatchUrlsToRegexes($site['match_urls'], $site['match_is_regex']);
		}

		return $bbCodeMediaSites;
	}

	public function uploadVideoThumbnail(XenForo_Upload $upload, array $video)
	{
		if (!$video)
		{
			return false;
		}

		if (!$upload->isValid())
		{
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage())
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$baseTempFile = $upload->getTempFile();

		$imageType = $upload->getImageInfoField('type');
		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');

		return $this->applyVideoThumbnail($video, $baseTempFile, $imageType, $width, $height);
	}

	public function applyVideoThumbnail(array $video, $fileName, $imageType = false, $width = false, $height = false)
	{
		if (!$imageType || !$width || !$height)
		{
			$imageInfo = getimagesize($fileName);
			if (!$imageInfo)
			{
				throw new XenForo_Exception('Non-image passed in to applyAvatar');
			}
			$width = $imageInfo[0];
			$height = $imageInfo[1];
			$imageType = $imageInfo[2];
		}

		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		if (!$this->_getPhotoModel()->canResizeImage($width, $height))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_image_is_too_big'), true);
		}

		$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
		if (!$image)
		{
			return false;
		}

		$dimensions = array();

		$smallThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
		$mediumThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
		$largeThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

		$smallSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_SMALL);
		$mediumSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_MEDIUM);
		$largeSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_LARGE);

		if ($smallThumbFile)
		{
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if ($image)
			{
				if ($image->thumbnail($smallSize*2, $smallSize*2))
				{
					$x = floor(($image->getWidth() - $smallSize) /2);
					$y = floor(($image->getHeight() - $smallSize) /2);
					$image->crop($x, $y, $smallSize, $smallSize);
					$image->output(IMAGETYPE_JPEG, $smallThumbFile, 100);

					$dimensions['small_width'] = $image->getWidth();
					$dimensions['small_height'] = $image->getHeight();
				}
				else
				{
					copy($fileName, $smallThumbFile);

					$dimensions['small_width'] = $image->getWidth();
					$dimensions['small_height'] = $image->getHeight();
				}

				unset($image);
			}
		}

		if ($mediumThumbFile)
		{
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if ($image)
			{
				$resizeWidth = $mediumSize;
				$resizeHeight = $mediumSize*2;

				if ($width*($resizeHeight/$height) < $mediumSize)
				{
					$resizeHeight = $height*($resizeWidth/$width);
				}

				if ($image->thumbnail($resizeWidth, $resizeHeight))
				{
					$image->output(IMAGETYPE_JPEG, $mediumThumbFile, 100);

					$dimensions['medium_width'] = $image->getWidth();
					$dimensions['medium_height'] = $image->getHeight();
				}
				else
				{
					copy($fileName, $mediumThumbFile);

					$dimensions['medium_width'] = $image->getWidth();
					$dimensions['medium_height'] = $image->getHeight();
				}

				unset($image);
			}
		}

		if ($largeThumbFile)
		{
			$image = XenForo_Image_Abstract::createFromFile($fileName, $imageType);
			if ($image)
			{
				if ($image->thumbnail($largeSize, $largeSize))
				{
					$image->output(IMAGETYPE_JPEG, $largeThumbFile, 100);

					$dimensions['large_width'] = $image->getWidth();
					$dimensions['large_height'] = $image->getHeight();
				}
				else
				{
					copy($fileName, $largeThumbFile);

					$dimensions['large_width'] = $image->getWidth();
					$dimensions['large_height'] = $image->getHeight();
				}

				unset($image);
			}
		}

		try
		{
			$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
			if ($video['content_data_id'])
			{
				$dataDw->setExistingData($video['content_data_id']);
			}
			else
			{
				$dataDw->bulkSet(array(
					'content_type' => sonnb_XenGallery_Model_Video::$contentType,
					'temp_hash' => '',
					'duration' => 0,
					'unassociated' => 0,
					'extension' => '',
					'file_size' => 0,
					'file_hash' => '0'
				));
			}

			if (isset($dimensions))
			{
				if (!$dataDw->get('extension'))
				{
					$dataDw->set('extension', sonnb_XenGallery_Model_ContentData::$extensionMap[IMAGETYPE_JPEG]);
				}

				$dataDw->bulkSet($dimensions);

				if ($smallThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_SMALL_THUMB_FILE, $smallThumbFile);
				}
				if ($mediumThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_MEDIUM_THUMB_FILE, $mediumThumbFile);
				}
				if ($largeThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_LARGE_THUMB_FILE, $largeThumbFile);
				}
			}
			$dataDw->save();

			$videoData = $dataDw->getMergedData();

			@unlink($fileName);

			if ($smallThumbFile)
			{
				@unlink($smallThumbFile);
			}
			if ($mediumThumbFile)
			{
				@unlink($mediumThumbFile);
			}
			if ($largeThumbFile)
			{
				@unlink($largeThumbFile);
			}

			$db = $this->_getDb();
			$db->update(
				'sonnb_xengallery_content',
				array(
					'content_data_id' => $videoData['content_data_id'],
					'content_updated_date' => XenForo_Application::$time
				),
				'content_id = '.$video['content_id']
			);
		}
		catch (Exception $e)
		{
			@unlink($fileName);

			if ($smallThumbFile)
			{
				@unlink($smallThumbFile);
			}
			if ($mediumThumbFile)
			{
				@unlink($mediumThumbFile);
			}
			if ($largeThumbFile)
			{
				@unlink($largeThumbFile);
			}

			throw $e;
		}
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}
