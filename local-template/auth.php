<?php

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

    // Maybe check the uid for correct form here?
    // Adapt to your local needs!
    // if (!preg_match('/^[a-z_0-9]{0,8}$/', $uid) {
    //     return false;
    // }

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

