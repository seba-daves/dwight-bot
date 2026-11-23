<?php
//Sessions and login verification: who is logged in, and how they get in.
//Included by api/index.php. The app logic only calls Login(), CheckAuth(),
//IsAuthorized(), GetUID(), GetUsername() and Logout().

$SESSION_VERSION=1;           //Bump it to log everyone out
$SESSION_IDLE_TIMEOUT=7200;   //Seconds without requests before logout (2h)
$SESSION_MAX_AGE=43200;       //Seconds since login before logout, even if active (12h)
//Checked when the username doesn't exist, so a wrong username takes as long
//as a wrong password (otherwise response times reveal which usernames exist).
//Its result is ignored. Regenerate it if you change the bcrypt cost.
$DUMMY_HASH='$2y$10$xxM8LYX0hzs5i0imESb/OuhywIJTP0hKAANBld7l2viJVrf2DXKEC';

function Login($username,$password){
    //Checks the credentials and logs in. Always ends with a Notice or an Error.
    if(TooManyAttempts($username)){
        Error(6);
    }
    require "./incls/incls.dbh.php";
    $statement = $conn->prepare("SELECT * FROM `dwight-admins` WHERE `Username`= ? AND `IsActive`=1;");
    $statement->bind_param("s",$username);
    $statement->execute();
    $Res=$statement->get_result();
    $Row = mysqli_fetch_assoc($Res);

    //An account with an empty Hash can't log in (the Dwight bot account)
    if(isset($Row['ID']) && $Row['ID']!="" && $Row['Hash']!=""){
        if(password_verify($password,$Row['Hash'])){
            if(password_needs_rehash($Row['Hash'], PASSWORD_DEFAULT)){
                UpdateHash($Row['ID'],$password);
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
        password_verify($password,$GLOBALS['DUMMY_HASH']);
        RecordFailedAttempt($username);
        Error(2);
    }
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
