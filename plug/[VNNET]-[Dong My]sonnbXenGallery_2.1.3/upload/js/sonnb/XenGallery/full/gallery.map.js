
/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
!function($, window, document, _undefined) {
    XenForo.XenGalleryLoadMap = function ($container)
    {
        this.__construct($container);
    }

    XenForo.XenGalleryLoadMap.prototype =
    {
        __construct: function($container)
        {
            var script;

            script= document.createElement('script');
            script.type = 'text/javascript';
            script.src = $container.data('loadurl') + '&callback=loadMap';
            document.body.appendChild(script);

            script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = 'js/sonnb/XenGallery/jquery.geocomplete.js';
            document.body.appendChild(script);

            script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = 'js/sonnb/XenGallery/gallery.map.js';
            document.body.appendChild(script);

            window.loadMap = function()
            {
                var $inputLat = $('input[name="location_lat"]', $container),
                    $inputLng = $('input[name="location_lng"]', $container),
                    $inputAddress = $('#ctrl_location_edit', $container),
                    name = $inputAddress.val(),
                    lat = $inputLat.val(),
                    lng = $inputLng.val(),
                    marker,
                    geocoder = new google.maps.Geocoder(),
                    map = new google.maps.Map(document.getElementById("mapOverlay"), {
                        center: new google.maps.LatLng(lat.length ? lat : 34, lng.length ? lng : -118),
                        zoom: 8,
                        mapTypeId: google.maps.MapTypeId.ROADMAP
                    });

                if ($inputAddress.val())
                {
                    marker = new google.maps.Marker({
                        map: map,
                        position: new google.maps.LatLng(lat.length ? lat : 34, lng.length ? lng : -118),
                        title: name
                    });
                }

                $inputAddress.geocomplete().bind("geocode:result", function(e, results){
                    e.preventDefault();

                    if (results.geometry.location.lat())
                    {
                        $inputLat.val(results.geometry.location.lat());
                    }
                    if (results.geometry.location.lng())
                    {
                        $inputLng.val(results.geometry.location.lng());
                    }

                    if (marker)
                    {
                        marker.setMap(null);
                    }

                    marker = new google.maps.Marker({
                        map: map,
                        position: results.geometry.location,
                        title: results.formatted_address
                    });

                    map.setCenter(results.geometry.location);

                    return false;
                });

                google.maps.event.addListener(map, 'click', function(event) {
                    if (marker)
                    {
                        marker.setMap(null);
                    }

                    marker = new google.maps.Marker({
                        position: event.latLng,
                        map: map
                    });

                    if (event.latLng.lat())
                    {
                        $inputLat.val(event.latLng.lat());
                    }
                    if (event.latLng.lng())
                    {
                        $inputLng.val(event.latLng.lng());
                    }

                    geocoder.geocode({ latLng: event.latLng }, function (results, status) {
                        if (status == google.maps.GeocoderStatus.OK)
                        {
                            $inputAddress.val(results[0].formatted_address);
                        }
                    });
                });
            }
        }
    }

    XenForo.register('form.locationForm', 'XenForo.XenGalleryLoadMap');
}(jQuery, this, document);