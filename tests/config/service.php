<?php

return [
    'password' => env('TRAFFIC_PASSWORD', ''),
    'login' => env('TRAFFIC_LOGIN', ''),
    'secret' => env('TRAFFIC_SECRET', ''),
    'api_url' => env('TRAFFIC_API_URL', 'http://api.traffic-crm.ru'),
    'importer_api_url' => env('TRAFFIC_IMPORTER_API_URL', 'http://skoda-services.api.traffic-crm.ru')
];