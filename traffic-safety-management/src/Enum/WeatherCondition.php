<?php

namespace App\Enum;

enum WeatherCondition: string {
    case CLEAR = 'clear';
    case CLOUDY = 'cloudy';
    case RAIN = 'rain';
    case HEAVY_RAIN = 'heavy_rain';
    case SNOW = 'snow';
    case ICE = 'ice';
    case FOG = 'fog';
    case WIND = 'wind';
    case OTHER = 'other';
}

