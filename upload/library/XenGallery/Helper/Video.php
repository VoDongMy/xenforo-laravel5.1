<?php

class XenGallery_Helper_Video
{
	protected $_filename;
	protected $_ffmpegPath;
	protected $_transcodeEnabled;

	protected $_filenameError;
	protected $_ffmpegPathError;
	protected $_ffmpegVersion;
	protected $_ffmpegVersionError;
	protected $_ffmpegEncoderError;

	public function __construct($filename = '', $ffmpegPath = null, $transcodeEnabled = null)
	{
		if ($filename)
		{
			$this->setFilename($filename);
		}
		$this->_transcodeEnabled = $transcodeEnabled;
		$this->setFfmpegPath($ffmpegPath);
	}

	public function setFilename($filename)
	{
		if (file_exists($filename) && is_file($filename) && is_readable($filename))
		{
			$this->_filename = realpath($filename);
			$this->_filenameError = null;

			return true;
		}
		else
		{
			$this->_filename = null;
			$this->_filenameError = "File '$filename' does not exist or cannot be read";

			return false;
		}
	}

	public function getFilenameError()
	{
		return $this->_filenameError;
	}

	public function hasValidFilename()
	{
		return $this->_filename && !$this->_filenameError;
	}

	public function setFfmpegPath($ffmpegPath = null, $validatePath = true)
	{
		if ($ffmpegPath === null)
		{
			$ffmpegPath = XenForo_Application::getOptions()->get('xengalleryVideoTranscoding', 'ffmpegPath');
		}

		if ($this->_isWindowsOS())
		{
			$ffmpegPath = str_replace('/', '\\', $ffmpegPath);
		}

		$ffmpegPath = trim($ffmpegPath);

		// this must be set here as we run a command below based on it
		$this->_ffmpegPath = $ffmpegPath;

		if ($validatePath)
		{
			if (!$ffmpegPath)
			{
				$this->_ffmpegPathError = new XenForo_Phrase('xengallery_ffmpeg_path_error');
				$this->_ffmpegPath = null;

				return false;
			}

			if (!file_exists($ffmpegPath))
			{
				$this->_ffmpegPathError = new XenForo_Phrase(
					'xengallery_ffmpeg_path_find_error_x',
					array('ffmpegPath' => $ffmpegPath)
				);
				$this->_ffmpegPath = null;

				return false;
			}

			$output = $this->_runFfmpegCommand('-encoders');
			if (!$this->_assertFfmpegIsValid($output))
			{
				return false;
			}
		}

		$this->_ffmpegPathError = null;
		$this->_ffmpegVersionError = null;
		$this->_ffmpegEncoderError = null;

		return true;
	}

	protected function _assertFfmpegIsValid(array $output)
	{
		$versionYear = null;
		$encoders = null;

		foreach ($output AS $line)
		{
			if (preg_match('/\(c\)\s\d{4}-(\d{4})/is', $line, $matches))
			{
				if (isset($matches[1]))
				{
					$this->_ffmpegVersion = $versionYear = $matches[1];
					continue;
				}
			}

			if (preg_match('/(?:[a-z]|\.){6}\s(libvo_aacenc|libx264|png)\s/im', $line, $matches))
			{
				$encoders[$matches[1]] = true;
			}
		}

		if ($versionYear !== null && $versionYear < 2013)
		{
			$this->_ffmpegVersionError = new XenForo_Phrase('xengallery_xfmg_ffmpeg_version_1_1_0');
			return false;
		}

		if ($encoders !== null)
		{
			$required = array('png');
			if ($this->_transcodeEnabled)
			{
				$required = array_merge($required, array(
					'libvo_aacenc', 'libx264'
				));
			}
			$available = array_keys($encoders);

			if ($notAvailable = array_diff($required, $available))
			{
				$this->_ffmpegEncoderError = new XenForo_Phrase('xengallery_xfmg_requires_following_encoders_to_be_enabled', array('notAvailable' => implode(', ', $notAvailable)));
				return false;
			}
		}

		if ($versionYear === null && $encoders === null)
		{
			$this->_ffmpegPathError = new XenForo_Phrase(
				'xengallery_ffmpeg_path_execute_error_x',
				array('ffmpegPath' => $this->_ffmpegPath)
			);
			$this->_ffmpegPath = null;
			return false;
		}

		return true;
	}

	public function getFfmpegPathError()
	{
		return $this->_ffmpegPathError;
	}

	public function getFfmpegVersionYear($line)
	{
		return $this->_ffmpegVersion;
	}

	public function getFfmpegVersionError()
	{
		return $this->_ffmpegVersionError;
	}

	public function getFfmpegEncoderError()
	{
		return $this->_ffmpegEncoderError;
	}

	public function getFfmpegErrors()
	{
		$errors = array();

		if ($this->_ffmpegPathError)
		{
			$errors[] = $this->_ffmpegPathError;
		}
		if ($this->_ffmpegVersionError)
		{
			$errors[] = $this->_ffmpegVersionError;
		}
		if ($this->_ffmpegEncoderError)
		{
			$errors[] = $this->_ffmpegEncoderError;
		}

		return $errors;
	}

	public function hasValidFfmpegPath()
	{
		return $this->_ffmpegPath && !$this->_ffmpegPathError;
	}

	public function getVideoInfo(&$return = null)
	{
		$filename = $this->_getValidatedVideoFile();

		return $this->_runFfmpegCommand("-i {file} 2>&1", array('file' => $filename), $return);
	}

	public function getVideoDimensions($afterRotation = true)
	{
		$output = $this->getVideoInfo();
		foreach ($output AS $line)
		{
			$line = trim($line);
			if ($line && preg_match('/(\b[^0]\d+x[^0]\d+\b)/', $line, $match))
			{
				$dimensions = explode('x', $match[1]);

				if ($afterRotation)
				{
					$rotation = $this->_getRotationFromInfo($output);
					if ($rotation)
					{
						if (
							($rotation >= 90 && $rotation <= 180)
							|| ($rotation >= 270 && $rotation <= 360)
						)
						{
							$dimensions = array_reverse($dimensions);
						}
					}
				}

				return $dimensions;
			}
		}

		return null;
	}

	public function getVideoDuration()
	{
		$output = $this->getVideoInfo();
		foreach ($output AS $line)
		{
			$line = trim($line);
			if ($line && preg_match('/Duration: (\d+):(\d+):(\d+)/s', $line, $match))
			{
				array_shift($match);
				list($hours, $minutes, $seconds) = $match;

				$duration = 0;

				$duration += $hours * 60 * 60;
				$duration += $minutes * 60;
				$duration += $seconds;

				return $duration;
			}
		}

		return null;
	}

	public function getVideoRotation()
	{
		$output = $this->getVideoInfo();
		return $this->_getRotationFromInfo($output);
	}

	protected function _getRotationFromInfo(array $info)
	{
		foreach ($info AS $line)
		{
			$line = trim($line);
			if ($line && preg_match('/rotate\s+:\s+([\d\.]+)/s', $line, $match))
			{
				return round($match[1]);
			}
		}

		return null;
	}

	public function getKeyFrame()
	{
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		$inputFile = $this->_getValidatedVideoFile();
		$outputFile = $tempFile . '.png';

		$seek = 10;

		$duration = $this->getVideoDuration();
		if ($duration >= 10)
		{
			$seek = round($duration / 10);
		}

		$this->_runFfmpegCommand(
			'-ss {seek} -i {input} -vframes 1 {output}',
			array(
				'seek' => $seek,
				'input' => $inputFile,
				'output' => $outputFile
			)
		);

		try
		{
			XenForo_Helper_File::safeRename($outputFile, $tempFile);
		}
		catch (Exception $e)
		{
			@unlink($tempFile);
			$tempFile = null; // will get default nothumb image
		}

		return $tempFile;
	}

	public function queueTranscode(array $transcodeData = array())
	{
		$transcodeData = array_merge($transcodeData, array(
			'filename' => $this->_getValidatedVideoFile()
		));

		/** @var XenGallery_Model_Transcode $transcodeModel */
		$transcodeModel = XenForo_Model::create('XenGallery_Model_Transcode');
		$transcodeModel->insertTranscodeQueue($transcodeData);
	}

	public function beginTranscode(array $queueRecord)
	{
		$options = XenForo_Application::getOptions();

		$phpPath = $options->get('xengalleryVideoTranscoding', 'phpPath');
		$filePath = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'video-transcode.php';

		$command = sprintf(
			"%s %s %s",
			escapeshellarg($phpPath),
			escapeshellarg($filePath),
			escapeshellarg($queueRecord['transcode_queue_id'])
		);

		if ($this->_isWindowsOS())
		{
			if (class_exists('COM', false))
			{
				$shell = new COM("WScript.Shell");
				$shell->Run($command, 0, false);
			}
			else
			{
				pclose(popen("start \"XFMG\" /MIN $command", 'r'));
			}
		}
		else
		{
			exec("nohup $command > /dev/null 2> /dev/null &");
		}
	}

	public function transcodeProcess()
	{
		$inputFile = $this->_getValidatedVideoFile();
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		$outputFile = $tempFile . '.mp4';

		$this->_runFfmpegCommand(
			'-y -i {input} -vcodec libx264 -acodec libvo_aacenc -ar 48000 -ac 2 -movflags faststart {output}',
			array(
				'input' => $inputFile,
				'output' => $outputFile
			)
		);

		@unlink($tempFile);

		return $outputFile;
	}

	public function finalizeTranscode(array $queueRecord, $outputFile)
	{
		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete(
			'xengallery_transcode_queue',
			'transcode_queue_id = ' . $db->quote($queueRecord['transcode_queue_id'])
		);

		$queueData = $queueRecord['queue_data'];
		$media = $queueData['media'];

		/** @var XenGallery_Model_Transcode $transcodeModel */
		$transcodeModel = XenForo_Model::create('XenGallery_Model_Transcode');

		if (!file_exists($outputFile))
		{
			// transcode failure
			$params = array('username' => $media['username'], 'title' => $media['media_title']);
			$this->_transcodeException($media, 'xengallery_video_by_x_named_y_failed_transcoding', $params);
		}

		$videoInfo = new XenGallery_VideoInfo_Preparer($outputFile);
		$result = $videoInfo->getInfo();

		/** @var XenForo_Model_Attachment $attachmentModel */
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		$attachment = $attachmentModel->getAttachmentById($queueData['attachmentId']);

		if (!$result->isValid() || $result->requiresTranscoding())
		{
			$params = array('username' => $media['username'], 'title' => $media['media_title']);
			$this->_transcodeException($media, 'xengallery_video_by_x_named_y_failed_transcoding', $params);
		}

		clearstatcache();
		$fields = array(
			'file_hash' => md5_file($outputFile),
			'file_size' => filesize($outputFile)
		);

		try
		{
			$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dataDw->setExistingData($attachment['data_id']);
			$dataDw->bulkSet($fields);
			$dataDw->save();
		}
		catch (XenForo_Exception $e)
		{
			$params = array('username' => $media['username'], 'title' => $media['media_title']);
			$this->_transcodeException($media, 'xengallery_video_by_x_named_y_failed_transcoding', $params);
		}

		/** @var XenGallery_Model_Media $mediaModel */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$data = $dataDw->getMergedData();

		$filePath = $attachmentModel->getAttachmentDataFilePath($data);

		$originalThumbPath = $mediaModel->getMediaThumbnailFilePath($attachment);
		$thumbPath = $mediaModel->getMediaThumbnailFilePath($data);

		XenForo_Helper_File::safeRename($originalThumbPath, $thumbPath);
		XenForo_Helper_File::safeRename($outputFile, $filePath);

		@unlink($queueData['filename']);

		$mediaDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);

		$tagger = null;
		if (isset($queueData['tags']) && isset($queueData['tagger_permissions']))
		{
			$tagModel = XenForo_Model::create('XenForo_Model_Tag');

			$tagger = $tagModel->getTagger('xengallery_media')
				->setPermissionsFromContext($queueData)
				->setTags($tagModel->splitTags($queueData['tags']));
		}

		if (!empty($queueData['customFields']))
		{
			$mediaDw->setCustomFields($queueData['customFields'], $queueData['customFieldsShown']);
		}

		$mediaDw->bulkSet($media);

		if ($mediaDw->save())
		{
			$mediaModel->markMediaViewed($mediaDw->getMergedData(), $media);

			if ($tagger)
			{
				$tagger->setContent($mediaDw->get('media_id'), true)->save();
			}

			$attachmentData = array(
				'content_type' => 'xengallery_media',
				'content_id' => $mediaDw->get('media_id'),
				'temp_hash' => '',
				'unassociated' => 0
			);
			$db->update('xf_attachment', $attachmentData, "attachment_id = $attachment[attachment_id]");

			$mediaDw->updateUserMediaQuota();

			$transcodeModel->sendTranscodeAlert($mediaDw->getMergedData(), true);
		}
		else
		{
			$params = array('username' => $media['username'], 'title' => $media['media_title']);
			$this->_transcodeException($media, 'xengallery_media_uploaded_by_x_named_y_failed_creation', $params);
		}

		XenForo_Db::commit($db);

		$this->_requeueDeferred();
	}

	protected function _transcodeException(array $media, $errorMessage = '', array $errorParams = array())
	{
		/** @var XenGallery_Model_Transcode $transcodeModel */
		$transcodeModel = XenForo_Model::create('XenGallery_Model_Transcode');

		$transcodeModel->sendTranscodeAlert($media, false);
		$this->_requeueDeferred();

		XenForo_Db::commit();

		$error = new XenForo_Phrase($errorMessage, $errorParams);
		throw new XenForo_Exception($error->render());
	}

	protected function _requeueDeferred()
	{
		/** @var XenGallery_Model_Transcode $transcodeModel */
		$transcodeModel = XenForo_Model::create('XenGallery_Model_Transcode');

		if (!$transcodeModel->isDeferredQueued())
		{
			// This will clean up any pending transcodes if there are any remaining and not currently queued.
			try
			{
				XenForo_Application::defer('XenGallery_Deferred_TranscodeQueue', array(), 'TranscodeQueue');
			}
			catch (Exception $e) {}
		}
	}

	protected function _runFfmpegCommand($command, array $args = array(), &$return = null)
	{
		$ffmpegPath = escapeshellarg($this->_getValidatedFfmpegPath());

		$origCommand = $command;

		preg_match_all('#\{([a-z0-9_]+)}#i', $command, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			$key = $match[1];
			if (!isset($args[$key]))
			{
				throw new XenForo_Exception("Command '$origCommand' did not provide argument '$key'");
			}

			$value = escapeshellarg($args[$key]);
			$command = str_replace($match[0], $value, $command);
		}

		$output = array();
		exec("$ffmpegPath $command 2>&1", $output, $return);

		return $output;
	}

	protected function _getValidatedVideoFile()
	{
		if (!$this->_filename)
		{
			if ($this->_filenameError)
			{
				throw new XenForo_Exception(strval($this->_filenameError));
			}
			else
			{
				throw new XenForo_Exception("No filename specified");
			}
		}

		return $this->_filename;
	}
	
	protected function _getValidatedFfmpegPath()
	{
		if (!$this->_ffmpegPath)
		{
			if ($this->_ffmpegPathError)
			{
				throw new XenForo_Exception(strval($this->_ffmpegPathError));
			}
			else
			{
				throw new XenForo_Exception("No FFmpeg path available");
			}
		}
		
		return $this->_ffmpegPath;
	}

	protected function _isWindowsOS()
	{
		return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	}
}