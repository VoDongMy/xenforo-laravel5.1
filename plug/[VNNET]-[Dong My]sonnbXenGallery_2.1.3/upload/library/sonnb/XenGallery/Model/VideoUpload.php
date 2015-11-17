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
class sonnb_XenGallery_Model_VideoUpload extends XenForo_Upload
{
	protected $_videoSize = null;
	protected $_videoInfo = null;

	public static function getUploadedFile($formField, array $source = null)
	{
		$files = sonnb_XenGallery_Model_VideoUpload::getUploadedFiles($formField, $source);

		return reset($files);
	}

	public static function getUploadedFiles($formField, array $source = null)
	{
		if ($source === null)
		{
			$source = $_FILES;
		}
		if (empty($source[$formField]))
		{
			return array();
		}

		$files = array();
		$field = $source[$formField];

		if (isset($field['name']))
		{
			if (is_array($field['name']))
			{
				foreach (array_keys($field['name']) AS $key)
				{
					if ($field['name'][$key])
					{
						$files[] = new sonnb_XenGallery_Model_VideoUpload($field['name'][$key], $field['tmp_name'][$key]);
					}
				}
			}
			elseif ($field['name'])
			{
				$files[] = new sonnb_XenGallery_Model_VideoUpload($field['name'], $field['tmp_name']);
			}
		}

		return $files;
	}

	protected function _checkVideoState()
	{
		if ($this->_videoInfo !== null)
		{
			return;
		}

		$this->_videoInfo = false;

		if (!$this->_tempFile)
		{
			return;
		}

		if ($this->_videoSize === null)
		{
			$this->_videoSize = @filesize($this->_tempFile);
		}

		if (!in_array($this->_extension, sonnb_XenGallery_Model_VideoData::$videoExtension))
		{
			$this->_errors['extension'] = new XenForo_Phrase('sonnb_xengallery_the_uploaded_file_was_not_an_video_as_expected');
			return;
		}

		if (function_exists('finfo_open'))
		{
			$fInfo = finfo_open(FILEINFO_MIME);
			$this->_videoInfo = @finfo_file($fInfo, $this->_tempFile);
			$this->_videoInfo = substr($this->_videoInfo, 0, strpos($this->_videoInfo, ';'));
			finfo_close($fInfo);
		}
		elseif (function_exists('mime_content_type'))
		{
			$this->_videoInfo = @mime_content_type($this->_tempFile);
		}

		if (!isset(sonnb_XenGallery_Model_VideoData::$videoMimes[$this->_extension])
				|| sonnb_XenGallery_Model_VideoData::$videoMimes[$this->_extension] !== $this->_videoInfo)
		{

			$this->_errors['extension'] = new XenForo_Phrase('sonnb_xengallery_we_only_support_following_video_type', array('type' => implode(',', sonnb_XenGallery_Model_VideoData::$videoExtension)));
			return;
		}

		$fp = @fopen($this->_tempFile, 'rb');
		if ($fp)
		{
			$previous = '';
			while (!@feof($fp))
			{
				$content = fread($fp, 256000);
				$test = $previous . $content;
				$exists = (
					strpos($test, '<?php') !== false
						|| preg_match('/<script\s+language\s*=\s*(php|"php"|\'php\')\s*>/i', $test)
				);

				if ($exists)
				{
					@fclose($fp);
					$this->_errors['content'] = new XenForo_Phrase('sonnb_xengallery_uploaded_video_contains_invalid_content');
					return;
				}

				$previous = $content;
			}

			@fclose($fp);
		}
	}

	protected function _checkForErrors()
	{
		$this->_checkVideoState();

		if ($this->_allowedExtensions && !in_array($this->_extension, $this->_allowedExtensions))
		{
			$this->_errors['extension'] = new XenForo_Phrase('uploaded_file_does_not_have_an_allowed_extension');
		}

		if ($this->_tempFile && $this->_maxFileSize && filesize($this->_tempFile) > $this->_maxFileSize)
		{
			$this->_errors['fileSize'] = new XenForo_Phrase('uploaded_file_is_too_large');
		}

		if (!$this->_tempFile)
		{
			$this->_errors['fileSize'] = new XenForo_Phrase('uploaded_file_is_too_large_for_server_to_process');
		}

		$this->_errorsChecked = true;
	}

	public function isVideo()
	{
		$this->_checkVideoState();

		return in_array($this->_extension, sonnb_XenGallery_Model_VideoData::$videoExtension);
	}

	public function getVideoSize()
	{
		$this->_checkVideoState();

		return $this->_videoSize ? $this->_videoSize : 0;
	}

	public function getVideoExtension()
	{
		$this->_checkVideoState();

		return $this->_extension ? $this->_extension : '';
	}
}
