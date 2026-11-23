<?php
//Security that the app logic takes for granted: every request goes through
//CheckRequest() before the switch in api/index.php.
// - POST only
// - who is calling: a browser (session cookie) or a program (API token)
// - CSRF for browser requests, checked for every action automatically
// - brute-force limit on logins
// - PHP errors never end up in the JSON

$LOGIN_WINDOW=900;            //Seconds the failed logins are counted for (15 min)
$LOGIN_MAX_FAILS_USER=5;      //Failed logins per username in the window
$LOGIN_MAX_FAILS_IP=20;       //Failed logins per IP in the window
$LOGIN_CLEANUP_CHANCE=100;    //Old attempts are deleted on 1 failed login in this many

//Actions a browser session can call without the CSRF token: only ones that change nothing.
//Every other action is checked automatically, so a new action is protected without doing anything.
$NO_CSRF_ACTIONS=["session","verify"];
//Actions that only make sense in a browser: not available with an API token
$SESSION_ONLY_ACTIONS=["session","login","verify","logout"];

//Never print PHP errors into the JSON: log them and answer "Server Error"
ini_set("display_errors","0");
set_exception_handler(function($e){
    error_log("[dwight] ".$e);
    Error(99);
});

function CheckRequest(){
    //Returns the action to run, or ends the request with an Error.

    //POST only: actions can't be triggered by opening a link
    if($_SERVER['REQUEST_METHOD']!="POST"){
        Error(4);
    }
    if(!isset($_POST['action']) || !is_string($_POST['action']) || $_POST['action']==""){
        Error(0);
    }
    $action=$_POST['action'];

    //Who is calling?
    // - "token":   a program (the C# bot) sending "Authorization: Bearer <token>".
    //              No cookies are involved, so CSRF can't happen and isn't checked.
    // - "session": a browser with the session cookie: CSRF is checked
    //              for every action except $NO_CSRF_ACTIONS.
    $bearer=GetBearerToken();
    if($bearer!=""){
        $GLOBALS['AUTH_TYPE']="token";
        if(in_array($action,$GLOBALS['SESSION_ONLY_ACTIONS'])){
            Error(0,"Not available with an API token");
        }
        if(!CheckApiToken($bearer)){
            Error(3,"Invalid API token");
        }
    }else{
        $GLOBALS['AUTH_TYPE']="session";
        StartSession();
        if(!in_array($action,$GLOBALS['NO_CSRF_ACTIONS']) && !CheckCsrf()){
            Error(5);
        }
    }
    return $action;
}
function IsHttps(){
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!="" && $_SERVER['HTTPS']!="off") || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT']==443);
}
function GetIP(){
    //Only REMOTE_ADDR: X-Forwarded-For can be set by anyone
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "";
}
function GetCsrfToken(){
    if(!isset($_SESSION['dwight_csrf']) || $_SESSION['dwight_csrf']==""){
        $_SESSION['dwight_csrf']=bin2hex(random_bytes(32));
    }
    return $_SESSION['dwight_csrf'];
}
function CheckCsrf(){
    //The page sends back the token it got from the API: another site can make
    //the browser send a request, but it can't read the token
    if(isset($_SESSION['dwight_csrf']) && isset($_POST['csrf']) && is_string($_POST['csrf'])){
        return hash_equals($_SESSION['dwight_csrf'],$_POST['csrf']);
    }
    return FALSE;
}
function TooManyAttempts($username){
    //In the database, not in $_SESSION: an attacker can just drop the cookie.
    //Two separate counts, so each one uses its index ((Username, AttemptedAt) and
    //(IP, AttemptedAt)) and reads only the matching rows, not the whole table.
    require "./incls/incls.dbh.php";
    $window=$GLOBALS['LOGIN_WINDOW'];

    $statement = $conn->prepare("SELECT COUNT(*) AS `N` FROM `dwight-login-attempts` WHERE `Username`= ? AND `AttemptedAt` > NOW() - INTERVAL ? SECOND;");
    $statement->bind_param("si",$username,$window);
    $statement->execute();
    $Res=$statement->get_result();
    $Row = mysqli_fetch_assoc($Res);
    if((int)$Row['N']>=$GLOBALS['LOGIN_MAX_FAILS_USER']){
        return TRUE;
    }

    $ip=GetIP();
    $statement = $conn->prepare("SELECT COUNT(*) AS `N` FROM `dwight-login-attempts` WHERE `IP`= ? AND `AttemptedAt` > NOW() - INTERVAL ? SECOND;");
    $statement->bind_param("si",$ip,$window);
    $statement->execute();
    $Res=$statement->get_result();
    $Row = mysqli_fetch_assoc($Res);
    if((int)$Row['N']>=$GLOBALS['LOGIN_MAX_FAILS_IP']){
        return TRUE;
    }else{
        return FALSE;
    }
}
function RecordFailedAttempt($username){
    require "./incls/incls.dbh.php";
    $ip=GetIP();
    $statement = $conn->prepare("INSERT INTO `dwight-login-attempts` (`Username`,`IP`) VALUES (?, ?);");
    $statement->bind_param("ss",$username,$ip);
    $statement->execute();
    //Nothing older than a day is needed. Done only once in a while, not on every
    //failure, so a flood of wrong logins doesn't also become a flood of deletes,
    //and at most 1000 rows at a time, so no single request does a long delete.
    if(random_int(1,$GLOBALS['LOGIN_CLEANUP_CHANCE'])==1){
        $conn->query("DELETE FROM `dwight-login-attempts` WHERE `AttemptedAt` < NOW() - INTERVAL 1 DAY ORDER BY `AttemptedAt` LIMIT 1000;");
    }
}
function ClearFailedAttempts($username){
    require "./incls/incls.dbh.php";
    $statement = $conn->prepare("DELETE FROM `dwight-login-attempts` WHERE `Username`= ?;");
    $statement->bind_param("s",$username);
    $statement->execute();
}
