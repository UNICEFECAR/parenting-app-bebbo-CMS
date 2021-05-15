<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Core\Validation\Plugin\Validation\Constraint\LengthConstraint;
use Drupal\json_field\Plugin\Field\FieldType\JSONItem;

/**
 * @coversDefaultClass \Drupal\json_field\Plugin\Field\FieldType\JSONItem
 *
 * @group json_field
 */
class JsonItemTest extends KernelTestBase {

  /**
   * Tests that field values are saved a retrievable.
   */
  public function testFieldCreate() {
    $this->createTestField();

    $entity = EntityTest::create([
      'test_json_field' => json_encode([]),
    ]);
    $entity->save();

    $this->assertEquals(json_encode([]), $entity->test_json_field->value);
  }

  /**
   * Tests that default values are used when no value is added.
   */
  public function testFieldCreateWithDefaultValue() {
    $field_settings = [
      'default_value' => [
        0 => [
          'value' => json_encode(['hey' => 'joe']),
        ],
      ],
    ];
    $this->createTestField([], $field_settings);

    $entity = EntityTest::create([]);
    $entity->save();

    $this->assertEquals(json_encode(['hey' => 'joe']), $entity->test_json_field->value);
  }

  /**
   * Tests the validators.
   */
  public function testValidation() {
    $this->createTestField();

    $entity = EntityTest::create([
      'test_json_field' => "this-is-not-valid-json';2';12'3;12'3;",
    ]);
    $entity->save();

    $constraint_list = $entity->validate()->getByField('test_json_field');
    $this->assertEquals(1, $constraint_list->count());
    $this->assertEquals('The supplied value is not valid JSON data (@error).', $constraint_list->get(0)->getMessage()->getUntranslatedString());
  }

  /**
   * Test character limit constraints.
   *
   * @dataProvider providerTestCharacterLimit
   */
  public function testCharacterLimit($size, $limit) {
    $storage = [
      'settings' => [
        'size' => $size,
      ],
    ];
    $this->createTestField($storage);

    $entity = EntityTest::create([
      // Valid JSON 1 character larger than $limit.
      'test_json_field' => '"' . str_repeat('x', $limit - 1) . '"',
    ]);

    $constraint_list = $entity->validate()->getByField('test_json_field');
    $this->assertEquals(1, $constraint_list->count());
    /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
    $violation = $constraint_list->get(0);
    $this->assertTrue($violation->getConstraint() instanceof LengthConstraint);
  }

  /**
   * Data provider.
   *
   * @see testCharacterLimit()
   */
  public function providerTestCharacterLimit() {
    return [
      [JSONItem::SIZE_SMALL, JSONItem::SIZE_SMALL],
      [JSONItem::SIZE_NORMAL, JSONItem::SIZE_NORMAL / 4],
      [JSONItem::SIZE_MEDIUM, JSONItem::SIZE_MEDIUM / 4],
      // JSONItem::SIZE_BIG is too large to test like this.
    ];
  }

  /**
   * @dataProvider providerTestSchemaSize
   */
  public function testSchemaSize($size, array $expected) {
    $storage = [
      'settings' => [
        'size' => $size,
      ],
    ];
    $this->createTestField($storage);

    $schema = $this->getTableSchema(\Drupal::database(), 'entity_test__test_json_field');
    $this->assertEquals($expected, $schema['fields']['test_json_field_value']);
  }

  /**
   *
   */
  public function providerTestSchemaSize() {
    $data = [];
    $data[] = [JSONItem::SIZE_SMALL, [
      'type' => 'varchar',
      'not null' => 1,
      'length' => 255,
    ]];
    $data[] = [JSONItem::SIZE_NORMAL, [
      'type' => 'text',
      'not null' => 1,
      'size' => 'normal',
    ]];
    $data[] = [JSONItem::SIZE_MEDIUM, [
      'type' => 'text',
      'not null' => 1,
      'size' => 'medium',
    ]];
    $data[] = [JSONItem::SIZE_BIG, [
      'type' => 'text',
      'not null' => 1,
      'size' => 'big',
    ]];

    return $data;
  }

  /**
   *
   */
  protected function getTableSchema(Connection $connection, $table) {
    // Check this is MySQL.
    if ($connection->databaseType() !== 'mysql') {
      throw new \RuntimeException('This script can only be used with MySQL database backends.');
    }

    $query = $connection->query("SHOW FULL COLUMNS FROM {" . $table . "}");
    $definition = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $name = $row['Field'];
      // Parse out the field type and meta information.
      preg_match('@([a-z]+)(?:\((\d+)(?:,(\d+))?\))?\s*(unsigned)?@', $row['Type'], $matches);
      $type  = $this->fieldTypeMap($connection, $matches[1]);
      if ($row['Extra'] === 'auto_increment') {
        // If this is an auto increment, then the type is 'serial'.
        $type = 'serial';
      }
      $definition['fields'][$name] = [
        'type' => $type,
        'not null' => $row['Null'] === 'NO',
      ];
      if ($size = $this->fieldSizeMap($connection, $matches[1])) {
        $definition['fields'][$name]['size'] = $size;
      }
      if (isset($matches[2]) && $type === 'numeric') {
        // Add precision and scale.
        $definition['fields'][$name]['precision'] = $matches[2];
        $definition['fields'][$name]['scale'] = $matches[3];
      }
      elseif ($type === 'time' || $type === 'datetime') {
        // @todo Core doesn't support these, but copied from `migrate-db.sh` for now.
        // Convert to varchar.
        $definition['fields'][$name]['type'] = 'varchar';
        $definition['fields'][$name]['length'] = '100';
      }
      elseif (!isset($definition['fields'][$name]['size'])) {
        // Try use the provided length, if it doesn't exist default to 100. It's
        // not great but good enough for our dumps at this point.
        $definition['fields'][$name]['length'] = isset($matches[2]) ? $matches[2] : 100;
      }

      if (isset($row['Default'])) {
        $definition['fields'][$name]['default'] = $row['Default'];
      }

      if (isset($matches[4])) {
        $definition['fields'][$name]['unsigned'] = TRUE;
      }

      // Check for the 'varchar_ascii' type that should be 'binary'.
      if (isset($row['Collation']) && $row['Collation'] == 'ascii_bin') {
        $definition['fields'][$name]['type'] = 'varchar_ascii';
        $definition['fields'][$name]['binary'] = TRUE;
      }

      // Check for the non-binary 'varchar_ascii'.
      if (isset($row['Collation']) && $row['Collation'] == 'ascii_general_ci') {
        $definition['fields'][$name]['type'] = 'varchar_ascii';
      }

      // Check for the 'utf8_bin' collation.
      if (isset($row['Collation']) && $row['Collation'] == 'utf8_bin') {
        $definition['fields'][$name]['binary'] = TRUE;
      }
    }

    // Set primary key, unique keys, and indexes.
    $this->getTableIndexes($connection, $table, $definition);

    // Set table collation.
    $this->getTableCollation($connection, $table, $definition);

    return $definition;
  }

  /**
   * Adds primary key, unique keys, and index information to the schema.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to find indexes for.
   * @param array &$definition
   *   The schema definition to modify.
   */
  protected function getTableIndexes(Connection $connection, $table, &$definition) {
    // Note, this query doesn't support ordering, so that is worked around
    // below by keying the array on Seq_in_index.
    $query = $connection->query("SHOW INDEX FROM {" . $table . "}");
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $index_name = $row['Key_name'];
      $column = $row['Column_name'];
      // Key the arrays by the index sequence for proper ordering (start at 0).
      $order = $row['Seq_in_index'] - 1;

      // If specified, add length to the index.
      if ($row['Sub_part']) {
        $column = [$column, $row['Sub_part']];
      }

      if ($index_name === 'PRIMARY') {
        $definition['primary key'][$order] = $column;
      }
      elseif ($row['Non_unique'] == 0) {
        $definition['unique keys'][$index_name][$order] = $column;
      }
      else {
        $definition['indexes'][$index_name][$order] = $column;
      }
    }
  }

  /**
   * Set the table collation.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to find indexes for.
   * @param array &$definition
   *   The schema definition to modify.
   */
  protected function getTableCollation(Connection $connection, $table, &$definition) {
    $query = $connection->query("SHOW TABLE STATUS LIKE '{" . $table . "}'");
    $data = $query->fetchAssoc();

    // Set `mysql_character_set`. This will be ignored by other backends.
    $definition['mysql_character_set'] = str_replace('_general_ci', '', $data['Collation']);
  }

  /**
   * Gets all data from a given table.
   *
   * If a table is set to be schema only, and empty array is returned.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table to query.
   *
   * @return array
   *   The data from the table as an array.
   */
  protected function getTableData(Connection $connection, $table) {
    $order = $this->getFieldOrder($connection, $table);
    $query = $connection->query("SELECT * FROM {" . $table . "} " . $order);
    $results = [];
    while (($row = $query->fetchAssoc()) !== FALSE) {
      $results[] = $row;
    }
    return $results;
  }

  /**
   * Given a database field type, return a Drupal type.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $type
   *   The MySQL field type.
   *
   * @return string
   *   The Drupal schema field type. If there is no mapping, the original field
   *   type is returned.
   */
  protected function fieldTypeMap(Connection $connection, $type) {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $connection->schema()->getFieldTypeMap());
    $map = array_flip($map);

    // The MySql map contains type:size. Remove the size part.
    return isset($map[$type]) ? explode(':', $map[$type])[0] : $type;
  }

  /**
   * Given a database field type, return a Drupal size.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $type
   *   The MySQL field type.
   *
   * @return string
   *   The Drupal schema field size.
   */
  protected function fieldSizeMap(Connection $connection, $type) {
    // Convert everything to lowercase.
    $map = array_map('strtolower', $connection->schema()->getFieldTypeMap());
    $map = array_flip($map);

    $schema_type = explode(':', $map[$type])[0];
    // Only specify size on these types.
    if (in_array($schema_type, ['blob', 'float', 'int', 'text'])) {
      // The MySql map contains type:size. Remove the type part.
      return explode(':', $map[$type])[1];
    }
  }

  /**
   * Gets field ordering for a given table.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The table name.
   *
   * @return string
   *   The order string to append to the query.
   */
  protected function getFieldOrder(Connection $connection, $table) {
    // @todo This is MySQL only since there are no Database API functions for
    // table column data.
    // @todo This code is duplicated in `core/scripts/migrate-db.sh`.
    $connection_info = $connection->getConnectionOptions();
    // Order by primary keys.
    $order = '';
    $query = "SELECT `COLUMN_NAME`
      FROM `information_schema`.`COLUMNS`
      WHERE (`TABLE_SCHEMA` = '" . $connection_info['database'] . "')
      AND (`TABLE_NAME` = '{" . $table . "}')
      AND (`COLUMN_KEY` = 'PRI')
      ORDER BY COLUMN_NAME";
    $results = $connection->query($query);
    while (($row = $results->fetchAssoc()) !== FALSE) {
      $order .= $row['COLUMN_NAME'] . ', ';
    }
    if (!empty($order)) {
      $order = ' ORDER BY ' . rtrim($order, ', ');
    }
    return $order;
  }

}
