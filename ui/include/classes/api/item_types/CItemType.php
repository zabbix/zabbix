<?php declare(strict_types = 1);
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
	const FIELD_NAMES = [];

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
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID],
							['else' => true, 'type' => API_EMPTY_ID]
						]];

					default:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID, 'flags' => API_REQUIRED],
							['else' => true, 'type' => API_EMPTY_ID]
						]];
				}

			case 'authtype':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST]), 'default' => DB::getDefault('items', 'authtype')];

					case ITEM_TYPE_SSH:
						return ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]), 'default' => DB::getDefault('items', 'authtype')];
				}

			case 'username':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')],
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
							['if' => ['field' => 'authtype', 'in' => implode(',', [HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'password')]
						]];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')];
				}

			case 'params':
				switch (static::TYPE) {
					case ITEM_TYPE_CALCULATED:
						return ['type' => API_CALC_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'params')];

					default:
						return ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
				}

			case 'timeout':
				return ['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')];

			case 'delay':
				switch (static::TYPE) {
					case ITEM_TYPE_ZABBIX_ACTIVE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'delay')]
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
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
							}, 'type' => API_ID],
							['else' => true, 'type' => API_EMPTY_ID]
						]];

					default:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
									&& in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
										ITEM_TYPE_DB_MONITOR, ITEM_TYPE_CALCULATED, ITEM_TYPE_DEPENDENT, ITEM_TYPE_SCRIPT
									]);
							}, 'type' => API_ID, 'flags' => API_REQUIRED],
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
							}, 'type' => API_ID],
							['else' => true, 'type' => API_EMPTY_ID]
						]];
				}

			case 'authtype':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])];

					case ITEM_TYPE_SSH:
						return ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])];
				}

			case 'username':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'username')]
						]];

					case ITEM_TYPE_SSH:
					case ITEM_TYPE_TELNET:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['type'], [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL,
									ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_CALCULATED,
									ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
								]) || (in_array($db_item['type'], [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT]) && $db_item['username'] === '');
							}, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')],
							['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')]
						]];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')];
				}

			case 'password':
				switch (static::TYPE) {
					case ITEM_TYPE_HTTPAGENT:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'authtype', 'in' => implode(',', [HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'password')]
						]];

					default:
						return ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')];
				}

			case 'params':
				switch (static::TYPE) {
					case ITEM_TYPE_CALCULATED:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['type'], [
									ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
									ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT,
									ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP
								]);
							}, 'type' => API_CALC_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'params')],
							['else' => true, 'type' => API_CALC_FORMULA, 'length' => DB::getFieldLength('items', 'params')]
						]];

					default:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['type'], [
									ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
									ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT,
									ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP
								]);
							}, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')],
							['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')]
						]];
				}

			case 'timeout':
				return ['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')];

			case 'delay':
				switch (static::TYPE) {
					case ITEM_TYPE_ZABBIX_ACTIVE:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data) use ($db_item): bool {
								return in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
									|| (strncmp($data['key_'], 'mqtt.get', 8) !== 0 && strncmp($db_item['key_'], 'mqtt.get', 8) === 0);
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'delay')]
						]];

					default:
						return ['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
									|| ($db_item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($db_item['key_'], 'mqtt.get', 8) === 0);
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')]
						]];
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
					['if' => static function () use ($db_item): bool {
						return in_array($db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
					}, 'type' => API_ID],
					['else' => true, 'type' => API_EMPTY_ID]
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
						return ['type' => API_CALC_FORMULA, 'length' => DB::getFieldLength('items', 'params')];

					case ITEM_TYPE_SCRIPT:
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
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'delay')]
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
				return ['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED];
		}
	}
}
