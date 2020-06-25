<?php

// Settings for seat booking.
//
// Copy this template to config.php and update it to your local needs.

// Optionally enable debug output.
define('DEBUG', false);

// Database host.
define('DB_HOST', 'localhost');

// Name of database.
define('DB_NAME', 'sitzplatzreservierung');

// Name of database.
define('DB_TABLE', 'seatbookings');

// Name of database user.
define('DB_USER', 'sitzplatzreservierung');

// Password of database user.
define('DB_PASS', 'mysecret');

// Delete bookings older than $MAX_AGE days.
define('MAX_AGE', 28);
// Allow bookings for the next MAX_DAYS days. Update also HINTS text below.
define('MAX_DAYS', 14);

// Short and long texts for bookings.
define('TEXTS', [
    'a3' => 'Bibliotheksbereich A3',
    'eh' => 'Bibliotheksbereich Schloss Ehrenhof',
    'no' => 'keine Buchung'
]);

// Maximum number of daily bookings.
define('LIMIT', [
    'a3' => 80,
    'eh' => 80,
]);

// Maximum number of open bookings per user.
define('PERSONAL_LIMIT', 5);

// Booking can be restricted to certain groups of users.
define('USERGROUPS', [
    // Allowed user groups.
]);

// Test users.
define('TEST_USERS', [
    'testuser1',
    'testuser2',
    'testuser2',
]);

// MAGIC_PASSWORD is used as a master password if it is defined.
// The master password allows viewing and changing bookings of any user
// and can give access to additional functionality like reports.
define('MAGIC_PASSWORD', 'mysecretpassword');

// Special closing days (public holidays, ...).
define('CLOSED', [
    'Sat', 'Sun',
    '2020-05-31',
    '2020-06-01',
    '2020-06-11',
]);

// The global array `$ldap` is used to hold user specific information.
// That information can come from LDAP or other sources.
$ldap = array();

// The following functions should be adopted to the local requirements.

// Fill $ldap array with user specific information.
function get_ldap($uid, &$ldap) {
    // The following information is directly extracted from LDAP.
    $ldap = array();
    $ldap['sn'] = '';
    $ldap['givenName'] = '';
    $ldap['mail'] = '';
}

// Get authorization (for example from LDAP server).
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return 'master';
    }
    // For the demo we just allow any uid with a matching password.
    return $uid == $password;
}

// Return full name of user for display in the web gui.
function get_usertext() {
    global $ldap, $master;
    $sn = $ldap['sn'];
    $givenName = $ldap['givenName'];
    $usertext = "$sn, $givenName";
    return $usertext;
}

require_once 'Swift/swift_required.php';

// Mail implementation using Swift Mailer.
// See documentation: https://swiftmailer.symfony.com/docs/introduction.html
function sendmail($uid, $text) {
    $text = "Diese Reservierungen sind für die Benutzerkennung $uid vorgemerkt:\n\n" . $text;

    // Sendmail for transport.
    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    $from = ['user@example.org' => 'Demo Sender'];
    $to = $ldap['mail'];

    // Create a message
    $message = (new Swift_Message('Sitzplatzreservierung'))
        ->setFrom($from)
        ->setTo($to)
        ->setBody($text)
    ;

    // Send the message
    $result = $mailer->send($message);
}
