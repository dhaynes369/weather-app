<?php

$current = [
    'temperature_2m',
    'apparent_temperature',
    'dew_point_2m',
    'wet_bulb_temperature_2m',
    'wind_speed_10m',
    'wind_direction_10m',
    'wind_gusts_10m',
    'precipitation',
    'rain',
    'showers',
    'snowfall',
    'snow_depth',
    'relative_humidity_2m',
    'pressure_msl',
    'surface_pressure',
    'cloud_cover',
    'cloud_cover_low',
    'cloud_cover_mid',
    'cloud_cover_high',
    'visibility',
    'uv_index',
    'uv_index_clear_sky',
    'sunshine_duration',
    'weather_code',
    'is_day',
];

return [

    // Weather data provider endpoints.
    'provider' => 'open-meteo',

    'endpoints' => [
        'forecast' => 'https://api.open-meteo.com/v1/forecast',
        'geocoding' => 'https://geocoding-api.open-meteo.com/v1/search',
    ],

    // HTTP connection and retry configuration.
    'http' => [
        'connect_timeout' => 2,
        'timeout' => 5,
        'retries' => 1,
        'retry_delay_ms' => 250,
        'retryable_statuses' => [500, 502, 503, 504],
        'rate_limit_cooldown' => 10,
    ],

    // Cache lifetimes in seconds.
    'cache' => [
        'schema_version' => 2,
        'forecast_fresh' => 900,
        'forecast_retention' => 7200,
        'geocoding' => 86400,
        'geocoding_empty' => 300,
    ],

    // Location search configuration.
    'geocoding' => [
        'minimum_search_length' => 3,
        'maximum_search_length' => 100,
        'result_limit' => 10,
        'language' => 'en',
        'debounce_ms' => 500,
    ],

    // Forecast acquisition and display configuration.
    'forecast' => [
        'days' => 14,
        'default_display_days' => 7,
        'temperature_unit' => 'celsius',
        'wind_speed_unit' => 'mph',
        'precipitation_unit' => 'mm',
        'timezone' => 'auto',
        'timeformat' => 'unixtime',

        'current' => $current,

        'hourly' => [
            ...$current,
            'precipitation_probability',
        ],

        'daily' => [
            'temperature_2m_max',
            'temperature_2m_min',
            'temperature_2m_mean',
            'apparent_temperature_max',
            'apparent_temperature_min',
            'apparent_temperature_mean',
            'wind_speed_10m_max',
            'wind_speed_10m_mean',
            'wind_speed_10m_min',
            'wind_gusts_10m_max',
            'wind_direction_10m_dominant',
            'precipitation_sum',
            'rain_sum',
            'showers_sum',
            'snowfall_sum',
            'precipitation_hours',
            'precipitation_probability_max',
            'precipitation_probability_mean',
            'precipitation_probability_min',
            'relative_humidity_2m_mean',
            'relative_humidity_2m_max',
            'relative_humidity_2m_min',
            'cloud_cover_mean',
            'visibility_mean',
            'pressure_msl_mean',
            'sunrise',
            'sunset',
            'daylight_duration',
            'sunshine_duration',
            'uv_index_max',
            'uv_index_clear_sky_max',
            'weather_code',
        ],
    ],

];
