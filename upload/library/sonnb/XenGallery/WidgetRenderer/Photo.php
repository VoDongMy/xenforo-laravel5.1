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
class sonnb_XenGallery_WidgetRenderer_Photo extends sonnb_XenGallery_WidgetRenderer_Content
{
	/**
	 * @return array
	 */
    protected function _getConfiguration ()
    {
        $configuration = parent::_getConfiguration();

        $configuration['name'] = '[Gallery] Top Photos';

        return $configuration;
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @return string
     */
    protected function _getRenderTemplate (array $widget, $positionCode, array $params)
    {
        return 'sonnb_xengallery_widget_photo';
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
		if (!$this->_canViewGallery())
		{
			return;
		}

		$photoModel = $this->_getPhotoModel();

		$conditions = array(
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
			'content_state' => 'visible'
		);

		$this->_prepareConditions($widget, $conditions, $fetchOptions);

		$photos = $photoModel->getContents($conditions, $fetchOptions);
		$photos = $photoModel->prepareContents($photos, $fetchOptions);

		$this->_prepareContents($widget, $photos, $photoModel);

		if (empty($photos))
		{
			return;
		}

		$template->setParam('photos', null);
		$template->setParams(array(
			'widget' => $widget,
			'contents' => $photos
		));

		if (method_exists('WidgetFramework_Template_Trojan', 'WidgetFramework_setRequiredExternals'))
		{
			$externalRequires = array(
				'css' => array('sonnb_xengallery_widget_photo')
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
			array('css', 'sonnb_xengallery_widget_photo')
		);

		return $return;
	}
}