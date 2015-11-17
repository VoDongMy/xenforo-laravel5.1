<?php

class XenGallery_ControllerPublic_Thread extends XFCP_XenGallery_ControllerPublic_Thread
{
	public function actionIndex()
	{
		$parent = parent::actionIndex();

		if ($parent instanceof XenForo_ControllerResponse_View)
		{
			$messages = array();
			foreach ($parent->params['posts'] AS $post)
			{
				$messages[$post['post_id']] = $post['message'];
			}

			$mediaIds = array();
			$mediaPostIds = array();
			foreach ($messages AS $postId => $message)
			{
				if (preg_match_all('/\[GALLERY\](.*)\[\/GALLERY]/isU', $message, $matches))
				{
					if (isset($matches[1]) && is_array($matches[1]))
					{
						foreach ($matches[1] AS $match)
						{
							$mediaPostIds[$postId][] = array(
								'media_id' => $match,
								'post_id' => $postId
							);

							$mediaIds[$match] = $match;
						}
					}
				}
			}

			$mediaModel = $this->_getMediaModel();

			$mediaConditions = array(
				'media_id' => $mediaIds
			);
			$mediaFetchOptions = array(
				'join' => XenGallery_Model_Media::FETCH_USER
					| XenGallery_Model_Media::FETCH_ATTACHMENT
					| XenGallery_Model_Media::FETCH_CATEGORY
					| XenGallery_Model_Media::FETCH_ALBUM
			);

			$media = $mediaModel->getMedia($mediaConditions, $mediaFetchOptions);
			$media = $mediaModel->prepareMediaItems($media);

			$groupedMedia = array();
			foreach ($mediaPostIds AS $mediaPostIdArray)
			{
				foreach ($mediaPostIdArray AS $mediaPostId)
				{
					$groupedMedia[$mediaPostId['post_id']][$mediaPostId['media_id']] = $media[$mediaPostId['media_id']];
				}
			}

			$parent->params['media'] = $groupedMedia;
		}

		return $parent;
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}