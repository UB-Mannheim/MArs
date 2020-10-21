<?php

// MAGIC_PASSWORD is used as a master password if it is defined.
// The master password allows viewing and changing bookings of any user
// and can give access to additional functionality like reports.
define('MAGIC_PASSWORD', 'mysecretpassword');

// Gather user information from your local source, e.g. an LDAP server
function get_user_info($uid) {
    // For demo we just return static values
    $user = array();
    $user['id'] = $uid;
    $user['mail'] = "user@example.org";
    $user['surname'] = "Doe";
    $user['givenname'] = "Jane";
    $user['gender'] = "W";
    $user['is_member'] = True;
    return $user;
}

// Authorize user
function get_authorization($uid, $password) {
    global $user;

    $authorized = false;
    $user = get_user_info($uid);

    // Do some checks with your user data here
    // For the demo we just allow any uid with a matching password...
    $authorized = $uid == $password;

    // ... or the magic password.
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return 'master';
    }

    return $authorized;
}

