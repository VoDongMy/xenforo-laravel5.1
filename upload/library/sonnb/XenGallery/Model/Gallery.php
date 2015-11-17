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
class sonnb_XenGallery_Model_Gallery extends sonnb_XenGallery_Model_Abstract
{
	public function groupContentsContentType(array $items, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (empty($items))
		{
			return $items;
		}

		$fetchQueue = array();
		foreach ($items as &$_item)
		{
			if (in_array($_item['content_type'], array('photo', 'video')))
			{
				$_item['content_type'] = 'content';
			}

			$fetchQueue[$_item['content_type']][$_item['content_id']] = $_item['content_id'];
		}

		foreach ($fetchQueue AS $handlerClass => $contentIds)
		{
			$fetchData[$handlerClass] = sonnb_XenGallery_ContentHandler_Abstract::create($handlerClass)->getContentsByIds(
				$contentIds, $viewingUser
			);
		}

		foreach ($items AS $id => $item)
		{
			if (!isset($fetchData[$item['content_type']][$item['content_id']]))
			{
				unset($items[$id]);
				continue;
			}

			if (!sonnb_XenGallery_ContentHandler_Abstract::create($item['content_type'])->canViewContent(
				$fetchData[$item['content_type']][$item['content_id']], $viewingUser
			))
			{
				unset($items[$id]);
				continue;
			}

			$items[$id]['content'] = $fetchData[$item['content_type']][$item['content_id']];
		}

		return $items;
	}
	
	public function canViewGallery(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewGallery');
	}
	
	public function canUpload(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'canUpload');
	}

	public function canEmbedVideo(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'canEmbedVideo');
	}

	public function canCustomizeWatermark(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		$xenOptions = XenForo_Application::getOptions();
		$watermark = $xenOptions->sonnbXG_watermark;

		return !empty($watermark['overlay'])
					&& $watermark['overlay'] === 'text'
					&& !empty($watermark['override'])
					&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'customizeWatermark');
	}

	public function canManageCover(array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] === $viewingUser['user_id'])
		{
			return true;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'manageCover');
	}

	public function getMaximumStreamCount(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'maximumStreams');
	}
	
	public function isMobileDevice()
	{
		return XenForo_Visitor::isBrowsingWith('mobile');
	}

    public function includeJsTagger()
    {
        return XenForo_Application::$versionId >= 1020000 && XenForo_Application::$versionId < 1030000;
    }
	
	public function isMobileiOS()
	{
		if (!$this->isMobileDevice())
		{
			return false;
		}

		if (XenForo_Visitor::isBrowsingWith('webkit'))
		{
			if (!isset($_SERVER['HTTP_USER_AGENT']))
			{
				return false;
			}

			if (preg_match('/(iPhone.*Mobile|iPod.*Mobile|iPad.*Mobile)/is', $_SERVER['HTTP_USER_AGENT'], $matchiOS))
			{
				if (preg_match('/OS ((\d+_?){2,3})\s/', $_SERVER['HTTP_USER_AGENT'], $matchVersion))
				{
					if (intval($matchVersion[1] < 6))
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function getTitleForUrl($location)
	{
		$location = trim($location);

		$aPattern = array (
			"a" => "á|à|ạ|ả|ã|ă|ắ|ằ|ặ|ẳ|ẵ|â|ấ|ầ|ậ|ẩ|ẫ|Á|À|Ạ|Ả|Ã|Ă|Ắ|Ằ|Ặ|Ẳ|Ẵ|Â|Ấ|Ầ|Ậ|Ẩ|Ẫ",
			"o" => "ó|ò|ọ|ỏ|õ|ô|ố|ồ|ộ|ổ|ỗ|ơ|ớ|ờ|ợ|ở|ỡ|Ó|Ò|Ọ|Ỏ|Õ|Ô|Ố|Ồ|Ộ|Ổ|Ỗ|Ơ|Ớ|Ờ|Ợ|Ở|Ỡ",
			"e" => "é|è|ẹ|ẻ|ẽ|ê|ế|ề|ệ|ể|ễ|É|È|Ẹ|Ẻ|Ẽ|Ê|Ế|Ề|Ệ|Ể|Ễ",
			"u" => "ú|ù|ụ|ủ|ũ|ư|ứ|ừ|ự|ử|ữ|Ú|Ù|Ụ|Ủ|Ũ|Ư|Ứ|Ừ|Ự|Ử|Ữ",
			"i" => "í|ì|ị|ỉ|ĩ|Í|Ì|Ị|Ỉ|Ĩ",
			"y" => "ý|ỳ|ỵ|ỷ|ỹ|Ý|Ỳ|Ỵ|Ỷ|Ỹ",
			"d" => "đ|Đ",
		);

		while(list($key,$value) = each($aPattern))
		{
			$location = preg_replace('/'.$value.'/i', $key, $location);
		}

		$location = XenForo_Link::getTitleForUrl($location);

		return $location;
	}

	public function uploadAuthorCover(XenForo_Upload $cover, $userId)
	{
		if (!$userId)
		{
			return false;
		}

		if (!$cover->isValid())
		{
			throw new XenForo_Exception($cover->getErrors(), true);
		}

		if (!$cover->isImage())
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$imageType = $cover->getImageInfoField('type');
		if (!isset(sonnb_XenGallery_Model_ContentData::$extensionMap[$imageType]))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$baseTempFile = $cover->getTempFile();
		$width = $cover->getImageInfoField('width');
		$height = $cover->getImageInfoField('height');

		return $this->applyAuthorCover($baseTempFile, $userId, $imageType, $width, $height);
	}

	public function applyAuthorCover($tempFile, $userId, $imageType = null, $width = null, $height = null)
	{
		if (!$imageType || !$width || !$height)
		{
			$imageInfo = @getimagesize($tempFile);
			if (!$imageInfo)
			{
				throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
			}

			$width = $imageInfo[0];
			$height = $imageInfo[1];
			$imageType = $imageInfo[2];
		}

		if (!$this->_getPhotoModel()->canResizeImage($width, $height))
		{
			throw new XenForo_Exception(new XenForo_Phrase('sonnb_xengallery_your_image_is_too_big_cannot_set_cover'), true);
		}

		$xenOptions = XenForo_Application::getOptions();
		$useAttachmentStore = $xenOptions->sonnbXG_useBdStore;

		$engine = $storeOptions = array();

		if (!class_exists('bdAttachmentStore_Option'))
		{
			$useAttachmentStore = false;
		}

		if ($useAttachmentStore && empty($engine))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$defaultEngine = $fileModel->getDefaultEngine();

			if ($defaultEngine === bdAttachmentStore_Option::MODE_ATTACHMENT
				|| $defaultEngine === bdAttachmentStore_Option::MODE_EXTERNAL_DATA)
			{
				$defaultEngine = '';
			}

			if (!empty($defaultEngine))
			{
				$engine = $defaultEngine;
			}
		}

		if (!empty($engine))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$storeOptions = $fileModel->getStorageOptions($engine);

			if (!isset($storeOptions['keepLocalCopy']))
			{
				$storeOptions['keepLocalCopy'] = bdAttachmentStore_Option::get('keepLocalCopy');
			}
		}

		$user = array(
			'user_id' => $userId,

			'sonnb_xengallery_cover' => array(
				'bdattachmentstore_engine' => $engine,
				'bdattachmentstore_options' => $storeOptions,

				'upload_date' => XenForo_Application::$time,
				'extension' => sonnb_XenGallery_Model_ContentData::$extensionMap[$imageType],

				'width' => $width,
				'height' => $height
			)
		);

		$outputFile = $this->_getGalleryModel()->getAuthorCoverFile($user, true);
		$directory = dirname($outputFile);

		$success = false;
		if (!empty($engine))
		{
			$filePathStore = $this->getAuthorCoverStoreFile($user);
			$success = $this->_bdAttachmentStore_getFileModel()->saveFile($engine, $storeOptions, $tempFile, $filePathStore, basename($filePathStore));
		}

		if (empty($engine) || (!empty($engine) && $storeOptions['keepLocalCopy']))
		{
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$success = @copy($tempFile, $outputFile);
			}
		}

		if ($tempFile)
		{
			@unlink($tempFile);
		}

		if ($success)
		{
			$db = $this->_getDb();
			$db->update(
				'xf_user',
				array(
					'sonnb_xengallery_cover' => serialize(array(
						'bdattachmentstore_engine' => $engine,
						'bdattachmentstore_options' => $storeOptions,
						'upload_date' => $user['sonnb_xengallery_cover']['upload_date'],
						'extension' => sonnb_XenGallery_Model_ContentData::$extensionMap[$imageType]
					))
				)
				, 'user_id = '.$userId
			);

			return $user['sonnb_xengallery_cover'];
		}

		return false;
	}

	public function cropAuthorCover(array $user, $x, $y, $relativeWidth, $relativeHeight)
	{
		if (empty($user['sonnb_xengallery_cover']['upload_date']))
		{
			return;
		}

		$newUser = $user;
		$newUser['sonnb_xengallery_cover']['upload_date'] = XenForo_Application::$time;
		$outputFile = $this->_getGalleryModel()->getAuthorCoverFile($newUser);

		$file = $this->_getGalleryModel()->getAuthorCoverFile($user);
		$imageInfo = @getimagesize($file);
		if (!$imageInfo)
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$imageType = $imageInfo[2];

		if (!$this->_getPhotoModel()->canResizeImage($imageInfo[0], $imageInfo[1]))
		{
			throw new XenForo_Exception(new XenForo_Phrase('sonnb_xengallery_your_cover_is_too_big_cannot_crop'), true);
		}

		$image = XenForo_Image_Abstract::createFromFile($file, $imageType);
		if (!$image)
		{
			return false;
		}

		$image->thumbnail($relativeWidth);

		if ($image->getHeight() > $relativeHeight)
		{
			$cropWidth = $relativeWidth;
			if ($relativeWidth > $image->getWidth())
			{
				$cropWidth = $image->getWidth();
			}

			$cropHeight = $relativeHeight;
			if ($cropHeight > $image->getWidth())
			{
				$cropHeight = $image->getHeight();
			}

			$image->crop($x, $y, $cropWidth, $cropHeight);
		}

		if (isset($newUser['sonnb_xengallery_cover']['bdattachmentstore_engine']))
		{
			$engine = $newUser['sonnb_xengallery_cover']['bdattachmentstore_engine'];
			$options = $newUser['sonnb_xengallery_cover']['bdattachmentstore_options'];
			$keepLocalCopy = !empty($options['keepLocalCopy']);
		}
		else
		{
			$engine = '';
			$options = array();
		}

		$success = $image->output($imageType, $outputFile, 100);

		if ($success)
		{
			$newData = array(
				'bdattachmentstore_engine' => $engine,
				'bdattachmentstore_options' => $options,
				'upload_date' => $newUser['sonnb_xengallery_cover']['upload_date'],
				'extension' => sonnb_XenGallery_Model_ContentData::$extensionMap[$imageType]
			);

			if (empty($engine) || (!empty($engine) && $keepLocalCopy))
			{
				$oldLocalCover = $this->_getGalleryModel()->getAuthorCoverFile($user, true);
				@unlink($oldLocalCover);

				$newLocalCover = $this->_getGalleryModel()->getAuthorCoverFile($newUser, true);
				$directory = dirname($newLocalCover);

				if (XenForo_Helper_File::createDirectory($directory, true))
				{
					copy($outputFile, $newLocalCover);

					if (!empty($engine))
					{
						@unlink($outputFile);
					}
				}
			}

			if (!empty($engine))
			{
				$filePath = $this->getAuthorCoverStoreFile($newUser);
				$this->_bdAttachmentStore_getFileModel()->saveFile($engine, $options, $outputFile, $filePath, basename($filePath));

				if (!$keepLocalCopy)
				{
					@unlink($outputFile);
				}
			}

			$db = $this->_getDb();
			$db->update(
				'xf_user',
				array(
					'sonnb_xengallery_cover' => serialize($newData)
				)
				, 'user_id = '.$user['user_id']
			);

			return $newData;
		}

		unset($image);

		return false;
	}

	public function deleteAuthorCover($user)
	{
		$db = $this->_getDb();
		$db->update('xf_user', array('sonnb_xengallery_cover' => 'a:0:{}'), 'user_id = '.$user['user_id']);

		$oldCover = $this->_getGalleryModel()->getAuthorCoverFile($user);

		if (isset($newUser['sonnb_xengallery_cover']['bdattachmentstore_engine']))
		{
			$engine = $user['sonnb_xengallery_cover']['bdattachmentstore_engine'];
			$options = $user['sonnb_xengallery_cover']['bdattachmentstore_options'];
			$keepLocalCopy = !empty($options['keepLocalCopy']);
		}
		else
		{
			$engine = '';
			$options = array();
		}

		if (empty($engine) || (!empty($engine) && $keepLocalCopy))
		{
			$oldLocalCover = $this->_getGalleryModel()->getAuthorCoverFile($user, true);
			@unlink($oldLocalCover);
		}

		if (!empty($engine))
		{
			$this->_bdAttachmentStore_getFileModel()->deleteFile($engine, $options, $oldCover);
		}

		return array();
	}

	public function getAuthorCoverFile(array $user, $forceLocal = false, $externalDataPath = null)
	{
		$coverData = $user['sonnb_xengallery_cover'];
		if (!is_array($coverData))
		{
			$coverData = @unserialize($coverData);
		}

		if (empty($coverData))
		{
			return '';
		}

		if (!empty($coverData['bdattachmentstore_engine']) && $forceLocal === false)
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $coverData['bdattachmentstore_engine'];
			$engineOptions = $coverData['bdattachmentstore_options'];
			$filePath = $this->getAuthorCoverStoreFile($user);

			$file = $fileModel->getAccessibleFilePath($engine, $engineOptions, $filePath, true);

			if ($file)
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		return sprintf('%s/xengallery-covers/%d/%d-%d.%s',
			$externalDataPath,
			floor($user['user_id'] / 1000),
			$user['user_id'],
			$coverData['upload_date'],
			$coverData['extension']
		);
	}

	public function getAuthorCoverUrl(array $user, $forceLocal = false, $externalDataPath = null)
	{
		$coverData = $user['sonnb_xengallery_cover'];
		if (!is_array($coverData))
		{
			$coverData = @unserialize($coverData);
		}

		if (empty($coverData))
		{
			return '';
		}

		if ($forceLocal === false && !empty($coverData['bdattachmentstore_engine']))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $coverData['bdattachmentstore_engine'];
			$engineOptions = $coverData['bdattachmentstore_options'];

			$filePath = $this->getAuthorCoverStoreFile($user);
			$file = $fileModel->getFileUrl($engine, $engineOptions, $filePath);

			if ($file)
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Application::$externalDataUrl;
		}

		return sprintf('%s/xengallery-covers/%d/%d-%d.%s',
			$externalDataPath,
			floor($user['user_id'] / 1000),
			$user['user_id'],
			$coverData['upload_date'],
			$coverData['extension']
		);
	}

	public function getAuthorCoverStoreFile(array $user)
	{
		$coverData = $user['sonnb_xengallery_cover'];
		if (!is_array($coverData))
		{
			$coverData = @unserialize($coverData);
		}

		if (empty($coverData))
		{
			return '';
		}

		return sprintf(
			'%s/xengallery_covers_%d_%d.%s',
			date('Y/m', $coverData['upload_date']),
			$user['user_id'],
			$coverData['upload_date'],
			$coverData['extension']
		);
	}
}
