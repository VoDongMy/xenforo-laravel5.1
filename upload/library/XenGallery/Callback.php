<?php

class XenGallery_Callback
{
	public static function getMediaForBlock($content, $params, XenForo_Template_Abstract $template)
	{
		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$visitor = XenForo_Visitor::getInstance();

		$conditions = array(
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
		);
		if ($params['categories'])
		{
			$conditions['category_id'] = explode(',', $params['categories']);
		}
		else
		{
			$conditions['category_id'] = 'nocategories';
		}

		if ($params['categories'] == 'all')
		{
			$conditions['category_id'] = $params['categories'];
		}

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_ATTACHMENT,
			'order' => $params['type'],
			'orderDirection' => 'desc',
			'limit' => $params['limit'],
		);

		if ($params['type'] == 'rand')
		{
			$mediaIds = XenForo_Application::getSimpleCacheData('xengalleryRandomMediaCache');
			if (!$mediaIds)
			{
				return ''; // No random media cache, do not proceed.
			}
			shuffle($mediaIds);
			$mediaIds = array_slice($mediaIds, 0, $params['limit'] * 4);
			$conditions['media_id'] = $mediaIds;

			unset($fetchOptions['limit'], $fetchOptions['order']);
		}

		if ($params['albums'])
		{
			$conditions = $conditions + array(
				'privacyUserId' => $visitor->user_id,
				'mediaBlock' => true
			);

			$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_PRIVACY;
		}
		else
		{
			$conditions['album_id'] = 'noalbums';
		}

		$media = $mediaModel->getMedia($conditions, $fetchOptions);

		if ($params['type'] == 'rand')
		{
			shuffle($media);
			$media = array_slice($media, 0, $params['limit']);
		}

		$viewParams = array(
			'media' => $mediaModel->prepareMediaItems($media),
			'captions' => !empty($params['captions']) ? true : false
		);

		return $template->create('xengallery_media_block_items', $viewParams);
	}

	public static function getAdditionalDataForPageCriteriaSelection($content, $params, XenForo_Template_Abstract $template)
	{
		$categories = XenForo_Model::create('XenGallery_Model_Category')->getCategoryStructure();

		$params += array(
			'pageGalleryCriteriaData' => array(
				'xengallery_categories' => $categories
			)
		);

		return $template->create('xengallery_helper_criteria_page', $params);
	}

	public static function getCommentsForBlock($content, $params, XenForo_Template_Abstract $template)
	{
		$params = array_merge(array(
			'limit' => 5,
			'title' => new XenForo_Phrase('xengallery_recent_comments')
		), $params);

		/** @var $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		$comments = array();
		if ($commentModel->canViewComments())
		{
			/** @var $mediaModel XenGallery_Model_Media */
			$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

			$visitor = XenForo_Visitor::getInstance();

			$fetchOptions = array(
				'privacyUserId' => $visitor->user_id,
				'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
			);
			$comments = $commentModel->getCommentsForBlockOrFeed($params['limit'], $fetchOptions);
			$comments = $commentModel->prepareCommentsForBlock($comments);
			$comments = $mediaModel->prepareMediaItems($comments);
		}

		$viewParams = array(
			'comments' => $comments,
			'title' => $params['title']
		);

		return $template->create('xengallery_comments_block', $viewParams);
	}
}