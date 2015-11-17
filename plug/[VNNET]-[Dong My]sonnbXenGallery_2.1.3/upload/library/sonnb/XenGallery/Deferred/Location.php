<?php

class sonnb_XenGallery_Deferred_Location extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 10,
			'position' => 0,
			'positionRebuild' => false
		), $data);

        /* @var sonnb_XenGallery_Model_Location $locationModel */
        $locationModel = XenForo_Model::create('sonnb_XenGallery_Model_Location');

        $locations = $locationModel->getLocationsWithoutCoordinate($data['position'], $data['batch']);
        if (count($locations) < 1)
        {
            return false;
        }

        $db = XenForo_Application::getDb();
		/** @var sonnb_XenGallery_Model_Location $locationModel */
		$locationModel = XenForo_Model::create('sonnb_XenGallery_Model_Location');
        foreach ($locations AS $locationId => $location)
        {
            $data['position'] = $location['location_id'];

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

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('sonnb_xengallery_location');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}