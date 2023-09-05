CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Using Twig Templates
 * Maintainers


INTRODUCTION
------------

The Content Moderation Notifications module allows notifications to be sent to
all users of a particular role, or to the content's author when a piece of
content is transitioned from one state to another via core's Content Moderation
module.

 * For a full description of the module visit:
   https://www.drupal.org/project/content_moderation_notifications

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/content_moderation_notifications


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install the Content Moderation Notifications module as you would normally
install a contributed Drupal module. Visit https://www.drupal.org/node/1897420
for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module and its
       dependencies.
    2. Navigate to Administration > Configuration > Workflow > Content
       Moderation Notifications to add notifications.
    3. Select a workflow.
    4. Select which transitions triggers this notification.
    5. Choose which roles and/or ad-hoc email addresses should receive notifications.
    6. Configure the message settings.
    7. Create Notification.

USING TWIG TEMPLATES
--------------------

You may use any valid Twig syntax within the Subject, Adhoc Email addresses, and
Message fields.

This will allow you to include additional fields, entity references, conditional
logic and TWIG functions in these fields.

NOTE: The Twig Tweak module will provide many additional Twig functions to your
template, such as drupal_token() which can be used to display any available
tokens.

Examples:

Subject: (no Twig used)
  New Content Needs Review

Subject: (using Twig variables)
  {{ entity.bundle|title }} from {{ entity.Owner.name.0.value }} Needs Review

Subject: (using Twig variables, comments, and conditionals and Twig Tweak's drupal_token() function)
  {% set author = entity.Owner.name.0.value %}
  {{ entity.bundle|title }} from {{ author }} Needs Review
  {# Get their attention if it's from someone important #}
  {% if author == "admin" %}
    !!! DO IT NOW !!!
  {% endif %}
  ({{ drupal_token('site:name') }})

Adhoc Email addresses: (add an email in an single-value entity reference field and a standard email box)
  {{ entity.field_department.entity.field_manager_email.0.value }}, dropbox@example.com

Adhoc Email addresses: (add a standard email box and all emails in an multi-value entity reference field)
  dropbox@example.com
  {% for referenced_entity in entity.field_content_owners %}
    {{ referenced_entity.entity.field_email.0.value }}
  {% endfor %}

Message:  (Can support all the same Twig options, but will run template output through the selected
           Text Format's (Basic HTML, Full HTML, etc) Input Filters  before it will be mailed)
  Please update this content from {{ entity.Owner.name.0.value }}.


MAINTAINERS
-----------

 * Jonathan Hedstrom (jhedstrom) - https://www.drupal.org/u/jhedstrom
 * Rob Holmes - https://www.drupal.org/u/rob-holmes

Supporting organization:

 * S8080 - https://www.drupal.org/s8080
