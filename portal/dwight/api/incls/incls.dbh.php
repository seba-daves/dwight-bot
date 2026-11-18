<?php
    //CONFIGURE THIS PART

    /*MYSql Section
    -------------------------
    XAMPP defaults. In production use the webapp's own DB user.
    */
    /*MYSql Server IP/FQDN*/ $dbServername = "localhost";
    /*MYSql Username      */ $dbUsername = "root";
    /*MYSql Password      */ $dbPassword ="";
    /*MYSql Database Name */ $dbName = "dwight";

    //The first require opens the connection, the next ones reuse it
    //(before, every function opened a new one)
    if(!isset($GLOBALS['dwight_conn'])){
        $GLOBALS['dwight_conn'] = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);
        $GLOBALS['dwight_conn']->set_charset("utf8mb4");
    }
    $conn = $GLOBALS['dwight_conn'];
