<?php

return [
    'start_early_minutes' => (int) env('TRIP_START_EARLY_MINUTES', 30),
    'emergency_live_location_max_age_seconds' => (int) env('EMERGENCY_LIVE_LOCATION_MAX_AGE_SECONDS', 60),
];
