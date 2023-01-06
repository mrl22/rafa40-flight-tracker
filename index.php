<?php


require '_config.php';

if ($_GET['username']) {

    // custom, lat, lon, alt, bearing, speed

    $user = $db::table('users')->where('user_id', (int)$_GET['username'])->first();

    if ($user->user_id > 0) {
        $data = array(
            'user_id' => $user->user_id,
            'track_lat' => $_GET['latitude'],
            'track_lon' => $_GET['longitude'],
            'track_alt' => $_GET['extrainfo'],
            'track_bearing' => $_GET['direction'],
            'track_speed' => $_GET['speed']
        );
        //file_put_contents('geo.txt', print_r($data, true).PHP_EOL, FILE_APPEND);


        $db::table('users')
            ->where('user_id', $user->user_id)
            ->update(array(
                'user_prevgps' => $user->user_gps,
                'user_gps' => json_encode($data)
            ));
        $db::table('tracking')->insert($data);
    }


    echo 'success';
    exit;
}

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

if ($_GET['get_markers']) {
    echo json_encode(get_markers((int)$_GET['get_markers']), true);
    exit;
}
function get_markers($count=1)
{
    global $db;

    $expire = 60 * 60; // One hour

    $latest = $db::table('users')
//        ->where('timestamp', '>', time()-$expire)
        ->get();

    $markers = array();
    if ($latest->count()) {
        foreach ($latest as $row) {
            if ($count<2) $user_gps = json_decode($row->user_prevgps);
            else $user_gps = json_decode($row->user_gps);

            $history = $db::table('tracking')
                ->where('user_id', $row->user_id)
                ->skip(1)
                ->take(10)
                ->orderBy('track_id', 'desc')
                ->get();
            $hist = array();
            foreach ($history as $h) {
                $hist[] = array(
                    'lat' => $h->track_lat,
                    'lon' => $h->track_lon,
//                    'alt' => $h->track_alt,
//                    'bearing' => $h->track_bearing,
//                    'speed' => $h->track_speed
                );
            }

            if ($user_gps->user_id)
                $markers[$row->user_id] = array(
                    'id' => $row->user_id,
                    'color' => $row->user_color,
                    'lat' => $user_gps->track_lat,
                    'lon' => $user_gps->track_lon,
//                    'timestamp' => time(),
                    'name' => $row->user_name,
                    'alt' => $user_gps->track_alt,
                    'bearing' => $user_gps->track_bearing,
                    'speed' => $user_gps->track_speed,
                    'ago' => \Carbon\Carbon::parse($row->user_modified)->diffForHumans(\Carbon\Carbon::now(),  ['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]),
                    'history' => $hist
                );
        }
    }

    return $markers;

}
?><!DOCTYPE html>
<html>
<head>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-138087946-1"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-138087946-1');
</script>
    <title>RAFA40 Live Tracker - </title>
    <link rel="shortcut icon" type="image/png" href="raf.png"/>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

    <style>
        html, body {
            height: 100%;
            padding: 0;
            margin: 0;
        }

        #map_wrapper {
            height: 100%;
        }

        #map_canvas {
            width: 100%;
            height: 100%;
        }

        .control {
            position: absolute;
            bottom: 0;
            left: 0;
            font-size: 1em;
            font-family: Helvetica, Arial, sans-serif;
            z-index: 99999;
        }

        .control > * {
            float: left;
            height: 32px;
            vertical-align: middle;
            margin-right: 5px;
            padding: 10px 10px 0 10px;
            background: black;
            z-index: 9999;
            color: white;
        }

        .control a.donate {
            background: rgba(205, 18, 49, 1);
            text-decoration: none;
        }

        .control a.info {
            background: #29487d;
            text-decoration: none;
        }

        .control input {
            vertical-align: middle;
            transform: scale(2);
            margin-top: 4px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
<div class="control">
    <label for="follow"><input name="follow" id="follow" type="checkbox" value="1" checked/> Follow</label>
    <a class="info" href="https://www.facebook.com/RAFA40paramotor/" target="_blank">
        More Info
    </a>
    <a class="donate" href="https://www.justgiving.com/fundraising/rafa40" target="_blank">
        Donate
    </a>
</div>

<div id="map_wrapper">
    <div id="map_canvas"></div>
</div>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDn0ttMK5OWmjgNTAxqUDX8uAaE_ZC61VU"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marker-animate-unobtrusive/0.2.8/vendor/markerAnimate.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marker-animate-unobtrusive/0.2.8/SlidingMarker.min.js"></script>
<script>

    var map, markers = new Object(), marker_data = new Object(), dots = new Object(), infoWindow, openWindow;
    var refresh = 60; // seconds
    var refresh_count = 1;

    function refresh_markers() {
        jQuery.get({
            url: '',
            cache: false,
            data: {'get_markers': refresh_count },
            success: function (data) {
                marker_data = data;

                // Clear Trail
                for (var i in dots) {
                    dots[i].setMap(null);
                }
                dots = [];

                for (var i in marker_data) {

                    // Trail
                    for (var x in marker_data[i].history) {
                        var pos = new google.maps.LatLng(marker_data[i].history[x].lat, marker_data[i].history[x].lon);
                        var dot = new google.maps.Marker({
                            map: map,
                            position: pos,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                fillColor: '#'+marker_data[i].color,
                                fillOpacity: 0.6 - (x * 0.5),
                                strokeWeight: 0,
                                scale: 8 - (x * 0.5)
                            }
                        });
                        dots.push(dot);
                    }


                    var position = new google.maps.LatLng(marker_data[i].lat, marker_data[i].lon);

                    var details = '<div class="marker">' +
                        '<strong>' + marker_data[i].name + '</strong><br />'
                        + '<span>Speed: ' + marker_data[i].speed + '</span> mph<br />'
                        + '<span>Altitude: ' + marker_data[i].alt + '</span> ft<br />'
                        //+ '<span>Last Position: ' + marker_data[i].ago + '</span><br />'
                        + '</div>';

                    if (typeof markers[i] !== "undefined") {

                        markers[i].setPosition(position);

                        //update info window
                        if (i === openWindow) {

                            infoWindow.setContent(details);
                        }

                    } else {
                        // create new marker
                        markers[i] = new SlidingMarker({
                            duration: refresh * 1000,
                            easing: 'linear',
                            position: position,
                            map: map,
                            icon: 'https://devfwd.com/geo/marker_' + i + '.png?' + Math.random(),
                            id: i,
                            title: marker_data[i].name
                        });


                        google.maps.event.addListener(markers[i], 'click', (function (i) {
                            return function () {
var details = '<div class="marker">' +
                        '<strong>' + marker_data[i].name + '</strong><br />'
                        + '<span>Speed: ' + marker_data[i].speed + '</span> mph<br />'
                        + '<span>Altitude: ' + marker_data[i].alt + '</span> ft<br />'
                        //+ '<span>Last Position: ' + marker_data[i].ago + '</span><br />'
                        + '</div>';

                                infoWindow.setContent(details);
                                infoWindow.open(map, markers[i]);
                                openWindow = i;
                            }
                        })(i));
                    }

                }
		refresh_count++;
		if (refresh_count==2) refresh_markers();
            },
            dataType: 'json'
        });
    }

    function follow_markers() {
        if (document.getElementById('follow').checked === false) return;
        var bounds = new google.maps.LatLngBounds();
        for (var i in markers) {
            bounds.extend(markers[i].getAnimationPosition());
        }
        if (Object.keys(markers).length > 0) map.fitBounds(bounds, {top: 30, right: 30, left: 30, bottom: 50});
    }

    function map_initialize() {


        var mapOptions = {
            mapTypeId: 'roadmap',
            maxZoom: 17,
            streetViewControl: false,
        };

        map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);

        map.setCenter({lat: 52.8, lng: -0.7});
        map.setZoom(8);
        // if (typeof window.orientation !== 'undefined') {
        //     // mobile
        //     map.setZoom(8);
        // } else {
        //     // desktop
        //     map.setZoom(9);
        // }


        refresh_markers();

        // KML
        var src = 'https://devfwd.com/geo/rafa40-route.kml?' + Math.random();
        var kmlLayer = new google.maps.KmlLayer(src, {
            suppressInfoWindows: false,
            preserveViewport: true,
            map: map
        });
        // kmlLayer.addListener('click', function(kmlEvent) {
        //     var details = kmlEvent.featureData.name;
        //     alert(details);
        // });

        infoWindow = new google.maps.InfoWindow();

        // map.addListener('zoom_changed', disableCheck );
        map.addListener('drag', disableCheck);

        setInterval('refresh_markers()', refresh * 1000);
        setInterval('follow_markers()', 100);


    }

    function disableCheck () {
        document.getElementById('follow').checked = false;
    }

    map_initialize();
</script>
</body>
</html>
