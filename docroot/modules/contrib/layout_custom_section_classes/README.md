# Layout Custom Section Classes & Attributes

Adds possibility to add ID, class, style, data-* attributes to Layout Sections
and for each Region in the Section.

The module is built with a similar UI
as https://www.drupal.org/project/layout_builder_component_attributes
to provide a unified UX.


## Install

The module requires *Php CSS Lint* (https://github.com/neilime/php-css-lint)
for validating inline styles. If you install with composer it should be
installed with it.


## Usage

- Enable Layout Custom Section Classes module.

- Go to /admin/config/content/layout-builder-section-attributes to set up
what attributes should be available to set. You can enable setting ID, class,
style, data-* attributes + you can predefine a class list.
Layout editors that way can select from predefined classes.
By default, every attribute from these will be available to use.

- Set permissions:
  - Administer Layout Builder Custom Section Classes settings:
  this will provide access to
  /admin/config/content/layout-builder-section-attributes form.
  - Administer Layout Builder Section Attributes:
  this will provide access to edit section attributes in Layout Builder.
  - Administer Layout Builder Section's Regions' Attributes:
  this will provide access to edit section region attributes in Layout Builder.

- Enable Layout Builder in manage display for a content type or taxonomy or
whatever entity you're displaying with Layouts.
- Add or Configure a section and there you can enter your settings for section
as a whole and for each regions in the section.
Note that for ID or classes you must provide a valid class,
so you can't use my!#class_1 because only my-class-1 will be valid.
For setting styles, proper CSS syntax must be used.

Setting the classes won't be enough. You need to make sure that the layout
(that you chose for the section) uses the {{ attributes }} variable in its
template for section classes and attributes and
also {{ region_attributes.REGIONNAME }} for each region classes and attributes.
Because that variable contains the specified classes. An example
for the variable usage is in Drupal core's layout--onecol.html.twig:

```
{%
  set classes = [
    'layout',
    'layout--onecol',
  ]
%}
{% if content %}
  <div {{ attributes.addClass(classes) }}>
    <div {{ region_attributes.content.addClass('layout__region') }}>
      {{ content.content }}
    </div>
  </div>
{% endif %}
```
