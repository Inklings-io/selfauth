<html>
<body>
<h1>Setup Selfauth</h1>
<div>In order to configure selfauth, you need to fill in a few values, this page helps generate those options.</div>
<?php if(isset($_POST['username'])):?>
<div>
<?php
    define('RANDOM_BYTE_COUNT', 30);

    $app_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] 
      . str_replace('setup.php', '', $_SERVER['REQUEST_URI']);

    if(function_exists('random_bytes')) {
        $app_key = md5(random_bytes(RANDOM_BYTE_COUNT));
    } elseif(function_exists('openssl_random_pseudo_bytes')){
        $app_key = md5(openssl_random_pseudo_bytes(RANDOM_BYTE_COUNT));
    } else {
        $app_key = md5(mt_rand());
    }

    $user = $_POST['username'];

    $user_tmp = trim(preg_replace('/^https?:\/\//', '', $_POST['username']), '/');
    $pass = md5($user_tmp . $_POST['password'] . $app_key);

    $config_file_contents = "<?php
define('APP_URL', '$app_url');
define('APP_KEY', '$app_key');
define('USER_HASH', '$pass');
define('USER_URL', '$user');";



    $configfile= __DIR__ . '/config.php';

    $configured = true;

    if(file_exists($configfile)){

        require_once $configfile;
        
        if((!defined('APP_URL') || APP_URL == '')
            || (!defined('APP_KEY') || APP_KEY == '')
            || (!defined('USER_HASH') || USER_HASH == '')
            || (!defined('USER_URL') || USER_URL == '')
        ) {
            $configured = false;
        }
    } else {
        $configured = false;
    }

	$file_written = false;

    if(is_writeable($configfile) && !$configured()){

		$handle = fopen($configfile, 'w');

		if($handle){
			$result = fwrite($handle, $config_file_contents);
			if($result !== FALSE){
				$file_written = true;
			}
			
		}

		fclose($handle);
    }


    if($file_written){
		echo 'config.php was successfully written to disk';
	} else {
        echo 'Fill in the file config.php with the following content';
        echo '<pre>';
        echo htmlentities($config_file_contents);
        echo '</pre>';
    }
 ?>
</div>
<?php endif ?>
<form method="POST" action="">
<label>Login Url</label>
<input name='username' />
<br>
<label>Password</label>
<input type='password' name='password' />

<input type="submit" name="submit" />
</form>
</body>
</html>
