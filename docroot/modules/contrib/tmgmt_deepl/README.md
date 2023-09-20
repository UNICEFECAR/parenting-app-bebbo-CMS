TMGMT DeepL (tmgmt_deepl)
---------------------

TMGMT DeepL module is a plugin for Translation Management Tool module (tmgmt).
It uses the DeepL API (https://www.deepl.com/en/docs-api/) for automated
translation of the content. You can use the DeepL API Free (limited to 500.000 
characters per month) or the DeepL API Pro for more than 500.000 characters.
More information on pricing can be found on https://www.deepl.com/pro#developer.

REQUIREMENTS
------------

This module requires TMGMT (http://drupal.org/project/tmgmt) module to be 
installed.

Also you will need to enter your DeepL API authentification key. You can find 
them on the page https://www.deepl.com/pro#developer after registration on 
https://www.deepl.com.

CONFIGURATION
-------------

- add a new translation provider at /admin/tmgmt/translators
- choose between "DeepL API Free" or "DeepL API Pro" and enter your API key
- set additional settings related to the DeepL API 

You can use DeepL simulator to figure out the right DeepL API plugin settings
for your provider: https://www.deepl.com/docs-api/simulator/
