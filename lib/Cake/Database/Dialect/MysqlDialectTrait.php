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
 * @package       Cake.Model
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Database\Dialect;

use Cake\Database\SqlDialectTrait;
use Cake\Error;

/**
 * Contains functions that encapsulates the SQL dialect used by MySQL,
 * including query translators and schema introspection.
 */
trait MysqlDialectTrait {

	use SqlDialectTrait;

/**
 *  String used to start a database identifier quoting to make it safe
 *
 * @var string
 */
	public $startQuote = '`';

/**
 * String used to end a database identifier quoting to make it safe
 *
 * @var string
 */
	public $endQuote = '`';

/**
 * Get the schema dialect.
 *
 * Used by Cake\Schema package to reflect schema and
 * generate schema.
 *
 * @return Cake\Database\Schema\MysqlSchema
 */
	public function schemaDialect() {
		return new \Cake\Database\Schema\MysqlSchema($this);
	}

}