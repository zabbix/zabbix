<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup regexps
 */
class testAuditlogRegexp extends testAuditlogCommon {

	/**
	 * Created regexp ID.
	 */
	protected static $resourceid;

	/**
	 * Created regexp expression ID (before update).
	 */
	protected static $expressionid;

	public function testAuditlogRegexp_Create() {
		$create = $this->call('regexp.create', [
			[
				'name' => 'Created regex',
				'test_string' => 'test_String',
				'expressions' => [
					[
						'expression' => 'created regex example',
						'expression_type' => '1',
						'exp_delimiter' => '.',
						'case_sensitive' => '1'
					]
				]
			]
		]);

		self::$resourceid = $create['result']['regexpids'][0];
		self::$expressionid = CDBHelper::getRow('SELECT expressionid FROM expressions WHERE regexpid='.
				zbx_dbstr(self::$resourceid)
		);

		$created = json_encode([
			'regexp.name' => ['add', 'Created regex'],
			'regexp.test_string' => ['add', 'test_String'],
			'regexp.expressions['.self::$expressionid['expressionid'].']' => ['add'],
			'regexp.expressions['.self::$expressionid['expressionid'].'].expression' => ['add', 'created regex example'],
			'regexp.expressions['.self::$expressionid['expressionid'].'].expression_type' => ['add', '1'],
			'regexp.expressions['.self::$expressionid['expressionid'].'].exp_delimiter' => ['add', '.'],
			'regexp.expressions['.self::$expressionid['expressionid'].'].case_sensitive' => ['add', '1'],
			'regexp.expressions['.self::$expressionid['expressionid'].'].expressionid'
					=> ['add', self::$expressionid['expressionid']],
			'regexp.regexpid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogRegexp_Create
	 */
	public function testAuditlogRegexp_Update() {
		$this->call('regexp.update', [
			[
				'regexpid' => self::$resourceid,
				'name' => 'Updated regex',
				'test_string' => 'updated_test_string',
				'expressions' => [
					[
						'expression' => 'updated epxression',
						'expression_type' => '3',
						'case_sensitive' => '0'
					]
				]
			]
		]);

		$after_expressionid = CDBHelper::getRow('SELECT expressionid FROM expressions WHERE regexpid='.
				zbx_dbstr(self::$resourceid)
		);

		$updated = json_encode([
			'regexp.expressions['.self::$expressionid['expressionid'].']' => ['delete'],
			'regexp.expressions['.$after_expressionid['expressionid'].']' => ['add'],
			'regexp.name' => ['update', 'Updated regex', 'Created regex'],
			'regexp.test_string' => ['update', 'updated_test_string', 'test_String'],
			'regexp.expressions['.$after_expressionid['expressionid'].'].expression' => ['add', 'updated epxression'],
			'regexp.expressions['.$after_expressionid['expressionid'].'].expression_type' => ['add', '3'],
			'regexp.expressions['.$after_expressionid['expressionid'].'].expressionid'
					=> ['add', $after_expressionid['expressionid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogRegexp_Create
	 */
	public function testAuditlogRegexp_Delete() {
		$this->call('regexp.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated regex', self::$resourceid);
	}
}
