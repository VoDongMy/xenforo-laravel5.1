<?php

class XenGallery_Deferred_Thumbnail extends XenForo_Deferred_Abstract
{
	protected $_thumbnailPath = null;

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$mediaIds = $mediaModel->getMediaIdsInRange($data['position'], $data['batch'], 'all');
		if (sizeof($mediaIds) == 0)
		{
			return true;
		}

		$options = XenForo_Application::getOptions();

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
		);

		$media = $mediaModel->getMediaByIds($mediaIds, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		foreach ($media AS $item)
		{
			$data['position'] = $item['media_id'];

			if ($item['thumbnail_date'])
			{
				continue;
			}

			$thumbnailPath = $mediaModel->getMediaThumbnailFilePath($item);
			$dataPath = $mediaModel->getOriginalDataFilePath($item, true);

			if ($item['media_type'] == 'image_upload')
			{
				XenForo_Helper_File::createDirectory(dirname($thumbnailPath), true);

				if (!file_exists($dataPath) || !is_readable($dataPath))
				{
					continue;
				}

				$image = new XenGallery_Helper_Image($dataPath);
				if ($image)
				{
					$image->resize(
						$dimensions['thumbnail_width'] = $options->xengalleryThumbnailDimension['width'],
						$dimensions['thumbnail_height'] = $options->xengalleryThumbnailDimension['height'], 'crop'
					);

					$image->saveToPath($thumbnailPath);

					unset($image);

					$attachmentDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
					$attachmentDataWriter->setExistingData($item['data_id']);

					$attachmentDataWriter->bulkSet($dimensions);
					$attachmentDataWriter->save();
				}
			}
			else if ($item['media_type'] == 'video_upload')
			{
				XenForo_Helper_File::createDirectory(dirname($thumbnailPath), true);

				if (!file_exists($dataPath) || !is_readable($dataPath))
				{
					continue;
				}

				$tempThumbFile = false;

				if ($options->get('xengalleryVideoTranscoding', 'thumbnail'))
				{
					try
					{
						$video = new XenGallery_Helper_Video($dataPath);
						$tempThumbFile = $video->getKeyFrame();
					}
					catch (XenForo_Exception $e) { }
				}

				if (!$tempThumbFile)
				{
					$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
					if ($tempThumbFile)
					{
						@copy($options->xengalleryDefaultNoThumb, $tempThumbFile);
					}
				}

				$image = new XenGallery_Helper_Image($tempThumbFile);
				if ($image)
				{
					$image->resize(
						$dimensions['thumbnail_width'] = $options->xengalleryThumbnailDimension['width'],
						$dimensions['thumbnail_height'] = $options->xengalleryThumbnailDimension['height'], 'crop'
					);

					$image->saveToPath($thumbnailPath);

					unset($image);

					$attachmentDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
					$attachmentDataWriter->setExistingData($item['data_id']);

					$attachmentDataWriter->bulkSet($dimensions);
					$attachmentDataWriter->save();
				}
			}
			else if ($item['media_type'] == 'video_embed')
			{
				preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $item['media_tag'], $parts);

				$mediaModel->getVideoThumbnailUrlFromParts($parts, true);
			}

			/** @var $mediaWriter XenGallery_DataWriter_Media */
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setImportMode(true);

			$mediaWriter->setExistingData($item['media_id']);
			$mediaWriter->set('last_edit_date', XenForo_Application::$time);

			$mediaWriter->save();
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuild_thumbnails');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}