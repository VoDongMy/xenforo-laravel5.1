<?php

/**
	Copyright (c) 2010 until today, Manuel Reinhard

	Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
	associated documentation files (the "Software"), to deal in the Software without restriction, including
	without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to
	the following conditions:

	The above copyright notice and this permission notice shall be included in all copies or substantial
	portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
 */
class XenGallery_Helper_Image
{
	//Set variables
	protected $_image = '';
	protected $_imageInfo = array();
	protected $_fileInfo = array();
	public $tmpFile = array();
	protected $_pathToTempFiles = '';
	protected $_watermark;
	protected $_newFileType;
	protected $_imageMagick = false;
	public $importMode = false;

	/**
	 * Constructor of this class
	 * @param string $image (path to image)
	 */
	public function __construct($image)
	{
		@ini_set('gd.jpeg_ignore_warning', 1);

		$this->setPathToTempFiles(XenForo_Helper_File::getTempDir());

		if (file_exists($image))
		{
			if (class_exists('Imagick'))
			{
				$this->_imageMagick = true;
			}
			$this->_image = $image;

			$imageInfo = $this->_readImageInfo();
			if (strlen(implode($imageInfo)) == 0)
			{
				return false;
			}
		}
		else
		{
			throw new Zend_Exception('Image ' . $image . ' can not be found, try another image.');
		}
	}

	/**
	 * Destructor of this class
	 * @param string $image (path to image)
	 */
	public function __destruct()
	{
		if (file_exists($this->tmpFile))
		{
			unlink($this->tmpFile);
		}
	}

	/**
	 * Read and set some basic info about the image
	 * @param string $image (path to image)
	 */
	protected function _readImageInfo()
	{
		$data = getimagesize($this->_image);

		$this->_imageInfo['width'] = $data[0];
		$this->_imageInfo['height'] = $data[1];
		$this->_imageInfo['imagetype'] = $data[2];
		$this->_imageInfo['htmlWidthAndHeight'] = $data[3];
		$this->_imageInfo['mime'] = $data['mime'];
		$this->_imageInfo['channels'] = (isset($data['channels']) ? $data['channels'] : NULL);

		return $this->_imageInfo;
	}

	/**
	 * Sets path to temp files
	 * @param string $path
	 */
	public function setPathToTempFiles($path)
	{
		if (!$path)
		{
			$this->_pathToTempFiles = XenForo_Helper_File::getTempDir();
			$path = $this->_pathToTempFiles;
		}
		$path = realpath($path) . DIRECTORY_SEPARATOR;
		$this->_pathToTempFiles = $path;
		$this->tmpFile = tempnam($this->_pathToTempFiles, 'xfmg');

		return true;
	}

	/**
	 * Sets new image type
	 * @param string $newFileType (jpeg, png, bmp, gif, vnd.wap.wbmp, xbm)
	 */
	public function setNewFileType($newFileType)
	{
		$this->_newFileType = strtolower($newFileType);

		return true;
	}

	/**
	 * Sets new main image
	 * @param string $pathToImage
	 */
	protected function _setNewMainImage($pathToImage)
	{
		$this->_image = $pathToImage;
		$this->_readImageInfo();

		return true;
	}

	/**
	 * Resizes an image
	 * Some portions of this function as found on
	 * http://www.bitrepository.com/resize-an-image-keeping-its-aspect-ratio-using-php-and-gd.html
	 * @param int $max_width
	 * @param int $max_height
	 * @param string $method
	 *               fit = Fits image into width and height while keeping original aspect ratio. Expect your image not to use the full area.
	 *               crop = Crops image to fill the area while keeping original aspect ratio. Expect your image to get, well, cropped.
	 *               fill = Fits image into the area without taking care of any ratios. Expect your image to get deformed.
	 *
	 * @param string $cropAreaLeftRight
	 *               l = left
	 *               c = center
	 *               r = right
	 *               array( x-coordinate, width)
	 *
	 * @param string $cropAreaBottomTop
	 *               t = top
	 *               c = center
	 *               b = bottom
	 *               array( y-coordinate, height)
	 */
	public function resize($max_width, $max_height, $method = 'fit', $cropAreaLeftRight = 'c', $cropAreaBottomTop = 'c', $jpgQuality = 90)
	{
		$width = $this->getWidth();
		$height = $this->getHeight();

		$newImage_width = $max_width;
		$newImage_height = $max_height;
		$srcX = 0;
		$srcY = 0;

		//Get ratio of max_width : max_height
		$ratioOfMaxSizes = $max_width / $max_height;

		//Want to fit in the area?
		if ($method == 'fit')
		{

			if ($ratioOfMaxSizes >= $this->getRatioWidthToHeight())
			{
				$max_width = $max_height * $this->getRatioWidthToHeight();
			}
			else
			{
				$max_height = $max_width * $this->getRatioHeightToWidth();
			}

			//set image data again
			$newImage_width = $max_width;
			$newImage_height = $max_height;


			//or want to crop it?
		}
		elseif ($method == 'crop')
		{
			//set new max height or width
			if ($ratioOfMaxSizes > $this->getRatioWidthToHeight())
			{
				$max_height = $max_width * $this->getRatioHeightToWidth();
			}
			else
			{
				$max_width = $max_height * $this->getRatioWidthToHeight();
			}

			//which area to crop?
			if (is_array($cropAreaLeftRight))
			{
				$srcX = $cropAreaLeftRight[0];
				if ($ratioOfMaxSizes > $this->getRatioWidthToHeight())
				{
					$width = $cropAreaLeftRight[1];
				}
				else
				{
					$width = $cropAreaLeftRight[1] * $this->getRatioWidthToHeight();
				}
			}
			elseif ($cropAreaLeftRight == 'r')
			{
				$srcX = $width - (($newImage_width / $max_width) * $width);
			}
			elseif ($cropAreaLeftRight == 'c')
			{
				$srcX = ($width / 2) - ((($newImage_width / $max_width) * $width) / 2);
			}

			if (is_array($cropAreaBottomTop))
			{
				$srcY = $cropAreaBottomTop[0];

				if ($ratioOfMaxSizes > $this->getRatioWidthToHeight())
				{
					$height = $cropAreaBottomTop[1] * $this->getRatioHeightToWidth();
				}
				else
				{
					$height = $cropAreaBottomTop[1];
				}
			}
			elseif ($cropAreaBottomTop == 'b')
			{
				$srcY = $height - (($newImage_height / $max_height) * $height);
			}
			elseif ($cropAreaBottomTop == 'c')
			{
				$srcY = ($height / 2) - ((($newImage_height / $max_height) * $height) / 2);
			}
		}

		//Let's get it on, create image!
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();

		if ($imageSaveFunc == 'ImageGIF' && $this->_imageMagick && !$this->importMode)
		{
			$image = new Imagick($this->_image);
			$image = $image->coalesceimages();

			foreach ($image AS $frame)
			{
				$frame->cropImage($max_width, $max_height, $srcX, $srcY);
				$frame->thumbnailImage($newImage_width, $newImage_height);
				$frame->setImagePage($frame->getImageWidth(), $frame->getImageHeight(), 0, 0);
			}

			if (XenForo_Application::getOptions()->xengalleryAnimatedThumbnails)
			{
				@set_time_limit(120);

				try
				{
					$image->writeImages($this->tmpFile, true);
				}
				catch (Exception $e)
				{
					$image->writeImage($this->tmpFile);
				}
			}
			else
			{
				$image->writeImage($this->tmpFile);
			}

			$this->_setNewMainImage($this->tmpFile);

			$image->clear();
			$image->destroy();

			return true;
		}

		try
		{
			$imageC = ImageCreateTrueColor($newImage_width, $newImage_height);
			$newImage = @$imageCreateFunc($this->_image);

			if (!$newImage)
			{
				return false;
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		if ($imageSaveFunc == 'ImagePNG' || $imageSaveFunc == 'ImageGIF')
		{
			$transparency = imagecolortransparent($newImage);
			if ($transparency >= 0)
			{
				$transparency = imagecolorallocatealpha($imageC, 255, 255, 255, 127);
				imagefill($imageC, 0, 0, $transparency);
				imagecolortransparent($imageC, $transparency);
			}
			elseif ($imageSaveFunc == 'ImagePNG')
			{
				imagealphablending($imageC, false);
				$color = imagecolorallocatealpha($imageC, 0, 0, 0, 127);
				imagefill($imageC, 0, 0, $color);
				imagesavealpha($imageC, true);
			}
		}

		ImageCopyResampled($imageC, $newImage, 0, 0, $srcX, $srcY, $max_width, $max_height, $width, $height);

		if ($imageSaveFunc == 'imageJPG' || $imageSaveFunc == 'ImageJPEG')
		{
			if (!$imageSaveFunc($imageC, $this->tmpFile, $jpgQuality))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}
		else
		{
			if (!$imageSaveFunc($imageC, $this->tmpFile))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}

		$this->_setNewMainImage($this->tmpFile);

		//Free memory!
		imagedestroy($imageC);
		return true;
	}

	public function cropExact($x, $y, $width, $height, $jpgQuality = 100)
	{
		$origWidth = $this->getWidth();
		$origHeight = $this->getHeight();

		//Let's get it on, create image!
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();

		$imageC = ImageCreateTrueColor($width, $height);
		$newImage = $imageCreateFunc($this->_image);

		if ($imageSaveFunc == 'ImagePNG')
		{
			imagealphablending($imageC, false);
			imagesavealpha($imageC, true);
			$transparent = imagecolorallocatealpha($imageC, 255, 255, 255, 127);
			imagefilledrectangle($imageC, 0, 0, $width, $height, $transparent);
		}

		ImageCopyResampled($imageC, $newImage, 0, 0, $x, $y, $width, $height, $width, $height);

		//Set image
		if ($imageSaveFunc == 'imageJPG' || $imageSaveFunc == 'ImageJPEG')
		{
			if (!$imageSaveFunc($imageC, $this->tmpFile, $jpgQuality))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}
		else
		{
			if (!$imageSaveFunc($imageC, $this->tmpFile))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}

		$this->_setNewMainImage($this->tmpFile);

		//Free memory!
		imagedestroy($imageC);

		return true;
	}

	/**
	 * Adds a watermark
	 */
	public function addWatermark($imageWatermark)
	{
		$this->_watermark = new self($imageWatermark);
		$this->_watermark->setPathToTempFiles($this->_pathToTempFiles);

		return $this->_watermark;
	}


	/**
	 * Writes Watermark to the File
	 * @param int $oapcity
	 * @param int $marginH (margin in pixel from base image horizontally)
	 * @param int $marginV (margin in pixel from base image vertically)
	 *
	 * @param string $positionWatermarkLeftRight
	 *                  l = left
	 *               c = center
	 *               r = right
	 *
	 * @param string $positionWatermarkTopBottom
	 *                  t = top
	 *               c = center
	 *               b = bottom
	 */

	public function writeWatermark($opacity = 50, $marginH = 0, $marginV = 0, $positionWatermarkLeftRight = 'c', $positionWatermarkTopBottom = 'c')
	{
		if (!$this->_imageMagick)
		{
			throw new Exception('Cannot watermark without ImageMagick installed.');
		}

		return $this->writeWatermarkIm($opacity, $marginH, $marginV, $positionWatermarkLeftRight, $positionWatermarkTopBottom);
	}

	public function writeWatermarkIm($opacity = 50, $marginH = 0, $marginV = 0, $positionWatermarkLeftRight = 'c', $positionWatermarkTopBottom = 'c')
	{
		//add Watermark
		list ($imageCreateFunc, $imageSaveFunc) = $this->_watermark->_getFunctionNames();
		$watermarkImage = $imageCreateFunc($this->_watermark->_getImage());

		//get base image
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();
		$baseImage = @$imageCreateFunc($this->_image);

		if (!$baseImage)
		{
			return false;
		}

		//Calculate margins
		if ($positionWatermarkLeftRight == 'r')
		{
			$marginH = imagesx($baseImage) - imagesx($watermarkImage) - $marginH;
		}

		if ($positionWatermarkLeftRight == 'c')
		{
			$marginH = (imagesx($baseImage) / 2) - (imagesx($watermarkImage) / 2) - $marginH;
		}

		if ($positionWatermarkTopBottom == 'b')
		{
			$marginV = imagesy($baseImage) - imagesy($watermarkImage) - $marginV;
		}

		if ($positionWatermarkTopBottom == 'c')
		{
			$marginV = (imagesy($baseImage) / 2) - (imagesy($watermarkImage) / 2) - $marginV;
		}

		$image = new Imagick($this->_image);
		$image->coalesceimages();

		$watermark = new Imagick();
		$watermark->readimage($this->_watermark->_getImage());
		$watermark->evaluateImage(Imagick::EVALUATE_DIVIDE, 100 / $opacity, Imagick::CHANNEL_ALPHA);


		if ($imageSaveFunc == 'ImageGIF')
		{
			if (XenForo_Application::getOptions()->xengalleryWatermarkAnimated)
			{
				try
				{
					foreach ($image AS $frame)
					{
						$frame->compositeImage($watermark, imagick::COMPOSITE_OVER, $marginH, $marginV);
					}

					if (!$image->writeimages($this->tmpFile, true))
					{
						throw new Exception('Cannot save file ' . $this->tmpFile);
					}
				}
				catch (Exception $e)
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}
		else
		{
			$image->compositeImage($watermark, imagick::COMPOSITE_OVER, $marginH, $marginV);

			if (!$image->writeimage($this->tmpFile))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}

		//Set new main image
		$this->_setNewMainImage($this->tmpFile);

		$image->destroy();
		$watermark->destroy();

		return true;
	}

	public function writeWatermarkGd($opacity = 50, $marginH = 0, $marginV = 0, $positionWatermarkLeftRight = 'c', $positionWatermarkTopBottom = 'c')
	{
		//add Watermark
		list ($imageCreateFunc, $imageSaveFunc) = $this->_watermark->_getFunctionNames();
		$watermark = $imageCreateFunc($this->_watermark->_getImage());

		//get base image
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();
		$baseImage = $imageCreateFunc($this->_image);

		if ($imageSaveFunc == 'ImagePNG')
		{
			imagealphablending($baseImage, false);
			imagesavealpha($baseImage, true);
		}

		//Calculate margins
		if ($positionWatermarkLeftRight == 'r')
		{
			$marginH = imagesx($baseImage) - imagesx($watermark) - $marginH;
		}

		if ($positionWatermarkLeftRight == 'c')
		{
			$marginH = (imagesx($baseImage) / 2) - (imagesx($watermark) / 2) - $marginH;
		}

		if ($positionWatermarkTopBottom == 'b')
		{
			$marginV = imagesy($baseImage) - imagesy($watermark) - $marginV;
		}

		if ($positionWatermarkTopBottom == 'c')
		{
			$marginV = (imagesy($baseImage) / 2) - (imagesy($watermark) / 2) - $marginV;
		}

		//****************************
		//Add watermark and keep alpha channel of pngs.
		//The following lines are based on the code found on
		//http://ch.php.net/manual/en/function.imagecopymerge.php#92787
		//****************************

		// creating a cut resource
		$cut = imagecreatetruecolor(imagesx($watermark), imagesy($watermark));

		// copying that section of the background to the cut
		imagecopy($cut, $baseImage, 0, 0, $marginH, $marginV, imagesx($watermark), imagesy($watermark));

		// placing the watermark now
		imagecopy($cut, $watermark, 0, 0, 0, 0, imagesx($watermark), imagesy($watermark));

		imagecopymerge($baseImage, $cut, $marginH, $marginV, 0, 0, imagesx($watermark), imagesy($watermark), $opacity);

		//****************************
		//****************************

		//Set image
		if (!$imageSaveFunc($baseImage, $this->tmpFile))
		{
			throw new Exception('Cannot save file ' . $this->tmpFile);
		}

		//Set new main image
		$this->_setNewMainImage($this->tmpFile);

		//Free memory!
		imagedestroy($baseImage);
		unset($Watermark);
	}

	/**
	 * Roates an image
	 */
	public function rotate($degrees, $jpgQuality = 100)
	{
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();

		if ($imageSaveFunc == 'ImageGIF')
		{
			if ($this->_imageMagick && XenForo_Application::getOptions()->xengalleryAnimatedRotateFlip)
			{
				$image = new Imagick($this->_image);
				$image = $image->coalesceimages();

				foreach ($image AS $frame)
				{
					$frame->rotateImage(new ImagickPixel('#00000000'), -$degrees);
				}

				try
				{
					@set_time_limit(120);
					$image->writeImages($this->tmpFile, true);
				}
				catch (Exception $e)
				{
					return false;
				}

				$this->_setNewMainImage($this->tmpFile);

				$image->clear();
				$image->destroy();

				return true;
			}
			else
			{
				return false;
			}
		}

		$source = $imageCreateFunc($this->_image);

		if (function_exists('imagerotate'))
		{
			$imageRotated = imagerotate($source, $degrees, 0, true);
		}
		else
		{
			$imageRotated = $this->rotateImage($source, $degrees);
		}

		if ($imageSaveFunc == 'ImagePNG')
		{
			imagealphablending($imageRotated, false );
			imagesavealpha($imageRotated, true );
		}

		if ($imageSaveFunc == 'ImageJPEG')
		{
			if (!$imageSaveFunc($imageRotated, $this->tmpFile, $jpgQuality))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}
		else
		{
			if (!$imageSaveFunc($imageRotated, $this->tmpFile))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}

		//Set new main image
		$this->_setNewMainImage($this->tmpFile);

		return true;
	}

	public function flip($direction = 'horizontal',  $jpgQuality = 100)
	{
		list ($imageCreateFunc, $imageSaveFunc) = $this->_getFunctionNames();

		if ($imageSaveFunc == 'ImageGIF')
		{
			if ($this->_imageMagick && XenForo_Application::getOptions()->xengalleryAnimatedRotateFlip)
			{
				$image = new Imagick($this->_image);
				$image = $image->coalesceimages();

				foreach ($image AS $frame)
				{
					if ($direction == 'horizontal')
					{
						$frame->flopImage();
					}
					else
					{
						$frame->flipImage();
					}
				}

				try
				{
					@set_time_limit(120);
					$image->writeImages($this->tmpFile, true);
				}
				catch (Exception $e)
				{
					return false;
				}

				$this->_setNewMainImage($this->tmpFile);

				$image->clear();
				$image->destroy();

				return true;
			}
			else
			{
				return false;
			}
		}

		$source = $imageCreateFunc($this->_image);

		if ($direction == 'horizontal')
		{
			$imageFlipped = $this->_flipHorizontal($source, $imageSaveFunc);
		}
		else
		{
			$imageFlipped = $this->_flipVertical($source, $imageSaveFunc);
		}

		if ($imageSaveFunc == 'ImageJPEG')
		{
			if (!$imageSaveFunc($imageFlipped, $this->tmpFile, $jpgQuality))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}
		else
		{
			if (!$imageSaveFunc($imageFlipped, $this->tmpFile))
			{
				throw new Exception('Cannot save file ' . $this->tmpFile);
			}
		}

		//Set new main image
		$this->_setNewMainImage($this->tmpFile);

		return true;
	}

	protected function _flipHorizontal($source, $imageSaveFunc)
	{
		$size_x = imagesx($source);
		$size_y = imagesy($source);

		$newImg = imagecreatetruecolor($size_x, $size_y);
		if ($imageSaveFunc == 'ImagePNG')
		{
			imagealphablending($newImg, false );
			imagesavealpha($newImg, true );
		}

		$flipped = imagecopyresampled($newImg, $source, 0, 0, ($size_x-1), 0, $size_x, $size_y, 0-$size_x, $size_y);
		if ($flipped)
		{
			return $newImg;
		}
		else
		{
			return false;
		}
	}

	protected function _flipVertical($source, $imageSaveFunc)
	{
		$size_x = imagesx($source);
		$size_y = imagesy($source);

		$newImg = imagecreatetruecolor($size_x, $size_y);
		if ($imageSaveFunc == 'ImagePNG')
		{
			imagealphablending($newImg, false );
			imagesavealpha($newImg, true );
		}

		$flipped = imagecopyresampled($newImg, $source, 0, 0, 0, ($size_y-1), $size_x, $size_y, $size_x, 0-$size_y);
		if ($flipped)
		{
			return $newImg;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sends image data to browser
	 */
	public function display()
	{
		$mime = $this->getMimeType();
		header('Content-Type: ' . $mime);
		readfile($this->_image);
	}

	/**
	 * Saves image to file
	 */
	public function save($filename, $path = '', $extension = '')
	{
		//add extension
		if ($extension == '')
		{
			$filename .= $this->getExtension(true);
		}
		else
		{
			$filename .= '.' . $extension;
		}

		//add trailing slash if necessary
		if ($path != '')
		{
			$path = realpath($path) . DIRECTORY_SEPARATOR;
		}

		//create full path
		$fullPath = $path . $filename;

		//Copy file
		if (!copy($this->_image, $fullPath))
		{
			throw new Exception('Cannot save file ' . $fullPath);
		}

		//Set new main image
		$this->_setNewMainImage($fullPath);

		return $fullPath;
	}

	public function saveToPath($filePath)
	{
		//Copy file
		if (!copy($this->_image, $filePath))
		{
			throw new Exception('Cannot save file ' . $filePath);
		}

		//Set new main image
		$this->_setNewMainImage($filePath);

		return $filePath;
	}

	/**
	 * Returns function names
	 */
	protected function _getFunctionNames()
	{
		if (null == $this->_newFileType)
		{
			$this->setNewFileType($this->getType());
		}

		switch ($this->getType())
		{
			case 'jpg':
			case 'jpeg':
				$imageCreateFunc = 'ImageCreateFromJPEG';
				break;

			case 'png':
				$imageCreateFunc = 'ImageCreateFromPNG';
				break;

			case 'bmp':
				$imageCreateFunc = 'ImageCreateFromBMP';
				break;

			case 'gif':
				$imageCreateFunc = 'ImageCreateFromGIF';
				break;

			case 'vnd.wap.wbmp':
				$imageCreateFunc = 'ImageCreateFromWBMP';
				break;

			case 'xbm':
				$imageCreateFunc = 'ImageCreateFromXBM';
				break;

			default:
				$imageCreateFunc = 'ImageCreateFromJPEG';
		}

		switch ($this->_newFileType)
		{
			case 'jpg':
			case 'jpeg':
				$imageSaveFunc = 'ImageJPEG';
				break;

			case 'png':
				$imageSaveFunc = 'ImagePNG';
				break;

			case 'bmp':
				$imageSaveFunc = 'ImageBMP';
				break;

			case 'gif':
				$imageSaveFunc = 'ImageGIF';
				break;

			case 'vnd.wap.wbmp':
				$imageSaveFunc = 'ImageWBMP';
				break;

			case 'xbm':
				$imageSaveFunc = 'ImageXBM';
				break;

			default:
				$imageSaveFunc = 'ImageJPEG';
		}

		return array($imageCreateFunc, $imageSaveFunc);
	}

	/**
	 * returns the image
	 */
	protected function _getImage()
	{
		return $this->_image;
	}

	/**
	 * return info about the image
	 */
	public function getImageInfo()
	{
		return $this->_imageInfo;
	}

	/**
	 * return info about the file
	 */
	public function getFileInfo()
	{
		return $this->fileInfo;
	}

	/**
	 * Gets width of image
	 * @return int
	 */
	public function getWidth()
	{
		return $this->_imageInfo['width'];
	}

	/**
	 * Gets height of image
	 * @return int
	 */
	public function getHeight()
	{
		return $this->_imageInfo['height'];
	}

	/**
	 * Gets type of image
	 * @return string
	 */
	public function getExtension($withDot = false)
	{
		$extension = image_type_to_extension($this->_imageInfo['imagetype']);
		$extension = str_replace('jpeg', 'jpg', $extension);
		if (!$withDot)
		{
			$extension = substr($extension, 1);
		}

		return $extension;
	}

	/**
	 * Gets mime type of image
	 * @return string
	 */
	public function getMimeType()
	{
		return $this->_imageInfo['mime'];
	}

	/**
	 * Gets mime type of image
	 * @return string
	 */
	public function getType()
	{
		return substr(strrchr($this->_imageInfo['mime'], '/'), 1);
	}

	/**
	 * Get filesize
	 * @return string
	 */
	public function getFileSizeInBytes()
	{
		clearstatcache();
		return filesize($this->_image);
	}

	/**
	 * Gets the file hash of the image
	 * @return string
	 */
	public function getFileHash()
	{
		clearstatcache();
		return md5_file($this->_image);
	}

	/**
	 * Get filesize
	 * @return string
	 */
	public function getFileSizeInKiloBytes()
	{
		$size = $this->getFileSizeInBytes();
		return $size / 1024;
	}

	/**
	 * Returns a human readable filesize
	 * @author      wesman20 (php.net)
	 * @author      Jonas John
	 * @author      Manuel Reinhard
	 * @link        http://www.jonasjohn.de/snippets/php/readable-filesize.htm
	 * @link        http://www.php.net/manual/en/function.filesize.php
	 */
	public function getFileSize()
	{
		$size = $this->getFileSizeInBytes();

		$mod = 1024;
		$units = explode(' ', 'B KB MB GB TB PB');
		for ($i = 0; $size > $mod; $i++)
		{
			$size /= $mod;
		}

		//round differently depending on unit to use
		if ($i < 2)
		{
			$size = round($size);
		}
		else
		{
			$size = round($size, 2);
		}

		return $size . ' ' . $units[$i];
	}

	/**
	 * Gets ratio width:height
	 * @return float
	 */
	public function getRatioWidthToHeight()
	{
		if ($this->_imageInfo['width'] == 0 || $this->_imageInfo['height'] == 0)
		{
			$this->_readImageInfo();
		}

		$ratio = 1;
		try
		{
			$ratio = $this->_imageInfo['width'] / $this->_imageInfo['height'];
		}
		catch (Exception $e) {}

		return $ratio;
	}

	/**
	 * Gets ratio height:width
	 * @return float
	 */
	public function getRatioHeightToWidth()
	{
		if ($this->_imageInfo['width'] == 0 || $this->_imageInfo['height'] == 0)
		{
			$this->_readImageInfo();
		}
		return $this->_imageInfo['height'] / $this->_imageInfo['width'];
	}

	/************************************
	/* OTHER STUFF
	/************************************

	/**
	 * Replacement for imagerotate if it doesn't exist
	 * As found on http://www.php.net/manual/de/function.imagerotate.php#93692
	 */
	protected function rotateImage($img, $rotation)
	{
		$width = imagesx($img);
		$height = imagesy($img);
		switch ($rotation)
		{
			case 90:
				$newimg = @imagecreatetruecolor($height, $width);
				break;

			case 180:
				$newimg = @imagecreatetruecolor($width, $height);
				break;

			case 270:
				$newimg = @imagecreatetruecolor($height, $width);
				break;

			case 0:
				return $img;
				break;

			case 360:
				return $img;
				break;
		}

		if ($newimg)
		{
			for ($i = 0; $i < $width; $i++)
			{
				for ($j = 0; $j < $height; $j++)
				{
					$reference = imagecolorat($img, $i, $j);
					switch ($rotation)
					{
						case 90:
							if (!@imagesetpixel($newimg, ($height - 1) - $j, $i, $reference))
							{
								return false;
							}
							break;

						case 180:
							if (!@imagesetpixel($newimg, $width - $i, ($height - 1) - $j, $reference))
							{
								return false;
							}
							break;

						case 270:
							if (!@imagesetpixel($newimg, $j, $width - $i, $reference))
							{
								return false;
							}
							break;
					}
				}
			}

			return $newimg;
		}
		return false;
	}
}