# Drupal 8 I18N / Translation Migration Example

Although a majority of sites only offer their content in one language, there are many which offer all or some of their content in two or more languages. When a multi-language site decides to migrate to Drupal 8, one of the major concerns is migrating the content whilst preserving the translations. Luckily, Drupal 8 has a very straight forward and standardized framework for supporting translations, unlike its predecessors.

In this project, we would briefly discuss how to migrate translated content into Drupal 8. More specifically, we would see how to migrate the following items into Drupal 8:

* Drupal 6 content - translated with the 'content_translation' module.
* Drupal 7 content - translated with the 'content_translation' module.
* Drupal 7 content - translated with the 'entity_translation' module.
* Non-drupal content - CSV files containing base data and translations.

# Quick start

* Put this module in your Drupal installation:

    ```git clone https://github.com/evolvingweb/migrate_example_i18n.git modules/custom/migrate_example_i18n```

* Install the module.

    ```drush en migrate_example_i18n -y```

* Configure Drupal to talk to a secondary database. For the Drupal 6 example, you can add something like this to your `settings.php`:

    ```
    $databases['drupal_6']['default'] = array(
        'database' => 'migrate_i18n_d6',
        'driver' => 'mysql',
        'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
        'username' => 'root',
        'password' => 'f00b@r',
        'host' => '127.0.01',
    );
    ```
    
    Don't forget to modify the username, password and host!

    You can add similar stanzas for the D7 examples, see [settings.local.php](dump/settings.local.php).

* Create and populate your new source databases. Eg, for Drupal 6:
    
    ```
    drush sql-create --database=drupal_6 --yes
    drush sql-cli --database=drupal_6 < modules/custom/migrate_example_i18n/dump/sandbox_d6.sql
    ```
    
    Again, you can do something similar for D7.

* Check the current status of the migrations.

    ```drush migrate-status```

* Run some migrations introduced by this module. Eg, for Drupal 6:

    ```drush migrate-import --group=example_hybrid --update```

# The problem

We have 4 sets of data from various sources which we have to migrate into Drupal 8:

* **Drupal 6 - Content Translation:** A bunch of _story_ nodes about hybrid animals need to be migrated to Drupal 8. These have been handled in the `config/install/migrate_plus.migration.example_hybrid_*.yml` files.
* **Drupal 7 - Content Translation:** A bunch of _article_ nodes about dogs need to be migrated to Drupal 8. These have been handled in the `config/install/migrate_plus.migration.example_dog_*.yml` files.
* **Drupal 7 - Entity Translation:** A bunch of _article_ nodes about mythological creatures need to be migrated to Drupal 8. These have been handled in the `config/install/migrate_plus.migration.example_creature_*.yml` files.
* **Non-drupal source:** A table of chemical elements is provided in 2 different files - one in English and the other in Spanish. We need to migrate the contents of these two files and create nodes having translations in English and Spanish. These have been handled in the `config/install/migrate_plus.migration.example_element_*.yml` files.

# Assumptions

Since this is an advanced migration topic, it is assumed that you already have the following knowledge:

* How to create a custom module in Drupal 8
* How to write a basic migration in Drupal 8
* How to install and use [drush](http://www.drush.org/) commands
* How to configure a multi-linguage website on Drupal 8

# The module

There is nothing special about the module definition as such, however, here are certain things which need a mention:

* In Drupal 8, unlike Drupal 7, a module only provides a .module file only if required. In our example, we use that file to define some hooks which are required to make this module work correctly.
* Though the migrate module is in Drupal 8 core, we need most of these dependencies to enable / enhance migrations on the site:
  * [migrate_plus](https://www.drupal.org/project/migrate_plus): To make our life easy
  * [migrate_tools](https://www.drupal.org/project/migrate_tools): To make our life easy
  * [migrate_source_csv](https://www.drupal.org/project/migrate_source_csv): To use CSV files as migration data sources.
  * migrate_drupal: We need this module to use Drupal 6 and Drupal 7 sites as data sources for our migration. This module is a part of the Drupal 8.x core.

# Drupal 8 configuration

Before migrating translated content into Drupal 8, one must make sure that their Druapl 8 site actually supports translated content. To do this, we need to:

* Enable the `language` module and set up languages and method of language determination. Example: Set up English and French.
* Enable the `content_translation` module.
* Configure the content types which you want to be translatable. Example, edit the _Article_ content type and enable translations.
* Make sure you have your content types and fields configured as per the data you wish to import. Example, if your source articles have a field named _One-liner_, make sure the Drupal 8 nodes have a corresponding field to save the data in.

# Migrate hybrids: Drupal 6 content translations to Drupal 8

Since Drupal 6 is older, it looks like a better place to start. To get started, we create a migration group named [example_hybrid](config/install/migrate_plus.migration_group.example_hybrid.yml). This would let us execute all grouped migrations with one command like

    drush migrate-import --group=example_hybrid --update

Migrating translated content into Drupal 8 usually involves two steps:

* Base migration: Migrate data in base language and ignore translations.
* Translation migration: Migrate only the translations (and ignore data in base language). These translations are usually linked to the content we create in the first step, thereby leaving us with only one entity with multiple translations.

Before jumping into writing these migrations, it is important to mention that Drupal 6 and Drupal 8 translations work very differently. Here's the difference in a nutshell:

* **Drupal 6:** First, you create a piece of content in its base language. Then, you add a translation of it. However, when you create a translation, another fresh node is created with a different ID and a property named `tnid` is used to save the ID of the original node, thereby recording the fact that the node is a translation of another node. For language-neutral content the `tnid` is set to 0.
* **Drupal 8:** First, you create a piece of content in its base language. Then, you add a translation of it. When you create the translation, no new node is created. The translation is saved against the original node itself but measures are taken to save the translations in the other language.

Hence we follow the two step process for migrating translated content from Drupal 6.

## Hybrid base migration

Having created the migration group, we would create our first migration with the ID [example_hybrid_base](config/install/migrate_plus.migration.example_hybrid_base.yml). We do this by defining some usual parameters:

* **id:** An unique ID for the migration.
* **migration_group:** The group to which the migration belongs.
* **migration_tags:** A set of tags for the migration.
* **source:**
  * **plugin:** Since we want to import data from a Drupal installation, we need to set the source plugin to `d6_node`. The `d6_node` source plugin is introduced by the `migrate_drupal` module and it helps us read nodes from a Drupal 6 installation without having to manually write queries to read the nodes and attaching the relevant fields, etc.
  * **node_type:** With this parameter we tell the source plugin that we are interested in reading a particular node type only, in this case, _story_.
  * **key:** Since we intend to read the Drupal 6 data from a secondary database connection (the primary one being the Drupal 8 database), we need to define the secondary connection in the `$databases` global variable in our `settings.local.php` file. Once done, we need to mention the `key` of the `$databases` array where the Drupal 6 connection is defined.
  * **target:** Optionally, you can also define a _target_. This parameter defaults to `default` and should be defined if your connection is not defined in the `default` sub-key of `$databases`. But usually this parametre is left as `default`, so we can safely omit it.
  * **constants:** We define some static / hard-coded values under this parameter.
  * **translations:** We DO NOT define the translations parameter while migrating base data. Omiting the parameter or setting it to `false` tells the source plugin that we are only interested in migrating non-translations, i.e. content in base language and language-neutral content. It is important NOT to specify this parameter otherwise you will end up with separate nodes for every language variation of each node.
* **destination:**
  * **plugin:** Since we want to create node entities, we specify this as `entity:node`. That's it.
  * **translations:** We DO NOT define the translations parameter while migrating base data. Omiting the parameter or setting it to `false` tells the destination plugin that we are interested in creating fresh nodes for each record as opposed to associating them as translations for existing nodes.
* **process:** This is where we tell migrate how to map the old node properties to the new node properties. Most of the properties have been assigned as is, without alteration, however, some noteworthy properties have been discussed below:
  * **type:** We use a constant value to define the type of nodes we wish to create from the imported data.
  * **langcode:** The `langcode` parameter was formerly `language` in Drupal 6. So we need to assign it properly so that Drupal 8 knows as to in which language the node is to be created. We use the `default_value` plugin here to provide a fallback to the `und` or `undefined` language just in case some node is out of place, however, it is highly unlikely that it happens.
  * **body:** We can assign this property directly to the `body` property. However, the Drupal 6 data is treated as plain text in Drupal 8 in that case. So migrating with `body: body`, the imported nodes in Drupal 8 would show visible HTML markup on your site. To resolve this, we explicitly assign the old `body` to `body/value` and specify that the text is in HTML by writing `body/format: constants/body_format`. That tells Drupal to treat the body as _Full HTML_.

This takes care of the base data. If you run this migration with `drush migrate-import example_hybrid_i18n --update`, all Drupal 6 nodes which are in base language or are language-neutral will be migrated into Drupal 8.

## Hybrid translation migration

We are halfway through now and all that's missing is migrating translations of the nodes we migrated above. To do this, we create another migration with the ID [example_hybrid_i18n](config/install/migrate_plus.migration.example_hybrid_i18n.yml). The migration definition remains mostly the same but has the following important differences as compared to the base migration:

* **source:**
  * **translations:** We define this parameter to make the source plugin read only translation nodes and to make it ignore the nodes we already migrated in the base migration.
* **destination:**
  * **translations:** We define this parameter to make the destination plugin create translations for existing nodes instead of creating fresh nodes for each source record.
* **process:**
  * **nid:** Are we defining an ID for the nodes to be generated? Yes, we are. With the `nid` parameter, we use the `migration` plugin and tell Drupal to create translations for the nodes we created during the base migration, like `plugin: migration` and `migration: example_hybrid_base`. So, for every record, Drupal derives the ID of the relevant node created during the base migration and creates a translation for it.
  * **langcode:** This is important because here we define the language in which the translation should be created.
* **migration_dependencies:** Since we cannot associate the translations to the base nodes if the base nodes do not exist, we tell Drupal that this migration depends on the base migration `example_hybrid_base`. That way, one will be forced to run the base migration before running this migration.

That's it! We can run our translation migration with `drush migrate-import example_hybrid_i18n --update` and the translations will be imported into Drupal 8. You can check if everything went alright by clicking the `Translate` option for any translated node in Drupal 8. If everything went correctly, you should see that the node exists in the original language and has one or more translations.

# Migrate dogs: Drupal 7 content translations to Drupal 8

Great! So another set of content translations! The good news is that content translations work the same way in Drupal 7 as they do in Drupal 6. Drupal 8.3.x and higher support D7 content translations out of the box. For older D8 versions, we can support the `translations` parameter with a custom source plugin like [D7NodeContentTranslation](src/Plugin/migrate/src/D7NodeContentTranslation.php). Here's a quick introduction to the class:

* The class is derived from `\Drupal\node\Plugin\migrate\source\d7\Node` which would eventually support the `translations` parameter and make our lives easier.
* The annotation `@MigrateSource` makes it available as a migration source plugin. The plugin ID being `d7_node_content_translation`.
* The `query` method has been overridden to intercept the query used by the migration module to read source records. We call a `handleTranslations` method on the query which does what it's name says, handles translations.
* The `handleTranslations` method is an exact copy of the one which exists in the Drupal 6 node source plugin. It adds support for the `tranlsations` parameter:
  * If `translations: true`, then it modifies the query so that it would only return translated nodes.
  * If `translations: false`, then it modifies the query so that it would only return non-translations, i.e. nodes in base language and language-neutral nodes.

Apart from that, we have everything going just the way we did for Drupal 6.

## Dog base migration

We define a [example_dog_base](config/install/migrate_plus.migration.example_dog_base.yml) migration to migrate all non-translations first.

* We use our `d7_node_content_translation` plugin as the `source` plugin.
* We do not declare `translations` parameter for the `source` plugin, so that only non-translations are read from Drupal 7.
* We do not declare `translations` parameter for the `destination` plugin. Thus, separate Drupal 8 nodes will be generated for every Drupal 7 node.

## Dog translation migration

We define a [example_dog_i18n](config/install/migrate_plus.migration.example_dog_i18n.yml) migration to migrate all translations.

* We use our `d7_node_content_translation` plugin as the `source` plugin.
* We define `translations: true` for the source plugin so that only translated nodes are read from Drupal 7
* We define `translations: true` for the destination plugin so that the data is migrated as translations for nodes created during the base migration.
* We make sure that the `i18n` migration depends on the `base` migration.

That's it! We can run the base and i18n migrations one by one and all Drupal 7 nodes would be imported to Drupal 8 along with their translations. To execute both the migrations at once, we can run the command `drush migrate-import --group=example_dog --update`.  Perfect!

# Migrate creatures: Drupal 7 entity translations to Drupal 8

Entity translations! Amazing! Drupal 7 content translations are supported since Drupal 8.3. At the point of writing this, there is no standard method for migrating entity translations to Drupal 8. In this example, we will migrate D7 nodes translated with the [entity_translation](https://www.drupal.org/project/entity_translation) module. The procedure should be similar for other node types as well. Before we start, here are some notes about what's so different about entity translations:

* All translations have the same `entity_id`. So, for a translated node, the entity_translation module will result in only one entry in the `node` table.
* Translation information and revisions for entities is stored in the `entity_translation` table. So if an English node with ID 19 has translations in Spanish and French, the `entity_translations` table has the following records:
  * `entity_type: node; entity_id: 19; language: en; ...`
  * `entity_type: node; entity_id: 19; language: es; ...`
  * `entity_type: node; entity_id: 19; language: fr; ...`

The above data structure is significantly different from the content translation structure. In fact, Drupal 8 handles translations much like the entity translation module!

## class D7NodeEntityTranslation

To migrate entity translations, we must make significant number of changes to the migration source (at least at the time of writing this). We need to migrate Drupal 7 nodes, so we extend the `d7_node` migration source.

    class D7NodeEntityTranslation extends D7Node {
      // Determines if the node-type being translated supports entity_translation.
      protected function isEntityTranslatable() {}
      // Depending on the "source/translations" parameter, this method alters
      // the migration query to return only translations or non-translations.
      protected function handleTranslations(SelectInterface $query) {}
      // This method has been overridden to ensure that every node's fields are
      // are loaded in the correct language.
      public function prepareRow(Row $row) {}
      // This method is called by the prepareRow() method to load field values
      // for source nodes. We override this method to add support for $language.
      protected function getFieldValues($entity_type, $field, $entity_id, $revision_id = NULL, $language = NULL) {}
      // Since all source nodes have the same "nid", we need to use a
      // combination of "nid:language" to distinguish each source translation.
      public function getIds() {}
    }

With the above source class in place, we write our migrations as usual.

## Creature base migration

We define a [example_creature_base](config/install/migrate_plus.migration.example_creature_base.yml) migration to migrate all non-translations first.

* We use our `d7_node_entity_translation` plugin as the `source` plugin to handle entity translations correctly.
* We do not declare `translations` parameter for the `source` plugin, so that only non-translations are read from Drupal 7.
* We do not declare `translations` parameter for the `destination` plugin. Thus, separate Drupal 8 nodes will be generated for every Drupal 7 node.

## Creature translation migration

We define a [example_creature_i18n](config/install/migrate_plus.migration.example_creature_i18n.yml) migration to migrate all translations.

* We use our `d7_node_entity_translation` plugin as the `source` plugin to handle entity translations correctly.
* We define `translations: true` for the source plugin so that only translated nodes are read from Drupal 7
* We define `translations: true` for the destination plugin so that the data is migrated as translations for nodes created during the base migration.
* We make sure that the `i18n` migration depends on the `base` migration.

That's it! We can run the base and i18n migrations one by one and all Drupal 7 nodes would be imported to Drupal 8 along with their translations. To execute both the migrations at once, we can run the command `drush migrate-import --group=example_creature --update`. Perfect!

# Migrate elements: Non-drupal translated content to Drupal 8

As we do with any other translated content migration, we will follow the same old two steps here:

* Migrate the content in base language. In our case, this is English (en).
* Migrate the content in Spanish (es) such that they are saved as translations for the English content.

## Element base migration (English)

To achieve this, we define the [example_element_en](config/install/migrate_plus.migration.example_element_en.yml) migration to migrate element data in base language, which in our case is English (en). Here is a quick look at some important parameters used in the migration definition:

* **source:**
  * **plugin:** Since we want to import data from a CSV file, we need to use the _csv_ plugin provided by the [migrate_source_csv](https://www.drupal.org/project/migrate_source_csv) module.
  * **path:** Path to the CSV data source so that the source plugin can read the file.
  * **header_row_count:** Number of initial rows in the CSV file which do not contain actual data. This helps ignore column headings.
  * **keys:** The column or columns in the CSV file which help uniquely identify each record. In our example, the chemical symbol in the column _Symbol_ is unique to each row, hence, we use that as the key.
  * **fields:** A description for every column present in the CSV file. This is used for displaying source details in the UI.
  * **constants:** Some static values for use during the migration.
* **destination:**
  * **plugin:** Nothing fancy here. We aim to create _node_ entities, so we set the `plugin` as `entity:node`.
  * **translations:** Since we are importing the content in base language, we do not specify the `translations` parameter. This will make Drupal create individual nodes for every record.
* **process:** Most of the properties are migrated as is. However, here are some of them which need a special explication:
  * **type:** We hard-code the type of nodes we wish to create, i.e., `type: constants/node_element`.
  * **langcode:** Since all source records are in English, we inform Drupal to save the destination nodes in English as well. We do this by explicitly specifying `langcode` as `en`.
  * **field_element_discoverer:** This field is a bit tricky. Looking at the source, we realize that every element has one or more discoverers. Multiple discoverer names are separated by commas. Thus, we use `plugin: explode` and `delimiter: ', '` to split multiple records into arrays. With the values split into arrays, Drupal understands and saves the column data as multiple values.

After we run this migration like `drush migrate-import example_element_en`, we get a list of all elements in the base language (English).

## Element translation migration (Spanish)

With the base nodes in place, we define a similar migration to the previous one with the ID [example_element_es](config/install/migrate_plus.migration.example_element_es.yml). Let us look at some major differences between the `example_element_es` migration and the `example_element_en` migration:

* **source:**
  * **path:** Since the Spanish node data is in another file, we change the path accordingly.
  * **keys:** The Spanish word for _Symbol_ is _Símbolo_ and it is the column containing the unique ID of each record. Hence, we define it as the source data key. A noteworthy observation here would be the special `í` in the word `Símbolo`. Since it is a special character, setting it as a `key` did not work. So, as a workaround, I had to remove all such accented characters from the column headings and write the `key` parameter as `Simbolo` without the special `í` with a normal `i`.
  * **fields:** The field definitions had to be changed to match the Spanish column names used in the CSV.
* **destination:**
  * **translations:** Since we want Drupal to create translations for English language nodes created during the `example_element_en` migration, we specify `translations: true`.
* **process:**
  * **nid:** As mentioned above, we use the `plugin: migration` to make Drupal lookup nodes which were created during the English element migration and use their ID as the `nid`. This results in the Spanish translations being attached to the original nodes created in English.
  * **langcode:** Since all records in [element.data.es.csv](import/element/element.data.es.csv) are in Spanish, we hard-code the `langcode` to `es` for each record of this migration. This tell Drupal that these are _Spanish_ translations.
* **migration_dependencies:** This ensures that the base data is migrated before the translations. So to run this migration, one must run the `example_element_en` migration first.

Voilà! Run the Spanish migration like `drush migrate-import example_element_es` and you have the Spanish translations for the elements! If we had another file containing French translations, we would create another migration like we did for Spanish and import the French data in a similar way. I could not find a CSV with element data in French, so I could not include it in this example :(

# Things to remember

* We migrate the base data first! No need for setting any `translations` parameters here.
* We migrate the translations after the base data. We need to set `translations: true` for the `destination` plugin. We might have to set `translations: true` for the source plugin depending on the source we are using.
* We must ensure that the correct `langcode` is being set for the destination nodes.
* We must ensure that the translation migration depends on the base migration. We do this using the `migration_dependencies` parameter.
