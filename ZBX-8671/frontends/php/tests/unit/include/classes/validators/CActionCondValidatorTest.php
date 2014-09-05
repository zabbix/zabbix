<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		return array(
			array(array())
		);
	}

	public function validValuesProvider()
	{
		return array(
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_HOST_GROUP,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TEMPLATE,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TRIGGER,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_HOST,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DRULE,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DCHECK,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_PROXY,
				'value' => 1,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DHOST,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DOBJECT,
				'value' => EVENT_OBJECT_DSERVICE,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
				'value' => '5-7,00:00-09:00;1,10:00-20:00;',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DHOST_IP,
				'value' => '192.168.0.0/24'
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
				'value' => SVC_SSH,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
				'value' => '100-200',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DSTATUS,
				'value' => DOBJECT_STATUS_UP,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_MAINTENANCE,
				'value' => null,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
				'value' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
				'value' => TRIGGER_VALUE_TRUE,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
				'value' => EVENT_TYPE_ITEM_NORMAL,
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
				'value' => 'abc',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DUPTIME,
				'value' => 'abc',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_DVALUE,
				'value' => 'abc',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_APPLICATION,
				'value' => 'abc',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_HOST_NAME,
				'value' => 'abc',
			)),
			array(array(), array(
				'conditiontype' => CONDITION_TYPE_HOST_METADATA,
				'value' => 'abc',
			))
		);
	}

	public function invalidValuesProvider()
	{
		return array(
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TEMPLATE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_HOST,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DRULE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DCHECK,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_PROXY,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DOBJECT,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DOBJECT,
					'value' => 100,
				),
				'Incorrect action condition discovery object.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
					'value' => '',
				),
				'Empty time period.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
					'value' => 'QQQQQQ',
				),
				'Incorrect time period "QQQQQQ".'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => ''
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DHOST_IP,
					'value' => '192.168.443.0/432'
				),
				'Incorrect action condition ip "192.168.443.0/432".'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSERVICE_TYPE,
					'value' => 100,
				),
				'Incorrect action condition discovery check.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSERVICE_PORT,
					'value' => '3mdn-jiwiw',
				),
				'Incorrect action condition port "3mdn-jiwiw".'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSTATUS,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DSTATUS,
					'value' => 100,
				),
				'Incorrect action condition discovery status.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_MAINTENANCE,
					'value' => 123,
				),
				'Maintenance action condition value must be empty.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
					'value' => 100,
				),
				'Incorrect action condition trigger severity.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
					'value' => 100,
				),
				'Incorrect action condition trigger value.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
					'value' => 100,
				),
				'Incorrect action condition event type.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DUPTIME,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_DVALUE,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_APPLICATION,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_HOST_NAME,
					'value' => '',
				),
				'Empty action condition.'
			),
			array(array(),
				array(
					'conditiontype' => CONDITION_TYPE_HOST_METADATA,
					'value' => '',
				),
				'Empty action condition.'
			),
			// invalid condition type
			array(array(),
				array(
					'conditiontype' => 9999,
					'value' => '',
				),
				'Incorrect action condition type.'
			)
		);

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
		return array(
			array(
				array(),
				array(
					'conditiontype' => CONDITION_TYPE_HOST_GROUP,
					'value' => ''
				),
				'Empty action condition.'
			)
		);
	}

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	protected function createValidator(array $params = array()) {
		return new CActionCondValidator($params);
	}
}
