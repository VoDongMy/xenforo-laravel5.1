<?php

class XenGallery_Option_XenMedioRedirect
{
	public static function verifyOption(&$optionValue, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true;
		}

		$redirects = new XenGallery_Option_Redirects();

		$redirects->addOnId = 'EWRmedio';
		$redirects->route = 'ewrmedio';
		$redirects->replaceRoute = 'media';

		$redirects->verifyOptionForAddOn($optionValue, $dw, $fieldName);

		return true;
	}
}