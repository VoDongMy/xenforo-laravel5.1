<?php

class XenGallery_VideoInfo_Result implements ArrayAccess
{
	protected $_data = array();

	public function __construct()
	{
		$this->_data = array(
			'hasVideo' => false,
			'videoCodec' => '',
			'hasAudio' => false,
			'audioCodec' => ''
		);
	}

	/**
	 * Determines if the result is valid (has video).
	 *
	 * @return bool
	 */
	public function isValid()
	{
		return ($this->hasVideo);
	}

	/**
	 * Determines if the result suggests the video needs to be transcoded.
	 *
	 * @return bool
	 */
	public function requiresTranscoding()
	{
		$transcodeAudio = false;
		$transcodeVideo = false;

		if ($this->hasAudio)
		{
			switch ($this->audioCodec)
			{
				case 'mp3':
				case 'aac':
					$transcodeAudio = false;
					break;
				default:
					$transcodeAudio = true;
					break;
			}
		}

		if ($this->hasVideo)
		{
			switch ($this->videoCodec)
			{
				case 'h264':
					$transcodeVideo = false;
					break;
				default:
					$transcodeVideo = true;
					break;
			}
		}

		return ($transcodeAudio || $transcodeVideo);
	}

	public function offsetGet($offset)
	{
		return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
	}

	public function offsetSet($offset, $value)
	{
		$this->_data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->_data[$offset]);
	}

	public function offsetExists($offset)
	{
		return isset($this->_data[$offset]);
	}

	public function toArray()
	{
		return $this->_data;
	}

	public function __get($offset)
	{
		return $this->offsetGet($offset);
	}

	public function __set($offset, $value)
	{
		return $this->offsetSet($offset, $value);
	}
}