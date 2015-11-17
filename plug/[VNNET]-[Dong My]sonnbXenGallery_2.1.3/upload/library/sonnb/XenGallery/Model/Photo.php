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
class sonnb_XenGallery_Model_Photo extends sonnb_XenGallery_Model_Content
{

	public static $contentType = 'photo';
	public static $xfContentType = 'sonnb_xengallery_photo';

	public static $exifExposureProgram = array(
		1 => 'sonnb_xengallery_exif_program_manual',
		2 => 'sonnb_xengallery_exif_program_normal_program',
		3 => 'sonnb_xengallery_exif_program_aperture_priority',
		4 => 'sonnb_xengallery_exif_program_shutter_priority',
		5 => 'sonnb_xengallery_exif_program_creative_program',
		6 => 'sonnb_xengallery_exif_program_action_program',
		7 => 'sonnb_xengallery_exif_program_portrait_mode',
		8 => 'sonnb_xengallery_exif_program_landscape_mode',
	);

	public function countPhotosByUserId($userId, array $conditions = array())
	{
		if (!$userId)
		{
			return 0;
		}

		$conditions['user_id'] = $userId;
		$conditions['content_type'] = self::$contentType;

		return $this->countContents($conditions);
	}

	public function prepareContent(array $content, array $fetchOptions = array(), $viewingUser = null)
	{
		$content = parent::prepareContent($content, $fetchOptions, $viewingUser);

		if (!empty($content))
		{
			if (!empty($content['photo_exif']))
			{
				if (!is_array($content['photo_exif']))
				{
					$content['photo_exif'] = @unserialize($content['photo_exif']);
				}

				if (!empty($content['photo_exif']['ExposureTime']))
				{
					$content['photo_exif']['ExposureTimeOrigin'] = $content['photo_exif']['ExposureTime'];

					$ele = explode('/', $content['photo_exif']['ExposureTime']);

					if (!empty($ele[0]) && !empty($ele[1]))
					{
						if ($ele[1] > $ele[0])
						{
							$content['photo_exif']['ExposureTime'] = '1/' . floor($ele[1]/$ele[0]);
						}
						else
						{
							$content['photo_exif']['ExposureTime'] = floor($ele[0]/$ele[1]);
						}
					}
					else
					{
						$content['photo_exif']['ExposureTime'] = (!empty($ele[0]) ? floor($ele[0]) : (!empty($ele[1]) ? floor($ele[1]) : 0));
					}

					$content['photo_exif']['ExposureTime'] .= 's';
				}
			}
		}

		return $content;
	}

	public function getPhotoExif(array $contentData, $filePath = null)
	{
		$xenOptions = XenForo_Application::getOptions();
		$exif = array();

		if (!empty($contentData) && function_exists('exif_read_data'))
		{
			$exifKeys = array(
				'DateTime','DateTimeOriginal','DateTimeDigitized','Make',
				'Model','ExposureTime','ExposureProgram', 'ExposureMode', 'FNumber',
				'BrightnessValue', 'MeteringMode', 'Flash', 'ExposureMode', 'WhiteBalance',
				'ISOSpeedRatings','ShutterSpeedValue','ApertureValue','LightSource','FocalLength',
				'Artist','Copyright','ImageDescription','Software', 'Orientation',

				'LensType', 'LensInfo', 'LensMake', 'LensModel', 'LensSerialNumber', 'LensSpecification',
				//'MakerNote',

				'GPS', 'GPSLatitude','GPSLongitude','GPSLatitudeRef','GPSLongitudeRef', 'GPSAltitudeRef','GPSAltitude',
				//'GPSTimeStamp', 'GPSImgDirectionRef', 'GPSImgDirection'
			);

			if ($filePath === null)
			{
				$filePath =  $this->_getContentDataModel()->getContentDataFile($contentData);
			}

			if (file_exists($filePath) || is_readable($filePath))
			{
				//@ini_set('exif.encode_unicode', 'UTF-8');
				$exif = @exif_read_data($filePath, 0);
				$exif = $exif ? array_intersect_key($exif, array_flip($exifKeys)) : array();

				@getimagesize($filePath, $info);
				if(function_exists('iptcparse') && isset($info['APP13']))
				{
					$iptc = iptcparse($info['APP13']);

					if (!empty($iptc['2#105'][0]))
					{
						$exif['title'] = trim($iptc['2#105'][0]);
						$exif['title'] = utf8_bad_replace($exif['title'], '');
					}
					elseif (!empty($iptc['2#005'][0]))
					{
						$exif['title'] = trim($iptc['2#005'][0]);
						$exif['title'] = utf8_bad_replace($exif['title'], '');
					}
					if (!empty($iptc['2#120'][0]))
					{
						$caption = trim($iptc['2#120'][0]);
						$caption = utf8_bad_replace($caption, '');

						if (empty($exif['title']))
						{
							if (strlen($caption) < 80)
							{
								$exif['title'] = $caption;
							}
							else
							{
								$exif['description'] = $caption;
							}
						}
						elseif ($caption != $exif['title'])
						{
							$exif['description'] = $caption;
						}
					}
				}

				if ($exif)
				{
					if (!empty($exif['ImageDescription']))
					{
						$exif['ImageDescription'] = utf8_bad_replace($exif['ImageDescription'], '');

						if (empty($exif['title']) && strlen($exif['ImageDescription']) < 80)
						{
							$exif['title'] = $exif['ImageDescription'];
						}
						elseif (empty($exif['description']))
						{
							$exif['description'] = $exif['ImageDescription'];
						}
					}

					if (isset($exif['Software']))
					{
						$exif['Software'] = utf8_bad_replace($exif['Software'], '');
					}

					if (isset($exif['ImageDescription']))
					{
						$exif['ImageDescription'] = utf8_bad_replace($exif['ImageDescription'], '');
					}

					if (isset($exif['Artist']))
					{
						$exif['Artist'] = utf8_bad_replace($exif['Artist'], '');
					}

					if (isset($exif['Copyright']))
					{
						$exif['Copyright'] = utf8_bad_replace($exif['Copyright'], '');
					}

					if (!empty($exif['DateTime']))
					{
						try
						{
							$test = explode(':', $exif['DateTime']);

							if (count($test) > 3)
							{
								$exif['DateTime'] = preg_replace('/:/', '/', $exif['DateTime'], 2);
							}

							$date = new DateTime($exif['DateTime']);
							$exif['DateTime'] = $date->format('U');
						}
						catch (Exception $e){}
					}

					if (!empty($exif['DateTimeOriginal']))
					{
						try
						{
							$test = explode(':', $exif['DateTimeOriginal']);

							if (count($test) > 3)
							{
								$exif['DateTimeOriginal'] = preg_replace('/:/', '/', $exif['DateTimeOriginal'], 2);
							}

							$date = new DateTime($exif['DateTimeOriginal']);
							$exif['DateTimeOriginal'] = $date->format('U');
						}
						catch (Exception $e){}
					}

					if (!empty($exif['DateTimeDigitized']))
					{
						try
						{
							$test = explode(':', $exif['DateTimeDigitized']);

							if (count($test) > 3)
							{
								$exif['DateTimeDigitized'] = preg_replace('/:/', '/', $exif['DateTimeDigitized'], 2);
							}

							$date = new DateTime($exif['DateTimeDigitized']);
							$exif['DateTimeDigitized'] = $date->format('U');
						}
						catch (Exception $e){}
					}

					if (!empty($exif['Make']))
					{
						if (!isset($exif['Model']))
						{
							$exif['Model'] = "";
						}

						$exif['Model'] = utf8_bad_replace($exif['Model']);
						$exif['Make'] = utf8_bad_replace($exif['Make']);
						$exif['Make'] = ucfirst(strtolower($exif['Make']));

						$longVendors = array(
							'OLYMPUS IMAGING CORP.' => 'Olympus',
							'OLYMPUS OPTICAL CO.,LTD ' => 'Olympus',
							'SAMSUNG TECHWIN CO., LTD.' => 'Samsung',
							'OLYMPUS CORPORATION' => 'Olympus',
							'Zoran Corporation' => 'Zoran',
							'Medion OPTICAL CO,LTD' => 'Medion',
							'SANYO Electric Co.,Ltd.' => 'Sanyo',
							'CASIO COMPUTER CO.,LTD.' => 'Casio',
							'minolta Co., Ltd.' => 'Minolta',
							'Hewlett-Packard' => 'HP',
							'Research In Motion' => ''
						);

						foreach ($longVendors as $longVendor => $newName)
						{
							if (preg_match('#'.preg_quote($longVendor).'#is', $exif['Make']))
							{
								$exif['Make'] = $newName;
							}
						}

						if (empty($exif['Model']))
						{
							$exif['Model'] = $exif['Make'];
						}
						else
						{
							$vendors = array(
								'Acer', 'Apple', 'BenQ', 'BlackBerry', 'Canon', 'Casio', 'Concord', 'DoCoMo',
								'Epson', 'Fujifilm', 'Google', 'GoPro', 'Helio', 'HP', 'HTC', 'JVC', 'KDDI',
								'Kodak', 'Konica Minolta', 'Kyocera', 'Leaf', 'Leica', 'LG', 'Motorola',
								'Nikon', 'Nintendo', 'Nokia', 'Olympus', 'Palm', 'Panasonic', 'Pentax',
								'Phase One', 'Polaroid', 'Ricoh', 'Samsung', 'Sanyo', 'Sharp', 'Sigma',
								'Sony', 'Sony Ericsson', 'Toshiba', 'Vivitar'
							);

							foreach ($vendors as $_vendor)
							{
								if (preg_match('#'.preg_quote($_vendor).'#is', $exif['Model']) &&
										preg_match('#'.preg_quote($_vendor).'#is', $exif['Make']))
								{
									$exif['Make'] = '';
								}
							}

							$exif['Model'] = $exif['Make'].' '.$exif['Model'];
						}

						$exif['Model'] = trim($exif['Model']);
					}

					if (!empty($exif['FNumber']))
					{
                        $ele = $exif['FNumber'];
						if (!is_array($ele))
						{
							$ele = explode('/', $ele);
						}

						$ele = array_map('intval', $ele);

						if (!empty($ele[0]) && !empty($ele[1]))
						{
							$exif['FNumber'] = '&#402;/'. round($ele[0]/$ele[1], 1);
						}
						else
						{
							$exif['FNumber'] = '&#402;/'. (!empty($ele[0]) ? round($ele[0], 1) : (!empty($ele[1]) ? round($ele[1], 1) : 0));
						}
					}

					if (!empty($exif['FocalLength']))
					{
                        $ele = $exif['FocalLength'];
						if (!is_array($ele))
						{
							$ele = explode('/', $ele);
						}

						$ele = array_map('intval', $ele);

						if (!empty($ele[0]) && !empty($ele[1]))
						{
							$exif['FocalLength'] = floor($ele[0]/$ele[1]). ' mm';
						}
						else
						{
							$exif['FocalLength'] = (!empty($ele[0]) ? floor($ele[0]) : (!empty($ele[1]) ? floor($ele[1]) : 0)). ' mm';
						}
					}

					if (!empty($exif['ApertureValue']))
					{
                        $ele = $exif['ApertureValue'];
						if (!is_array($ele))
						{
							$ele = explode('/', $ele);
						}

						$ele = array_map('intval', $ele);

						if (!empty($ele[0]) && !empty($ele[1]))
						{
							$exif['ApertureValue'] = round($ele[0]/$ele[1], 1);
						}
						else
						{
							$exif['ApertureValue'] = (!empty($ele[0]) ? round($ele[0], 1) : (!empty($ele[1]) ? round($ele[1], 1) : 0));
						}
					}

                    if (!$xenOptions->sonnb_XG_disableLocation &&
                            (!empty($exif['GPS']) || !empty($exif['GPSLatitude'])))
                    {
                        $data = $exif;
                        if (!empty($exif['GPS']))
                        {
                            $data = $exif['GPS'];
                        }

                        $exif['latitude'] = $this->getCoordinateFromExif($data['GPSLatitude'], $data['GPSLatitudeRef']);
                        $exif['longitude'] = $this->getCoordinateFromExif($data['GPSLongitude'], $data['GPSLongitudeRef']);

                        try
                        {
	                        $client = XenForo_Helper_Http::getClient($this->_getLocationModel()->getGeocodeUrlForCoordinate($exif['latitude'], $exif['longitude']));
                            $response = $client->request('GET');
                            $response = json_decode($response->getBody(), true);

                            $exif['address'] = $response['results'][0]['formatted_address'];
                        }
                        catch(Exception $e)
                        {}
                    }
				}
			}
		}

		return $exif;
	}

    public function getCoordinateFromExif($coordinate, $hemi) {

        $degrees = count($coordinate) > 0 ? $this->_getCoordinatePart($coordinate[0]) : 0;
        $minutes = count($coordinate) > 1 ? $this->_getCoordinatePart($coordinate[1]) : 0;
        $seconds = count($coordinate) > 2 ? $this->_getCoordinatePart($coordinate[2]) : 0;

        $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);

    }

    protected function _getCoordinatePart($coordinatePart) {

        $parts = explode('/', $coordinatePart);

        if (count($parts) <= 0)
        {
            return 0;
        }

        if (count($parts) == 1)
        {
            return $parts[0];
        }

        return floatval($parts[0]) / floatval($parts[1]);
    }

	public function canResizeImage($width, $height)
	{
		$maximumResolution = XenForo_Application::getOptions()->sonnbXG_maxResolution;

		if (!$maximumResolution)
		{
			$maximumResolution = XenForo_Application::getConfig()->get('maxImageResizePixelCount', 16000000);
		}

		if ($width*$height > $maximumResolution)
		{
			return false;
		}

		return true;
	}

	public function getPhotoDataConstraints($forUpload = false)
	{
		$visitor = XenForo_Visitor::getInstance();
		$xenOptions = XenForo_Application::getOptions();

		$photoSize = $visitor->hasPermission('sonnb_xengallery', 'limitPhotoSize');
		$maxWidth = $visitor->hasPermission('sonnb_xengallery', 'limitPhotoWidth');
		$maxHeight = $visitor->hasPermission('sonnb_xengallery', 'limitPhotoHeight');
		$maxFiles = $visitor->hasPermission('sonnb_xengallery', 'maximumPhoto');

		$globalMaximumSize = $xenOptions->sonnbXG_globalMaxPhotoSize;
		if ($globalMaximumSize < 1024)
		{
			$globalMaximumSize = XenForo_Application::getConfig()->get('maxImageResizePixelCount', 16000000);
		}

		$globalMaximumHeight = $xenOptions->sonnbXG_globalMaxPhotoHeight;
		if ($globalMaximumHeight < 1)
		{
			$globalMaximumHeight = 6000;
		}

		$globalMaximumWidth = $xenOptions->sonnbXG_globalMaxPhotoWidth;
		if ($globalMaximumWidth < 1)
		{
			$globalMaximumWidth = 6000;
		}

        $constraints = array(
            'extensions' => sonnb_XenGallery_Model_ContentData::$imageExtension,
            'size' => ($photoSize > 0 ? $photoSize: $globalMaximumSize),
            'count' => ($maxFiles > 0 ? $maxFiles: 0),
        );

        if ($forUpload && $xenOptions->sonnbXG_enableResize)
        {
	        $constraints['width'] = $globalMaximumWidth;
            $constraints['height'] = $globalMaximumHeight;
        }
        else
        {
	        $constraints['width'] = ($maxWidth > 0 ? $maxWidth: $globalMaximumWidth);
            $constraints['height'] = ($maxHeight > 0 ? $maxHeight: $globalMaximumHeight);
        }

		return $constraints;
	}

	public function getPhotoCountLimit(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$maxFiles = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'maximumPhoto');

		return ($maxFiles <= 0 ? 0 : $maxFiles);
	}

	public function isAnimatedGif($file)
	{
		$count = 0;

		if(!($fh = @fopen($file, 'rb')))
		{
			return $count;
		}

		//an animated gif contains multiple "frames", with each frame having a
		//header made up of:
		// * a static 4-byte sequence (\x00\x21\xF9\x04)
		// * 4 variable bytes
		// * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

		// We read through the file til we reach the end of the file, or we've found
		// at least 2 frame headers
		while(!feof($fh) && $count < 2)
		{
			$chunk = fread($fh, 1024 * 100); //read 100kb at a time
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
		}

		fclose($fh);
		return $count > 1;
	}
}
