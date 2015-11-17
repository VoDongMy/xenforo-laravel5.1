<?php

class XenGallery_Option_XFRUserAlbumsRedirect
{
	public static function verifyOption(&$optionValue, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true;
		}

		$redirects = new XenGallery_Option_Redirects();

		$redirects->addOnId = 'XfRuUserAlbums';
		$redirects->route = 'xfruseralbums';
		$redirects->replaceRoute = 'useralbums';

		$redirects->verifyOptionForAddOn($optionValue, $dw, $fieldName);

		return true;
	}
}