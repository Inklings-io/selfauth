<html>
<body>
<h1>Setup Selfauth</h1>
<div>In order to configure selfauth, you need to fill in a few values, this page helps generate those options.</div>
<?php if(isset($_POST['submit'])):?>
<div>
Fill in the file config.php with the following content
<pre>
<?php
    $app_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] 
      . str_replace('setup.php', '', $_SERVER['REQUEST_URI']);
    $app_key = md5(time().$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    $user = $_POST['username'];

    $user_tmp = trim(preg_replace('/^https?:\/\//', '', $_POST['username']), '/');
    $pass = md5($user_tmp . $_POST['password'] . $app_key);
 ?>
&lt;?php 
define('APP_URL', '<?php echo $app_url ?>');
define('APP_KEY', '<?php echo $app_key ?>');
define('USER_HASH', '<?php echo $pass?>');
define('USER_URL', '<?php echo $user?>');
</pre>
</div>
<?php endif ?>
<form method="POST" action="">
<label>Login Url</label>
<input name='username' />
<br>
<label>Password</label>
<input name='password' />

<input type="submit" name="submit" />
</form>
</body>
</html>
