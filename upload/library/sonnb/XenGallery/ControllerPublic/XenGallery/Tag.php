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
class sonnb_XenGallery_ControllerPublic_XenGallery_Tag extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionAccept()
	{
		$tag = $this->_getTagOrError();

		if ($tag['tag_state'] === 'awaiting')
		{
			$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');
			$tagDw->setExistingData($tag, true);
			$tagDw->set('tag_state', 'accepted');
			$tagDw->save();
		}

		switch ($tag['content_type'])
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$redirectTarget = $this->_buildLink('gallery/albums', array('album_id' => $tag['content_id']));
				break;
			default:
				$content = $this->_getContentModel()->getContentById($tag['content_id']);
				$redirectTarget = $this->_buildLink('gallery/'.$content['content_type'].'s', $content);
				break;
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirectTarget
		);
	}

	public function actionDeny()
	{
		$tag = $this->_getTagOrError();

		if ($tag['tag_state'] === 'awaiting')
		{
			$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');
			$tagDw->setExistingData($tag, true);
			$tagDw->set('tag_state', 'rejected');
			$tagDw->save();
		}

		switch ($tag['content_type'])
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$redirectTarget = XenForo_Link::buildPublicLink('gallery/albums', array('album_id' => $tag['content_id']));
				break;
			default:
				$content = $this->_getContentModel()->getContentById($tag['content_id']);
				$redirectTarget = $this->_buildLink('gallery/'.$content['content_type'].'s', $content);
				break;
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirectTarget
		);
	}

	public function actionDelete()
	{
		$tag = $this->_getTagOrError();

		$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');
		$tagDw->setExistingData($tag, true);
		$tagDw->delete();

		switch ($tag['content_type'])
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$redirectTarget = XenForo_Link::buildPublicLink('gallery/albums', array('album_id' => $tag['content_id']));
				break;
			default:
				$content = $this->_getContentModel()->getContentById($tag['content_id']);
				$redirectTarget = $this->_buildLink('gallery/'.$content['content_type'].'s', $content);
				break;
		}

		if ($this->_request->isXmlHttpRequest() &&
				$tag['content_type'] !== sonnb_XenGallery_Model_Album::$contentType)
		{
			$this->_routeMatch->setResponseType('json');

			$content = $this->_getContentModel()->getContentById($tag['content_id']);

			if (!empty($photo['tagUsers']))
			{
				$content['tagUsers'] = $this->_getTagModel()->getTagsByContentId(
					$content['content_type'],
					$content['content_id'],
					array(
						'tag_state' => 'accepted'
					)
				);
			}

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Tag_Delete',
				'',
				array(
					'content' => $content
				)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirectTarget
			);
		}
	}

	protected function _getTagOrError()
	{
		if ($this->_input->inRequest('tag_id'))
		{
			$tagId = $this->_input->filterSingle('tag_id', XenForo_Input::UINT);
			$tag = $this->_getTagModel()->getTagById($tagId);
		}
		else
		{
			$content = $this->_input->filter(array(
				'content_id' => XenForo_Input::UINT,
				'content_type' => XenForo_Input::STRING
			));

			$tag = $this->_getTagModel()->getTagByContentUserId($content['content_type'], $content['content_id'], XenForo_Visitor::getUserId());
		}

		if (!$tag)
		{
			throw $this->_throwFriendlyNoPermission();
		}

		return $tag;
	}
}