<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CDataHelper.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

class testItemPreprocessing extends CAPITest {

	private const SSH_ITEM_FIELDS = [
		'type' => ITEM_TYPE_SSH,
		'username' => 'username',
		'params' => 'service mysql-server status',
		'delay' => '1m'
	];

	private const NS_STEP_FIELDS = [
		'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
		'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
		'error_handler_params' => ''
	];

	public static function setUpBeforeClass(): void {
		DBconnect($error);

		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'tg.preprocessing']
			],
			'templates' => [
				[
					'host' => 'test.ns.create',
					'lld_rules' => [
						['key_' => 'test.ns.create.rule']
					]
				],
				[
					'host' => 'test.ns.update',
					'items' => [
						['key_' => 'non-ssh.item'],
						['key_' => 'ssh.item'] + self::SSH_ITEM_FIELDS
					],
					'lld_rules' => [
						[
							'key_' => 'test.ns.update.rule',
							'item_prototypes' => [
								['key_' => 'non-ssh.item.prototype[{#LLD}]'],
								['key_' => 'ssh.item.prototype[{#LLD}]'] + self::SSH_ITEM_FIELDS
							]
						],
						['key_' => 'non-ssh.rule'],
						['key_' => 'ssh.rule'] + self::SSH_ITEM_FIELDS
					]
				],
				[
					'host' => 'item.verify.ns.sorting',
					'items' => [
						[
							'key_' => 'ssh.item.sorted',
							'preprocessing' => [
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'A'],
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}'],
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'B'],
								[
									'type' => ZBX_PREPROC_TRIM,
									'params' => ' ',
									'error_handler' => DB::getDefault('item_preproc', 'error_handler'),
									'error_handler_params' => DB::getDefault('item_preproc', 'error_handler_params')
								],
								self::NS_STEP_FIELDS + ['params' => ''],
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'A'],
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}'],
								self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'B']
							]
						] + self::SSH_ITEM_FIELDS
					]
				]
			]
		]);
	}

	public static function tearDownAfterClass(): void {
		CTestDataHelper::cleanUp();
	}

	/**
	 * @param bool $prototype
	 *
	 * @return string
	 */
	private static function next_id(bool $prototype = false): string {
		static $next = 0;

		return 'item.key.'.++$next.($prototype ? '.[{#LLD}]' : '');
	}

	public static function getTypeNotSupportedChecks() {
		return [
			'CHECK_NS:non-ssh-item: accept without params' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-item: reject params as non-string' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:non-ssh-item: accept params without mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-item: accept params with default match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-item: reject match regex mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item: reject non-match regex mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item: reject unknown mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) 999]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item: accept params with empty pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-item: reject match any with non-empty pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:non-ssh-item: reject multiple not supported steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],

			'CHECK_NS:non-ssh-itemprototype: accept without params' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype: reject params as non-string' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:non-ssh-itemprototype: accept params without mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype: accept params with default match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype: reject match regex mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype: reject non-match regex mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype: reject unknown mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) 999]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype: accept params with empty pattern' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				]),
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype: reject match any with non-empty pattern' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:non-ssh-itemprototype: reject multiple not supported steps' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],

			'CHECK_NS:non-ssh-LLD: step type is not supported' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/type": value must be one of '.implode(', ', [
					ZBX_PREPROC_REGSUB, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
					ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
					ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
					ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON,
					ZBX_PREPROC_SNMP_WALK_VALUE, ZBX_PREPROC_SNMP_WALK_TO_JSON
				]).'.'
			],
			'CHECK_NS:ssh-LLD: step type is not supported' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/type": value must be one of '.implode(', ', [
					ZBX_PREPROC_REGSUB, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
					ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
					ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
					ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON,
					ZBX_PREPROC_SNMP_WALK_VALUE, ZBX_PREPROC_SNMP_WALK_TO_JSON
				]).'.'
			],

			'CHECK_NS:ssh-item: accept without params' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: reject params as non-string' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:ssh-item: accept params without mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept params with default match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept params with empty pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: reject match any with non-empty pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:ssh-item: reject multiple match any steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],
			'CHECK_NS:ssh-item: reject match regex without pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n"]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'CHECK_NS:ssh-item: reject non-match regex without pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n"]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'CHECK_NS:ssh-item: accept match regex with pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept multiple match regex with pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\ndef"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept multiple match regex with pattern, even same' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept non-match regex with pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept multiple non-match regex with pattern' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\ndef"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept multiple non-match regex with pattern, even same' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept mix of modes' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept mix of modes, even with match any first' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: reject mix of modes if "match any" repeats' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/4": only one object can exist within the combinations of (type, params)=(26, -1\n).'
			],
			'CHECK_NS:ssh-item: accept mix of modes and non-check-ns steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						[
							'type' => ZBX_PREPROC_TRIM,
							'params' => ' '
						],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],

			'CHECK_NS:ssh-item: reject invalid regex on match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc("]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'CHECK_NS:ssh-item: reject invalid regex on non-match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc("]
				] + self::SSH_ITEM_FIELDS),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'CHECK_NS:ssh-item: accept macro in match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept macro in non-match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept macro as part of pattern in match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{$MACRO}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-item: accept macro as part of pattern in non-match mode' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{$MACRO}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept macro as part of pattern in match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{$MACRO}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept macro as part of pattern in non-match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{$MACRO}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept LLD macro as pattern in match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{#LLD}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept LLD macro as pattern in non-match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{#LLD}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept LLD macro as part of pattern in match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{#LLD}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype: accept LLD macro as part of pattern in non-match mode' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItemPrototype([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{#LLD}abc']
				] + self::SSH_ITEM_FIELDS),
				'error' => null
			],

			'CHECK_NS:non-ssh-item.upd: accept without params' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-item.upd: reject params as non-string' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:non-ssh-item.upd: accept params without mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-item.upd: accept params with default match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-item.upd: reject match regex mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item.upd: reject non-match regex mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item.upd: reject unknown mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) 999]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-item.upd: accept params with empty pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-item.upd: reject match any with non-empty pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:non-ssh-item.upd: reject multiple not supported steps' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:non-ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				],
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],

			'CHECK_NS:non-ssh-itemprototype.upd: accept without params' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype.upd: reject params as non-string' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: accept params without mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: accept params with default match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: reject match regex mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: reject non-match regex mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: reject unknown mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) 999]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be '.ZBX_PREPROC_MATCH_ERROR_ANY.'.'
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: accept params with empty pattern' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				],
				'error' => null
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: reject match any with non-empty pattern' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:non-ssh-itemprototype:.upd: reject multiple not supported steps' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:non-ssh.item.prototype[{#LLD}]',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				],
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],

			'CHECK_NS:non-ssh-LLD.upd: step type is not supported' => [
				'method' => 'discoveryrule.update',
				'params' =>  [
					'itemid' => ':lld_rule:non-ssh.rule',
					'preprocessing' => self::NS_STEP_FIELDS
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/type": value must be one of '.implode(', ', [
					ZBX_PREPROC_REGSUB, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
					ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
					ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
					ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON,
					ZBX_PREPROC_SNMP_WALK_VALUE, ZBX_PREPROC_SNMP_WALK_TO_JSON
				]).'.'
			],
			'CHECK_NS:ssh-LLD.upd: step type is not supported' => [
				'method' => 'discoveryrule.update',
				'params' =>  [
					'itemid' => ':lld_rule:ssh.rule',
					'preprocessing' => self::NS_STEP_FIELDS
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/type": value must be one of '.implode(', ', [
					ZBX_PREPROC_REGSUB, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
					ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
					ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
					ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON,
					ZBX_PREPROC_SNMP_WALK_VALUE, ZBX_PREPROC_SNMP_WALK_TO_JSON
				]).'.'
			],

			'CHECK_NS:ssh-item.upd: accept without params' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: reject params as non-string' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => 1]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'CHECK_NS:ssh-item.upd: accept params without mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => '']
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept params with default match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept params with empty pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: reject match any with non-empty pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\nabc"]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": value must be empty.'
			],
			'CHECK_NS:ssh-item.upd: reject multiple match any steps' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS,
						self::NS_STEP_FIELDS
					]
				],
				'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the combinations of (type, params)=('.ZBX_PREPROC_VALIDATE_NOT_SUPPORTED.', '.ZBX_PREPROC_MATCH_ERROR_ANY.'\\n).'
			],
			'CHECK_NS:ssh-item.upd: reject match regex without pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n"]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'CHECK_NS:ssh-item.upd: reject non-match regex without pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n"]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'CHECK_NS:ssh-item.upd: accept match regex with pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept multiple match regex with pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\ndef"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept multiple match regex with pattern, even same' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept non-match regex with pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept multiple non-match regex with pattern' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\ndef"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept multiple non-match regex with pattern, even same' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept mix of modes' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept mix of modes, even with match any first' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: reject mix of modes if "match any" repeats' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"]
					]
				],
				'error' => 'Invalid parameter "/1/preprocessing/4": only one object can exist within the combinations of (type, params)=(26, -1\n).'
			],
			'CHECK_NS:ssh-item.upd: accept mix of modes and non-check-ns steps' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => [
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"],
						[
							'type' => ZBX_PREPROC_TRIM,
							'params' => ' '
						],
						self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"]
					]
				],
				'error' => null
			],

			'CHECK_NS:ssh-item.upd: reject invalid regex on match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc("]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'CHECK_NS:ssh-item.upd: reject invalid regex on non-match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc("]
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'CHECK_NS:ssh-item.upd: accept macro in match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}']
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept macro in non-match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}']
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept macro as part of pattern in match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{$MACRO}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-item.upd: accept macro as part of pattern in non-match mode' => [
				'method' => 'item.update',
				'params' =>  [
					'itemid' => ':item:ssh.item',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{$MACRO}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept macro as part of pattern in match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{$MACRO}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept macro as part of pattern in non-match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{$MACRO}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept LLD macro as pattern in match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{#LLD}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept LLD macro as pattern in non-match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{#LLD}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept LLD macro as part of pattern in match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'abc{#LLD}abc']
				],
				'error' => null
			],
			'CHECK_NS:ssh-itemprototype:.upd: accept LLD macro as part of pattern in non-match mode' => [
				'method' => 'itemprototype.update',
				'params' =>  [
					'itemid' => ':item_prototype:ssh.item.prototype[{#LLD}]',
					'preprocessing' => self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'abc{#LLD}abc']
				],
				'error' => null
			],
		];
	}

	/**
	 * @dataProvider getTypeNotSupportedChecks
	 */
	public function testItemPreprocessing_TypeNotSupported(string $method, array $params, ?string $error = null) {
		CTestDataHelper::processReferences($method, $params);

		return $this->call($method, $params, $error);
	}

	public function testItemPreprocessing_TypeNotSupportedSorting() {
		$result = CDataHelper::call('item.get', [
			'filter' => [
				'key_' => 'ssh.item.sorted'
			],
			'selectPreprocessing' => API_OUTPUT_EXTEND
		]);
		$this->assertNotEmpty($result);

		$item = reset($result);
		$this->assertArrayHasKey('preprocessing', $item);

		$this->assertEquals($item['preprocessing'], [
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'A'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'B'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'A'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'B'],
			self::NS_STEP_FIELDS + ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n"],
			[
				'type' => ZBX_PREPROC_TRIM,
				'params' => ' ',
				'error_handler' => DB::getDefault('item_preproc', 'error_handler'),
				'error_handler_params' => DB::getDefault('item_preproc', 'error_handler_params')
			]
		], 'Steps should be sorted, with "match any" last of the not-supported steps, trim as the last step.');

		return true;
	}
}
