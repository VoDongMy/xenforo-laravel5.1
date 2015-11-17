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
class sonnb_XenGallery_XenForo_ControllerPublic_Account extends XFCP_sonnb_XenGallery_XenForo_ControllerPublic_Account
{

	public function actionXengallery()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('account/xengallery-privacy')
		);
	}

	public function actionXengalleryPrivacy()
	{
        $xenOptions = XenForo_Application::getOptions();
		$canCustomizeWatermark = $this->_getGalleryModel()->canCustomizeWatermark();

		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'album_allow_view' => XenForo_Input::STRING,
				'album_allow_comment' => XenForo_Input::STRING,
				'album_allow_download' => XenForo_Input::STRING,
				'album_allow_add_photo' => XenForo_Input::STRING,
				'album_allow_add_video' => XenForo_Input::STRING,

				'photo_allow_view' => XenForo_Input::STRING,
				'photo_allow_comment' => XenForo_Input::STRING,

				'video_allow_view' => XenForo_Input::STRING,
				'video_allow_comment' => XenForo_Input::STRING,

				'allow_tagging' => XenForo_Input::STRING,
				'direct_tagging' => XenForo_Input::UINT
			));

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$writer->setExistingData(XenForo_Visitor::getUserId());
			$writer->set('xengallery', $input);

            if ($canCustomizeWatermark)
            {
                $watermarkOptions = $this->_input->filter(array(
                    'text' => XenForo_Input::STRING,
                    'textSize' => XenForo_Input::UINT,
                    'textColor' => XenForo_Input::STRING,
                    'bgColor' => XenForo_Input::STRING,

                    'position' => XenForo_Input::STRING,
                    'margin' => XenForo_Input::UINT
                ));

	            if (!empty($watermarkOptions['text']))
	            {
		            $isSave = true;
		            if (empty($watermarkOptions['textSize']))
		            {
			            $watermarkOptions['textSize'] = 10;
		            }

		            if (!sonnb_XenGallery_Option_Check::isTextColorValid($watermarkOptions['textColor']))
		            {
			            $writer->error(new XenForo_Phrase('sonnb_xengallery_watermark_text_color_not_valid'), 'sonnb_xengallery_watermark');
			            $isSave = false;
		            }
		            if (!sonnb_XenGallery_Option_Check::isBgColorValid($watermarkOptions['bgColor']))
		            {
			            $writer->error(new XenForo_Phrase('sonnb_xengallery_watermark_bg_color_not_valid'), 'sonnb_xengallery_watermark');
			            $isSave = false;
		            }

		            if ($isSave)
		            {
                        $writer->set('sonnb_xengallery_watermark', $watermarkOptions);
		            }
	            }
	            else
	            {
		            $writer->set('sonnb_xengallery_watermark', array());
	            }
            }

			$writer->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/xengallery-privacy'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance()->toArray();

			if (empty($visitor['xengallery']))
			{
				$visitor['xengallery'] = array(
					'album_allow_view' => $xenOptions->sonnbXG_albumPrivacyView,
					'album_allow_comment' => $xenOptions->sonnbXG_albumPrivacyComment,
					'album_allow_download' => $xenOptions->sonnbXG_albumPrivacyDownload,
					'album_allow_add_photo' => $xenOptions->sonnbXG_albumPrivacyAdd,
					'album_allow_add_video' => $xenOptions->sonnbXG_albumPrivacyAddVideo,

					'photo_allow_view' => $xenOptions->sonnbXG_photoPrivacyView,
					'photo_allow_comment' => $xenOptions->sonnbXG_photoPrivacyComment,

					'video_allow_view' => $xenOptions->sonnbXG_videoPrivacyView,
					'video_allow_comment' => $xenOptions->sonnbXG_videoPrivacyComment,

					'allow_tagging' => 'everyone',
					'direct_tagging' => true
				);
			}

			if ($canCustomizeWatermark && empty($visitor['sonnb_xengallery_watermark']))
			{
				$visitor['sonnb_xengallery_watermark'] = array(
                    'text' => $xenOptions->sonnbXG_watermark_text,
                    'textSize' => $xenOptions->sonnbXG_watermark_textSize,
                    'textColor' => $xenOptions->sonnbXG_watermark_textColor,
                    'bgColor' => $xenOptions->sonnbXG_watermark_textColor,
                    'position' => $xenOptions->sonnbXG_watermark_position,
                    'margin' => $xenOptions->sonnbXG_watermark_margin
				);
			}

			return $this->_getWrapper(
				'account', 'xengallery-privacy',
				$this->responseView(
					'sonnb_XenGallery_ViewPublic_Account_Privacy',
					'sonnb_xengallery_account_privacy',
					array(
						'visitor' => $visitor,
                        'canCustomizeWatermark' => $canCustomizeWatermark
					)
				)
			);
		}
	}

	public function actionXengalleryAlert()
	{
		if ($this->_request->isPost())
		{
			$alert = $this->_input->filterSingle('alert', array(XenForo_Input::UINT, 'array' => true));

			$optOuts = array();
			foreach (array_keys($this->_input->filterSingle('alertSet', array(XenForo_Input::UINT, 'array' => true))) AS $optOut)
			{
				if (empty($alert[$optOut]))
				{
					$optOuts[$optOut] = $optOut;
				}
			}

			$specialCallbacks = array('setAlertOptOuts' => $optOuts);

			$this->_saveVisitorSettings(array(), $errors, $specialCallbacks);

			if (!empty($errors))
			{
				return $this->responseError($errors);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/xengallery-alert'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'alertOptOuts' => $this->getModelFromCache('XenForo_Model_Alert')->getAlertOptOuts(null, true)
			);

			return $this->_getWrapper(
				'account', 'xengallery-alert',
				$this->responseView(
					'sonnb_XenGallery_ViewPublic_Account_Alert',
					'sonnb_xengallery_account_alert',
					$viewParams
				)
			);
		}
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}
}