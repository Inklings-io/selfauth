# Selfauth

Selfauth is a self-hosted [Authorization Endpoint](https://indieweb.org/authorization-endpoint) used to login with a personal URL (as [Web sign-in](http://indieweb.org/Web_sign-in)) via [IndieAuth](https://indieweb.org/IndieAuth). See [How it works](#how-it-works) for more.


## Warnings

- While Selfauth will work with old versions of PHP, some of the more secure functions Selfauth uses were not added until version 5.6. While older versions are not completely insecure, **it is strongly recommended you upgrade to a newer version of PHP**.

- Currently selfauth only supports authentication, not authorization.  Meaning **scopes will not work yet**. If you need access to these features, it is advisable to use something else for now.


## Setup

To set up Selfauth, create a folder on your webserver and add the files in this repository to it. You can name the folder anything you like, but in this example we will work with 'auth' under `https://example.com/auth/`.

1. Create a folder called 'auth' on your webserver and add at least `index.php` and `setup.php`.

2. Go to `https://example.com/auth/setup.php` and fill in the form: pick the personal URL you're trying to log in for (in our case `https://example.com`) and choose a password.

3. Find the index-page of your domain and add the following code inside the `<head>` tag:
    ```html
    <link rel="authorization_endpoint" href="https://example.com/auth/" />
    ```
    ... where `https://example.com/auth/` is the URL you installed Selfauth to.
    (The exact location of your HTML `<head>` could be hidden in your CMS. Look for help in their documentation. Setting a HTTP Link header like `Link: <https://example.com/auth/>; rel="authorization_endpoint"` should work too.)

You can delete the file `setup.php` if you want, but this is optional. It will not be able to save a new password for you once the setup is completed.


## Changing your password

To change your password, make sure the `setup.php` file is in place again and delete `config.php`. Then follow the steps under [Setup](#setup) again.


## How it works

On a (Web)App which supports [IndieAuth](https://indieweb.org/IndieAuth), you can enter your personal URL. The App will detect Selfauth as Authorization Endpoint and redirect you to it. After you enter your password in Selfauth, you are redirected back to the App with a code. The App will verify the code with Selfauth and logs you in as your personal URL.

To test it, you can go to an App that supports IndieAuth and enter your personal URL. [IndieAuth.com](https://indieauth.com/) has a test-form on the frontpage. If you also link to your social media accounts using `rel="me"`, IndieAuth.com might show you a list of buttons, on which you can click the one that has your Selfauth URL on it.


## License

Copyright 2017 by Ben Roberts and contributors

Available under the Creative Commons CC0 1.0 Universal and MIT licenses.

See CC0-LICENSE.md and MIT-LICENSE.md for the text of these licenses.

