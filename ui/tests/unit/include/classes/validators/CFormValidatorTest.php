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


use PHPUnit\Framework\TestCase;

class CFormValidatorTest extends TestCase {
	protected $defaultTimezone;

	protected function setUp(): void {
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('Europe/Riga');
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->defaultTimezone);
	}

	public function dataProviderNormalizeRules() {
		return [
			[
				[],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: )'
			],
			[
				['object', 'fields' => []],
				null,
				'[RULES ERROR] Rule "fields" should contain non-empty array (Path: )'
			],
			[
				['object', 'fields' => [
					'host' => []
				]],
				['type' => 'object', 'fields' => [
					'host' => []
				]]
			],
			[
				['host' => 'required'],
				null,
				'[RULES ERROR] Unknown rule "host" (Path: )'
			],
			[
				['object', 'fields' => [
					'host' => 'required'
				]],
				null,
				'[RULES ERROR] Field "host" should have an array of rule rows (Path: )'
			],
			[
				['object', 'fields' => [
					'host' => ['required']
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['string', 'required']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'string', 'required' => true]]
				]]
			],
			[
				['object', 'fields' => [
					'host' => [['required']]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /host)'
			],
			[
				['object',
					'api_uniq' => ['hostget', ['host' => '{host}']]
				],
				null,
				'[RULES ERROR] Rule "api_uniq" should contain a valid API call (Path: , API call:hostget)'
			],
			[
				['object', 'fields' => [
					'host' => ['not_empty', 'string']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['not_empty' => true, 'type' => 'string']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['required', 'not_empty', 'string']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['required' => true, 'not_empty' => true, 'type' => 'string']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.host', 'required', 'not_empty']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'string', 'length' => 128, 'required' => true, 'not_empty' => true]]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.status', 'required']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'integer', 'required' => true]]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.hostid']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'id']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.description']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'string', 'length' => 65535]]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.hostid', 'id']
				]],
				null,
				'[RULES ERROR] Rule "type" is specified multiple times (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['db hosts.description', 'length' => 65535]
				]],
				null,
				'[RULES ERROR] Rule "length" is specified multiple times (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['integer']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['id']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'id']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['string']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['type' => 'string']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in 1,2', 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => ['1', '2'], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in 1:4', 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [['1', '4']], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in 1:', 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [['1', null]], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in :4', 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [[null, '4']], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in 0,1:4', 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => ['0', ['1', '4']], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in' => [1, 2], 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [1, 2], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in' => [[1, 4]], 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [[1, 4]], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['in' => [[1, 2, 4]], 'integer']
				]],
				null,
				'[RULES ERROR] Invalid value for rule "in" or "not_in" (Path: /status)'
			],
			[
				['object', 'fields' => [
					'status' => ['in' => [0, [1, 4]], 'integer']
				]],
				['type' => 'object', 'fields' => [
					'status' => [['in' => [0, [1, 4]], 'type' => 'integer']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['not_a_rule']
				]],
				null,
				'[RULES ERROR] Unknown rule "not_a_rule" (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['abc' => true]
				]],
				null,
				'[RULES ERROR] Unknown rule "abc" (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['required', 'required']
				]],
				null,
				'[RULES ERROR] Option "required" is specified multiple times (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['not_empty', 'integer']
				]],
				null,
				'[RULES ERROR] Rule "not_empty" is not compatible with type "integer" (Path: /host)'
			],
			[
				['object', 'fields' => [
					'host' => ['in 1,2,3', 'string']
				]],
				['type' => 'object', 'fields' => [
					'host' => [['in' => ['1','2','3'], 'type' => 'string']]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['in' => '1,2,3', 'string']
				]],
				null,
				'[RULES ERROR] Rule "in" should contain non-empty array (Path: /host)'
			],
			[
				['object', 'fields' => [
					'interfaces' => ['objects']
				]],
				null,
				'[RULES ERROR] For object/objects in non-conditional rule row "fields" rule must be present (Path: /interfaces)'
			],
			[
				['object', 'fields' => [
					'ip' => ['string'],
					'interfaces' => ['objects', 'when' => ['ip', 'empty']]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string']],
					'interfaces' => [['type' => 'objects', 'when' => [
						['ip', 'empty' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['string'],
					'interfaces' => ['objects', 'when' => ['ip', 'exist']]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string']],
					'interfaces' => [['type' => 'objects', 'when' => [
						['ip', 'exist' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['string'],
					'interfaces' => ['objects', 'when' => ['ip', 'not_exist']]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string']],
					'interfaces' => [['type' => 'objects', 'when' => [
						['ip', 'not_exist' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'interfaces' => ['fields' => ['ip' => []]]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /interfaces)'
			],
			[
				['object', 'fields' => [
					'interfaces' => ['objects', 'fields' => []]
				]],
				null,
				'[RULES ERROR] Rule "fields" should contain non-empty array (Path: /interfaces)'
			],
			[
				['object', 'fields' => [
					'interfaces' => ['objects', 'fields' => [
						'useip' => ['integer'],
						'ip' => [
							['db interface.ip'],
							['db interface.ip', 'required', 'not_empty', 'when' => ['useip', 'in' => [1]]]
						],
						'dns' => [
							['db interface.dns'],
							['db interface.dns', 'required', 'not_empty', 'when' => ['useip', 'in' => [1]]]
						]
					]]
				]],
				['type' => 'object', 'fields' => [
					'interfaces' => [['type' => 'objects', 'fields' => [
						'useip' => [['type' => 'integer']],
						'ip' => [
							['type' => 'string', 'length' => 64],
							['type' => 'string', 'length' => 64, 'required' => true, 'not_empty' => true, 'when' => [
								['useip', 'in' => [1]]
							]]
						],
						'dns' => [
							['type' => 'string', 'length' => 255],
							['type' => 'string', 'length' => 255, 'required' => true, 'not_empty' => true, 'when' => [
								['useip', 'in' => [1]]
							]]
						]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'details' => ['object', 'fields' => ['version' => []]]
				]],
				['type' => 'object', 'fields' => [
					'details' => [['type' => 'object', 'fields' => ['version' => []]]]
				]]
			],
			[
				['object', 'fields' => [
					'details' => ['object']
				]],
				null,
				'[RULES ERROR] For object/objects in non-conditional rule row "fields" rule must be present (Path: /details)'
			],
			[
				['object', 'fields' => [
					'ip' => ['string'],
					'details' => ['object', 'when' => ['ip', 'empty']]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string']],
					'details' => [['type' => 'object', 'when' => [
						['ip', 'empty' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'details' => ['fields' => ['version' => []]]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /details)'
			],
			[
				['object', 'fields' => [
					'details' => ['object', 'fields' => []]
				]],
				null,
				'[RULES ERROR] Rule "fields" should contain non-empty array (Path: /details)'
			],
			[
				['object', 'fields' => [
					'details' => ['object', 'fields' => ['version' => []]]
				]],
				['type' => 'object', 'fields' => [
					'details' => [['type' => 'object', 'fields' => ['version' => []]]]
				]]
			],
			[
				['object', 'fields' => [
					'details' => ['object', 'fields' => [
						'version' => ['db interface_snmp.version', 'required', 'in' => [SNMP_V1, SNMP_V2C, SNMP_V3]]
					]]
				]],
				['type' => 'object', 'fields' => [
					'details' => [['type' => 'object', 'fields' => [
						'version' => [['type' => 'integer', 'required' => true, 'in' => [SNMP_V1, SNMP_V2C, SNMP_V3]]]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'groups' => ['array']
				]],
				['type' => 'object', 'fields' => [
					'groups' => [['type' => 'array']]
				]]
			],
			[
				['object', 'fields' => [
					'groups' => ['field' => ['id']]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /groups)'
			],
			[
				['object', 'fields' => [
					'groups' => ['array', 'field' => []]
				]],
				null,
				'[RULES ERROR] Rule "field" should contain non-empty array (Path: /groups)'
			],
			[
				['object', 'fields' => [
					'groups' => ['array', 'field' => ['id']]
				]],
				['type' => 'object', 'fields' => [
					'groups' => [['type' => 'array', 'field' => ['type' => 'id']]]
				]]
			],
			[
				['object', 'fields' => [
					'groups' => ['array', 'field' => [['id'], ['string']]]
				]],
				null,
				'[RULES ERROR] For numeric keys, rule value should be a string: (Path: /groups, Key: 0)'
			],
			[
				['object', 'fields' => [
					'groups' => ['array', 'not_empty', 'field' => ['db hstgrp.groupid']]
				]],
				['type' => 'object', 'fields' => [
					'groups' => [['type' => 'array', 'not_empty' => true, 'field' => ['type' => 'id']]]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['string'],
					'ip' => ['string', 'when' => ['key', 'not_empty']]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'string']],
					'ip' => [['type' => 'string', 'when' => [
						['key', 'not_empty' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['when' => ['ip', 'not_empty']]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'ip' => ['when' => ['key', 'string', 'not_empty']]
				]],
				null,
				'[RULES ERROR] When condition should be an array with at least two elements (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'key' => ['integer'],
					'ip' => ['string', 'when' => ['key', 'in 1,3:6,8']]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'integer']],
					'ip' => [['type' => 'string', 'when' => [
						['key', 'in' => ['1', ['3', '6'], '8']]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['integer'],
					'ip' => ['string', 'when' => ['key', 'in' => [1, [3, 6], 8]]]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'integer']],
					'ip' => [['type' => 'string', 'when' => [
						['key', 'in' => [1, [3, 6], 8]]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['string'],
					'ip' => ['string', 'when' => ['key', 'string']]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'string']],
					'ip' => [['type' => 'string', 'when' => [
						['key', 'type' => 'string']
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['integer'],
					'ip' => ['string', 'when' => ['key', 'empty']]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'integer']],
					'ip' => [['type' => 'string', 'when' => [
						['key', 'empty' => true]
					]]]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['integer'],
					'ip' => [
						['integer'],
						['integer', 'required', 'when' => ['key', 'empty']]
					]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'integer']],
					'ip' => [
						['type' => 'integer'],
						['type' => 'integer', 'required' => true, 'when' => [
							['key', 'empty' => true]
						]]
					]
				]]
			],
			[
				['object', 'fields' => [
					'key' => ['integer'],
					'ip' => [
						['integer', 'when' => ['key', 'not_empty']],
						['string', 'required', 'when' => ['key', 'empty']]
					]
				]],
				['type' => 'object', 'fields' => [
					'key' => [['type' => 'integer']],
					'ip' => [
						['type' => 'integer', 'when' => [
							['key', 'not_empty' => true]
						]],
						['type' => 'string', 'required' => true, 'when' => [
							['key', 'empty' => true]
						]]
					]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => [['integer'], ['required']]
				]],
				null,
				'[RULES ERROR] Rule "type" is mandatory (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'ip' => ['integer', 'min' => 5, 'max' => 6]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'integer', 'min' => 5, 'max' => 6]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['string', 'length' => 6]
				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string', 'length' => 6]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['string', 'length' => 6.1]
				]],
				null,
				'[RULES ERROR] Rule "length" should contain an integer (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'ip' => ['string', 'min' =>'6']
				]],
				null,
				'[RULES ERROR] Rule "min" should contain a number (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'ip' => ['string', 'min' => 6]
				]],
				null,
				'[RULES ERROR] Rule "min" or "max" is not compatible with type "string" (Path: /ip)'
			],
			[
				['object', 'fields' => [
					'ip' => ['object', 'length' => 6]
				]],
				null,
				'[RULES ERROR] For object/objects in non-conditional rule row "fields" rule must be present (Path: /ip)'
			],
			[
				['required' => 'host'],
				null,
				'[RULES ERROR] Unknown rule "required" (Path: )'
			],
			[
				['object', 'fields' => [
					'ip' => ['string', 'invalid' => 'ipaddress']
				]],
				null,
				'[RULES ERROR] Unknown rule "invalid" (Path: /ip)'
			],
			[
				['object',
					'api_uniq' => ['host.get', [], [], []],
					'fields' => [
						'ip' => ['string', 'messages' => [
							'type' => 'String error.'
						]]
					]
				],
				['type' => 'object',
					'api_uniq' => [
						['host.get', ['filter' => []], [], []]
					],
					'fields' => [
						'ip' => [['type' => 'string', 'messages' => [
							'type' => 'String error.'
						]]]
					]
				]
			],
			[
				['object',
					'api_uniq' => [['host.get', ['host' => '{host}']]],
					'fields' => [
						'ip' => ['string', 'messages' => [
							'type' => 'String error.'
						]]
					]
				],
				['type' => 'object',
					'api_uniq' => [
						['host.get', ['filter' => ['host' => '{host}']], null, null]
					],
					'fields' => [
						'ip' => [['type' => 'string', 'messages' => [
							'type' => 'String error.'
						]]]
					]
				]
			],
			[
				['object',
					'api_uniq' => [['host.get'], ['host.get']],
					'fields' => [
						'ip' => ['string', 'messages' => [
							'type' => 'String error.'
						]]
					]
				],
				['type' => 'object',
					'api_uniq' => [
						['host.get', ['filter' => []], null, null],
						['host.get', ['filter' => []], null, null]
					],
					'fields' => [
						'ip' => [['type' => 'string', 'messages' => [
							'type' => 'String error.'
						]]]
					]
				]
			],
			[
				['object',
					'api_uniq' => [
						['host.get', [], null, null, ['param_1' => 'value_1']],
						['host.get', ['filter_param_1' => 'filter_value_1'], null, null, ['param_1' => 'value_1']],
						['host.get']
					],
					'fields' => [
						'ip' => ['string', 'messages' => [
							'type' => 'String error.'
						]]
					]
				],
				['type' => 'object',
					'api_uniq' => [
						['host.get', ['filter' => [], 'param_1' => 'value_1'], null, null],
						['host.get', ['filter' => ['filter_param_1' => 'filter_value_1'], 'param_1' => 'value_1'],
							null, null
						],
						['host.get', ['filter' => []], null, null]
					],
					'fields' => [
						'ip' => [['type' => 'string', 'messages' => [
							'type' => 'String error.'
						]]]
					]
				]
			],
			[
				['object', 'uniq' => ['field1', 'field2', 'field3'], 'fields' => [
					'field1' => ['string']
				]],
				['type' => 'object', 'uniq' => [['field1', 'field2', 'field3']], 'fields' => [
					'field1' => [['type' => 'string']]
				]]
			],
			[
				['object', 'uniq' => ['field1', ['field2', 'field3']], 'fields' => [
					'field1' => ['string']
				]],
				['type' => 'object', 'uniq' => [['field1'], ['field2', 'field3']], 'fields' => [
					'field1' => [['type' => 'string']]
				]]
			],
			[
				['object', 'fields' => [
					'field1' => ['boolean']
				]],
				['type' => 'object', 'fields' => [
					'field1' => [['type' => 'integer', 'in' => [0, 1]]]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['boolean']
				]],
				['type' => 'object', 'fields' => [
					'value' => [['type' => 'integer', 'in' => [0, 1]]]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['boolean', 'required']
				]],
				['type' => 'object', 'fields' => [
					'value' => [[
						'type' => 'integer',
						'in' => [1],
						'required' => true,
						'messages' => ['in' => 'Must be selected.']
					]]
				]]
			],
			[
				['object', 'fields' => [
					'ip' => ['string'],
					'dns' => ['string', 'when' => ['ip', 'not_empty']],
					'objects' => ['objects', 'fields' => [
						'field1' => ['string', 'when' => ['../ip', 'not_empty']],
						'field2' => ['string', 'when' => ['field1', 'not_empty']],
						'field3' => ['objects', 'fields' => [
							'deep1' => ['string', 'when' => ['../../ip', 'not_empty']],
							'deep2' => ['string', 'when' => ['../field1', 'not_empty']]
						]]
					]],
					'array' => ['array', 'field' =>
						['string', 'when' => ['ip', 'not_empty']]
					]

				]],
				['type' => 'object', 'fields' => [
					'ip' => [['type' => 'string']],
					'dns' => [['type' => 'string', 'when' => [['ip', 'not_empty' => true]]]],
					'objects' => [['type' => 'objects', 'fields' => [
						'field1' => [['type' => 'string', 'when' => [['../ip', 'not_empty' => true]]]],
						'field2' => [['type' => 'string', 'when' => [['field1', 'not_empty' => true]]]],
						'field3' => [['type' => 'objects', 'fields' => [
							'deep1' => [['type' => 'string', 'when' => [['../../ip', 'not_empty' => true]]]],
							'deep2' => [['type' => 'string', 'when' => [['../field1', 'not_empty' => true]]]]
						]]]
					]]],
					'array' => [['type' => 'array', 'field' =>
						['type' => 'string', 'when' => [['ip', 'not_empty' => true]]]
					]]
				]]
			],
			[
				['object', 'fields' => [
					'dns' => ['string', 'when' => ['ip', 'not_empty']],
					'objects' => ['objects', 'fields' => [
						'field1' => ['string', 'when' => ['../ip', 'not_empty']],
						'field2' => ['string', 'when' => ['field1', 'not_empty']],
						'field3' => ['objects', 'fields' => [
							'deep1' => ['string', 'when' => ['../../ip', 'not_empty']],
							'deep2' => ['string', 'when' => ['../field1', 'not_empty']]
						]]
					]],
					'array' => ['array', 'field' =>
						['string', 'when' => ['ip', 'not_empty']]
					],
					'ip' => ['string']
				]],
				null,
				'[RULES ERROR] Only fields defined prior to this can be used for "when" checks (Path: /dns)'
			]
		];
	}

	/**
	 * @dataProvider dataProviderNormalizeRules
	 *
	 * @param array  $rules
	 * @param mixed  $expected
	 */
	public function testNormalizeRules(array $rules, $expected, $error_message = '') {
		global $DB;

		$DB['TYPE'] = ZBX_DB_MYSQL;

		if ($expected === null) {
			$this->expectException(Exception::class);
			$this->expectExceptionMessage($error_message);
		}

		$validator = new CFormValidator($rules);
		$normalized_rules = $validator->getRules();

		$this->assertSame($expected, $normalized_rules);
	}

	public function dataProviderFormValidator() {
		return [
			[
				['object', 'fields' => [
					'host' => ['string', 'required']
				]],
				['host' => 'Zabbix server', 'name' => 'Zabbix server'],
				['host' => 'Zabbix server'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'host' => ['string', 'required']
				]],
				['name' => 'Zabbix server'],
				['name' => 'Zabbix server'],
				CFormValidator::ERROR,
				['/host' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['not_empty', 'string']
				]],
				['host' => 'Zabbix server'],
				['host' => 'Zabbix server'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'host' => ['not_empty', 'string']
				]],
				[],
				[],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'host' => ['not_empty', 'string']
				]],
				['host' => ''],
				[],
				CFormValidator::ERROR,
				['/host' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => '10074'],
				['hostid' => '10074'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => '00074'],
				['hostid' => '74'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => '00000'],
				['hostid' => '0'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => 'abc'],
				[],
				CFormValidator::ERROR,
				['/hostid' => [
					['message' => 'This value is not a valid identifier.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => []],
				[],
				CFormValidator::ERROR,
				['/hostid' => [
					['message' => 'This value is not a valid identifier.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'hostid' => ['id']
				]],
				['hostid' => 123],
				['hostid' => '123'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '1'],
				['status' => 1],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '0002'],
				['status' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '00000'],
				['status' => 0],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '-5'],
				['status' => -5],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '-0005'],
				['status' => -5],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => 'abc'],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value is not a valid integer.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => []],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value is not a valid integer.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => 123],
				['status' => 123],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => 2147483647],
				['status' => 2147483647],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => -2147483648],
				['status' => -2147483648],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '2147483648'],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value is not a valid integer.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer']
				]],
				['status' => '-2147483649'],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value is not a valid integer.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => 'Zabbix server'],
				['name' => 'Zabbix server'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => '0002'],
				['name' => '0002'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => '00000'],
				['name' => '00000'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => '-5'],
				['name' => '-5'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => '-0005'],
				['name' => '-0005'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => []],
				[],
				CFormValidator::ERROR,
				['/name' => [
					['message' => 'This value is not a valid string.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => 5.25],
				['name' => '5.25'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => 5],
				['name' => '5'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string']
				]],
				['name' => ''],
				['name' => ''],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['float']
				]],
				['name' => 5.0],
				['name' => 5.0],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['float']
				]],
				['name' => '5.0'],
				['name' => 5.0],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['float']
				]],
				['name' => '5'],
				['name' => 5.0],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['float']
				]],
				['name' => ''],
				[],
				CFormValidator::ERROR,
				['/name' => [
					['message' => 'This value is not a valid floating-point value.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [0, 1, 2]]
				]],
				['status' => 1],
				['status' => 1],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [0, 1, 2]]
				]],
				['status' => '01'],
				['status' => 1],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [0, 1, 2]]
				]],
				['status' => 3],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be one of "0", "1", "2".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [2]]
				]],
				['status' => 3],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be "2".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4]]]
				]],
				['status' => 3],
				['status' => 3],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4]]]
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range 1:4.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4], 8]]
				]],
				['status' => 8],
				['status' => 8],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4], 8]]
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be "8" or within range 1:4.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[null, 4]]]
				]],
				['status' => 2],
				['status' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[null, 4]]]
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range :4.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[6, null]]]
				]],
				['status' => 8],
				['status' => 8],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[6, null]]]
				]],
				['status' => 2],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range 6:.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4], [6, 9]]]
				]],
				['status' => 7],
				['status' => 7],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in' => [[1, 4], [6, 9]]]
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within ranges 1:4, 6:9.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['float', 'in' => [0, 1.1, 2]]
				]],
				['status' => 1.1],
				['status' => 1.1],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['float', 'in' => [[1, 3]]]
				]],
				['status' => 2.3],
				['status' => 2.3],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'name' => ['string', 'in' => ['item']]
				]],
				['name' => 'item'],
				['name' => 'item'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in :4']
				]],
				['status' => 2],
				['status' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in 6:']
				]],
				['status' => 8],
				['status' => 8],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in :4']
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range :4.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['integer', 'in 6:']
				]],
				['status' => 5],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range 6:.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'status' => ['float', 'in 1.1:1.5']
				]],
				['status' => 1.4],
				['status' => 1.4],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'status' => ['float', 'in 1.1:1.5']
				]],
				['status' => 1.8],
				[],
				CFormValidator::ERROR,
				['/status' => [
					['message' => 'This value must be within range 1.1:1.5.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'not_in' => [1, 5, 7]]
				]],
				['value' => 5],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value cannot be one of "1", "5", "7".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'not_in' => [1]]
				]],
				['value' => 5],
				['value' => 5],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'not_in' => [1, [4, 6]]]
				]],
				['value' => 5],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value cannot be "1" or within range 4:6.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'not_in' => [1, [4, 6]]]
				]],
				['value' => 3],
				['value' => 3],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['float', 'not_in' => [1, [1.1, 1.5]]]
				]],
				['value' => 1.01],
				['value' => 1.01],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'min' => 5]
				]],
				['value' => 3],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value must be no less than "5".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'min' => 5]
				]],
				['value' => 5],
				['value' => 5],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'min' => 5]
				]],
				['value' => 7],
				['value' => 7],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'max' => 5]
				]],
				['value' => 6],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value must be no greater than "5".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'max' => 5]
				]],
				['value' => 5],
				['value' => 5],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'max' => 5]
				]],
				['value' => 3],
				['value' => 3],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'length' => 20]
				]],
				['value' => 'strlen(text) < 20'],
				['value' => 'strlen(text) < 20'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'length' => 18]
				]],
				['value' => 'strlen(text) == 18'],
				['value' => 'strlen(text) == 18'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'length' => 1]
				]],
				['value' => 'ߐ'],
				['value' => 'ߐ'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'length' => 10]
				]],
				['value' => 'strlen(text) > 10'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value is too long.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => [
						'objects', 'not_empty', 'fields' => [
							'dns' => ['string', 'required']
						]
					]
				]],
				['value' => []],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => [
						'objects', 'not_empty', 'fields' => [
							'dns' => ['string', 'required']
						]
					]
				]],
				['value' => [['ip' => '123']]],
				[],
				CFormValidator::ERROR,
				['/value/0/dns' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => [
						'objects', 'not_empty', 'fields' => [
							'dns' => ['string', 'required']
						]
					]
				]],
				['value' => [['dns' => 'zabbix.com']]],
				['value' => [['dns' => 'zabbix.com']]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => [
						'objects', 'not_empty', 'fields' => [
							'ip' => ['string', 'required'],
							'dns' => ['string', 'required']
						]
					]
				]],
				['value' => [
					['ip' => '123', 'dns' => 'zabbix.com'],
					['ip' => '123']
				]],
				[],
				CFormValidator::ERROR,
				['/value/1/dns' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => [
						'object', 'fields' => [
							'name' => ['string']
						]
					]
				]],
				['value' => ['name' => 'Zabbix server']],
				['value' => ['name' => 'Zabbix server']],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => [
						'object', 'fields' => [
							'name' => ['string', 'required']
						]
					]
				]],
				['value' => []],
				[],
				CFormValidator::ERROR,
				['/value/name' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'interfaces' => [
						'object', 'fields' => [
							'status' => ['integer'],
							'value' => ['string', 'not_empty', 'when' => ['status', 'in' => [1]]]
						]
					]
				]],
				['interfaces' => [
					'status' => 2,
					'value' => ''
				]],
				['interfaces' => [
					'status' => 2,
					'value' => ''
				]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'interfaces' => [
						'object', 'fields' => [
							'status' => ['integer'],
							'value' => ['string', 'not_empty', 'when' => ['status', 'in' => [1]]]
						]
					]
				]],
				['interfaces' => [
					'status' => 1,
					'value' => 'filled'
				]],
				['interfaces' => [
					'status' => 1,
					'value' => 'filled'
				]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'interfaces' => [
						'object', 'fields' => [
							'status' => ['integer'],
							'value' => ['string', 'not_empty', 'when' => ['status', 'in' => [1]]]
						]
					]
				]],
				['interfaces' => [
					'status' => 1,
					'value' => ''
				]],
				[],
				CFormValidator::ERROR,
				['/interfaces/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'templates' => ['array', 'field' => ['id']],
					'value' => ['string', 'not_empty', 'when' => ['templates', 'not_empty']]
				]],
				['templates' => [], 'value' => ''],
				['templates' => [], 'value' => ''],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'templates' => ['array', 'field' => ['id']],
					'value' => ['string', 'not_empty', 'when' => ['templates', 'empty']]
				]],
				['templates' => ['1', '2', '3'], 'value' => ''],
				['templates' => ['1', '2', '3'], 'value' => ''],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'templates' => ['array', 'field' => ['id']],
					'value' => ['string', 'not_empty', 'when' => ['templates', 'empty']]
				]],
				['templates' => [], 'value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'templates' => ['array', 'field' => ['id']],
					'value' => ['string', 'not_empty', 'when' => ['templates', 'empty']]
				]],
				['templates' => [], 'value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['string'],
					'value' => ['string', 'not_empty', 'when' => ['host', 'exist']]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'host' => ['string'],
					'value' => ['string', 'not_empty', 'when' => ['host', 'exist']]
				]],
				['host' => 'name', 'value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['string'],
					'value' => ['string', 'not_empty', 'when' => ['host', 'exist']]
				]],
				['host' => '', 'value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['string'],
					'value' => ['string', 'not_empty', 'when' => ['host', 'not_exist']]
				]],
				['value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This field cannot be empty.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'host' => ['string'],
					'value' => ['string', 'not_empty', 'when' => ['host', 'not_exist']]
				]],
				['host' => '', 'value' => ''],
				['host' => '', 'value' => ''],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'not_empty', 'messages' => ['not_empty' => 'Custom message.']]
				]],
				['value' => ''],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'required', 'messages' => ['required' => 'Custom message.']]
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['id', 'messages' => ['type' => 'Custom message.']]
				]],
				['value' => 'abc'],
				['value' => 'abc'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'messages' => ['type' => 'Custom message.']]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'in' => [1], 'messages' => ['in' => 'Custom message.']]
				]],
				['value' => 3],
				['value' => 3],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'min' => 5, 'messages' => ['min' => 'Custom message.']]
				]],
				['value' => 3],
				['value' => 3],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['integer', 'max' => 2, 'messages' => ['max' => 'Custom message.']]
				]],
				['value' => 3],
				['value' => 3],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['float', 'messages' => ['type' => 'Custom message.']]
				]],
				['value' => 'abc'],
				['value' => 'abc'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['float', 'in' => [1], 'messages' => ['in' => 'Custom message.']]
				]],
				['value' => 2.0],
				['value' => 2.0],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['float', 'min' => 10, 'messages' => ['min' => 'Custom message.']]
				]],
				['value' => 5.0],
				['value' => 5.0],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['float', 'max' => 3, 'messages' => ['max' => 'Custom message.']]
				]],
				['value' => 5.0],
				['value' => 5.0],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'messages' => ['type' => 'Custom message.']]
				]],
				['value' => []],
				['value' => []],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'not_empty', 'messages' => ['not_empty' => 'Custom message.']]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['object', 'messages' => [
						'type' => 'Custom message.'
					], 'fields' => [
						'status' => ['integer']
					]]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['object', 'fields' => [
						'id' => ['id', 'required', 'messages' => ['required' => 'Custom message.']]
					]]
				]],
				['value' => []],
				['value' => []],
				CFormValidator::ERROR,
				['/value/id' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'messages' => [
						'type' => 'Custom message.'
					], 'fields' => [
						'status' => ['integer']
					]]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'not_empty', 'messages' => [
						'not_empty' => 'Custom message.'
					], 'fields' => [
						'status' => ['integer']
					]]
				]],
				['value' => []],
				['value' => []],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['array', 'messages' => ['type' => 'Custom message.']]
				]],
				['value' => ''],
				['value' => ''],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['array', 'not_empty', 'messages' => ['not_empty' => 'Custom message.']]
				]],
				['value' => []],
				['value' => []],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Custom message.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'list' => ['array', 'required', 'field' => ['id']]
				]],
				['list' => ['1', '2', 'invalid', '6']],
				['list' => ['1', '2', 'invalid', '6']],
				CFormValidator::ERROR,
				['/list/2' => [
					['message' => 'This value is not a valid identifier.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'regex' => '/^([0-9a-f]{1,})$/i']
				]],
				['value' => 'abcdef1234567890'],
				['value' => 'abcdef1234567890'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'regex' => '/^([0-9]{1,})$/i']
				]],
				['value' => 'abcdef1234567890'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value does not match pattern.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'in' => ['a']]
				]],
				['value' => 'a'],
				['value' => 'a'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'in' => ['a', 'b', 'c']]
				]],
				['value' => 'e'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value must be one of "a", "b", "c".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'not_in' => ['a']]
				]],
				['value' => 'a'],
				['value' => 'a'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value cannot be "a".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'not_in' => ['a', 'b', 'c']]
				]],
				['value' => 'd'],
				['value' => 'd'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CUserMacroParser::class, []]]
				]],
				['value' => '{$MACRO}'],
				['value' => '{$MACRO}'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CUserMacroParser::class, []]]
				]],
				['value' => '{$MACRO'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Unexpected end of macro.', 'level' => CFormValidator::ERROR_LEVEL_DELAYED]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CRangesParser::class, ['with_minus' => true]]]
				]],
				['value' => '700,-800'],
				['value' => '700,-800'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CRangesParser::class, ['with_minus' => false]]]
				]],
				['value' => '700,-800'],
				['value' => '700,-800'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Invalid string.', 'level' => CFormValidator::ERROR_LEVEL_DELAYED]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]]]
				]],
				['value' => '2024-01-08 12:00:00'],
				['value' => '2024-01-08 12:00:00'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CAbsoluteTimeParser::class, [], ['max' => 1704700000]]]
				]],
				['value' => '2024-01-08 12:00:00'],
				['value' => '2024-01-08 12:00:00'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Value must be smaller than 2024-01-08 09:46:40.', 'level' => CFormValidator::ERROR_LEVEL_DELAYED]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'use' => [CAbsoluteTimeParser::class, [], ['min' => 2147464799]]]
				]],
				['value' => '2024-01-08 12:00:00'],
				['value' => '2024-01-08 12:00:00'],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'Value must be greater than 2038-01-18 23:59:59.', 'level' => CFormValidator::ERROR_LEVEL_DELAYED]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'uniq' => ['macro'], 'fields' => [
						'macro' => ['string']
					]]
				]],
				['value' => [
					['macro' => 'macro_name'],
					['macro' => 'new_macro_name']
				]],
				['value' => [
					['macro' => 'macro_name'],
					['macro' => 'new_macro_name']
				]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'uniq' => ['macro'], 'fields' => [
						'macro' => ['string']
					]]
				]],
				['value' => [
					['macro' => 'macro_name'],
					['macro' => 'macro_name']
				]],
				[],
				CFormValidator::ERROR,
				['/value/1/macro' => [
					['message' => 'Entry "macro=macro_name" is not unique.', 'level' => CFormValidator::ERROR_LEVEL_UNIQ]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'uniq' => ['macro', 'value'], 'fields' => [
						'macro' => ['string']
					]]
				]],
				['value' => [
					['macro' => 'macro_name', 'value' => 'macro_value'],
					['macro' => 'macro_name', 'value' => 'macro_value']
				]],
				[],
				CFormValidator::ERROR,
				['/value/1/macro' => [
					['message' => 'Entry "macro=macro_name, value=macro_value" is not unique.', 'level' => CFormValidator::ERROR_LEVEL_UNIQ]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'uniq' => [['macro', 'value']], 'fields' => [
						'macro' => ['string']
					]]
				]],
				['value' => [
					['macro' => 'macro_name', 'value' => 'macro_value'],
					['macro' => 'macro_name', 'value' => 'macro_value']
				]],
				[],
				CFormValidator::ERROR,
				['/value/1/macro' => [
					['message' => 'Entry "macro=macro_name, value=macro_value" is not unique.', 'level' => CFormValidator::ERROR_LEVEL_UNIQ]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['objects', 'uniq' => [['macro', 'value'], 'description'], 'fields' => [
						'macro' => ['string']
					]]
				]],
				['value' => [
					['macro' => 'macro_name', 'value' => 'macro_value', 'description' => 'macro_description'],
					['macro' => 'new_macro_name', 'value' => 'new_macro_value', 'description' => 'macro_description']
				]],
				[],
				CFormValidator::ERROR,
				['/value/1/description' => [
					['message' => 'Entry "description=macro_description" is not unique.', 'level' => CFormValidator::ERROR_LEVEL_UNIQ]
				]]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'required', 'when' => ['field1', 'exist']]
				]],
				['field1' => 1, 'field2' => 2],
				['field1' => 1, 'field2' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['string'],
					'field2' => ['integer', 'required', 'when' => ['field1', 'regex' => '/^match$/']]
				]],
				['field1' => 'notmatch'],
				['field1' => 'notmatch'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['string'],
					'field2' => ['integer', 'required', 'when' => ['field1', 'regex' => '/^match$/']]
				]],
				['field1' => 'match'],
				['field1' => 'match'],
				CFormValidator::ERROR,
				['/field2' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['objects', 'fields' => [
						'ifield1' => ['integer'],
						'ifield2' => ['integer', 'required', 'when' => ['../field1', 'exist']],
						'ifield3' => ['integer', 'required', 'when' => ['ifield1', 'exist']]
					]]
				]],
				['field1' => 1, 'field2' => [['ifield1' => 1, 'ifield2' => 2, 'ifield3' => 3]]],
				['field1' => 1, 'field2' => [['ifield1' => 1, 'ifield2' => 2, 'ifield3' => 3]]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['objects', 'fields' => [
						'ifield2' => [
							['integer', 'when' => ['../field1', 'in' => [[1, null]]]],
							['string', 'when' => ['../field1', 'in' => [[null, 4]]]]
						],
						'ifield3' => ['integer', 'required', 'when' => ['ifield2', 'exist']]
					]]
				]],
				['field1' => 5, 'field2' => [['ifield2' => 2, 'ifield3' => 3]]],
				['field1' => 5, 'field2' => [['ifield2' => 2, 'ifield3' => 3]]],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'min' => 3, 'when' => ['field1', 'not_exist']]
				]],
				['field2' => 2],
				[],
				CFormValidator::ERROR,
				['/field2' => [
					['message' => 'This value must be no less than "3".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'min' => 3, 'when' => ['field1', 'exist']]
				]],
				['field2' => 2],
				['field2' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'min' => 3, 'when' => ['field1', 'not_exist']]
				]],
				['field1' => 1, 'field2' => 2],
				['field1' => 1, 'field2' => 2],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'required', 'when' => ['field1', 'exist']]
				]],
				['field1' => 1],
				[],
				CFormValidator::ERROR,
				['/field2' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'field1' => ['integer'],
					'field2' => ['integer', 'required', 'when' => ['field1', 'not_exist']]
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/field2' => [
					['message' => 'Required field is missing.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'allow_macro' => true, 'in' => ['abc']]
				]],
				['value' => '{$USER_MACRO}'],
				['value' => '{$USER_MACRO}'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'allow_macro' => true, 'in' => ['abc']]
				]],
				['value' => 'abc'],
				['value' => 'abc'],
				CFormValidator::SUCCESS,
				[]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'in' => ['abc']]
				]],
				['value' => '{$USER_MACRO}'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value must be "abc".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['string', 'in' => ['abc']]
				]],
				['value' => '{$USER_MACRO}'],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'This value must be "abc".', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['file', 'required', 'not_empty']
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'No file was uploaded.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]]
			],
			[
				['object', 'fields' => [
					'value' => ['file', 'max-size' => 1024]
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'File size must be less than 1 KB.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => 'phpunit.xml', 'error' => UPLOAD_ERR_OK,
					'size' => 1300
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['file', 'file-type' => 'image']
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'File format is unsupported.', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => 'phpunit.xml', 'error' => UPLOAD_ERR_OK,
					'size' => 10
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['file', 'file-type' => 'image', 'messages' => ['file-type' => 'msg1']]
				]],
				[],
				[],
				CFormValidator::ERROR,
				['/value' => [
					['message' => 'msg1', 'level' => CFormValidator::ERROR_LEVEL_PRIMARY]
				]],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => 'phpunit.xml', 'error' => UPLOAD_ERR_OK,
					'size' => 10
				]]
			],
			[
				['object', 'fields' => [
					'value' => ['file']
				]],
				[],
				[],
				CFormValidator::SUCCESS,
				[],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => 'phpunit.xml', 'error' => UPLOAD_ERR_OK,
					'size' => 10
				]],
				['value' => ['name' => '', 'type' => '', 'tmp_name' => 'phpunit.xml', 'error' => UPLOAD_ERR_OK,
					'size' => 10
				]]
			]
		];
	}

	/**
	 * @dataProvider dataProviderFormValidator
	 *
	 * @param array $rules
	 * @param array $data
	 * @param array $expected_data
	 * @param int   $expected_result
	 * @param array $expected_errors
	 */
	public function testFormValidator(array $rules, array $data, array $expected_data, int $expected_result,
			array $expected_errors, array $files = [], array $expected_files = []): void {
		global $DB;

		$DB['TYPE'] = ZBX_DB_MYSQL;

		$validator = new CFormValidator($rules);
		$normalized_rules = $validator->getRules();

		$this->assertSame('array', gettype($normalized_rules));

		$result = $validator->validate($data, $files);

		$this->assertSame($expected_result, $result);
		$this->assertSame($expected_errors, $validator->getErrors());

		if ($expected_result == CFormValidator::SUCCESS) {
			$this->assertSame($expected_data, $data);
			$this->assertSame($files, $expected_files);
		}
	}
}
