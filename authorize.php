<?php

$ldap = array();

// Get authorization from remote LDAP server.
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    global $ldap;

    $authorized = false;
    $output = array();
    $ldap = array();
    $ldap['accountStatus'] = '';
    $ldap['cn'] = '';
    $ldap['sn'] = '';
    $ldap['mail'] = '';
    $ldap['mailRoutingAddress'] = '';
    $ldap['rGender'] = '';
    $ldap['userType'] = '';

    // TODO: use PHP SSH2 instead of command line execution.
    // The remote side runs ldapsearch, for example
    // ldapsearch -x -H ldaps://134.155.99.76:636/ -b dc=uni-mannheim,dc=de uid=name
    exec("ssh root@proxy.bib.uni-mannheim.de '$uid'", $output, $result);
    foreach ($output as $line) {
        $field = explode(':: ', $line);
        if (count($field) == 1){
            $field = explode(': ', $line);
        }
        if (count($field) == 2) {
            $ldap[$field[0]] = $field[1];
        }
    }

    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return 'master';
    }

    if ($ldap['accountStatus'] == 'active') {
        // Account is active.
        if (isset($ldap['proxyHash'])) {
            // LDAP server stores USER:Proxy-Server:PASSWORD as MD5 string.
            $md5 = md5($uid . ':Proxy-Server:' . $password);
	    // catch possible base46 encoding
	    if (strpos((base64_decode($ldap['proxyHash'])), "Proxy-Server:") === 0) {
               $md5 = base64_encode("Proxy-Server:" . $md5 . "\n");
	    } else {
	        $md5 = 'Proxy-Server:' . $md5;
	    }	    
            $authorized = $ldap['proxyHash'] == $md5;
        }
    }

    // TODO: return all information from LDAP. This includes several useful entries:
    // : extern (can be used for reduced rights)
    // userType: staff (can be used for extended rights)
    // cn: Vorname Nachname (can be used in e-mail)
    // sn: Nachname (can be used in e-mail)
    // rGender: W (can be used in e-mail)
    // mail: name@mail.uni-mannheim.de (can be used in e-mail)

    return $authorized;
}
