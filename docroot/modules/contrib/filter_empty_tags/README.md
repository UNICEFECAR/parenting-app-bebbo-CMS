# Filter Empty Tags

Simple text format filter to recursively remove empty HTML tags.

## Why this module?

There are a few scenarios in which you will find this useful.

**Remove empty tags in automatically imported content.**

In some workflow, all your nodes are imported and synchronized every once a while (night, day, week, etc..) from an external resource with no clean-up process. Therefore you might end up with tags such as:
```
<p><b></b></p>
```
in you content. From a Drupal point of view, such a content would not be empty and it might become uselessly visible in your template, breaking you design. Unless you preprocess all of this data or manually edit the nodes, this will come back at every import.

_This filter is what you need !_

**Remove empty tags from your contributors**

You might have contributors adding unnecessary empty lines at the end of their contribution. But your CSS takes care of the extra spacing below paragraphs so no need for extra <code><p></p></code>.

_This filter is what you need !_

##Installation and Configuration

After installing the module in the usual way, you need to enable the filter:

- Go to your Input Formats configuration (admin/config/content/formats)
- Open the configuration form of the desired text format.
- In the Enabled filters section, you need to enable the "Filter Empty Tags" filter

You may also need to increase the weight of this filter so that it is the last filter that runs, it's recommended to run this at the last after any other HTML filters. This can be done in the Filter processing order section.

Do not forget to save the configuration by clicking on "Save configuration"

##Improvements and features

Feel free to use the [issue section](https://www.drupal.org/project/issues/filter_empty_tags) to ask for help or new feature on this module.
