<?php

class XenGallery_ControllerPublic_Category extends XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$defaultOrder = 'media_date';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		if (!$categoryId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xengallery')
			);
		}

		$mediaHelper = $this->_getMediaHelper();
		$categoryModel = $this->_getCategoryModel();

		$category = $mediaHelper->assertCategoryValidAndViewable($categoryId);
		$categoryBreadcrumbs = $categoryModel->getCategoryBreadcrumb($category, false);

		$containerCategory = false;

		$uploadUserGroups = unserialize($category['upload_user_groups']);
		if (!$uploadUserGroups)
		{
			$canAddMedia = false;
			$containerCategory = true;
		}
		else
		{
			$canAddMedia = $this->_getMediaModel()->canAddMediaToCategory($uploadUserGroups);
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::getOptions()->xengalleryMediaMaxPerPage;

		$childIds = array();
		$showCategory = false;
		if ($containerCategory)
		{
			$childCategories = $categoryModel->getCategoryStructure(null, $categoryId);

			foreach ($childCategories AS $child)
			{
				$childIds[] = $child['category_id'];
			}

			if (!$childCategories)
			{
				$containerCategory = false;
			}

			$showCategory = true;
		}

		$conditions = array(
			'category_id' => $containerCategory ? $childIds : $categoryId,
			'deleted' => XenForo_Permission::hasPermission(XenForo_Visitor::getInstance()->permissions, 'xengallery', 'viewDeleted'),
			'type' => $type
		);

		$fetchOptions = $this->_getMediaFetchOptions() + array(
			'order' => $order ? $order : $defaultOrder,
			'page' => $page,
			'perPage' => $perPage
		);

		$mediaModel = $this->_getMediaModel();

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		$inlineModOptions = $mediaModel->prepareInlineModOptions($media, false);

		$ignoredNames = array();
		foreach ($media AS $item)
		{
			if (!empty($item['isIgnored']))
			{
				$ignoredNames[] = $item['username'];
			}
		}

		$mediaCount = $mediaModel->countMedia($conditions);

		$this->canonicalizePageNumber($page, $perPage, $mediaCount, 'xengallery/categories', $category);
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xengallery/categories', $category, array('page' => $page))
		);

		$pageNavParams = array(
			'order' => ($order != $defaultOrder ? $order : false),
			'type' => ($type ? $type : false)
		);

		$session = XenForo_Application::getSession();
		$requiresTranscode = $session->get('xfmgVideoRequiresTranscode');
		if ($requiresTranscode)
		{
			$session->remove('xfmgVideoRequiresTranscode');
		}

		$viewParams = array(
			'category' => $category,
			'canWatchCategory' => $categoryModel->canWatchCategory(),
			'containerCategory' => $containerCategory,
			'showCategory' => $showCategory,
			'media' => $media,
			'order' => $order,
			'defaultOrder' => $defaultOrder,
			'type' => $type,
			'typeFilter' => $type,
			'ignoredNames' => array_unique($ignoredNames),
			'mediaCount' => $mediaCount,
			'page' => $page <= 1 ? '' : $page,
			'perPage' => $perPage,
			'canAddMedia' => $canAddMedia,
			'canViewRatings' => $mediaModel->canViewRatings(),
			'canViewComments' => $this->_getCommentModel()->canViewComments(),
			'categoryBreadcrumbs' => $categoryBreadcrumbs,
			'inlineModOptions' => $inlineModOptions,
			'pageNavParams' => $pageNavParams,
			'requiresTranscode' => $requiresTranscode
		);

		return $this->_getSiteMediaWrapper($categoryId,
			$this->responseView('XenGallery_ViewPublic_Category_View', 'xengallery_category_view', $viewParams)
		);
	}

	public function actionWatch()
	{
		$mediaHelper = $this->_getMediaHelper();
		$categoryModel = $this->_getCategoryModel();

		if (!$categoryModel->canWatchCategory())
		{
			return $this->responseNoPermission();
		}

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$category = $mediaHelper->assertCategoryValidAndViewable($categoryId);

		/** @var $categoryWatchModel XenGallery_Model_CategoryWatch */
		$categoryWatchModel = $this->getModelFromCache('XenGallery_Model_CategoryWatch');

		if ($this->isConfirmedPost())
		{
			if ($this->_input->filterSingle('stop', XenForo_Input::STRING))
			{
				$notifyOn = 'delete';
			}
			else
			{
				$notifyOn = $this->_input->filterSingle('notify_on', XenForo_Input::STRING);
			}

			$sendAlert = $this->_input->filterSingle('send_alert', XenForo_Input::BOOLEAN);
			$sendEmail = $this->_input->filterSingle('send_email', XenForo_Input::BOOLEAN);
			$includeChildren = $this->_input->filterSingle('include_children', XenForo_Input::BOOLEAN);

			$categoryWatchModel->setCategoryWatchState(
				XenForo_Visitor::getUserId(), $categoryId,
				$notifyOn, $sendAlert, $sendEmail, $includeChildren
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/categories', $category),
				null,
				array('linkPhrase' => ($notifyOn != 'delete' ? new XenForo_Phrase('xengallery_unwatch_category') : new XenForo_Phrase('xengallery_watch_category')))
			);
		}
		else
		{
			$categoryWatch = $categoryWatchModel->getUserCategoryWatchByCategoryId(
				XenForo_Visitor::getUserId(), $categoryId
			);

			$viewParams = array(
				'category' => $category,
				'categoryWatch' => $categoryWatch,
				'categoryBreadcrumbs' => $this->_getCategoryModel()->getCategoryBreadcrumb($category)
			);

			return $this->responseView('XenGallery_ViewPublic_Category_Watch', 'xengallery_category_watch', $viewParams);
		}
	}

	public function actionAddTitles()
	{
		return $this->responseView('XenGallery_ViewPublic_Media_Add_Titles', 'xengallery_media_edit_titles');
	}

	public function actionMarkViewed()
	{
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

		$category = array();
		if ($categoryId)
		{
			$category = $this->_getMediaHelper()->assertCategoryValidAndViewable($categoryId);
		}

		if ($this->isConfirmedPost())
		{
			$mediaModel = $this->_getMediaModel();

			$visitor = XenForo_Visitor::getInstance();
			$fetchOptions = array(
				'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
				'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray())
			);
			if ($categoryId)
			{
				$fetchOptions['category_id'] = $categoryId;
			}

			$mediaIds = $mediaModel->getUnviewedMediaIds(XenForo_Visitor::getInstance()->getUserId(), $fetchOptions);

			if (sizeof($mediaIds))
			{
				$media = $mediaModel->getMediaByIds($mediaIds);

				foreach ($media AS $item)
				{
					$mediaModel->markMediaViewed($item);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirectIfNot(XenForo_Link::buildPublicLink('find-new'), XenForo_Link::buildPublicLink('find-new/media'))
			);
		}
		else
		{
			$viewParams = array(
				'category' => $category
			);

			return $this->responseView('XenGallery_ViewPublic_Media_MarkViewed', 'xengallery_mark_viewed', $viewParams);
		}
	}
}