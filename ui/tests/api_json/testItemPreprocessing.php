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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CDataHelper.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testItemPreprocessing extends CAPITest {

	private const NS_STEP_FIELDS = [
		'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
		'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
		'error_handler_params' => ''
	];

	public static function prepareTestData(): void {
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
					'host' => 'item.verify.ns.sorting',
					'items' => [
						[
							'key_' => 'ssh.item.sorted',
							'type' => ITEM_TYPE_SSH,
							'username' => 'username',
							'params' => 'service mysql-server status',
							'delay' => '1m',
							'preprocessing' => [
								['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'A'] + self::NS_STEP_FIELDS,
								['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}'] + self::NS_STEP_FIELDS,
								['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'B'] + self::NS_STEP_FIELDS,
								[
									'type' => ZBX_PREPROC_TRIM,
									'params' => ' ',
									'error_handler' => DB::getDefault('item_preproc', 'error_handler'),
									'error_handler_params' => DB::getDefault('item_preproc', 'error_handler_params')
								],
								['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS,
								['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'A'] + self::NS_STEP_FIELDS,
								['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}'] + self::NS_STEP_FIELDS,
								['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'B'] + self::NS_STEP_FIELDS
							]
						]
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
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

	public static function itemPreprocessingStepsDataProvider() {
		return [
			'item-preproc: reject error_handler' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
						'params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/error_handler": value must be one of 1, 2, 3.'
			],
			'item-preproc: reject no params' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1": the parameter "params" is missing.'
			],
			'item-preproc: reject params non-string' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => 1] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'item-preproc: reject first param not in allowed list' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => "5"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be one of -1, 0, 1.'
			],
			'item-preproc: reject first param not numeric' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_ANY.".."] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": an integer is expected.'
			],

			'item-preproc-error-any: accept' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'item-preproc-error-any: reject have extra param' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n1"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "2".'
			],
			'item-preproc-error-any: reject duplicate steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS,
						['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/2": value (type, params)=(26, -1) already exists.'
			],

			'item-preproc-regexp-match: accept' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'item-preproc-regexp-match: accept duplicate steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS,
						['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS
					]
				]),
				'error' => null
			],
			'item-preproc-regexp-match: reject no second param' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": the parameter "2" is missing.'
			],
			'item-preproc-regexp-match: reject second param empty' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'item-preproc-regexp-match: reject second param not regexp' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nab[c"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'item-preproc-regexp-match: reject have extra param' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n1\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "3".'
			],

			'item-preproc-regexp-nomatch: accept' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'item-preproc-regexp-match: accept duplicate steps' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS,
						['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS
					]
				]),
				'error' => null
			],
			'item-preproc-regexp-nomatch: reject no second param' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": the parameter "2" is missing.'
			],
			'item-preproc-regexp-nomatch: reject second param empty' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'item-preproc-regexp-nomatch: reject second param not regexp' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nab[c"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'item-preproc-regexp-nomatch: reject have extra param' => [
				'method' => 'item.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n1\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "3".'
			]
		];
	}

	public static function itemPrototypeStepsPreprocessingDataProvider() {
		return [
			'itemprototype-preproc: reject error_handler' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => [
						'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
						'params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/error_handler": value must be one of 1, 2, 3.'
			],
			'itemprototype-preproc: reject no params' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1": the parameter "params" is missing.'
			],
			'itemprototype-preproc: reject params non-string' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => 1] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": a character string is expected.'
			],
			'itemprototype-preproc: reject first param not in allowed list' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => "5"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be one of -1, 0, 1.'
			],
			'itemprototype-preproc: reject first param not numeric' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_ANY.".."] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": an integer is expected.'
			],

			'itemprototype-preproc-error-any: accept' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'itemprototype-preproc-error-any: reject have extra param' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_ANY."\n1"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "2".'
			],
			'itemprototype-preproc-error-any: reject duplicate steps' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => [
						['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS,
						['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS
					]
				]),
				'error' => 'Invalid parameter "/1/preprocessing/2": value (type, params)=(26, -1) already exists.'
			],

			'itemprototype-preproc-regexp-match: accept' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'itemprototype-preproc-regexp-match: accept duplicate steps' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => [
						['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS,
						['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nabc"] + self::NS_STEP_FIELDS
					]
				]),
				'error' => null
			],
			'itemprototype-preproc-regexp-match: reject no second param' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_REGEX] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": the parameter "2" is missing.'
			],
			'itemprototype-preproc-regexp-match: reject second param empty' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'itemprototype-preproc-regexp-match: reject second param not regexp' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\nab[c"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'itemprototype-preproc-regexp-match: reject have extra param' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n1\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "3".'
			],

			'itemprototype-preproc-regexp-nomatch: accept' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'itemprototype-preproc-regexp-match: accept duplicate steps' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => [
						['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS,
						['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nabc"] + self::NS_STEP_FIELDS
					]
				]),
				'error' => null
			],
			'itemprototype-preproc-regexp-nomatch: reject no second param' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_NOT_REGEX] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": the parameter "2" is missing.'
			],
			'itemprototype-preproc-regexp-nomatch: reject second param empty' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
			],
			'itemprototype-preproc-regexp-nomatch: reject second param not regexp' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\nab[c"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/2": invalid regular expression.'
			],
			'itemprototype-preproc-regexp-nomatch: reject have extra param' => [
				'method' => 'itemprototype.create',
				'params' =>  CTestDataHelper::prepareItem([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'ruleid' => ':lld_rule:test.ns.create.rule',
					'preprocessing' => ['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n1\n"] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params": unexpected parameter "3".'
			]
		];
	}

	public static function lldPreprocessingStepsDataProvider() {
		return [
			'lld-preproc-regexp: accept' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						'type' => ZBX_PREPROC_VALIDATE_REGEX,
						'params' => '^regexp'
					] + self::NS_STEP_FIELDS
				]),
				'error' => null
			],
			'lld-preproc-regexp: reject no param' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['type' => ZBX_PREPROC_VALIDATE_REGEX] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1": the parameter "params" is missing.'
			],
			'lld-preproc-regexp: reject first param not regexp' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => [
						'type' => ZBX_PREPROC_VALIDATE_REGEX,
						'params' => 're[gexp'
					] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid regular expression.'
			],

			'lld-preproc: reject check for not supported' => [
				'method' => 'discoveryrule.create',
				'params' =>  CTestDataHelper::prepareLldRule([
					'key_' => self::next_id(true),
					'hostid' => ':template:test.ns.create',
					'preprocessing' => ['params' => (string) ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS
				]),
				'error' => 'Invalid parameter "/1/preprocessing/1/type": value must be one of 5, 11, 12, 14, 15, 16, 17, 20, 21, 23, 24, 25, 27, 28, 29, 30.'
			]
		];
	}

	/**
	 * @dataProvider itemPreprocessingStepsDataProvider
	 * @dataProvider itemPrototypeStepsPreprocessingDataProvider
	 * @dataProvider lldPreprocessingStepsDataProvider
	 */
	public function testItemPreprocessing_TypeNotSupported(string $method, array $params, ?string $error = null) {
		if ($method === 'item.create') {
			CTestDataHelper::convertItemReferences($params);
		}

		if ($method === 'itemprototype.create') {
			CTestDataHelper::convertItemPrototypeReferences($params);
		}

		if ($method === 'discoveryrule.create') {
			CTestDataHelper::convertLldRuleReferences($params);
		}

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
			['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'A'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'{$MACRO}'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_REGEX."\n".'B'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'A'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'{$MACRO}'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_NOT_REGEX."\n".'B'] + self::NS_STEP_FIELDS,
			['params' => ZBX_PREPROC_MATCH_ERROR_ANY] + self::NS_STEP_FIELDS,
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
