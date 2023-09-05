<?php

namespace Drupal\Tests\layout_section_classes\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\layout_section_classes\ClassyLayout;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\layout_section_classes\ClassyLayout
 * @group layout_section_classes
 */
class LayoutClassesPluginDefinitionFormatTest extends UnitTestCase {

  /**
   * @covers ::build
   */
  public function testClassesBuild() {
    $configuration = [
      'additional' => [
        'classes' => [
          'foo' => ['class_1', 'class_2'],
          'bar' => 'class_3',
          'baz' => '',
          'qux' => [''],
        ],
      ],
    ];
    $plugin = new ClassyLayout($configuration, 'foo_plugin', new LayoutDefinition([
      'classes' => [
        'foo' => [],
        'bar' => [],
        'baz' => [],
        'qux' => [],
      ],
    ]));
    $build = $plugin->build([]);
    $this->assertEquals([
      'class_1',
      'class_2',
      'class_3',
    ], $build['#attributes']['class']);
  }

  /**
   * Test the plugin definition parsing into a form.
   *
   * @covers ::buildConfigurationForm
   * @dataProvider definitionFormGenerationTestCases
   */
  public function testDefinitionFormGeneration($definition, $configuration, $expected_form) {
    $plugin = new ClassyLayout($configuration, 'foo_plugin', $definition);
    $plugin->setStringTranslation($this->prophesize(TranslationManager::class)->reveal());

    $form = $plugin->buildConfigurationForm([], new FormState());
    array_walk_recursive($form, function (&$value) {
      if ($value instanceof TranslatableMarkup) {
        $value = $value->getUntranslatedString();
      }
    });
    unset($form['label']);
    $this->assertEquals($expected_form['classes'], $form['classes']);
  }

  /**
   * Test cases for ::testDefinitionParsing.
   */
  public function definitionFormGenerationTestCases() {
    return [
      'Standard plugin' => [
        new LayoutDefinition([
          'label' => 'Two column',
          'classes' => [
            'style' => [
              'label' => 'Style',
              'description' => 'Select the style that will be applied to this region.',
              'multiple' => FALSE,
              'required' => FALSE,
              'options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
            ],
            'spacing' => [
              'label' => 'Spacing',
              'multiple' => FALSE,
              'required' => TRUE,
              'default' => 'section--bottom-l section--top-l',
              'options' => [
                'section--bottom-l section--top-l' => 'Standard',
                '' => 'Tight',
              ],
            ],
          ],
        ]),
        [
          'additional' => [
            'classes' => [
              'style' => [],
              'spacing' => [],
            ],
          ],
        ],
        [
          'classes' => [
            '#type' => 'container',
            '#tree' => TRUE,
            'style' => [
              '#title' => 'Style',
              '#type' => 'select',
              '#multiple' => FALSE,
              '#options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
              '#required' => FALSE,
              '#default_value' => [],
              '#description' => 'Select the style that will be applied to this region.',
              '#empty_option' => '- Select -',
            ],
            'spacing' => [
              '#title' => 'Spacing',
              '#type' => 'select',
              '#multiple' => FALSE,
              '#options' => [
                'section--bottom-l section--top-l' => 'Standard',
                '' => 'Tight',
              ],
              '#required' => TRUE,
              '#default_value' => [],
              '#description' => '',
            ],
          ],
        ],
      ],
      'Minimal plugin' => [
        new LayoutDefinition([
          'label' => 'Two column',
          'classes' => [
            'style' => [
              'options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
            ],
          ],
        ]),
        [],
        [
          'classes' => [
            '#type' => 'container',
            '#tree' => TRUE,
            'style' => [
              '#title' => 'Classes',
              '#type' => 'select',
              '#multiple' => FALSE,
              '#options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
              '#required' => FALSE,
              '#default_value' => NULL,
              '#description' => '',
              '#empty_option' => '- Select -',
            ],
          ],
        ],
      ],
      'Mixed string array configuration' => [
        new LayoutDefinition([
          'label' => 'Two column',
          'classes' => [
            'style' => [
              'multiple' => FALSE,
              'options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
            ],
            'style_2' => [
              'multiple' => TRUE,
              'options' => [
                'foo' => 'Foo',
                'bar' => 'Bar',
              ],
            ],
          ],
        ]),
        [
          'additional' => [
            'classes' => [
              'style' => 'background--primary-light',
              'style_2' => ['foo', 'bar'],
            ],
          ],
        ],
        [
          'classes' => [
            '#type' => 'container',
            '#tree' => TRUE,
            'style' => [
              '#title' => 'Classes',
              '#type' => 'select',
              '#multiple' => FALSE,
              '#options' => [
                'background--primary-light' => 'Light background',
                'background--wave-dark background--primary-light' => 'Wave background',
              ],
              '#required' => FALSE,
              '#default_value' => 'background--primary-light',
              '#description' => '',
              '#empty_option' => '- Select -',
            ],
            'style_2' => [
              '#title' => 'Classes',
              '#type' => 'select',
              '#multiple' => TRUE,
              '#options' => [
                'foo' => 'Foo',
                'bar' => 'Bar',
              ],
              '#required' => FALSE,
              '#default_value' => [
                'foo',
                'bar',
              ],
              '#description' => '',
              '#empty_option' => '- Select -',
            ],
          ],
        ],
      ],
    ];
  }

}
