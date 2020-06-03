<?php

// Settings for seat reservation.
//
// Copy this template to config.php and update it to your local needs.

// Optionally enable debug output.
define('DEBUG', false);

// Database host.
define('DB_HOST', 'localhost');

// Name of database.
define('DB_NAME', 'sitzplatzreservierung');

// Name of database user.
define('DB_USER', 'sitzplatzreservierung');

// Password of database user.
define('DB_PASS', 'mysecret');

// Delete reservations older than $MAX_AGE days.
define('MAX_AGE', 14);
// Allow reservations for the next MAX_DAYS days.
define('MAX_DAYS', 14);

// Short and long texts for reservations.
define('TEXTS', [
    ['a3', 'Bibliotheksbereich A3'],
    ['eh', 'Bibliotheksbereich Ehrenhof'],
    ['no', 'keine Reservierung']
]);

define('LIMIT', [
    'a3' => 50,
    'eh' => 70,
]);

// Test users.
define('TEST_USERS', [
    'testuser1',
    'testuser2',
    'testuser2',
]);

// MAGIC_PASSWORD is used as a master password if it is defined.
define('MAGIC_PASSWORD', 'mysecretpassword');

define('CLOSED', [
    'Sat', 'Sun',
    '2020-05-31',
    '2020-06-01',
    '2020-06-11',
]);

