<?php

class XenGallery_Option_StatisticsBlock
{
	public static function verifyOption(array &$values, XenForo_DataWriter $dw, $fieldName)
	{
		if (!$values)
		{
			XenForo_Application::setSimpleCacheData('xengalleryStatisticsCache', false);
		}

		return true;
	}
}