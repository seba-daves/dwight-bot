let csrfToken="";
function CallApi(formData){
    //Every request goes through here, so the CSRF token is always attached
    //and always updated when the server sends a new one (e.g. after login)
    formData.append("csrf", csrfToken);
    return fetch("./api/", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: formData.toString()
        })
    .then(response => response.json())
    .then(json => {
        if(json.result && json.result.csrf){
            csrfToken=json.result.csrf;
        }
        if(json.responseType === "Data" && json.responseData && json.responseData.csrf){
            csrfToken=json.responseData.csrf;
        }
        return json;
    });
}
function GetData(){
    let $_GET=GetVariables();
    switch ($_GET['res']) {
        case "unauth":
            Toast('error',"You Must be Logged in to Continue");
            break;
        case "logout":
            Toast('ok',"Logged Out");
            break;
        default:
            break;
    }
}
function GetVariables(){
  var $_GET = {};
  if(document.location.toString().indexOf('?') !== -1) {
      var query = document.location
                  .toString()
                  .replace(/^.*?\?/, '')
                  .replace(/#.*$/, '')
                  .split('&');
      for(var i=0, l=query.length; i<l; i++) {
      var aux = decodeURIComponent(query[i]).split('=');
      $_GET[aux[0]] = aux[1];
      }
  }
  return $_GET;
}
function Loader(){
    document.getElementsByClassName("page-wrap")[0].style.display="none";
    document.getElementById("loader").style.display="unset";
}
function StopLoad(){
    document.getElementsByClassName("page-wrap")[0].style.display="unset";
    document.getElementById("loader").style.display="none";
}
function GetSession(){
    //Gets the CSRF token, and skips the login page if you're already logged in
    const formData = new URLSearchParams();
    formData.append("action", "session");

    CallApi(formData)
    .then(json => {
    if (json.responseType === "Data") {
        if(json.responseData.loggedIn){
            parent.location='./index.html';
        }
    } else if (json.responseType === "Error" && json.responseCode === "7") {
        Toast("error","This Site Needs HTTPS");
    }
    })
    .catch(error => {
        Toast("error","Server Unreachable");
    });
}
function Verify(loginFeeback=false){
    const formData = new URLSearchParams();
    let addParam="";
    if(loginFeeback){
        addParam="?res=unauth";
    }
    formData.append("action", "verify");

    CallApi(formData)
    .then(json => {
    if (json.responseType === "Notice" && json.responseCode === "1") {
        let userLabel=document.getElementById("current-user");
        if(userLabel){
            userLabel.textContent=json.result.username;
        }
        StopLoad();
    } else {
        parent.location='./login.html'+addParam;
    }
    })
    .catch(error => {
        parent.location='./login.html'+addParam;
    });
}
function Logout(){
    const formData = new URLSearchParams();
    formData.append("action", "logout");

    CallApi(formData)
    .then(json => {
    if (json.responseType === "Notice" && json.responseCode === "2") {
        parent.location='./login.html?res=logout';
    } else if (json.responseType === "Error") {
        Toast("error","Error During Logout");
    }
    })
    .catch(error => {
        Toast("error","Error During Logout");
    });
}
function Login() {
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (username !== "" && password !== "") {
        window.scrollTo(0, 0);
        Loader();
        const formData = new URLSearchParams();
        formData.append("action", "login");
        formData.append("username", username);
        formData.append("password", password);

        CallApi(formData)
        .then(json => {
            if (json.responseType === "Notice" && json.responseCode === "0") {
                parent.location = "./index.html";
            } else if (json.responseType === "Error") {
                StopLoad();
                document.getElementById("password").value="";
                switch (json.responseCode) {
                    case "2":
                        Toast("error","Wrong Credentials");
                        break;
                    case "5":
                        Toast("error","Page Expired, Reload and Try Again");
                        break;
                    case "6":
                        Toast("error","Too Many Attempts, Wait a Few Minutes");
                        break;
                    case "7":
                        Toast("error","This Site Needs HTTPS");
                        break;
                    default:
                        Toast("error","Login Failed, Try Again");
                        break;
                }
            }
        })
        .catch(error => {
            StopLoad();
            Toast("error","Server Unreachable");
        });
    } else {
        Toast("error","Username and Password Required");
    }
}
function Toast(type="notice",text){
    let toast = document.createElement("div");
    toast.className="toast";
    let ToastIcon = document.createElement("img");
    ToastIcon.classList.add("toast-icon");
    if(type=="error"){
        ToastIcon.classList.add("toast-icon-error");
    }
    if(type=="notice"){
        ToastIcon.classList.add("toast-icon-notice");
    }
    if(type=="ok"){
        ToastIcon.classList.add("toast-icon-ok");
    }
    let ToastText = document.createElement("p");
    ToastText.className="toast-text";
    ToastText.innerText=text;
    toaster.appendChild(toast);
    toast.appendChild(ToastIcon);
    toast.appendChild(ToastText);

    setTimeout(function() {
        toast.classList.add("toast-hiding");
        setTimeout(function() {
            toast.remove();
        }, 400);
    }, 4000);


}
