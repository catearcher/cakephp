<?php
/**
 * PHP Version 5.4
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Database\Schema;

use Cake\Database\Connection;
use Cake\Error;

/**
 * Represents a single table in a database schema.
 *
 * Can either be populated using the reflection API's
 * or by incrementally building an instance using
 * methods.
 *
 * Once created Table instances can be added to
 * Schema\Collection objects.
 */
class Table {

/**
 * The name of the table
 *
 * @var string
 */
	protected $_table;

/**
 * Columns in the table.
 *
 * @var array
 */
	protected $_columns = [];

/**
 * Indexes + Keys in the table.
 *
 * @var array
 */
	protected $_indexes = [];

/**
 * The valid keys that can be used in a column
 * definition.
 *
 * @var array
 */
	protected $_columnKeys = [
		'type' => null,
		'length' => null,
		'null' => null,
		'default' => null,
		'fixed' => null,
		'comment' => null,
		'collate' => null,
		'charset' => null,
	];

/**
 * The valid keys that can be used in an index
 * definition.
 *
 * @var array
 */
	protected $_indexKeys = [
		'type' => null,
		'columns' => [],
		'length' => [],
	];

/**
 * Names of the valid index types.
 *
 * @var array
 */
	protected $_validIndexTypes = [
		self::INDEX_PRIMARY,
		self::INDEX_INDEX,
		self::INDEX_UNIQUE,
		self::INDEX_FOREIGN,
		self::INDEX_FULLTEXT,
	];

	const INDEX_PRIMARY = 'primary';
	const INDEX_INDEX = 'index';
	const INDEX_UNIQUE = 'unique';
	const INDEX_FULLTEXT = 'fulltext';
	const INDEX_FOREIGN = 'foreign';

/**
 * Constructor.
 *
 * @param string $table The table name.
 * @param array $columns The list of columns for the schema.
 */
	public function __construct($table, $columns = array()) {
		$this->_table = $table;
		foreach ($columns as $field => $definition) {
			$this->addColumn($field, $definition);
		}
	}

/**
 * Add a column to the table.
 *
 * ### Attributes
 *
 * Columns can have several attributes:
 *
 * - `type` The type of the column. This should be
 *   one of CakePHP's abstract types.
 * - `length` The length of the column.
 * - `default` The default value of the column.
 * - `null` Whether or not the column can hold nulls.
 * - `fixed` Whether or not the column is a fixed length column.
 *
 * In addition to the above keys, the following keys are
 * implemented in some database dialects, but not all:
 *
 * - `comment` The comment for the column.
 * - `charset` The charset for the column.
 * - `collate` The collation for the column.
 *
 * @param string $name The name of the column
 * @param array $attrs The attributes for the column.
 * @return Table $this
 */
	public function addColumn($name, $attrs) {
		if (is_string($attrs)) {
			$attrs = ['type' => $attrs];
		}
		$attrs = array_intersect_key($attrs, $this->_columnKeys);
		$this->_columns[$name] = $attrs + $this->_columnKeys;
		return $this;
	}

/**
 * Get the column names in the table.
 *
 * @return array
 */
	public function columns() {
		return array_keys($this->_columns);
	}

/**
 * Get column data in the table.
 *
 * @param string $name The column name.
 * @return array|null Column data or null.
 */
	public function column($name) {
		if (!isset($this->_columns[$name])) {
			return null;
		}
		return $this->_columns[$name];
	}

/**
 * Add an index or key.
 *
 * Used to add primary keys, indexes, and foreign keys 
 * to a table.
 *
 * ### Attributes
 *
 * - `type` The type of index being added.
 * - `columns` The columns in the index.
 *
 * @TODO implement foreign keys.
 * @param string $name The name of the index.
 * @param array $attrs The attributes for the index.
 * @return Table $this
 * @throws Cake\Error\Exception
 */
	public function addIndex($name, $attrs) {
		if (is_string($attrs)) {
			$attrs = ['type' => $attrs];
		}
		$attrs = array_intersect_key($attrs, $this->_indexKeys);
		$attrs = $attrs + $this->_indexKeys;

		if (!in_array($attrs['type'], $this->_validIndexTypes, true)) {
			throw new Error\Exception(__d('cake_dev', 'Invalid index type'));
		}
		foreach ($attrs['columns'] as $field) {
			if (empty($this->_columns[$field])) {
				throw new Error\Exception(__d('cake_dev', 'Columns used in indexes must already exist.'));
			}
		}
		$this->_indexes[$name] = $attrs;
		return $this;
	}

/**
 * Get the names of all the indexes in the table.
 *
 * @return array
 */
	public function indexes() {
		return array_keys($this->_indexes);
	}

/**
 * Read information about an index based on name.
 *
 * @param string $name The name of the index.
 * @return array|null Array of index data, or null
 */
	public function index($name) {
		if (!isset($this->_indexes[$name])) {
			return null;
		}
		return $this->_indexes[$name];
	}

/**
 * Get the column(s) used for the primary key.
 *
 * @return array|null Column name(s) for the primary key.
 *   Null will be returned if a table has no primary key.
 */
	public function primaryKey() {
		foreach ($this->_indexes as $name => $data) {
			if ($data['type'] === self::INDEX_PRIMARY) {
				return $data['columns'];
			}
		}
		return null;
	}

/**
 * Generate the SQL to create the Table.
 *
 * Uses the connection to access the schema dialect
 * to generate platform specific SQL.
 *
 * @param Connection $connection The connection to generate SQL for
 * @return string SQL statement to create the table.
 */
	public function createTableSql(Connection $connection) {
		$dialect = $connection->driver()->schemaDialect();
		$lines = [];
		foreach (array_keys($this->_columns) as $name) {
			$lines[] = $dialect->columnSql($this, $name);
		}
		foreach (array_keys($this->_indexes) as $name) {
			$lines[] = $dialect->indexSql($this, $name);
		}
		return $dialect->createTableSql($this->_table, $lines);
	}

}