<?php

abstract class XenGallery_Thumbnail_Abstract
{
	protected $_thumbnailUrl = '';
	protected $_thumbnailPath = null;

	protected $_mediaSiteId = null;
	protected $_videoId = null;

	abstract public function getThumbnailUrl($videoId);

	protected function __construct() {}

	public static function create($class)
	{
		$class = XenForo_Application::resolveDynamicClass($class);

		$object = new $class();
		if (!($object instanceof XenGallery_Thumbnail_Abstract))
		{
			return false;
		}
		return $object;
	}

	public static function saveThumbnailFromPath($mediaSiteId, $videoId, $path)
	{
		$videoId = XenGallery_Helper_String::cleanVideoId($videoId);
		$mediaSiteId = preg_replace('#[^a-zA-Z0-9_]#', '', $mediaSiteId);

		if (!$mediaSiteId
			|| !$videoId
			|| !$path
		)
		{
			return false;
		}

		$options = XenForo_Application::getOptions();

		$thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $mediaSiteId;
		XenForo_Helper_File::createDirectory($thumbnailPath, true);

		try
		{
			$fullThumbnailPath = $thumbnailPath . '/' . $mediaSiteId . '_' . $videoId . '.jpg';
			copy($path, $fullThumbnailPath);
		}
		catch (Exception $e)
		{
			return false;
		}

		$image = new XenGallery_Helper_Image($fullThumbnailPath);
		$image->resize($options->xengalleryThumbnailDimension['width'], $options->xengalleryThumbnailDimension['height'], 'crop');

		return $image->save($mediaSiteId . '_' . $videoId . '_thumb', $thumbnailPath, 'jpg');
	}

	public static function saveThumbnailFromUrl($mediaSiteId, $videoId, $url)
	{
		$videoId = XenGallery_Helper_String::cleanVideoId($videoId);
		$mediaSiteId = preg_replace('#[^a-zA-Z0-9_]#', '', $mediaSiteId);

		if (!$mediaSiteId
			|| !$videoId
			|| !$url
		)
		{
			return false;
		}

		$options = XenForo_Application::getOptions();

		$thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $mediaSiteId;
		XenForo_Helper_File::createDirectory($thumbnailPath, true);

		try
		{
			$fullThumbnailPath = $thumbnailPath . '/' . $mediaSiteId . '_' . $videoId . '.jpg';
			$client = XenForo_Helper_Http::getClient($url);

			$fp = fopen($fullThumbnailPath, 'w');

			fwrite($fp, $client->request('GET')->getBody());
			fclose($fp);
		}
		catch (Zend_Http_Client_Exception $e)
		{
			return false;
		}

		$image = new XenGallery_Helper_Image($fullThumbnailPath);
		$image->resize($options->xengalleryThumbnailDimension['width'], $options->xengalleryThumbnailDimension['height'], 'crop');

		return $image->save($mediaSiteId . '_' . $videoId . '_thumb', $thumbnailPath, 'jpg');
	}

	/**
	 * Saves a thumbnail locally
	 *
	 * @param $thumbnailUrl
	 */
	public function saveThumbnail()
	{
		$this->_videoId = XenGallery_Helper_String::cleanVideoId($this->_videoId);
		$this->_mediaSiteId = preg_replace('#[^a-zA-Z0-9_]#', '', $this->_mediaSiteId);

		if (!$this->_mediaSiteId || !$this->_videoId || !$this->_thumbnailUrl)
		{
			return false;
		}

		$options = XenForo_Application::getOptions();

		$this->_thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $this->_mediaSiteId;
		try
		{
			$thumbnailPath = $this->_thumbnailPath . '/' . $this->_mediaSiteId . '_' . $this->_videoId . '.jpg';
			$client = XenForo_Helper_Http::getClient($this->_thumbnailUrl);

			XenForo_Helper_File::createDirectory(dirname($thumbnailPath), true);
			$fp = @fopen($thumbnailPath, 'w');

			if (!$fp)
			{
				return false;
			}

			fwrite($fp, $client->request('GET')->getBody());
			fclose($fp);
		}
		catch (Zend_Http_Client_Exception $e)
		{
			return false;
		}

		$image = new XenGallery_Helper_Image($thumbnailPath);
		$image->resize($options->xengalleryThumbnailDimension['width'], $options->xengalleryThumbnailDimension['height'], 'crop');

		return $image->save($this->_mediaSiteId . '_' . $this->_videoId . '_thumb', $this->_thumbnailPath, 'jpg');
	}

	public function verifyThumbnailUrl($url)
	{
		if ($url)
		{
			$client = XenForo_Helper_Http::getClient($url);
			$response = $client->request('GET');

			if ($response->isSuccessful())
			{
				return $this->saveThumbnail();
			}
		}

		$options = XenForo_Application::getOptions();

		$this->_thumbnailUrl = $options->boardUrl . '/' . $options->xengalleryDefaultNoThumb;

		return $this->saveThumbnail();
	}
}