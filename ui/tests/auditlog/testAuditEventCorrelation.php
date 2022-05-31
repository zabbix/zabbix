<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup correlation, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditEventCorrelation extends testPageReportsAuditValues {

	/**
	 * Id of event correlation.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "correlation.correlationid: 1".
			"\ncorrelation.filter: Added".
			"\ncorrelation.filter.conditions[1]: Added".
			"\ncorrelation.filter.conditions[1].corr_conditionid: 1".
			"\ncorrelation.filter.conditions[1].tag: ok".
			"\ncorrelation.filter.conditions[1].type: 1".
			"\ncorrelation.name: New event correlation for audit".
			"\ncorrelation.operations[1]: Added".
			"\ncorrelation.operations[1].corr_operationid: 1".
			"\ncorrelation.operations[1].type: 1";

	public $updated = "correlation.filter: Updated".
			"\ncorrelation.filter.conditions[1]: Deleted".
			"\ncorrelation.filter.conditions[2]: Added".
			"\ncorrelation.filter.conditions[2].corr_conditionid: 2".
			"\ncorrelation.filter.conditions[2].tag: not ok".
			"\ncorrelation.filter.evaltype: 0 => 2".
			"\ncorrelation.name: New event correlation for audit => Updated event correlation name".
			"\ncorrelation.operations[1]: Deleted".
			"\ncorrelation.operations[2]: Added".
			"\ncorrelation.operations[2].corr_operationid: 2";

	public $deleted = 'Description: Updated event correlation name';

	public $resource_name = 'Event correlation';

	public function prepareCreateData() {
		$ids = CDataHelper::call('correlation.create', [
			[
				'name' => 'New event correlation for audit',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => 1,
							'tag' => 'ok'
						]
					]
				],
				'operations' => [
					[
						'type' => 1
					]
				]
			]
		]);
		$this->assertArrayHasKey('correlationids', $ids);
		self::$ids = $ids['correlationids'][0];
	}

	/**
	 * Check audit of created event correlation.
	 */
	public function testAuditEventCorrelation_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated event correlation.
	 */
	public function testAuditEventCorrelation_Update() {
		CDataHelper::call('correlation.update', [
			[
				'correlationid' => self::$ids,
				'name' => 'Updated event correlation name',
				'filter' => [
					'evaltype' => 2,
					'conditions' => [
						[
							'type' => 0,
							'tag' => 'not ok'
						]
					]
				],
				'operations' => [
					[
						'type' => 0
					]
				]
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted event correlation.
	 */
	public function testAuditEventCorrelation_Delete() {
		CDataHelper::call('correlation.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
