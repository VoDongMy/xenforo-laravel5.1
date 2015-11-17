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
class sonnb_XenGallery_DataWriter_Video extends sonnb_XenGallery_DataWriter_Content
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['sonnb_xengallery_video'] = array(
			'content_id' => array(
				'type' => self::TYPE_UINT,
				'default' => array('sonnb_xengallery_content', 'content_id')
			),
			'video_type' => array(
				'type' => self::TYPE_STRING,
				'default' => ''
			),
			'video_key' => array(
				'type' => self::TYPE_STRING,
				'maxLength' => 100,
				'default' => ''
			)
		);

		return $fields;
	}

	protected function _getDefaultPrivacy($contentType, $action)
	{
		if (empty($contentType))
		{
			$contentType = sonnb_XenGallery_Model_Video::$contentType;
		}

		return parent::_getDefaultPrivacy($contentType, $action);
	}

	protected function _preSave()
	{
		$this->set('content_type', sonnb_XenGallery_Model_Video::$contentType);

		parent::_preSave();
	}

	protected function _getExistingData($data)
	{
		$content = false;
		$contentId = $this->_getExistingPrimaryKey($data, 'content_id');
		$contentDataId = $this->_getExistingPrimaryKey($data, 'content_data_id');
		$fetchOptions = array('join' => sonnb_XenGallery_Model_Video::FETCH_VIDEO);

		if ($contentId)
		{
			$content = $this->_getVideoModel()->getContentByContentId(sonnb_XenGallery_Model_Video::$contentType, $contentId, $fetchOptions);
		}

		if (empty($content) && $contentDataId)
		{
			$content = $this->_getVideoModel()->getContentByDataId($contentDataId, $fetchOptions);
		}

		if (!$content)
		{
			return false;
		}

		return $this->getTablesDataFromArray($content);
	}
}