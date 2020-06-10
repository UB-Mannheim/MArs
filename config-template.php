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
    'no' => _('bookings.none')
]);

define('LIMIT', [
    'a3' => 80,
    'eh' => 80,
]);

define('PERSONAL_LIMIT', 5);

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
define('MAGIC_PASSWORD', 'mysecretpassword');

define('CLOSED', [
    'Sat', 'Sun',
    '2020-05-31',
    '2020-06-01',
    '2020-06-11',
]);

// Get authorization from remote LDAP server.
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return true;
    }
    return $uid == $password;
}

require_once 'Swift/swift_required.php';

// Mail implementation using Swift Mailer.
// See documentation: https://swiftmailer.symfony.com/docs/introduction.html
function sendmail($uid, $email, $text) {
    $body = sprintf(_('mail.salutation'), $uid);
    $body .= "\n\n" . sprintf(_('mail.bookings'), $uid);
    $body .= "\n\n" . $text;
    $body .= "\n" . _('mail.link');
    $body .= "\nhttps://" .$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?uid=" . $uid;
    $body .= "\n\n" . _('mail.greetings');

    // Sendmail for transport.
    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    $from = ['user@example.org' => 'Demo Sender'];

    // Create a message
    $message = (new Swift_Message(_('mail.subject')))
        ->setFrom($from)
        ->setTo($email)
        ->setBody($body)
    ;

    // Send the message
    $result = $mailer->send($message);
}
