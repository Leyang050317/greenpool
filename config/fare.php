<?php

return [
    'base_fare' => (float) env('FARE_BASE_FARE', 2.00),
    'minimum_fare' => (float) env('FARE_MINIMUM_FARE', 3.00),
    'maintenance_rate_per_km' => (float) env('FARE_MAINTENANCE_RATE_PER_KM', 0.20),

    // Defaults use Malaysia's unsubsidised pump prices. They can be updated in
    // .env without changing application code when the weekly price changes.
    'ron95_price' => (float) env('FARE_RON95_PRICE', 3.82),
];
