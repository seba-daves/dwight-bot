<?php
require "./incls/rateandsecurity.php";   //POST only, CSRF, rate limits: checked before every action
require "./incls/auth.php";              //sessions and login

$action=CheckRequest();

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
            Login(trim($_POST['username']),$_POST['password']);
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
    //Add the portal's actions here. CSRF is already checked by CheckRequest(),
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
