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
class sonnb_XenGallery_Model_PhotoUpload extends XenForo_Upload
{
	public static function getUploadedFile($formField, array $source = null)
	{
		$files = sonnb_XenGallery_Model_PhotoUpload::getUploadedFiles($formField, $source);

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
						$files[] = new sonnb_XenGallery_Model_PhotoUpload($field['name'][$key], $field['tmp_name'][$key]);
					}
				}
			}
			elseif ($field['name'])
			{
				$files[] = new sonnb_XenGallery_Model_PhotoUpload($field['name'], $field['tmp_name']);
			}
		}

		return $files;
	}

	protected function _checkImageState()
	{
		if ($this->_imageInfo !== null)
		{
			return;
		}

		$this->_imageInfo = false;

		if (!$this->_tempFile)
		{
			return;
		}

		$imageInfo = @getimagesize($this->_tempFile);
		if (!$imageInfo)
		{
			if (in_array($this->_extension, sonnb_XenGallery_Model_PhotoData::$imageExtension))
			{
				$this->_errors['extension'] = new XenForo_Phrase('the_uploaded_file_was_not_an_image_as_expected');
			}
			return;
		}

		$imageInfo['width'] = $imageInfo[0];
		$imageInfo['height'] = $imageInfo[1];
		$imageInfo['type'] = $imageInfo[2];

		$type = $imageInfo['type'];
		$extensionMap = array(
			IMAGETYPE_GIF => array('gif'),
			IMAGETYPE_JPEG => array('jpg', 'jpeg', 'jpe'),
			IMAGETYPE_PNG => array('png')
		);

		if (!isset($extensionMap[$type]))
		{
			return;
		}
		if (!in_array($this->_extension, $extensionMap[$type]))
		{
			$this->_errors['extension'] = new XenForo_Phrase('contents_of_uploaded_image_do_not_match_files_extension');
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
				if ($exists) {
					@fclose($fp);
					$this->_errors['content'] = new XenForo_Phrase('uploaded_image_contains_invalid_content');
					return;
				}

				$previous = $content;
			}

			@fclose($fp);
		}

		$this->_imageInfo = $imageInfo;
	}
}
