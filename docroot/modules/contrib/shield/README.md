Shield
------

### Summary

PHP Authentication shield. It creates a simple shield for the site with HTTP
basic authentication. It hides the sites, if the user does not know a simple
username/password. It handles Drupal as a
["walled garden"](http://en.wikipedia.org/wiki/Walled_garden_%28technology%29).

This module helps you to protect your (dev) site with HTTP authentication.

### Basic configuration

To enable shield:

1. Enable the module
2. Go to the admin interface (admin/config/system/shield).
3. Check **Enable Shield** box, fill the **User** and **Password** fields.
4. Nothing else :)

### Exception options

There may be situation where shield should not interact with the default
process. For this reason, it is possible to configure some exceptions:

#### CLI
Allow the site to be accessed from the command line without Shield interfering.

#### IP
Allow specific IPs or range of IPs to access the site without being prompted
the basic auth. Be cautious using this option with reverse proxy caching in
use as the response may be cached and so accessible publicly.

#### HTTP method
Allow specific HTTP methods to not be protected by Shield. One example of use
is to allow OPTIONS requests in CORS context. See issue
[3085510](https://www.drupal.org/project/shield/issues/3085510) for more
details.

#### Domain
Allow specific domains to be publicly accessible. Typical use case is to allow
front-office domain so the back-office domain remains protected.

#### Paths
Allow specific path to be accessed without Shield being prompted (exclude mode)
or protect only specific path (include mode).

### Misc.

#### Debug header
With all the exception options, it may be unclear why basic auth is not
prompted. For this reason a debug header option has been added. It will
a `X-Shield-Status` header in the response. These are the various
options:
- `pending` (HTTP 401, basic auth is prompted)
- `authenticated` (HTTP 200, basic auth has been submitted)
- `disabled` (Enabled shield box is unchecked)
- `skipped (cli)` (HTTP 200, basic auth not prompted because it is a CLI
request
and "Allow command line access" is checked)
- `skipped (ip)` (HTTP 200, basic auth not prompted because the visitor's IP is
allowed)
- `skipped (http method)` (HTTP 200, basic auth not prompted because the HTTP
method of the request is allowed)
- `skipped (subrequest)` (HTTP 200, basic auth not prompted because it is
a subrequest and shield does handle it)
- `skipped (domain)` (HTTP 200, basic auth not prompted because the domain is
allowed)
- `skipped (path)` (HTTP 200, basic auth not prompted because the path is
allowed)

### Configuration via settings.php

The shield module can be configured via settings.php. This allows
it to be enabled on some environments and not on others using code.

#### Example with shield disabled:
To disable shield set **shield_enable** to **FALSE**.

```php
$config['shield.settings']['shield_enable'] = FALSE;
```
#### Example with shield enabled:
To enable shield set **user** and **pass** to real values.

```php
$config['shield.settings']['shield_enable'] = TRUE;
$config['shield.settings']['credentials']['shield']['user'] = 'username';
$config['shield.settings']['credentials']['shield']['pass'] = 'password';
$config['shield.settings']['print'] = 'Protected by a username and password.';
```

### Key module

The configuration storage supports storing the authentication in configuration
or in secure keys using http://www.drupal.org/project/key module. For the most
secure keys, use the key module 1.7 or higher which has a multi-value
user/password key for storing the user and password in a single key.

***See: <https://www.drupal.org/project/shield>***
