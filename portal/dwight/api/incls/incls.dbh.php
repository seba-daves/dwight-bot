<?php
    //CONFIGURE THIS PART

    /*MYSql Section
    -------------------------
    */
    /*MYSql Server IP/FQDN*/ $dbServername = "localhost";
    /*MYSql Username      */ $dbUsername = "root";
    /*MYSql Password      */ $dbPassword ="";
    /*MYSql Database Name */ $dbName = "dwight";

    /*Connection Section
    -------------------------
    HTTPS mode TRUE:  only HTTPS, plain HTTP requests are refused.
    HTTPS mode FALSE: plain HTTP allowed, e.g. on XAMPP, or on a LAN/VPN that is
                      already private.
    */
    /*HTTPS mode          */ $HTTPS_MODE = FALSE;

    /*Debug Section
    -------------------------
    TRUE only while developing:
    - server errors show the real PHP message instead of just "Server Error"
    - the CSRF check is off
    Never leave it on where others can reach it: any site they visit could then
    send requests as a logged-in admin (only SameSite=Strict would still stop it).
    */
    /*Debug mode          */ $DEBUG_MODE = TRUE;

    //Set before connecting, so the error handler can read them even if the DB is down
    $GLOBALS['HTTPS_MODE'] = ($HTTPS_MODE !== FALSE);
    $GLOBALS['DEBUG_MODE'] = ($DEBUG_MODE === TRUE);

    //The first require opens the connection, the next ones reuse it
    if(!isset($GLOBALS['dwight_conn'])){
        $GLOBALS['dwight_conn'] = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);
        $GLOBALS['dwight_conn']->set_charset("utf8mb4");
    }
    $conn = $GLOBALS['dwight_conn'];
