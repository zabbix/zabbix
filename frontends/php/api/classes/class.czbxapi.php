<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
class CZBXAPI {
	public static $userData;

	/**
	 * The name of the table.
	 *
	 * @var string
	 */
	protected $tableName;

	/**
	 * The alias of the table.
	 *
	 * @var string
	 */
	protected $tableAlias;


	/**
	 * The name of the field used as a private key. If the PK consists of multiple
	 * fields, the names will be stored as an array.
	 *
	 * @var mixed
	 */
	protected $pk;


	public function __construct() {
		$schema = $this->getTableSchema();

		// set the PK of the table
		$pk = explode(',', $schema['key']);
		$this->pk = (count($pk) > 1) ? $pk : $pk[0];
	}


	/**
	 * Returns the name of the database table that contains the objects.
	 *
	 * @return string
	 */
	protected function tableName() {
		return $this->tableName;
	}


	/**
	 * Returns the alias of the database table that contains the objects.
	 *
	 * @return string
	 */
	protected function tableAlias() {
		return $this->tableAlias;
	}


	/**
	 * Returns the table name with the table alias.
	 *
	 * @return string
	 */
	protected function tableId() {
		return $this->tableName().' '.$this->tableAlias();
	}


	/**
	 * Prepends the table alias to the given field name.
	 *
	 * @return string
	 */
	protected function fieldId($fieldName) {
		return $this->tableAlias().'.'.$fieldName;
	}


	/**
	 * Returns the columns that contain the private key.
	 *
	 * @see CZBXAPI::$pk
	 *
	 * @return mixed
	 */
	protected function pk() {
		return $this->pk;
	}


	/**
	 * Returns an array that described the schema of the database table.
	 *
	 * @return array
	 */
	protected function getTableSchema() {
		return DB::getSchema($this->tableName());
	}


	/**
	 * Returns true if the table has the given field.
	 *
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	protected function hasField($fieldName) {
		$schema = $this->getTableSchema();

		return isset($schema['fields'][$fieldName]);
	}


	/**
	 * Unsets the fields that haven't been explicitly asked for by the user, but
	 * have been included in the resulting object for whatever reasons.
	 *
	 * @param array $object    The object from the database
	 * @param array $sqlWhere
	 * @param array $options
	 *
	 * @return array           The resulting object
	 */
	protected function unsetExtraFields(array $object, array $options, array $sqlParts) {

		// unset the pk forced by the 'preservedkeys' option
		if ($options['preservekeys'] !== null && in_array($this->fieldId($this->pk()), $sqlParts['select'])
			&& is_array($options['output']) && !in_array($this->pk(), $options['output'])) {

			unset($object[$this->pk()]);
		}

		return $object;
	}


	/**
	 * Throws an API exception.
	 *
	 * @param type $code
	 * @param type $error
	 */
	protected static function exception($code = ZBX_API_ERROR_INTERNAL, $error = '') {
		throw new APIException($code, $error);
	}
}
?>
