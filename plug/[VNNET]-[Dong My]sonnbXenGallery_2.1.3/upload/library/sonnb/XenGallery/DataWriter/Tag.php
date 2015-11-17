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
class sonnb_XenGallery_DataWriter_Tag extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_tag' => array(
				'tag_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'album',
					'allowedValues' => array('album', 'photo', 'video', 'audio'),
				),
				'content_id' => array(
					'type' => self::TYPE_UINT,
					'required' => true
				),
				'user_id' => array(
					'type' => self::TYPE_UINT,
					'required' => true
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'default' => ''
				),
				'tag_x' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'tag_y' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'tag_state' => array(
					'type' => self::TYPE_STRING,
					'default' => 'awaiting',
					'allowedValues' => array('awaiting', 'accepted', 'rejected'),
				),
				'tagger_user_id' => array(
					'type' => self::TYPE_UINT,
				),
				'tagger_username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'default' => ''
				),
				'tag_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time
				),
			)
		);
	}
	
	protected function _postSave()
	{
		if ($this->isChanged('tag_state') && $this->get('tag_state') === 'accepted')
		{
			$tagData = $this->getMergedData();

			$dw = XenForo_DataWriter::create($this->_getContentDwClass());
			$dw->setExistingData($this->get('content_id'));
			$dw->addTag($tagData);
			$dw->save();

			$watchModel = $this->_getWatchModel();
			$watchModel->insertUpdateWatcherByContentId(
				array(
					'username' => $tagData['username'],
					'user_id' => $tagData['user_id']
				),
				$tagData['content_type'],
				$tagData['content_id']
			);

			$this->_publishNewFeed();
		}
	}

	protected function _publishNewFeed()
	{
		$xfContentType = '';
		switch ($this->get('content_type'))
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Photo::$xfContentType;
				break;
		}

		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			$xfContentType,
			$this->get('content_id'),
			'tag'
		);
	}
	
	protected function _postDelete()
	{
		if ($this->get('tag_state') === 'accepted')
		{
			$dw = $this->_getXfContentDw(null, XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($this->get('content_id'));
			$dw->removeTag($this->getMergedData());
			$dw->save();
		}
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'tag_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_tag' => $this->_getTagModel()->getTagById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'tag_id = ' . $this->_db->quote($this->getExisting('tag_id'));
	}
}