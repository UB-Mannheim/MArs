<?php
// ====================
// Settings for MArs
// ====================

// ====================
// Include likely customized files
// ====================

require_once 'auth.php';
require_once 'mail.php';

// ====================
// Database settings
// ====================

define('DB_HOST', 'MyDbHost');
define('DB_NAME', 'MyDbName');
define('DB_TABLE', 'MyDbTable');
define('DB_USER', 'MyDbUser');
define('DB_PASS', 'MyDbPass');

// ====================
// Settings for seat booking.
// ====================

// Closing days for which bookings are disabled (e.g. weekend, public holidays).
define('CLOSED', [
    'Sat', 'Sun',
    '2020-05-31',
    '2020-06-01',
    '2020-06-11',
]);

// TODO: is this actually used anywhere?
// Delete bookings older than $MAX_AGE days.
define('MAX_AGE', 28);

// Allow bookings for the next MAX_DAYS days.
define('MAX_DAYS', 14);

// Areas with longnames and seat limits per user type and day
// You should keep 'no' to allow booking cancellation
define('AREAS', [
    'area1' => [
        'name' => 'Area One',
        'limit' => [
            'member' => 80,
            'extern' => 20
        ],
    ],
    'area2' => [
        'name' => 'Area Two',
        'limit' => [
            'member' => 80,
            'extern' => 20
        ],
    ],
    'no' => 'keine Buchung'
]);

// Maximum number of open bookings per user.
// TODO add groups?
define('PERSONAL_LIMIT', 5);

// Booking can be restricted to certain groups of users.
define('USERGROUPS', [
    // Allowed user groups.
]);

// ====================
// Debug and test
// ====================

// Enable test.
define('TEST', false);

// Enable debug output.
define('DEBUG', false);

// Test users.
define('TEST_USERS', [
    'testuser1',
    'testuser2',
    'testuser2',
]);

// User notified in the trace function.
// Useful for debugging live systems.
define('TRACE_USER', '');

// ====================
// Misc
// ====================

// Optional text displayed below booking form.
define('HINTS', "");

