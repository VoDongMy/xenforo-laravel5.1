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
abstract class sonnb_XenGallery_Importer_Abstract extends XenForo_Importer_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Import
	 */
	protected $_importModel;

	protected $_sourceDb;

	protected $_prefix;

	protected $_config;

	protected $_charset = 'windows-1252';

	public function __construct()
	{
		@set_time_limit(0);
		ignore_user_abort(true);

		$this->_db = XenForo_Application::getDb();
		$this->_importModel = XenForo_Model::create('sonnb_XenGallery_Model_Import');

		$this->_db->setProfiler(false);
	}

	public function retainKeysReset()
	{
		//Do nothing just in case the importer reach into this function which is not possible.
		return true;
	}

	protected function _mbTrim($string, $len = 0, $id = 0)
	{
		if (utf8_strlen($string) <= $len)
		{
			return $string;
		}

		if ($len < 2)
		{
			throw new XenForo_Exception('Multi-byte Trim Parameter Error : Len '.$len);
		}

		if ($id)
		{
			$string = $id.'#'.$string;
		}

		return utf8_substr($string,0,$len);
	}

	public function getSteps()
	{
		return array(
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums')
			),
			'photos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_photos'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('photos')
			)
		);
	}

	protected function _createFiles(sonnb_XenGallery_Model_ContentData $model, $filePath, $contentData, $useTemp = true, $isVideo = false)
	{
		$smallThumbFile = $model->getContentDataSmallThumbnailFile($contentData);
		$mediumThumbFile = $model->getContentDataMediumThumbnailFile($contentData);
		$largeThumbFile = $model->getContentDataLargeThumbnailFile($contentData);
		$originalFile = $model->getContentDataFile($contentData);

		if ($useTemp)
		{
			$filename = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			@copy($filePath, $filename);
		}
		else
		{
			$filename = $filePath;
		}

        if ($isVideo === false && $originalFile)
        {
            $directory = dirname($originalFile);
            if (XenForo_Helper_File::createDirectory($directory, true))
            {
                @copy($filename, $originalFile);
                XenForo_Helper_File::makeWritableByFtpUser($originalFile);
            }
            else
            {
                return false;
            }
        }

		if ($isVideo === false)
		{
			$ext = sonnb_XenGallery_Model_ContentData::$typeMap[$contentData['extension']];
		}
		else
		{
			$ext = sonnb_XenGallery_Model_ContentData::$typeMap[sonnb_XenGallery_Model_VideoData::$videoEmbedExtension];
		}

        $model->createContentDataThumbnailFile($filename, $largeThumbFile, $ext, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE);
        $model->createContentDataThumbnailFile($largeThumbFile, $mediumThumbFile, $ext, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM);
        $model->createContentDataThumbnailFile($largeThumbFile, $smallThumbFile, $ext, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL);

		@unlink($filename);

		return true;
	}

	protected function _createPhotoFiles($filePath, $photoData, $useTemp = true)
	{
		/* @var $photoDataModel sonnb_XenGallery_Model_PhotoData */
		$photoDataModel = XenForo_Model::create('sonnb_XenGallery_Model_PhotoData');

		return $this->_createFiles($photoDataModel, $filePath, $photoData, $useTemp);
	}

	protected function _createVideoFiles($filePath, $videoData, $useTemp = true)
	{
		/* @var $videoDataModel sonnb_XenGallery_Model_VideoData */
		$videoDataModel = XenForo_Model::create('sonnb_XenGallery_Model_VideoData');

		return $this->_createFiles($videoDataModel, $filePath, $videoData, $useTemp, true);
	}
}