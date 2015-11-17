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
class sonnb_XenGallery_CacheRebuilder_Location extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * @return string|XenForo_Phrase
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('sonnb_xengallery_rebuild_locations');
	}

	/**
	 * @return bool
	 */
	public function showExitLink()
	{
		return true;
	}

	/**
	 * @param int $position
	 * @param array $options
	 * @param string $detailedMessage
	 * @return bool|int|string|true
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options['batch'] = max(1, isset($options['batch']) ? $options['batch'] : 10);

		/* @var sonnb_XenGallery_Model_Location $locationModel */
		$locationModel = XenForo_Model::create('sonnb_XenGallery_Model_Location');

		$locations = $locationModel->getLocationsWithoutCoordinate($position, $options['batch']);
		if (count($locations) < 1)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

        $db = XenForo_Application::getDb();
		/** @var sonnb_XenGallery_Model_Location $locationModel */
		$locationModel = XenForo_Model::create('sonnb_XenGallery_Model_Location');
		foreach ($locations AS $locationId => $location)
		{
			$position = $location['location_id'];

            try
            {
	            $client = XenForo_Helper_Http::getClient($locationModel->getGeocodeUrlForAddress($location['location_name']));
                $response = $client->request('GET');
                $response = @json_decode($response->getBody(), true);

	            if (empty($response['results'][0]))
	            {
		            continue;
	            }

                $address = $response['results'][0]['formatted_address'];
                $lat = $response['results'][0]['geometry']['location']['lat'];
                $lng = $response['results'][0]['geometry']['location']['lng'];

                $db->update(
                    'sonnb_xengallery_location',
                    array(
                        'location_name' => $address,
                        'location_lat' => $lat,
                        'location_lng' => $lng
                    ),
                    array(
                        'location_id = ?' => $location['location_id']
                    )
                );
            }
            catch(Exception $e)
            {
	            continue;
            }
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}