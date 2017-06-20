<?php
require_once __DIR__ . '/config.php';


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
function generate_code($redirect_uri, $client_id)
{
    $t = time();
    $m = intval(date('i', $t) / 5) * 5;
    return md5(APP_KEY . USER_URL . $redirect_uri . $client_id . date('Y-M-d G:', $t) . $m);

}

function verify_code($redirect_uri, $client_id, $code)
{
    $t = time();
    $t2 = time() - 300;
    $m = intval(date('i', $t) / 5) * 5;
    $m2 = intval(date('i', $t2) / 5) * 5;
    if( md5(APP_KEY . USER_URL . $redirect_uri . $client_id . date('Y-M-d G:', $t) . $m) == $code 
            || md5(APP_KEY . USER_URL . $redirect_uri . $client_id . date('Y-M-d G:', $t2) . $m2) == $code ) {
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

if(!defined('APP_URL') 
    || !defined('APP_KEY')
    || !defined('USER_HASH')
    || !defined('USER_URL')
) {
    die('<html><body>Endpoint not yet configured, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.</body></html>');
}


if(empty($_POST) && isset($_POST['code'])) {

    $redirect_uri   = (isset($_GET['redirect_uri'] ) ? $_GET['redirect_uri']    : null );
    $client_id      = (isset($_GET['client_id']    ) ? $_GET['client_id']       : null );
    if(verify_code($redirect_uri, $client_id, $code)){
        //TODO this needs correct headers;
        header('Content-Type: application/x-www-form-urlencoded');
        echo 'me='.USER_URL;
        exit();
    }


} elseif(empty($_POST)) {
    $me             = (isset($_GET['me']           ) ? $_GET['me']              : null );  
    $redirect_uri   = (isset($_GET['redirect_uri'] ) ? $_GET['redirect_uri']    : null );
    $response_type  = (isset($_GET['response_type']) ? $_GET['response_type']   : 'id' );
    $state          = (isset($_GET['state']        ) ? $_GET['state']           : ''   );
    $client_id      = (isset($_GET['client_id']    ) ? $_GET['client_id']       : null );
    $scope          = (isset($_GET['scope']        ) ? $_GET['scope']           : ''   );
    //TODO how would we support scope?

    //TODO check all fields
    if(empty($redirect_uri)){
        //TODO make a better error page
        die('<html><body>No redirect_uri given</body></html>');
    }
    $csrf_code = generate_csrf_code($redirect_uri, $client_id, $state);
?>
    <html><body>
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
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" />
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
$scope          = (isset($_POST['scope']        ) ? $_POST['scope']           : ''   );

//TODO check all fields

if(!verify_csrf_code($redirect_uri, $client_id, $state, $csrf_code)){
    die('invalid csrf code, please try again');
}

// verify login
if(verify_password($me, $pass_input)) {
    $code = generate_code($redirect_uri, $client_id);

    $final_redir = $redirect_uri;
    if(strpos('?', $redirect_uri) === FALSE){
        $final_redir .= '?';
    } else {
        $final_redir .= '&';
    }
    $final_redir .= 'code=' . $code . '&state=' . $state . '&me=' . $me;

    // redirect back
    header('Location: ' . $find_redir);
} else {
    die('login failed');
    //TODO make this look nicer
}


