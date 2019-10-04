<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'groupids' => ['1']
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
				'operator' => CONDITION_OPERATOR_NOT_EQUAL,
				'groupids' => ['1']
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'oldtag' => 'test',
				'newtag' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test',
				'value' => ''
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_EQUAL,
				'tag' => 'test',
				'value' => ''
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_EQUAL,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_LIKE,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_LIKE,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test',
				'value' => ''
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_EQUAL,
				'tag' => 'test',
				'value' => ''
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_EQUAL,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_LIKE,
				'tag' => 'test',
				'value' => 'test'
			]],
			[[], [
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_NOT_LIKE,
				'tag' => 'test',
				'value' => 'test'
			]]
		];
	}

	public function invalidValuesProvider()
	{
		return [
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
					'operator' => '',
					'tag' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => ''
				],
				'Incorrect value for field "tag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG,
					'operator' => '',
					'tag' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => ''
				],
				'Incorrect value for field "tag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
					'operator' => '',
					'groupids' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
					'operator' => CONDITION_OPERATOR_LIKE,
					'groupids' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
					'operator' => CONDITION_OPERATOR_NOT_LIKE,
					'groupids' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'groupids' => ''
				],
				'Incorrect value for field "groupid": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
					'operator' => CONDITION_OPERATOR_NOT_EQUAL,
					'groupids' => ['0']
				],
				'Incorrect value for field "groupid": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
					'operator' => '',
					'oldtag' => '',
					'newtag' => ''
				],
				'Incorrect value for "operator" field.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'oldtag' => '',
					'newtag' => ''
				],
				'Incorrect value for field "oldtag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'oldtag' => 'test',
					'newtag' => ''
				],
				'Incorrect value for field "newtag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => '',
					'value' => ''
				],
				'Incorrect value for field "tag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => 'test',
					'value' => 1
				],
				'Incorrect value for field "value": a character string is expected.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_LIKE,
					'tag' => 'test',
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_NOT_LIKE,
					'tag' => 'test',
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => '',
					'value' => ''
				],
				'Incorrect value for field "tag": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => 'test',
					'value' => 1
				],
				'Incorrect value for field "value": a character string is expected.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_LIKE,
					'tag' => 'test',
					'value' => ''
				],
				'Incorrect value for field "value": cannot be empty.'
			],
			[[],
				[
					'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_NOT_LIKE,
					'tag' => 'test',
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
		return new CEventCorrCondValidator($params);
	}
}
