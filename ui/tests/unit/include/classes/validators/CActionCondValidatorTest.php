<?php declare(strict_types = 0);
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


class CActionCondValidatorTest extends CValidatorTest {

	public function dataProviderValidParam() {
		return [];
	}

	public function dataProviderValidValues() {
		return [
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_HOST,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DCHECK,
				'value' => '1'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
				'value' => ['1']
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DHOST
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DSERVICE
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_TIME_PERIOD,
				'value' => '5-7,00:00-09:00;1,10:00-20:00'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.0/16'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.0/30'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0-255.0-255'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/0'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/111'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/112'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/128'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80::c0a8:0/112'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80::c0a8:0/128'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.1-127,192.168.2.1'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
				'value' => '0-255.0-255.0-255.0-255'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_TYPE,
				'value' => SVC_SSH
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_PORT,
				'value' => '100-200'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DSTATUS,
				'value' => DOBJECT_STATUS_UP
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
				'value' => null
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
				'value' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
				'value' => 'abc'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_DVALUE,
				'value' => 'abc'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
				'value' => 'abc'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
				'value' => 'abc'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG,
				'value' => 'Tag01'
			]],
			[[], [
				'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
				'value' => 'Value 01',
				'value2' => 'Tag01'
			]]
		];
	}

	public function dataProviderInvalidValues() {
		return [
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_HOST,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DCHECK,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DOBJECT,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DOBJECT,
					'value' => 100
				],
				'Incorrect action condition discovery object.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TIME_PERIOD,
					'value' => ''
				],
				'Invalid time period.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TIME_PERIOD,
					'value' => 'QQQQQQ'
				],
				'Invalid time period.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.0.0/31'
				],
				'Invalid action condition: incorrect address starting from "/31".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.0.0/16-30'
				],
				'Invalid action condition: incorrect address starting from "-30".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => 'fe80:0:0:0:0:0:c0a8:0/129'
				],
				'Invalid action condition: incorrect address starting from "/129".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => 'fe80::c0a8:0/129'
				],
				'Invalid action condition: incorrect address starting from "/129".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.443.0/432'
				],
				'Invalid action condition: incorrect address starting from "192.168.443.0/432".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
					'value' => '{$A}'
				],
				'Invalid action condition: incorrect address starting from "{$A}".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_TYPE,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_TYPE,
					'value' => 100
				],
				'Incorrect action condition discovery check.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_PORT,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSERVICE_PORT,
					'value' => '3mdn-jiwiw'
				],
				'Incorrect action condition port "3mdn-jiwiw".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSTATUS,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DSTATUS,
					'value' => 100
				],
				'Incorrect action condition discovery status.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
					'value' => 123
				],
				'Incorrect value for field "value": should be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => 100
				],
				'Incorrect action condition trigger severity.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
					'value' => 100
				],
				'Incorrect action condition event type.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => 11 /* ZBX_CONDITION_TYPE_DUPTIME */,
					'value' => -1
				],
				'Incorrect value for field "value": value must be between "0" and "2592000".'
			],
			[[],
				[
					'conditiontype' => 11 /* ZBX_CONDITION_TYPE_DUPTIME */,
					'value' => 2592001 /* SEC_PER_MONTH + 1 */
				],
				'Incorrect value for field "value": value must be between "0" and "2592000".'
			],
			[[],
				[
					'conditiontype' => 11 /* ZBX_CONDITION_TYPE_DUPTIME */,
					'value' => 'abc'
				],
				'Incorrect value for field "value": value must be between "0" and "2592000".'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_DVALUE,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			// invalid condition type
			[[],
				[
					'conditiontype' => 9999,
					'value' => ''
				],
				'Incorrect action condition type.'
			]
		];

	}

	/**
	 * Test that a correct error message is generated when setting an object name.
	 *
	 * @dataProvider dataProviderInvalidValuesWithObjects()
	 *
	 * @param array 	$params
	 * @param mixed 	$value
	 * @param string 	$expectedError
	 */
	public function testValidateInvalidWithObject(array $params, $value, $expectedError) {
		// We have no tests because messages in this validator are hardcoded for now.
		$this->markTestIncomplete();
	}

	public function dataProviderInvalidValuesWithObjects() {
		return [
			[
				[],
				[
					'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			]
		];
	}

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	protected function createValidator(array $params = []) {
		return new CActionCondValidator($params);
	}
}
