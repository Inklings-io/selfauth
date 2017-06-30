<?php
function error_page($header, $body)
{
    die("<html>
    <head>
        <style>
            .error{
                width:100%;
                text-align:center;
                margin-top:10%;
            }
        </style>
        <title>Error: $header</title>
    </head>
    <body>
        <div class='error'>
            <h1>Error: $header</h1>
            <p>$body</p>
        </div>
    </body>
</html>");
}

$configfile= __DIR__ . '/config.php';
if(file_exists($configfile)){
    require_once $configfile;
} else {
    error_page('Configuration Error', 'Endpoint not yet configured, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.');
}

//temp code generation to protect against CSRF, this is only going to be valid for between 2 and 4 minutes
function generate_csrf_code($redirect_uri, $client_id, $state)
{
    $t = time();
    $m = intval(date('i', $t) / 2) * 2;
    return md5(APP_KEY . $client_id . $redirect_uri . $state . date('Y-M-d G:', $t) . $m);

}

function verify_csrf_code($redirect_uri, $client_id, $state, $code)
{
    $t = time();
    $t2 = time() - 120;
    $m = intval(date('i', $t) / 2) * 2;
    $m2 = intval(date('i', $t2) / 2) * 2;
    if( md5(APP_KEY . $client_id . $redirect_uri . $state .  date('Y-M-d G:', $t) . $m) == $code 
            || md5(APP_KEY . $client_id . $redirect_uri . $state . date('Y-M-d G:', $t2) . $m2) == $code ) {
        return true;
    }
    return false;
}


//temp code generation, this is only going to be valid for between 5 and 10 minutes
function generate_code($redirect_uri, $client_id, $scope)
{
    $t = time();
    $m = intval(date('i', $t) / 5) * 5;
    return md5(APP_KEY . USER_URL . $redirect_uri . $client_id . $scope . date('Y-M-d G:', $t) . $m);

}

function verify_code($redirect_uri, $client_id, $scope, $code)
{
    $t = time();
    $t2 = time() - 300;
    $m = intval(date('i', $t) / 5) * 5;
    $m2 = intval(date('i', $t2) / 5) * 5;

    if( md5(APP_KEY . USER_URL . $redirect_uri . $client_id . $scope . date('Y-M-d G:', $t) . $m) == $code 
            || md5(APP_KEY . USER_URL . $redirect_uri . $client_id . $scope . date('Y-M-d G:', $t2) . $m2) == $code ) {
        return true;

    }
    return false;
}

function verify_password($url, $pass)
{
    $input_user = trim(preg_replace('/^https?:\/\//', '', $url), '/');

    $hash = md5($input_user . $pass . APP_KEY);

    $configured_user = trim(preg_replace('/^https?:\/\//', '', USER_URL), '/');

    return ($input_user == $configured_user && $hash == USER_HASH);
}

if((!defined('APP_URL') || APP_URL == '')
    || (!defined('APP_KEY') || APP_KEY == '')
    || (!defined('USER_HASH') || USER_HASH == '')
    || (!defined('USER_URL') || USER_URL == '')
) {
    error_page('Configuration Error', 'Endpoint not configured correctly, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.');
}

if(!empty($_POST) && isset($_POST['code'])) {

    $redirect_uri   = (isset($_POST['redirect_uri'] ) ? $_POST['redirect_uri']    : null );
    $client_id      = (isset($_POST['client_id']    ) ? $_POST['client_id']       : null );
    $fullcode       = (isset($_POST['code']         ) ? $_POST['code']            : null );
    
    //send code = something like 0123456789abcdef:create,edit
    $code_parts = explode(':', $fullcode);
    $code = $code_parts[0];
    $scope_encoded = isset($code_parts[1]) ? $code_parts[1] : '';

    if(verify_code($redirect_uri, $client_id, $scope_encoded, $code)){
        //TODO support scope
        header('Content-Type: application/json');
        $json = array('me' => USER_URL);
        echo json_encode($json);
        exit();
    } else {
        error_page('Verification Failed', 'Given Code Was Invalid');
    }


} elseif(empty($_POST)) {
    $me             = (isset($_GET['me']           ) ? $_GET['me']                          : null );  
    $redirect_uri   = (isset($_GET['redirect_uri'] ) ? $_GET['redirect_uri']                : null );
    $response_type  = (isset($_GET['response_type']) ? strtolower($_GET['response_type'])   : 'id' );
    $state          = (isset($_GET['state']        ) ? $_GET['state']                       : null );
    $client_id      = (isset($_GET['client_id']    ) ? $_GET['client_id']                   : null );
    $scope          = (isset($_GET['scope']        ) ? $_GET['scope']                       : ''   );
    //TODO how would we support scope?

    //TODO check scope and response_type make sense once they are supported
    if(empty($me)){
        error_page('Incomplete Request', 'There was an error with the request.  No "me" field given.');
    }
    if(empty($client_id)){
        error_page('Incomplete Request', 'There was an error with the request.  No "client_id" field given.');
    }
    if(empty($redirect_uri)){
        error_page('Incomplete Request', 'There was an error with the request.  No "redirect_uri" field given.');
    }
    if(empty($state)){
        error_page('Incomplete Request', 'There was an error with the request.  No "state" field given.');
    }
    //if($response_type == 'code'){
        //error_page('Not Supported', 'Selfauth currently only supports "response_type=id" (authentication).');
    //}
    if($response_type != 'code' && $response_type != 'id'){
        error_page('Invalid Request', 'Unknown value encountered. "response_type" must be "id" or "code".');
    }
    $csrf_code = generate_csrf_code($redirect_uri, $client_id, $state);
?>
    <html>
    <head>
        <title>Login</title>
        <style>
h1{text-align:center;margin-top:3%;}
body {text-align:center;}
pre {width:400px; margin-left:auto; margin-right:auto;margin-bottom:50px; background-color:#FFC; min-height:1em;}

form{ 
margin-left:auto;
width:300px;
margin-right:auto;
text-align:center;
margin-top:20px;
border:solid 1px black;
padding:20px;
}
.form-line{ margin-top:5px;}
.submit{width:100%}

        </style>
    </head>
    <body>
    <h1>Authenticate</h1>
    <div>You are attempting to login with client <pre><?php echo $client_id?></pre></div>
    <div>It is requesting the following scopes <pre><?php echo $scope?></pre></div>
    <div>After login you will be redirected to  <pre><?php echo $redirect_uri?></pre></div>

    <form method="POST" action="">
        <input type="hidden" name="_csrf" value="<?php echo $csrf_code?>" />
        <input type="hidden" name="redirect_uri" value="<?php echo $redirect_uri?>" />
        <input type="hidden" name="me" value="<?php echo $me?>" />
        <input type="hidden" name="response_type" value="<?php echo $response_type?>" />
        <input type="hidden" name="state" value="<?php echo $state?>" />
        <input type="hidden" name="scope" value="<?php echo $scope?>" />
        <input type="hidden" name="client_id" value="<?php echo $client_id?>" />
        <div class="form-line"><label for="password">Password:</label> <input type="password" name="password" id="password" /></div>
        <div class="form-line"><input class="submit" type="submit" name="submit" value="Submit" /></div>
    </form>

    </body></html>
<?php 
    exit();
} //end elseif

$csrf_code      = (isset($_POST['_csrf']        ) ? $_POST['_csrf']           : null );  
$pass_input     = (isset($_POST['password']     ) ? $_POST['password']        : null );  
$me             = (isset($_POST['me']           ) ? $_POST['me']              : null );  
$redirect_uri   = (isset($_POST['redirect_uri'] ) ? $_POST['redirect_uri']    : null );
$response_type  = (isset($_POST['response_type']) ? $_POST['response_type']   : 'id' );
$state          = (isset($_POST['state']        ) ? $_POST['state']           : ''   );
$client_id      = (isset($_POST['client_id']    ) ? $_POST['client_id']       : null );
$scope          = (isset($_POST['scope']        ) ? $_POST['scope']           : '' );

//TODO check scope and response_type make sense once they are supported

if(!verify_csrf_code($redirect_uri, $client_id, $state, $csrf_code)){
    error_page('Invalid csrf code','Usually this means you took too long to log in. Please try again.');
}
if(empty($me)){
    error_page('Incomplete Request', 'There was an error with the request.  No "me" field given.');
}
if(empty($client_id)){
    error_page('Incomplete Request', 'There was an error with the request.  No "client_id" field given.');
}
if(empty($redirect_uri)){
    error_page('Incomplete Request', 'There was an error with the request.  No "redirect_uri" field given.');
}
if(empty($state)){
    error_page('Incomplete Request', 'There was an error with the request.  No "state" field given.');
}
if(empty($pass_input)){
    error_page('Incomplete Request', 'No Password Given.');
}

// verify login
if(verify_password($me, $pass_input)) {
    $scope_encoded = preg_replace('/ +/', ',', trim($scope));

    $code = generate_code($redirect_uri, $client_id, $scope_encoded);

    $fullcode = $code . ':' . $scope_encoded;

    $final_redir = $redirect_uri;
    if(strpos($redirect_uri, '?') === FALSE){
        $final_redir .= '?';
    } else {
        $final_redir .= '&';
    }
    $final_redir .= 'code=' . $fullcode . '&state=' . $state . '&me=' . $me;

    // redirect back
    header('Location: ' . $final_redir);
    exit();
} else {
    error_page('Login Failed', 'Invalid username or password.');
}


