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
class sonnb_XenGallery_WidgetRenderer_Comment extends WidgetFramework_WidgetRenderer
{
	/**
	 * @return array
	 */
	protected function _getConfiguration ()
	{
		return array(
			'name' => '[Gallery] Top Comments',
			'useCache' => true,
			'useUserCache' => true,
        	'cacheSeconds' => 300,
			'options' => array(
				'type' => XenForo_Input::STRING,
				'wrapper' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
				'message_limit' => XenForo_Input::UINT,

				'thumbnail_width' => XenForo_Input::UINT,
				'thumbnail_height' => XenForo_Input::UINT,

				'disableWrapper' => XenForo_Input::UINT
			),
		);
	}

	public function useWrapper(array $widget)
	{
		return $widget['options']['disableWrapper'];
	}

	/**
	 * @return string
	 */
	protected function _getOptionsTemplate ()
	{
		return 'sonnb_xengallery_widget_comment';
	}

	/**
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 * @return string
	 */
	protected function _getRenderTemplate (array $widget, $positionCode, array $params)
	{
		return 'sonnb_xengallery_widget_comment';
	}

	/**
	 * @param XenForo_Template_Abstract $template
	 * @return bool|void
	 */
	protected function _renderOptions (XenForo_Template_Abstract $template)
	{
		//Set params for configuration template.
	}

	/**
	 * @param $optionKey
	 * @param $optionValue
	 * @return bool
	 * @throws XenForo_Exception
	 */
	protected function _validateOptionValue ($optionKey, &$optionValue)
	{
		$validType = array('new', 'mostLiked', 'random', 'recentlyLiked');

		switch ($optionKey)
		{
			case 'type':
				if (!in_array($optionValue, $validType))
				{
					throw new XenForo_Exception(new XenForo_Phrase('sonnb_xengallery_invalid_type'), true);
				}
				break;
			case 'wrapper':
				if (empty($optionValue))
				{
					$optionValue = false;
				}
				else
				{
					$optionValue = true;
				}
				break;
			case 'limit':
				if (empty($optionValue))
				{
					$optionValue = 10;
				}
				break;
			case 'message_limit':
				if (empty($optionValue))
				{
					$optionValue = 100;
				}
				break;
		}
		
		return true;
	}

	/**
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 * @param XenForo_Template_Abstract $template
	 * @return string
	 */
	protected function _render (array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
	{
		$core = WidgetFramework_Core::getInstance();
		/* @var $commentModel sonnb_XenGallery_Model_Comment */
		$commentModel = $core->getModelFromCache('sonnb_XenGallery_Model_Comment');
		/* @var $galleryModel sonnb_XenGallery_Model_Gallery */
		$galleryModel = $core->getModelFromCache('sonnb_XenGallery_Model_Gallery');

		if (!$galleryModel->canViewGallery())
		{
			return;
		}

		$conditions = array(
			'comment_state' => 'visible'
		);

		$fetchOptions = array(
			'orderDirection' => 'desc',
			'limit' => $widget['options']['limit']*3,
			'join' => sonnb_XenGallery_Model_Comment::FETCH_USER
		);

		switch ($widget['options']['type'])
		{
			case 'new':
				$fetchOptions['order'] = 'comment_date';
				break;
			case 'random':
				$fetchOptions['order'] = 'random';
				break;
			case 'recentlyLiked':
				$fetchOptions['order'] = 'recently_liked';
				break;
			case 'mostLiked':
			default:
				$fetchOptions['order'] = 'likes';
				break;
		}

		$comments = $commentModel->getComments($conditions, $fetchOptions);
		$comments = $commentModel->prepareComments($comments, $fetchOptions);

		if (empty($comments))
		{
			return;
		}

		$comments = $galleryModel->groupContentsContentType($comments);
		$comments = array_slice($comments, 0, $widget['options']['limit']);

		foreach ($comments as &$comment)
		{
			switch ($comment['content_type'])
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$content =& $comment['content']['cover'];
					break;
				case 'content':
				case sonnb_XenGallery_Model_Photo::$contentType:
				case sonnb_XenGallery_Model_Video::$contentType:
					$content =& $comment['content'];
					break;
			}

			if (!empty($content['medium_height']) && !empty($content['medium_width'])
					&& !empty($widget['options']['thumbnail_width']) && !empty($widget['options']['thumbnail_height']))
			{
				$widthRatio = $content['medium_width']/$widget['options']['thumbnail_width'];
				$heightRatio = $content['medium_height']/$widget['options']['thumbnail_height'];

				if ($widthRatio == $heightRatio)
				{
					$content['medium_width'] = $widget['options']['thumbnail_width'];
					$content['medium_height'] = $widget['options']['thumbnail_height'];
					$content['style'] = '';
				}
				elseif ($widthRatio < $heightRatio)
				{
					$content['medium_height'] = $content['medium_height']/($content['medium_width']/$widget['options']['thumbnail_width']);
					$content['medium_width'] = $widget['options']['thumbnail_width'];
					$content['style'] = 'top: -'.(($content['medium_height']-$widget['options']['thumbnail_height'])/2).'px;';
				}
				else
				{
					$content['medium_width'] = $content['medium_width']/($content['medium_height']/$widget['options']['thumbnail_height']);
					$content['medium_height'] = $widget['options']['thumbnail_height'];
					$content['style'] = 'left: -'.(($content['medium_width']-$widget['options']['thumbnail_width'])/2).'px;';
				}
			}
		}
		
		$template->setParams(array(
			'widget' => $widget,
			'comments' => $comments
		));

		if (method_exists('WidgetFramework_Template_Trojan', 'WidgetFramework_setRequiredExternals'))
		{
			$externalRequires = array(
				'css' => array('sonnb_xengallery_widget_comment')
			);

			WidgetFramework_Template_Trojan::WidgetFramework_setRequiredExternals($externalRequires);
		}
		
		return $template->render();
	}

	/**
	 * @param array $widget
	 * @return array
	 */
	protected function _getRequiredExternal (array $widget)
	{
		$return = array(
			array('css', 'sonnb_xengallery_widget_comment')
		);
		
		return $return;
	}
}