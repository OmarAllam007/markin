<?php

return [
    /*
     * ponytail: integrity gates for mobile punches. Reject what is provably fake,
     * only flag what is merely suspicious — a rooted phone or an emulator is
     * recorded but still allowed, so a dev build or a power user is not locked out.
     * Flip the flags per deployment once you know your workforce.
     */
    'max_gps_accuracy_meters' => (int) env('PUNCH_MAX_GPS_ACCURACY', 100),
    'reject_mocked_location' => (bool) env('PUNCH_REJECT_MOCKED', true),
    'reject_rooted_devices' => (bool) env('PUNCH_REJECT_ROOTED', false),
    'reject_emulators' => (bool) env('PUNCH_REJECT_EMULATORS', false),
];
