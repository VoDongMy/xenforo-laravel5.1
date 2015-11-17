<?php

class XenGallery_DataWriter_Rating extends XenForo_DataWriter
{
	const DATA_PREVENT_ALERTS = 'preventAlerts';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xengallery_rating' => array(
				'rating_id'  => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'content_id'         => array('type' => self::TYPE_UINT, 'required' => true),
				'content_type' => array('type' => self::TYPE_STRING, 'default' => 'media',
					'allowedValues' => array('media', 'album')
				),
				'user_id'             => array('type' => self::TYPE_UINT, 'required' => true),
				'username' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'requiredError' => 'xengallery_please_enter_valid_username'
				),				
				'rating'              => array('type' => self::TYPE_UINT, 'required' => true, 'min' => 1, 'max' => 5),
				'rating_date'         => array('type' => self::TYPE_UINT, 'required' => true, 'default' => XenForo_Application::$time)
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		$ratingId = 0;

		if (!is_array($data))
		{
			$ratingId = $data;
		}
		
		if ($ratingId)
		{
			$rating = $this->_getRatingModel()->getRatingById($ratingId);
		}
		else
		{
			$rating = $this->_getRatingModel()->getRatingByContentAndUser($data['content_id'], $data['content_type'], $data['user_id']);
		}
		
		return array('xengallery_rating' => $rating);
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'rating_id = ' . $this->_db->quote($this->getExisting('rating_id'));
	}

	protected function _preSave()
	{
		if (!$this->get('user_id') || !$this->get('content_id'))
		{
			throw new XenForo_Exception(new XenForo_Phrase('xengallery_an_unexpected_error_occurred'));
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_updateContent($this->get('rating'), $this->get('content_type'));
		
		if ($this->isInsert())
		{
			$contentType = $this->get('content_type');
			
			if ($contentType == 'media')
			{
				$content = $this->_getMediaModel()->getMediaById($this->get('content_id'), array(
					'join' => XenGallery_Model_Media::FETCH_USER | XenGallery_Model_Media::FETCH_USER_OPTION
				));
			}
			
			if ($contentType == 'album')
			{
				$content = $this->_getAlbumModel()->getAlbumById($this->get('content_id'), array(
					'join' => XenGallery_Model_Album::FETCH_USER | XenGallery_Model_Album::FETCH_USER_OPTION
				));
			}

			if ($this->getExtraData(self::DATA_PREVENT_ALERTS) !== true)
			{
				if ($content && XenForo_Model_Alert::userReceivesAlert($content, 'xengallery_rating', 'insert'))
				{
					$ratingUser = array(
						'user_id' => $this->get('user_id'),
						'username' => $this->get('username')
					);

					XenForo_Model_Alert::alert(
						$content['user_id'],
						$ratingUser['user_id'],
						$ratingUser['username'],
						'xengallery_rating',
						$this->get('rating_id'),
						'insert'
					);
				}

				$this->_getNewsFeedModel()->publish(
					$this->get('user_id'),
					$this->get('username'),
					'xengallery_rating',
					$this->get('rating_id'),
					'insert'
				);
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_updateContent($this->getExisting('rating'), $this->getExisting('content_type'), true);
		
		$comment = $this->_getCommentModel()->getCommentByRatingId($this->getExisting('rating_id'));
		
		if ($comment)
		{
			$commentDw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
			$commentDw->setExistingData($comment['comment_id']);
			$commentDw->set('rating_id', 0);
			$commentDw->save();			
		}

		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_rating', $this->get('rating_id'));
	}

	/**
	 * Update the content table to reflect the new rating
	 *
	 * @param integer $rating
	 * @param string $contentType
	 */
	protected function _updateContent($rating, $contentType)
	{
		if ($contentType == 'media')
		{
			$mediaDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', self::ERROR_SILENT);
			$mediaDw->setExistingData($this->get('content_id'));
			
			$mediaDw->set('rating_sum', $mediaDw->get('rating_sum') + $rating - $this->getExisting('rating'));
			
			$mediaDw->updateRating();
			
			$mediaDw->save();	
		}
		
		if ($contentType == 'album')
		{
			$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', self::ERROR_SILENT);
			$albumDw->setExistingData($this->get('content_id'));
				
			$albumDw->set('album_rating_sum', $albumDw->get('album_rating_sum') + $rating - $this->getExisting('rating'));
				
			$albumDw->updateRating();
				
			$albumDw->save();
		}
	}

	/**
	* @return XenGallery_Model_Rating
	*/
	protected function _getRatingModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Rating');
	}
	
	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}	
}