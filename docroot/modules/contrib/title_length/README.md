# Title Length

## Introduction
The Title Length allows to change the length of the entities title field.

## Installation and configuration

* Set the desired size. By default, the module increases the size of the field
  up to 500 characters. If you want another size, you can change this value with
  any of the following variables in the settings.php file:
  * `$settings['node_title_length_chars'] = WANTED_SIZE;`
  * `$settings['taxonomy_term_title_length_chars'] = WANTED_SIZE;`
* Activate the node_title_length and/or the taxonomy_term_title_length module
  to increase the length of the title/name field.
* If you change the size after installing the module, you can rerun the updates
  with the following commands:
  * `drush title_length:update node`
  * `drush title_length:update taxonomy_term`
