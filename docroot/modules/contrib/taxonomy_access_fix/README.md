<!--- cspell:ignore Dekhteruk GABB Oleksandr pifagor rudiedirkx -->
CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

*Taxonomy Access Fix* module extends access handling of Drupal Core's Taxonomy module. It

* adds several permissions to view published and unpublished term names.
* adds several permissions to view published and unpublished terms.
* adds several permissions to reorder terms in vocabularies.
* adds several permissions to select published and unpublished terms in autocomplete widgets of certain entity reference fields.
* adds several permissions to view vocabulary names.
* adds several permissions to reset vocabularies.
* adds permissions to create, delete or edit terms in any vocabulary.
* removes vocabularies the user doesn't have permission to either create, delete, edit, reorder terms in or reset from the vocabulary overview page.

See *CONFIGURATION* for more information on the provided permissions.

For more information about the module, visit the project page:
https://www.drupal.org/project/taxonomy_access_fix

To submit bug reports related to access checks or other security vulnerabilities:
https://security.drupal.org/node/add/project-issue/taxonomy_access_fix

To submit other bug reports and feature suggestions, or to track changes:
https://www.drupal.org/project/issues/taxonomy_access_fix


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install the Taxonomy Access Fix module as you would normally install a contributed Drupal module. Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------
Change permissions as needed on the *Manage* -> *People* -> *Permissions* page. Per-vocabulary permissions can also be managed using the *Manage permissions* tab of a Taxonomy Vocabulary.

A module can't add permissions on behalf of another module, so the extra permissions are listed under "Taxonomy Access Fix" and not under "Taxonomy".


**Access to vocabulary overview page**

To access the vocabulary overview page for a vocabulary, users must have permission to either

* create, edit, delete, reorder terms in or reset that vocabulary in addition to the Taxonomy module's permission to "Access the taxonomy vocabulary overview page".
* to "Administer vocabularies and terms".


**Create, edit or delete terms in any vocabulary**

Per-vocabulary permissions to create, update or delete terms are provided by Drupal Core. Taxonomy Access Fix additionally provides permissions to create, update or delete terms in any vocabulary.


**View term names / View terms**

The per-vocabulary "VOCABULARY: View published term names" permission will allow users to view the term name of published terms. To view term names of unpublished terms, users will need the per-vocabulary "VOCABULARY: View unpublished term names" permission or permission to "Administer vocabularies and terms" provided by Drupal Core.

The per-vocabulary "VOCABULARY: View published terms" permission will allow users to view published terms. To view unpublished terms, users will need the per-vocabulary "VOCABULARY: View unpublished terms" permission or permission to "Administer vocabularies and terms" provided by Drupal Core.

Users having permission to "view terms" do not have permission to view the term name unless they also have a permission required to "view term names".

Permissions to view any terms and view any term names are also available both for published and unpublished terms. They will grant access to view terms or term names for any vocabulary.


**View vocabulary name**

The per-vocabulary "VOCABULARY: View vocabulary name" permission will allow users to view the name of the vocabulary. There is also a permission to "view any vocabulary name".

Users having permission to access the overview page of a vocabulary (i.e. the page that lists all terms in a vocabulary) do also have access to view the vocabulary name even if they don't have permission to "VOCABULARY: View vocabulary name" or "View any vocabulary name".


**Reorder terms**

The per-vocabulary "VOCABULARY: Reorder terms" permission will allow users to change the order of terms.

A permission to "Reorder terms in any vocabulary" is also available.

Users with permission to reorder terms will not have permission to reset the term order to alphabetical, unless they also have permission to reset a vocabulary.


**Reset vocabulary**

The per-vocabulary "VOCABULARY: Reset" permission will allow users to access to the vocabulary reset form that resets the term order of the vocabulary to alphabetical order. If they also have the "Access vocabulary overview page" permission provided by Drupal Core, they will be granted access to the vocabulary collection page and the vocabulary overview page for the relevant vocabulary and the "Reset to alphabetical order" button on that page.

A permission to "Reset any vocabulary" is also available, that will grant access to reset the term order to alphabetical for any vocabulary.

If users don't have permission to access the vocabulary overview page, they will be redirect to the front page after confirming or canceling the reset operation. You can override the redirect by setting a suitable `destination` query string parameter.


**Select terms**

The module will replace the default entity reference selection plugin for Taxonomy Terms with an extended version that checks for a separate per-vocabulary "VOCABULARY: Select published terms" permission. To select unpublished terms, users will need the per-vocabulary "VOCABULARY: Select unpublished terms" permission or permission to "Administer vocabularies and terms" provided by Drupal Core.

Note: Users with permission to select terms will be able to see the term labels of those terms in the results of the autocomplete widget, even if they don't have permission to "view" those term names or terms. Only Entity Reference fields referencing Taxonomy terms using the "Default" reference method will honor this permission.

Permissions to select any term are also available both for published and unpublished terms. They will grant access to "select terms" for any vocabulary.


MAINTAINERS
-----------

* Oleksandr Dekhteruk (pifagor) - https://www.drupal.org/u/pifagor
* rudiedirkx - https://www.drupal.org/u/rudiedirkx

Supporting organizations:

* GOLEMS GABB - https://www.drupal.org/golems-gabb

