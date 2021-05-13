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
3. In the form select the **Enable** checkbox and add **User** and **Password**.
4. Nothing else :)

Leaving the **User** field blank disables shield even if **Enable** is checked.

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
$config['shield.settings']['print'] = 'This site is protected by a username and password.';
```

### Key module

The configuration storage supports storing the authentication in configuration
or in secure keys using http://www.drupal.org/project/key module. For the most
secure keys, use the key module 1.7 or higher which has a multi-value
user/password key for storing the user and password in a single key.

***See: <https://www.drupal.org/project/shield>***
