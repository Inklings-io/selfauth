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
if (file_exists($configfile)) {
    require_once $configfile;
} else {
    error_page('Configuration Error', 'Endpoint not yet configured, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.');
}

// Signed codes always have an time-to-live, by default 1 year (31536000 seconds).
function create_signed_code($key, $message, $ttl = 31536000, $appended_data = '')
{
    $expires = time() + $ttl;
    $body = $message . $expires . $appended_data;
    $signature = hash_hmac('sha256', $body, $key);
    return dechex($expires) . ':' . $signature . ':' . $appended_data;
}

function verify_signed_code($key, $message, $code)
{
    $code_parts = explode(':', $code, 3);
    if (count($code_parts) !== 3) {
        return false;
    }
    $expires = hexdec($code_parts[0]);
    if (time() > $expires) {
        return false;
    }
    $body = $message . $expires . $code_parts[2];
    $signature = hash_hmac('sha256', $body, $key);
    if (function_exists('hash_equals')) {
        return hash_equals($signature, $code_parts[1]);
    } else {
        return $signature === $code_parts[1];
    }
}

function verify_password($url, $pass)
{
    $input_user = trim(preg_replace('/^https?:\/\//', '', $url), '/');

    $hash = md5($input_user . $pass . APP_KEY);

    $configured_user = trim(preg_replace('/^https?:\/\//', '', USER_URL), '/');

    return ($input_user == $configured_user && $hash == USER_HASH);
}

if ((!defined('APP_URL') || APP_URL == '')
    || (!defined('APP_KEY') || APP_KEY == '')
    || (!defined('USER_HASH') || USER_HASH == '')
    || (!defined('USER_URL') || USER_URL == '')
) {
    error_page('Configuration Error', 'Endpoint not configured correctly, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.');
}

if (!empty($_POST) && isset($_POST['code'])) {
    $redirect_uri = (isset($_POST['redirect_uri']) ? $_POST['redirect_uri'] : null );
    $client_id    = (isset($_POST['client_id']) ? $_POST['client_id']       : null );
    $code         = (isset($_POST['code']) ? $_POST['code']                 : null );

    if (verify_signed_code(APP_KEY, USER_URL . $redirect_uri . $client_id, $code)) {
        // A regular expression for extracting */*, application/*, application/json, and application/x-www-form-urlencoded, as well as their respective q values.
        $regex = '/(?<=^|,)\s*(\*\/\*|application\/\*|application\/x-www-form-urlencoded|application\/json)\s*(?:[^,]*?;\s*q\s*=\s*([0-9.]+))?\s*(?:,|$)/';
        $out = preg_match_all($regex, $_SERVER['HTTP_ACCEPT'], $matches);
        $types = array_combine($matches[1], $matches[2]);

        // Find the q value for application/json.
        if (array_key_exists('application/json', $types)) {
            $json = $types['application/json'] === '' ? 1 : $types['application/json'];
        } elseif (array_key_exists('application/*', $types)) {
            $json = $types['application/*'] === '' ? 1 : $types['application/*'];
        } elseif (array_key_exists('*/*', $types)) {
            $json = $types['*/*'] === '' ? 1 : $types['*/*'];
        } else {
            $json = 0;
        }
        $json = floatval($json);

        // Find the q value for application/x-www-form-urlencoded.
        if (array_key_exists('application/x-www-form-urlencoded', $types)) {
            $form = $types['application/x-www-form-urlencoded'] === '' ? 1 : $types['application/x-www-form-urlencoded'];
        } elseif (array_key_exists('application/*', $types)) {
            $form = $types['application/*'] === '' ? 1 : $types['application/*'];
        } elseif (array_key_exists('*/*', $types)) {
            $form = $types['*/*'] === '' ? 1 : $types['*/*'];
        } else {
            $form = 0;
        }
        $form = floatval($form);

        // Respond in the correct way.
        if ($json === 0 && $form === 0) {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
            header($protocol . ' 406 Not Acceptable');
            error_page('No Accepted Response Types', 'The client accepts neither JSON nor Form encoded responses.');
        } elseif ($json >= $form) {
            header('Content-Type: application/json');
            exit(json_encode(array('me' => USER_URL)));
        } else {
            header('Content-Type: application/x-www-form-urlencoded');
            exit(http_build_query(array('me' => USER_URL)));
        }
    } else {
        error_page('Verification Failed', 'Given Code Was Invalid');
    }
} elseif (empty($_POST)) {
    $me            = (isset($_GET['me']) ? $_GET['me']                                   : null );
    $redirect_uri  = (isset($_GET['redirect_uri']) ? $_GET['redirect_uri']               : null );
    $response_type = (isset($_GET['response_type']) ? strtolower($_GET['response_type']) : 'id' );
    $state         = (isset($_GET['state']) ? $_GET['state']                             : null );
    $client_id     = (isset($_GET['client_id']) ? $_GET['client_id']                     : null );
    $scope         = (isset($_GET['scope']) ? $_GET['scope']                             : ''   );
    //TODO how would we support scope?

    //TODO check scope and response_type make sense once they are supported
    if (empty($me)) {
        error_page('Incomplete Request', 'There was an error with the request.  No "me" field given.');
    }
    if (empty($client_id)) {
        error_page('Incomplete Request', 'There was an error with the request.  No "client_id" field given.');
    }
    if (empty($redirect_uri)) {
        error_page('Incomplete Request', 'There was an error with the request.  No "redirect_uri" field given.');
    }
    if (empty($state)) {
        error_page('Incomplete Request', 'There was an error with the request.  No "state" field given.');
    }
    //if($response_type == 'code'){
        //error_page('Not Supported', 'Selfauth currently only supports "response_type=id" (authentication).');
    //}
    if ($response_type != 'code' && $response_type != 'id') {
        error_page('Invalid Request', 'Unknown value encountered. "response_type" must be "id" or "code".');
    }
    $csrf_code = create_signed_code(APP_KEY, $client_id . $redirect_uri . $state, 2 * 60);
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
    <div>You are attempting to login with client <pre><?php echo $client_id; ?></pre></div>
    <div>It is requesting the following scopes <pre><?php echo $scope; ?></pre></div>
    <div>After login you will be redirected to  <pre><?php echo $redirect_uri; ?></pre></div>

    <form method="POST" action="">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf_code); ?>" />
        <input type="hidden" name="redirect_uri" value="<?php echo htmlspecialchars($redirect_uri); ?>" />
        <input type="hidden" name="me" value="<?php echo htmlspecialchars($me); ?>" />
        <input type="hidden" name="response_type" value="<?php echo htmlspecialchars($response_type); ?>" />
        <input type="hidden" name="state" value="<?php echo htmlspecialchars($state); ?>" />
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>" />
        <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>" />
        <div class="form-line"><label for="password">Password:</label> <input type="password" name="password" id="password" /></div>
        <div class="form-line"><input class="submit" type="submit" name="submit" value="Submit" /></div>
    </form>

    </body></html>
<?php
exit();
} //end elseif

$csrf_code      = (isset($_POST['_csrf']) ? $_POST['_csrf']           : null );
$pass_input     = (isset($_POST['password']) ? $_POST['password']        : null );
$me             = (isset($_POST['me']) ? $_POST['me']              : null );
$redirect_uri   = (isset($_POST['redirect_uri']) ? $_POST['redirect_uri']    : null );
$response_type  = (isset($_POST['response_type']) ? $_POST['response_type']   : 'id' );
$state          = (isset($_POST['state']) ? $_POST['state']           : ''   );
$client_id      = (isset($_POST['client_id']) ? $_POST['client_id']       : null );
$scope          = (isset($_POST['scope']) ? $_POST['scope']           : '' );

//TODO check scope and response_type make sense once they are supported

if (!verify_signed_code(APP_KEY, $client_id . $redirect_uri . $state, $csrf_code)) {
    error_page('Invalid csrf code', 'Usually this means you took too long to log in. Please try again.');
}
if (empty($me)) {
    error_page('Incomplete Request', 'There was an error with the request.  No "me" field given.');
}
if (empty($client_id)) {
    error_page('Incomplete Request', 'There was an error with the request.  No "client_id" field given.');
}
if (empty($redirect_uri)) {
    error_page('Incomplete Request', 'There was an error with the request.  No "redirect_uri" field given.');
}
if (empty($state)) {
    error_page('Incomplete Request', 'There was an error with the request.  No "state" field given.');
}
if (empty($pass_input)) {
    error_page('Incomplete Request', 'No Password Given.');
}

// verify login
if (verify_password($me, $pass_input)) {
    $scope_encoded = preg_replace('/ +/', ',', trim($scope));

    $code = create_signed_code(APP_KEY, USER_URL . $redirect_uri . $client_id, 5 * 60, $scope_encoded);

    $final_redir = $redirect_uri;
    if (strpos($redirect_uri, '?') === false) {
        $final_redir .= '?';
    } else {
        $final_redir .= '&';
    }
    $final_redir .= http_build_query(array(
        'code' => $code,
        'state' => $state,
        'me' => $me
    ));

    // redirect back
    header('Location: ' . $final_redir);
    exit();
} else {
    error_page('Login Failed', 'Invalid username or password.');
}


