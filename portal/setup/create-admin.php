<?php
//Creates an admin from the command line (there's no sign-up page on purpose).
//Keep this file OUTSIDE htdocs.
//
//  php create-admin.php <username> [--privileges=A] [--telegram=<id>]
//      Asks for the password.
//  php create-admin.php <username> --bot [--telegram=<id>]
//      The account for the Dwight bot: no password, it can never log in to the portal.
//  php create-admin.php <username> --reset-password
//      Asks for a new password for an existing admin.

if(PHP_SAPI!="cli"){
    http_response_code(404);
    exit;
}
//Path to the webapp's DB config: change it if you move the folders
require __DIR__."/../dwight/api/incls/incls.dbh.php";

$username=NULL;
$options=[];
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,"--")){
        $parts=explode("=",substr($arg,2),2);
        $options[$parts[0]]=isset($parts[1]) ? $parts[1] : TRUE;
    }else{
        $username=$arg;
    }
}

if($username==NULL || mb_strlen($username)>64){
    echo "Usage: php create-admin.php <username> [--bot] [--privileges=A] [--telegram=<id>]\n";
    echo "       php create-admin.php <username> --reset-password\n";
    exit(1);
}

$statement = $conn->prepare("SELECT * FROM `dwight-admins` WHERE `Username`= ?;");
$statement->bind_param("s",$username);
$statement->execute();
$Res=$statement->get_result();
$Row = mysqli_fetch_assoc($Res);
$exists=(isset($Row['ID']) && $Row['ID']!="");

if(isset($options['reset-password'])){
    if($exists){
        $hash=password_hash(AskPassword(), PASSWORD_DEFAULT);
        $statement = $conn->prepare("UPDATE `dwight-admins` SET `Hash`= ? WHERE `ID`= ?;");
        $statement->bind_param("si",$hash,$Row['ID']);
        $statement->execute();
        echo "Password changed. To log out their open sessions now, bump \$SESSION_VERSION in api/index.php.\n";
    }else{
        echo "No admin called '".$username."'.\n";
        exit(1);
    }
}else{
    if(!$exists){
        $isBot=isset($options['bot']);
        $hash=$isBot ? "" : password_hash(AskPassword(), PASSWORD_DEFAULT);
        $uuid=guidv4();
        $privileges=(isset($options['privileges']) && is_string($options['privileges'])) ? $options['privileges'] : "A";
        $telegram=(isset($options['telegram']) && is_string($options['telegram'])) ? (int)$options['telegram'] : NULL;

        $statement = $conn->prepare("INSERT INTO `dwight-admins` (`UUID`,`Username`,`Hash`,`UsesOTP`,`Privileges`,`IsActive`,`TelegramUID`) VALUES (?, ?, ?, 0, ?, 1, ?);");
        $statement->bind_param("ssssi",$uuid,$username,$hash,$privileges,$telegram);
        $statement->execute();

        if($isBot){
            echo "Bot account '".$username."' created (ID ".$conn->insert_id."). It can't log in to the portal.\n";
        }else{
            echo "Admin '".$username."' created (ID ".$conn->insert_id.").\n";
        }
    }else{
        echo "An admin called '".$username."' already exists.\n";
        exit(1);
    }
}

function AskPassword(){
    while(TRUE){
        $password=Prompt("Password (min 12 characters): ");
        if(strlen($password)<12){
            echo "Too short.\n";
        }else if(Prompt("Repeat it: ")!==$password){
            echo "They don't match.\n";
        }else{
            return $password;
        }
    }
}
function Prompt($label){
    //Hides what you type on Linux/macOS; on Windows the password is visible while typing
    echo $label;
    $hide=(DIRECTORY_SEPARATOR=="/" && stream_isatty(STDIN));
    if($hide){
        shell_exec("stty -echo");
    }
    $value=rtrim((string)fgets(STDIN),"\r\n");
    if($hide){
        shell_exec("stty echo");
        echo "\n";
    }
    return $value;
}
function guidv4($data = null) {
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
