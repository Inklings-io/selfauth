<?php
function error_page($header, $body, $http = '400 Bad Request')
{
    $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
    header($protocol . ' ' . $http);
    $html = <<<HTML
<!doctype html>
<html>
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
</html>
HTML;
    die($html);
}

$configfile= __DIR__ . '/config.php';
if (file_exists($configfile)) {
    include_once $configfile;
} else {
    error_page(
        'Configuration Error',
        'Endpoint not yet configured, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.'
    );
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

function filter_input_regexp($type, $variable, $regexp)
{
    return filter_input(
        $type,
        $variable,
        FILTER_VALIDATE_REGEXP,
        array(
            'options' => array('regexp' => $regexp)
        )
    );
}

function get_q_value($mime, $accept)
{
    $fulltype = preg_replace('@^([^/]+\/).+$@', '$1*', $mime);
    $regex = implode(
        '',
        array(
            '/(?<=^|,)\s*(\*\/\*|',
            preg_quote($fulltype, '/'),
            '|',
            preg_quote($mime, '/'),
            ')\s*(?:[^,]*?;\s*q\s*=\s*([0-9.]+))?\s*(?:,|$)/'
        )
    );
    $out = preg_match_all($regex, $accept, $matches);
    $types = array_combine($matches[1], $matches[2]);
    if (array_key_exists($mime, $types)) {
        $q = $types[$mime];
    } elseif (array_key_exists($fulltype, $types)) {
        $q = $types[$fulltype];
    } elseif (array_key_exists('*/*', $types)) {
        $q = $types['*/*'];
    } else {
        return 0;
    }
    return $q === '' ? 1 : floatval($q);
}

if ((!defined('APP_URL') || APP_URL == '')
    || (!defined('APP_KEY') || APP_KEY == '')
    || (!defined('USER_HASH') || USER_HASH == '')
    || (!defined('USER_URL') || USER_URL == '')
) {
    error_page(
        'Configuration Error',
        'Endpoint not configured correctly, visit <a href="setup.php">setup.php</a> for instructions on how to set it up.'
    );
}

// First handle verification of codes.
$code = filter_input_regexp(INPUT_POST, 'code', '@^[0-9a-f]+:[0-9a-f]{64}:@');

if ($code !== null) {
    $redirect_uri = filter_input(INPUT_POST, 'redirect_uri', FILTER_VALIDATE_URL);
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_URL);

    // Exit if there are errors in the client supplied data.
    if (!(is_string($code)
        && is_string($redirect_uri)
        && is_string($client_id)
        && verify_signed_code(APP_KEY, USER_URL . $redirect_uri . $client_id, $code))
    ) {
        error_page('Verification Failed', 'Given Code Was Invalid');
    }

    // Find the q value for application/json.
    $json = get_q_value('application/json', $_SERVER['HTTP_ACCEPT']);

    // Find the q value for application/x-www-form-urlencoded.
    $form = get_q_value('application/x-www-form-urlencoded', $_SERVER['HTTP_ACCEPT']);

    // Respond in the correct way.
    if ($json === 0 && $form === 0) {
        error_page(
            'No Accepted Response Types',
            'The client accepts neither JSON nor Form encoded responses.',
            '406 Not Acceptable'
        );
    } elseif ($json >= $form) {
        header('Content-Type: application/json');
        exit(json_encode(array('me' => USER_URL)));
    } else {
        header('Content-Type: application/x-www-form-urlencoded');
        exit(http_build_query(array('me' => USER_URL)));
    }
}

// If this is not verification, collect all the client supplied data. Exit on errors.

$me = filter_input(INPUT_GET, 'me', FILTER_VALIDATE_URL);
$client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_URL);
$redirect_uri = filter_input(INPUT_GET, 'redirect_uri', FILTER_VALIDATE_URL);
$state = filter_input_regexp(INPUT_GET, 'state', '@^[\x20-\x7E]*$@');
$response_type = filter_input_regexp(INPUT_GET, 'response_type', '@^(id|code)$@');
$scope = filter_input_regexp(INPUT_GET, 'scope', '@^[\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*$@');

if (!is_string($me)) { // me is either omitted or not a valid URL.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "me" field is invalid.'
    );
}
if (!is_string($client_id)) { // client_id is either omitted or not a valid URL.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "client_id" field is invalid.'
    );
}
if (!is_string($redirect_uri)) { // redirect_uri is either omitted or not a valid URL.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "redirect_uri" field is invalid.'
    );
}
if ($state === false) { // state contains invalid characters.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "state" field contains invalid data.'
    );
}
if ($response_type === false) { // response_type is given as something other than id or code.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "response_type" field must be "id" or "code".'
    );
}
if ($scope === false) { // scope contains invalid characters.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "scope" field contains invalid data.'
    );
}
if ($response_type !== 'code' & $scope !== null) { // scope defined on identification request.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "scope" field cannot be used with identification.'
    );
}
if ($response_type === 'code' & $scope === null) { // scope omitted on code request.
    error_page(
        'Faulty Request',
        'There was an error with the request. The "scope" field must be used with code requests.'
    );
}

// If the user submitted a password, get ready to redirect back to the callback.

$pass_input = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

if ($pass_input !== null) {
    $csrf_code = filter_input(INPUT_POST, '_csrf', FILTER_UNSAFE_RAW);

    // Exit if the CSRF does not verify.
    if ($csrf_code === null || !verify_signed_code(APP_KEY, $client_id . $redirect_uri . $state, $csrf_code)) {
        error_page(
            'Invalid CSF Code',
            'Usually this means you took too long to log in. Please try again.'
        );
    }

    // Exit if the password does not verify.
    if (!verify_password($me, $pass_input)) {
        error_page('Login Failed', 'Invalid username or password.');
    }

    $code = create_signed_code(APP_KEY, USER_URL . $redirect_uri . $client_id, 5 * 60, $scope);

    $final_redir = $redirect_uri;
    if (strpos($redirect_uri, '?') === false) {
        $final_redir .= '?';
    } else {
        $final_redir .= '&';
    }
    $final_redir .= http_build_query(
        array(
            'code' => $code,
            'state' => $state,
            'me' => $me
        )
    );

    // Redirect back.
    header('Location: ' . $final_redir, true, 302);
    exit();
}

// If neither password nor a code was submitted, we need to ask the user to authenticate.

$csrf_code = create_signed_code(APP_KEY, $client_id . $redirect_uri . $state, 2 * 60);

?><!doctype html>
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
        <div>It is requesting the following scopes <pre><?php echo htmlspecialchars($scope); ?></pre></div>
        <div>After login you will be redirected to  <pre><?php echo $redirect_uri; ?></pre></div>
        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?php echo $csrf_code; ?>" />
            <div class="form-line">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" />
            </div>
            <div class="form-line">
                <input class="submit" type="submit" name="submit" value="Submit" />
            </div>
        </form>
    </body>
</html>
