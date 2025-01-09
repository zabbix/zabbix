<?php declare(strict_types = 1);
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

abstract class CItemType {

	/**
	 * Item type.
	 *
	 * @var int|null
	 */
	const TYPE = null;

	/**
	 * Field names of specific type.
	 *
	 * @var array
	 */
	const FIELD_NAMES = [
		// The fields used for multiple item types.
		'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

		// Dependent item type specific fields.
		'master_itemid',

		// HTTP Agent item type specific fields.
		'url', 'query_fields', 'request_method', 'post_type', 'posts',
		'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
		'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

		// IPMI item type specific fields.
		'ipmi_sensor',

		// JMX item type specific fields.
		'jmx_endpoint',

		// Script item type specific fields.
		'parameters',

		// SNMP item type specific fields.
		'snmp_oid',

		// SSH item type specific fields.
		'publickey', 'privatekey'
	];

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	abstract public static function getCreateValidationRules(array $item): array;

	/**
	 * @param array $db_item
	 *
	 * @return array
	 */
	abstract public static function getUpdateValidationRules(array $db_item): array;

	/**
	 * @param array $db_item
	 *
	 * @return array
	 */
	abstract public static function getUpdateValidationRulesInherited(array $db_item): array;

	/**
	 * @return array
	 */
	abstract public static function getUpdateValidationRulesDiscovered(): array;

	/**
	 * @param string $field_name
	 * @param array  $item
	 *
	 * @return array
	 */
	final protected static function getCreateFieldRule(string $field_name, array $item): array {
		$is_item_prototype = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		switch ($field_name) {
			case 'interfaceid':
				switch (static::TYPE) {
					case ITEM_TYPE_SIMPLE:
					case ITEM_TYPE_EXTERNAL:
					case ITEM_TYPE_SSH:
					case ITEM_TYPE_TELNET:
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID],
							['else' => true, 'type' => API_ID, 'in' => '0']
						]];

					default:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID, 'flags' => API_REQUIRED],
							['else' => true, 'type' => API_ID, 'in' => '0']
						]];
				}

			case 'authtype':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST]), 'default' => DB::getDefault('items', 'authtype')];

					case ITEM_TYPE_SSH:
						return ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]), 'default' => DB::getDefault('items', 'authtype')];
				}

			case 'username':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'username')]
						]];

					case ITEM_TYPE_SSH:
					case ITEM_TYPE_TELNET:
						return ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')];
				}

			case 'password':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'password')]
						]];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')];
				}

			case 'params':
				switch (static::TYPE) {
					case ITEM_TYPE_CALCULATED:
						return ['type' => API_CALC_FORMULA, 'flags' => API_REQUIRED | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

					default:
						return ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
				}

			case 'timeout':
				switch (static::TYPE) {
					case ITEM_TYPE_SIMPLE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'icmpping', 8) != 0 && strncmp($data['key_'], 'vmware.', 7) != 0;
							}, 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'timeout')]
						]];

					case ITEM_TYPE_SNMP:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['snmp_oid'], 'get[', 4) == 0 || strncmp($data['snmp_oid'], 'walk[', 5) == 0;
							}, 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'timeout')]
						]];

					default:
						return ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')];
				}

			case 'delay':
				switch (static::TYPE) {
					case ITEM_TYPE_ZABBIX_ACTIVE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) != 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'delay')]
						]];

					default:
						return ['type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
				}

			case 'trapper_hosts':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'allow_traps', 'in' => HTTPCHECK_ALLOW_TRAPS_ON], 'type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'trapper_hosts')]
						]];

					case  ITEM_TYPE_TRAPPER:
						return ['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
				}

			case 'parameters':
				return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
					'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
					'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
				]];
		}
	}

	/**
	 * @param string $field_name
	 * @param array  $db_item
	 *
	 * @return array
	 */
	final protected static function getUpdateFieldRule(string $field_name, array $db_item): array {
		$is_item_prototype = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		switch ($field_name) {
			case 'interfaceid':
				return ['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID],
					['else' => true, 'type' => API_ID, 'in' => '0']
				]];

			case 'authtype':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])];

					case ITEM_TYPE_SSH:
						return ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])];
				}

			case 'username':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'username')]
						]];

					case ITEM_TYPE_SSH:
					case ITEM_TYPE_TELNET:
						return ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')];
				}

			case 'password':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'password')]
						]];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')];
				}

			case 'params':
				switch (static::TYPE) {
					case ITEM_TYPE_CALCULATED:
						return ['type' => API_CALC_FORMULA, 'flags' => ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

					default:
						return ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
				}

			case 'timeout':
				switch (static::TYPE) {
					case ITEM_TYPE_SIMPLE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'icmpping', 8) != 0 && strncmp($data['key_'], 'vmware.', 7) != 0;
							}, 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'timeout')]
						]];

					case ITEM_TYPE_SNMP:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['snmp_oid'], 'get[', 4) == 0 || strncmp($data['snmp_oid'], 'walk[', 5) == 0;
							}, 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'timeout')]
						]];

					default:
						return ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.(10 * SEC_PER_MIN), 'length' => DB::getFieldLength('items', 'timeout')];
				}

			case 'delay':
				switch (static::TYPE) {
					case ITEM_TYPE_ZABBIX_ACTIVE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) != 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'delay')]
						]];

					case ITEM_TYPE_DEPENDENT:
						return ['type' => API_ITEM_DELAY, 'in' => DB::getDefault('items', 'delay')];

					default:
						return ['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
				}

			case 'trapper_hosts':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'allow_traps', 'in' => HTTPCHECK_ALLOW_TRAPS_ON], 'type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'trapper_hosts')]
						]];

					case  ITEM_TYPE_TRAPPER:
						return ['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
				}

			case 'parameters':
				return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
					'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
					'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
				]];
		}
	}

	/**
	 * @param string $field_name
	 * @param array  $db_item
	 *
	 * @return array
	 */
	final protected static function getUpdateFieldRuleInherited(string $field_name, array $db_item): array {
		$is_item_prototype = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		switch ($field_name) {
			case 'interfaceid':
				return ['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID],
					['else' => true, 'type' => API_ID, 'in' => '0']
				]];

			case 'authtype':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];

					case ITEM_TYPE_SSH:
						return ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])];
				}

			case 'username':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];

					case ITEM_TYPE_SSH:
					case ITEM_TYPE_TELNET:
						return ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')];
				}

			case 'password':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')];
				}

			case 'params':
				switch (static::TYPE) {
					case ITEM_TYPE_CALCULATED:
						return ['type' => API_CALC_FORMULA, 'flags' => ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

					case ITEM_TYPE_SCRIPT:
					case ITEM_TYPE_BROWSER:
						return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];

					default:
						return ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
				}

			case 'timeout':
				return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];

			case 'delay':
				switch (static::TYPE) {
					case ITEM_TYPE_ZABBIX_ACTIVE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) != 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'delay')]
						]];

					default:
						return ['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
				}

			case 'trapper_hosts':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'allow_traps', 'in' => HTTPCHECK_ALLOW_TRAPS_ON], 'type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'trapper_hosts')]
						]];

					case  ITEM_TYPE_TRAPPER:
						return ['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
				}

			case 'parameters':
				return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED];
		}
	}

	/**
	 * @param string $field_name
	 *
	 * @return array
	 */
	final protected static function getUpdateFieldRuleDiscovered(string $field_name): array {
		switch ($field_name) {
			case 'interfaceid':
			case 'authtype':
			case 'username':
			case 'password':
			case 'params':
			case 'timeout':
			case 'delay':
			case 'trapper_hosts':
			case 'parameters':
				return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED];
		}
	}

	/**
	 * @return array
	 */
	final public static function getDefaultValidationRules(): array {
		return [
			// The fields used for multiple item types.
			'interfaceid' =>		['type' => API_ID, 'in' => '0'],
			'authtype' =>			['type' => API_INT32, 'in' => DB::getDefault('items', 'authtype')],
			'username' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'username')],
			'password' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'password')],
			'params' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'params')],
			'timeout' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'timeout')],
			'delay' =>				['type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'delay')],
			'trapper_hosts' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'trapper_hosts')],

			// Dependent item type specific fields.
			'master_itemid' =>		['type' => API_ID, 'in' => '0'],

			// HTTP Agent item type specific fields.
			'url' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'url')],
			'query_fields' =>		['type' => API_OBJECTS, 'length' => 0],
			'request_method' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'request_method')],
			'post_type' =>			['type' => API_INT32, 'in' => DB::getDefault('items', 'post_type')],
			'posts' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'posts')],
			'headers' =>			['type' => API_OBJECTS, 'length' => 0],
			'status_codes' =>		['type' => API_INT32_RANGES, 'in' => DB::getDefault('items', 'status_codes')],
			'follow_redirects' =>	['type' => API_INT32, 'in' => DB::getDefault('items', 'follow_redirects')],
			'retrieve_mode' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'retrieve_mode')],
			'output_format' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'output_format')],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'http_proxy')],
			'verify_peer' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'verify_peer')],
			'verify_host' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'verify_host')],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'ssl_key_password')],
			'allow_traps' =>		['type' => API_INT32, 'in' => DB::getDefault('items', 'allow_traps')],

			// IPMI item type specific fields.
			'ipmi_sensor' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'ipmi_sensor')],

			// JMX item type specific fields.
			'jmx_endpoint' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'jmx_endpoint')],

			// Script item type specific fields.
			'parameters' =>			['type' => API_OBJECTS, 'length' => 0],

			// SNMP item type specific fields.
			'snmp_oid' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'snmp_oid')],

			// SSH item type specific fields.
			'publickey' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'publickey')],
			'privatekey' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'privatekey')]
		];
	}
}
