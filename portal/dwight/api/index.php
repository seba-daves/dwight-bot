<?php
$SESSION_VERSION=1;           //Bump it to log everyone out
$SESSION_IDLE_TIMEOUT=7200;   //Seconds without requests before logout (2h)
$SESSION_MAX_AGE=43200;       //Seconds since login before logout, even if active (12h)
$LOGIN_WINDOW=900;            //Seconds the failed logins are counted for (15 min)
$LOGIN_MAX_FAILS_USER=5;      //Failed logins per username in the window
$LOGIN_MAX_FAILS_IP=20;       //Failed logins per IP in the window
//Checked when the username doesn't exist, so a wrong username takes as long
//as a wrong password (otherwise response times reveal which usernames exist)
$DUMMY_HASH='$2y$10$xxM8LYX0hzs5i0imESb/OuhywIJTP0hKAANBld7l2viJVrf2DXKEC';

//Actions a browser session can call without the CSRF token: only ones that change nothing.
//Every other action is checked automatically below, before the switch.
$NO_CSRF_ACTIONS=["session","verify"];
//Actions that only make sense in a browser: not available with an API token
$SESSION_ONLY_ACTIONS=["session","login","verify","logout"];

//Never print PHP errors into the JSON: log them and answer "Server Error"
ini_set("display_errors","0");
set_exception_handler(function($e){
    error_log("[dwight] ".$e);
    Error(99);
});

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
// - "session": a browser with the session cookie. CSRF is checked here for every
//              action except $NO_CSRF_ACTIONS, so a new action is protected even
//              if you forget about it.
$bearer=GetBearerToken();
if($bearer!=""){
    $AUTH_TYPE="token";
    if(in_array($action,$SESSION_ONLY_ACTIONS)){
        Error(0,"Not available with an API token");
    }
    if(!CheckApiToken($bearer)){
        Error(3,"Invalid API token");
    }
}else{
    $AUTH_TYPE="session";
    StartSession();
    if(!in_array($action,$NO_CSRF_ACTIONS) && !CheckCsrf()){
        Error(5);
    }
}

switch ($action){
    case "session":
        //Called when the login page loads: gives the CSRF token and tells if you're already logged in
        $loggedIn=CheckAuth();
        $json=[];
        $json["csrf"]=GetCsrfToken();
        $json["loggedIn"]=$loggedIn;
        $json["username"]=$loggedIn ? $_SESSION['dwight_uname'] : NULL;
        SendJSON($json);
        break;
    case "login":
        if(isset($_POST['username']) && isset($_POST['password']) && is_string($_POST['username']) && is_string($_POST['password']) && trim($_POST['username'])!="" && $_POST['password']!="" && mb_strlen($_POST['username'])<=64 && strlen($_POST['password'])<=1024){
            $username=trim($_POST['username']);
            if(!TooManyAttempts($username)){
                require "./incls/incls.dbh.php";
                $statement = $conn->prepare("SELECT * FROM `dwight-admins` WHERE `Username`= ? AND `IsActive`=1;");
                $statement->bind_param("s",$username);
                $statement->execute();
                $Res=$statement->get_result();
                $Row = mysqli_fetch_assoc($Res);

                //An account with an empty Hash can't log in (the Dwight bot account)
                if(isset($Row['ID']) && $Row['ID']!="" && $Row['Hash']!=""){
                    if(password_verify($_POST['password'],$Row['Hash'])){
                        if(password_needs_rehash($Row['Hash'], PASSWORD_DEFAULT)){
                            UpdateHash($Row['ID'],$_POST['password']);
                        }
                        // Prevent session fixation by regenerating session id after login
                        session_regenerate_id(true);
                        $_SESSION=[];
                        $_SESSION['dwight_uid']=$Row['ID'];
                        $_SESSION['dwight_uuid']=$Row['UUID'];
                        $_SESSION['dwight_uname']=$Row['Username'];
                        $_SESSION['dwight_privileges']=$Row['Privileges'];
                        $_SESSION['dwight_sessver']=$GLOBALS['SESSION_VERSION'];
                        $_SESSION['dwight_logintime']=time();
                        $_SESSION['dwight_lastseen']=time();
                        ClearFailedAttempts($username);
                        Notice(0,"",["csrf"=>GetCsrfToken(),"username"=>$Row['Username']]);
                    }else{
                        RecordFailedAttempt($username);
                        Error(2);
                    }
                }else{
                    password_verify($_POST['password'],$GLOBALS['DUMMY_HASH']);
                    RecordFailedAttempt($username);
                    Error(2);
                }
            }else{
                Error(6);
            }
        }else{
            Error(1);
        }
        break;
    case "verify":
        if(CheckAuth()){
            Notice(1,"",["csrf"=>GetCsrfToken(),"username"=>$_SESSION['dwight_uname']]);
        }else{
            Error(3);
        }
        break;
    case "logout":
        Logout();
        Notice(2);
        break;
    case "whoami":
        //Works for both a logged-in admin and an API token: handy to test the bot's token
        if(IsAuthorized()){
            $json=[];
            $json["uid"]=GetUID();
            $json["username"]=GetUsername();
            $json["authType"]=$GLOBALS['AUTH_TYPE'];
            SendJSON($json);
        }else{
            Error(3);
        }
        break;
    //Add the portal's actions here. CSRF is already checked above,
    //so each action only checks that the caller is logged in:
    //case "get-bans":
    //    if(IsAuthorized()){
    //        require "./incls/incls.dbh.php";
    //        ...
    //        SendJSON($json);
    //    }else{
    //        Error(3);
    //    }
    //    break;
    default:
        Error(0);
        break;
}

function StartSession(){
    if (session_status() === PHP_SESSION_NONE) {
        ini_set("session.use_strict_mode","1");
        session_name("DWIGHTSESSID");
        session_set_cookie_params([
            "lifetime"=>0,
            "path"=>"/",
            "secure"=>IsHttps(),    //HTTPS only (off on http://localhost)
            "httponly"=>true,       //JavaScript can't read it
            "samesite"=>"Strict"    //not sent by requests coming from other sites
        ]);
        session_start();
    }
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
function Logout(){
    StartSession();
    $_SESSION=[];
    $params=session_get_cookie_params();
    setcookie(session_name(),"",[
        "expires"=>time()-3600,
        "path"=>$params['path'],
        "secure"=>$params['secure'],
        "httponly"=>$params['httponly'],
        "samesite"=>$params['samesite']
    ]);
    session_destroy();
}
function CheckAuth(){
    StartSession();
    if(isset($_SESSION['dwight_sessver']) && $_SESSION['dwight_sessver']!=$GLOBALS['SESSION_VERSION']){
        Logout();
        return FALSE;
    }else{
        if(isset($_SESSION['dwight_uid']) && isset($_SESSION['dwight_uname']) && isset($_SESSION['dwight_logintime']) && isset($_SESSION['dwight_lastseen'])){
            $now=time();
            if($now-$_SESSION['dwight_lastseen']>$GLOBALS['SESSION_IDLE_TIMEOUT'] || $now-$_SESSION['dwight_logintime']>$GLOBALS['SESSION_MAX_AGE']){
                Logout();
                return FALSE;
            }
            require "./incls/incls.dbh.php";
            $statement = $conn->prepare("SELECT * FROM `dwight-admins` WHERE `ID`= ? AND `IsActive`= 1;");
            $statement->bind_param("i",$_SESSION['dwight_uid']);
            $statement->execute();
            $Res=$statement->get_result();
            $Row = mysqli_fetch_assoc($Res);

            //Still active, same username and still allowed to log in?
            if(isset($Row['ID']) && $Row['ID']!="" && $Row['Hash']!=""){
                if($Row['Username']==$_SESSION['dwight_uname']){
                    $_SESSION['dwight_privileges']=$Row['Privileges'];
                    $_SESSION['dwight_lastseen']=$now;
                    return TRUE;
                }
            }
            Logout();
            return FALSE;
        }
    }
    return FALSE;
}
function UpdateHash($uid,$password){
    require "./incls/incls.dbh.php";
    $hash=password_hash($password, PASSWORD_DEFAULT);
    $statement = $conn->prepare("UPDATE `dwight-admins` SET `Hash`= ? WHERE `ID`= ?;");
    $statement->bind_param("si",$hash,$uid);
    $statement->execute();
}
function TooManyAttempts($username){
    //In the database, not in $_SESSION: an attacker can just drop the cookie
    require "./incls/incls.dbh.php";
    $ip=GetIP();
    $window=$GLOBALS['LOGIN_WINDOW'];
    $statement = $conn->prepare("SELECT SUM(`Username`= ?) AS `ByUser`, SUM(`IP`= ?) AS `ByIP` FROM `dwight-login-attempts` WHERE `AttemptedAt` > NOW() - INTERVAL ? SECOND;");
    $statement->bind_param("ssi",$username,$ip,$window);
    $statement->execute();
    $Res=$statement->get_result();
    $Row = mysqli_fetch_assoc($Res);
    if((int)$Row['ByUser']>=$GLOBALS['LOGIN_MAX_FAILS_USER'] || (int)$Row['ByIP']>=$GLOBALS['LOGIN_MAX_FAILS_IP']){
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
    //Nothing older than a day is needed
    $conn->query("DELETE FROM `dwight-login-attempts` WHERE `AttemptedAt` < NOW() - INTERVAL 1 DAY;");
}
function ClearFailedAttempts($username){
    require "./incls/incls.dbh.php";
    $statement = $conn->prepare("DELETE FROM `dwight-login-attempts` WHERE `Username`= ?;");
    $statement->bind_param("s",$username);
    $statement->execute();
}
function IsAuthorized(){
    //A valid API token was already checked before the switch
    if($GLOBALS['AUTH_TYPE']=="token"){
        return TRUE;
    }
    return CheckAuth();
}
function GetUID(){
    if($GLOBALS['AUTH_TYPE']=="token"){
        return $GLOBALS['TOKEN_ADMIN']['ID'];
    }
    $UID=$_SESSION['dwight_uid'];
    return $UID;
}
function GetUsername(){
    if($GLOBALS['AUTH_TYPE']=="token"){
        return $GLOBALS['TOKEN_ADMIN']['Username'];
    }
    return $_SESSION['dwight_uname'];
}
function GetBearerToken(){
    //Apache sometimes hides the Authorization header from PHP: api/.htaccess passes it on
    $header="";
    if(isset($_SERVER['HTTP_AUTHORIZATION'])){
        $header=$_SERVER['HTTP_AUTHORIZATION'];
    }else if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])){
        $header=$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if(preg_match('/^Bearer\s+(\S+)$/i',trim($header),$matches)){
        return $matches[1];
    }
    return "";
}
function CheckApiToken($token){
    //TODO: API access isn't implemented yet, so every token is refused.
    //To implement it:
    // - add a `dwight-api-tokens` table (AdminID, TokenHash, Name, CreatedAt, LastUsedAt, RevokedAt)
    // - store only hash("sha256",$token), never the token itself
    // - look up the hash (not revoked, admin still IsActive=1)
    // - on success fill $GLOBALS['TOKEN_ADMIN'] with the admin row (ID, Username, Privileges)
    //   and return TRUE: GetUID(), GetUsername() and IsAuthorized() already use it
    $GLOBALS['TOKEN_ADMIN']=NULL;
    return FALSE;
}
function SendJSON($struct,$code=0){
    http_response_code(200);
    header("Content-Type: application/json");
    $json=[];
    $json["responseType"]="Data";
    $json["responseCode"]=(string)$code;
    $json["responseData"]=$struct;
    echo json_encode($json);
    exit;
}
function Error($error_id, $error_comment=""){
    switch($error_id){
        case 0:
            $httpCode=400;
            $message="Unspecified Action";
        break;
        case 1:
            $httpCode=400;
            $message="Incomplete Request";
        break;
        case 2:
            $httpCode=401;
            $message="Wrong Credentials";
        break;
        case 3:
            $httpCode=401;
            $message="Not Authorized";
        break;
        case 4:
            $httpCode=405;
            $message="Method Not Allowed";
        break;
        case 5:
            $httpCode=403;
            $message="Invalid CSRF Token";
        break;
        case 6:
            $httpCode=429;
            $message="Too Many Attempts";
        break;
        case 99:
            $httpCode=500;
            $message="Server Error";
        break;
        default:
            $httpCode=400;
            $message="Error";
        break;
    }
    if($error_comment!=""){
        $message.=" - ".$error_comment;
    }
    http_response_code($httpCode);
    header("Content-Type: application/json");
    $json=[];
    $json["responseType"]="Error";
    $json["responseCode"]=(string)$error_id;
    $json["responseData"]=$message;
    $json["result"]=new stdClass();
    echo json_encode($json);
    exit;
}
function Notice($notice_id, $notice_comment="", $result=[]){
    switch($notice_id){
        case 0:
            $message="Successful Login";
        break;
        case 1:
            $message="Authorized";
        break;
        case 2:
            $message="Logged Out";
        break;
        default:
            $message="Notice";
        break;
    }
    if($notice_comment!=""){
        $message.=" - ".$notice_comment;
    }
    http_response_code(200);
    header("Content-Type: application/json");
    $json=[];
    $json["responseType"]="Notice";
    $json["responseCode"]=(string)$notice_id;
    $json["responseData"]=$message;
    $json["result"]=(object)$result;
    echo json_encode($json);
    exit;
}
