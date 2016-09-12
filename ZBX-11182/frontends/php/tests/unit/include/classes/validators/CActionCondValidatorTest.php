<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CActionCondValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return [
			[[]]
		];
	}

	public function validValuesProvider()
	{
		return [
			[[], [
				'conditiontype' => CONDITION_TYPE_HOST_GROUP,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_TEMPLATE,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_TRIGGER,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_HOST,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DRULE,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DCHECK,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_PROXY,
				'value' => 1,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DHOST,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DSERVICE,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
				'value' => '5-7,00:00-09:00;1,10:00-20:00;',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.0/16'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.0/30'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0-255.0-255'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/0'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/111'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/112'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80:0:0:0:0:0:c0a8:0/128'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80::c0a8:0/112'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => 'fe80::c0a8:0/128'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.1-127,192.168.2.1'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '0-255.0-255.0-255.0-255'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
				'value' => SVC_SSH,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
				'value' => '100-200',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DSTATUS,
				'value' => DOBJECT_STATUS_UP,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_MAINTENANCE,
				'value' => null,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
				'value' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DUPTIME,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_DVALUE,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_APPLICATION,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_HOST_NAME,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_HOST_METADATA,
				'value' => 'abc',
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_EVENT_TAG,
				'value' => 'Tag01'
			]],
			[[], [
				'conditiontype' => CONDITION_TYPE_EVENT_TAG_VALUE,
				'value' => 'Value 01',
				'value2' => 'Tag01'
			]]
		];
	}

	public function invalidValuesProvider()
	{
		return [
			[[],
				[
					'conditiontype' => CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TEMPLATE,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TRIGGER,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_HOST,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DRULE,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DCHECK,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_PROXY,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DOBJECT,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DOBJECT,
					'value' => 100,
				],
				'Incorrect action condition discovery object.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
					'value' => '',
				],
				'Empty time period.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
					'value' => 'QQQQQQ',
				],
				'Incorrect time period "QQQQQQ".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => ''
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.0.0/31'
				],
				'Invalid IP address range "192.168.0.0/31".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.0.0/16-30'
				],
				'Invalid IP address range "192.168.0.0/16-30".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => 'fe80:0:0:0:0:0:c0a8:0/129'
				],
				'Invalid IP address range "fe80:0:0:0:0:0:c0a8:0/129".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => 'fe80::c0a8:0/129'
				],
				'Invalid IP address range "fe80::c0a8:0/129".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.443.0/432'
				],
				'Invalid IP address range "192.168.443.0/432".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => '{$A}'
				],
				'Invalid IP address range "{$A}".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
					'value' => 100,
				],
				'Incorrect action condition discovery check.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
					'value' => '3mdn-jiwiw',
				],
				'Incorrect action condition port "3mdn-jiwiw".'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSTATUS,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DSTATUS,
					'value' => 100,
				],
				'Incorrect action condition discovery status.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_MAINTENANCE,
					'value' => 123,
				],
				'Maintenance action condition value must be empty.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => 100,
				],
				'Incorrect action condition trigger severity.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
					'value' => 100,
				],
				'Incorrect action condition event type.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DUPTIME,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_DVALUE,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_APPLICATION,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_HOST_NAME,
					'value' => '',
				],
				'Empty action condition.'
			],
			[[],
				[
					'conditiontype' => CONDITION_TYPE_HOST_METADATA,
					'value' => '',
				],
				'Empty action condition.'
			],
			// invalid condition type
			[[],
				[
					'conditiontype' => 9999,
					'value' => '',
				],
				'Incorrect action condition type.'
			]
		];

	}

	/**
	 * Test that a correct error message is generated when setting an object name.
	 *
	 * @dataProvider invalidValuesWithObjectsProvider()
	 *
	 * @param array 	$params
	 * @param mixed 	$value
	 * @param string 	$expectedError
	 */
	public function testValidateInvalidWithObject(array $params, $value, $expectedError) {
		// We have no tests because messages in this validator are hardcoded for now.
		$this->markTestIncomplete();
	}

	public function invalidValuesWithObjectsProvider() {
		return [
			[
				[],
				[
					'conditiontype' => CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				],
				'Empty action condition.'
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
