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

use Cake\Database\Schema\Table;
use Cake\Error;

class PostgresSchema {

/**
 * The driver instance being used.
 *
 * @var Cake\Database\Driver\Postgres
 */
	protected $_driver;

	public function __construct($driver) {
		$this->_driver = $driver;
	}

/**
 * Get the SQL to list the tables
 *
 * @param array $config The connection configuration to use for
 *    getting tables from.
 * @return array An array of (sql, params) to execute.
 */
	public function listTablesSql($config) {
		$sql = "SELECT table_name as name FROM INFORMATION_SCHEMA.tables WHERE table_schema = ? ORDER BY name";
		$schema = empty($config['schema']) ? 'public' : $config['schema'];
		return [$sql, [$schema]];
	}

/**
 * Get the SQL to describe a table in Postgres.
 *
 * @param string $table The table name to describe
 * @param array $config The connection configuration to use
 * @return array An array of (sql, params) to execute.
 */
	public function describeTableSql($table, $config) {
		$sql =
		"SELECT DISTINCT table_schema AS schema, column_name AS name, data_type AS type,
			is_nullable AS null, column_default AS default, ordinal_position AS position,
			character_maximum_length AS char_length, character_octet_length AS oct_length,
			d.description as comment, i.indisprimary = 't' as pk
		FROM information_schema.columns c
		INNER JOIN pg_catalog.pg_namespace ns ON (ns.nspname = table_schema)
		INNER JOIN pg_catalog.pg_class cl ON (cl.relnamespace = ns.oid AND cl.relname = table_name)
		LEFT JOIN pg_catalog.pg_index i ON (i.indrelid = cl.oid AND i.indkey[0] = c.ordinal_position)
		LEFT JOIN pg_catalog.pg_description d on (cl.oid = d.objoid AND d.objsubid = c.ordinal_position)
		WHERE table_name = ? AND table_schema = ?  ORDER BY position";
		$schema = empty($config['schema']) ? 'public' : $config['schema'];
		return [$sql, [$table, $schema]];
	}

/**
 * Convert a column definition to the abstract types.
 *
 * The returned type will be a type that
 * Cake\Database\Type can handle.
 *
 * @param string $column The column type + length
 * @throws Cake\Error\Exception when column cannot be parsed.
 * @return array Array of column information.
 */
	public function convertColumn($column) {
		preg_match('/([a-z\s]+)(?:\(([0-9,]+)\))?/i', $column, $matches);
		if (empty($matches)) {
			throw new Error\Exception(__d('cake_dev', 'Unable to parse column type from "%s"', $column));
		}

		$col = strtolower($matches[1]);
		$length = null;
		if (isset($matches[2])) {
			$length = (int)$matches[2];
		}

		if (in_array($col, array('date', 'time', 'boolean'))) {
			return ['type' => $col, 'length' => null];
		}
		if (strpos($col, 'timestamp') !== false) {
			return ['type' => 'datetime', 'length' => null];
		}
		if ($col === 'serial' || $col === 'integer') {
			return ['type' => 'integer', 'length' => 10];
		}
		if ($col === 'bigserial' || $col === 'bigint') {
			return ['type' => 'biginteger', 'length' => 20];
		}
		if ($col === 'smallint') {
			return ['type' => 'integer', 'length' => 5];
		}
		if ($col === 'inet') {
			return ['type' => 'string', 'length' => 39];
		}
		if ($col === 'uuid') {
			return ['type' => 'string', 'fixed' => true, 'length' => 36];
		}
		if ($col === 'char' || $col === 'character') {
			return ['type' => 'string', 'fixed' => true, 'length' => $length];
		}
		if (strpos($col, 'char') !== false) {
			return ['type' => 'string', 'length' => $length];
		}
		if (strpos($col, 'text') !== false) {
			return ['type' => 'text', 'length' => null];
		}
		if ($col === 'bytea') {
			return ['type' => 'binary', 'length' => null];
		}
		if ($col === 'real' || strpos($col, 'double') !== false) {
			return ['type' => 'float', 'length' => null];
		}
		if (
			strpos($col, 'numeric') !== false ||
			strpos($col, 'money') !== false ||
			strpos($col, 'decimal') !== false
		) {
			return ['type' => 'decimal', 'length' => null];
		}
		return ['type' => 'text', 'length' => null];
	}

/**
 * Get additional column meta data used in schema reflections.
 *
 * @return array
 */
	public function extraSchemaColumns() {
		return [
			'comment' => [
				'column' => 'comment',
			]
		];
	}

/**
 * Convert field description results into abstract schema fields.
 *
 * @param Cake\Database\Schema\Table $table The table object to append fields to.
 * @param array $row The row data from describeTableSql
 * @param array $fieldParams Additional field parameters to parse.
 * @return void
 */
	public function convertFieldDescription(Table $table, $row, $fieldParams = []) {
		$field = $this->convertColumn($row['type']);

		if ($field['type'] === 'boolean') {
			if ($row['default'] === 'true') {
				$row['default'] = 1;
			}
			if ($row['default'] === 'false') {
				$row['default'] = 0;
			}
		}

		$field += [
			'null' => $row['null'] === 'YES' ? true : false,
			'default' => $row['default'],
		];
		$field['length'] = $row['char_length'] ?: $field['length'];
		foreach ($fieldParams as $key => $metadata) {
			if (!empty($row[$metadata['column']])) {
				$field[$key] = $row[$metadata['column']];
			}
		}
		$table->addColumn($row['name'], $field);
		if (!empty($row['pk'])) {
			$table->addIndex('primary', [
				'type' => Table::INDEX_PRIMARY,
				'columns' => [$row['name']]
			]);
		}
	}

}