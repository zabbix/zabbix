<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testLowLevelDiscovery.php';

/**
 * @backup hosts
 *
 * @onBefore prepareLLDTemplateData
 */
class testFormLowLevelDiscoveryFromTemplate extends testLowLevelDiscovery {

	protected static $templateid;

	public function prepareLLDTemplateData() {
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template with LLD',
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => 'Master item',
						'key_' => 'master.test',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'LLD for update scenario',
						'key_' => 'vfs.fs.discovery1',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					],
					[
						'name' => 'LLD for delete scenario',
						'key_' => 'vfs.fs.discovery2',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					],
					[
						'name' => 'LLD for clone scenario',
						'key_' => 'vfs.fs.discovery3',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					],
					[
						'name' => 'LLD for simple update scenario',
						'key_' => 'update_key',
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => "1h;wd1-2h7-14",
						'url' => 'https://www.test.com/search',
						'query_fields' => [['name' => 'test_name1', 'value' => 'value1'], ['name' => '2', 'value' => 'value2']],
						'request_method' => HTTPCHECK_REQUEST_HEAD,
						'post_type' => ZBX_POSTTYPE_JSON,
						'posts' => "{\"zabbix_export\": {\"version\": \"6.0\",\"date\": \"2024-03-20T20:05:14Z\"}}",
						'headers' => [['name' => 'name1', 'value' => 'value']],
						'status_codes' => '400, 600',
						'follow_redirects' => 1,
						'retrieve_mode' => 1,
						'http_proxy' => '161.1.1.5',
						'authtype' => ZBX_HTTP_AUTH_NTLM,
						'username' => 'user',
						'password' => 'pass',
						'verify_peer' => ZBX_HTTP_VERIFY_PEER_ON,
						'verify_host' => ZBX_HTTP_VERIFY_HOST_ON,
						'ssl_cert_file' => '/home/test/certdb/ca.crt',
						'ssl_key_file' => '/home/test/certdb/postgresql-server.crt',
						'ssl_key_password' => '/home/test/certdb/postgresql-server.key',
						'timeout' => '10s',
						'lifetime_type' => ZBX_LLD_DELETE_AFTER,
						'lifetime' => '15d',
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_NEVER,
						'allow_traps' => HTTPCHECK_ALLOW_TRAPS_ON,
						'trapper_hosts' => '127.0.2.3',
						'description' => 'LLD for test',
						'preprocessing' => [['type' => ZBX_PREPROC_STR_REPLACE, 'params' => "a\nb"]],
						'lld_macro_paths' => ['lld_macro' => '{#MACRO}', 'path' => '$.path'],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'macro' => '{#MACRO}',
									'value' => 'expression',
									'operator' => CONDITION_OPERATOR_NOT_REGEXP
								]
							]
						],
						'overrides' => [
							'name' => 'Override',
							'step' => 1,
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => '',
										'operator' => CONDITION_OPERATOR_EXISTS
									]
								]
							],
							'operations' => [
								'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => 'test',
								'opstatus' => ['status' => ZBX_PROTOTYPE_DISCOVER]
							]
						]
					],
					[
						'name' => 'LLD for cancel scenario',
						'key_' => 'ssh.run[test]',
						'type' => ITEM_TYPE_SSH,
						'delay' => "3h;20s/1-3,00:02-14:30",
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'username' => 'username',
						'password' => 'passphrase',
						'publickey' => '/home/test/public-server.key',
						'privatekey' => '/home/test/private-server.key',
						'params' => 'test script',
						'timeout' => '',
						'lifetime_type' => ZBX_LLD_DELETE_NEVER,
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_AFTER,
						'enabled_lifetime' => '20h',
						'description' => 'Description for cancel scenario',
						'preprocessing' => [['type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE, 'params' => '30s']],
						'lld_macro_paths' => ['lld_macro' => '{#LLDMACRO}', 'path' => '$.path.to.node'],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'macro' => '{#FILTERMACRO}',
									'operator' => CONDITION_OPERATOR_NOT_EXISTS
								]
							]
						],
						'overrides' => [
							'name' => 'Cancel override',
							'step' => 1,
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#OVERRIDE_MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => 'expression'
									]
								]
							],
							'operations' => [
								'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
								'operator' => CONDITION_OPERATOR_NOT_EQUAL,
								'value' => 'test',
								'opstatus' => ['status' => ZBX_PROTOTYPE_DISCOVER],
								'opseverity' => ['severity' => TRIGGER_SEVERITY_HIGH]
							]
						]
					]
				]
			]
		]);

		self::$templateid = $templates['templateids']['Template with LLD'];
	}

	public function testFormLowLevelDiscoveryFromTemplate_Layout() {
		$this->checkFormLayout('template');
	}

	public function testFormLowLevelDiscoveryFromTemplate_SimpleUpdate() {
		$this->checkSimpleUpdate('template');
	}

	/**
	 * @dataProvider getLLDData
	 */
	public function testFormLowLevelDiscoveryFromTemplate_Create($data) {
		$this->checkForm($data, false, 'template');
	}

	/**
	 * @dataProvider getLLDData
	 */
	public function testFormLowLevelDiscoveryFromTemplate_Update($data) {
		$this->checkForm($data, true, 'template');
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormLowLevelDiscoveryFromTemplate_Cancel($data) {
		$this->checkCancel($data, 'template');
	}

	public function testFormLowLevelDiscoveryFromTemplate_Delete() {
		$this->checkDelete('template');
	}
}
