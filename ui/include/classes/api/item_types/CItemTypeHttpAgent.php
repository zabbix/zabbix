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

class CItemTypeHttpAgent extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_HTTPAGENT;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
		'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype', 'username',
		'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'timeout',
		'delay', 'allow_traps', 'trapper_hosts'
	];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		$is_item_prototype = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		return [
			'url' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'url')],
			'query_fields' =>		['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'request_method' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]), 'default' => DB::getDefault('items', 'request_method')],
			'post_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]), 'default' => DB::getDefault('items', 'post_type')],
			'posts' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_RAW], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'posts')],
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_JSON], 'type' => API_JSON, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'macros_n' => ['{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.PORT}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}', '{ITEM.KEY}', '{ITEM.KEY.ORIG}'], 'length' => DB::getFieldLength('items', 'posts')],
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_XML], 'type' => API_XML, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'posts')]
			]],
			'headers' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'status_codes' =>		['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'status_codes')],
			'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
			'retrieve_mode' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'request_method', 'in' => HTTPCHECK_REQUEST_HEAD], 'type' => API_INT32, 'in' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS],
										['else' => true, 'type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])]
			]],
			'output_format' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'http_proxy')],
			'interfaceid' =>		self::getCreateFieldRule('interfaceid', $item),
			'authtype' =>			self::getCreateFieldRule('authtype', $item),
			'username' =>			self::getCreateFieldRule('username', $item),
			'password' =>			self::getCreateFieldRule('password', $item),
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_password')],
			'timeout' =>			self::getCreateFieldRule('timeout', $item),
			'delay' =>				self::getCreateFieldRule('delay', $item),
			'allow_traps' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]), 'default' => DB::getDefault('items', 'allow_traps')],
			'trapper_hosts' =>		self::getCreateFieldRule('trapper_hosts', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		$is_item_prototype = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		return [
			'url' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'url')],
			'query_fields' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'request_method' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD])],
			'post_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML])],
			'posts' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_RAW], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'posts')],
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_JSON], 'type' => API_JSON, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'macros_n' => ['{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.PORT}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}', '{ITEM.KEY}', '{ITEM.KEY.ORIG}'], 'length' => DB::getFieldLength('items', 'posts')],
										['if' => ['field' => 'post_type', 'in' => ZBX_POSTTYPE_XML], 'type' => API_XML, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'posts')]
			]],
			'headers' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'status_codes' =>		['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'status_codes')],
			'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
			'retrieve_mode' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'request_method', 'in' => HTTPCHECK_REQUEST_HEAD], 'type' => API_INT32, 'in' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS],
										['else' => true, 'type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])]
			]],
			'output_format' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'http_proxy')],
			'interfaceid' =>		self::getUpdateFieldRule('interfaceid', $db_item),
			'authtype' =>			self::getUpdateFieldRule('authtype', $db_item),
			'username' =>			self::getUpdateFieldRule('username', $db_item),
			'password' =>			self::getUpdateFieldRule('password', $db_item),
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_password')],
			'timeout' =>			self::getUpdateFieldRule('timeout', $db_item),
			'delay' =>				self::getUpdateFieldRule('delay', $db_item),
			'allow_traps' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])],
			'trapper_hosts' =>		self::getUpdateFieldRule('trapper_hosts', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'url' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'query_fields' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'request_method' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'post_type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'posts' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'headers' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'status_codes' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'follow_redirects' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'retrieve_mode' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'output_format' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'http_proxy' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'interfaceid' =>		self::getUpdateFieldRuleInherited('interfaceid', $db_item),
			'authtype' =>			self::getUpdateFieldRuleInherited('authtype', $db_item),
			'username' =>			self::getUpdateFieldRuleInherited('username', $db_item),
			'password' =>			self::getUpdateFieldRuleInherited('password', $db_item),
			'verify_peer' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'verify_host' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'ssl_cert_file' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'ssl_key_file' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'ssl_key_password' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'timeout' =>			self::getUpdateFieldRuleInherited('timeout', $db_item),
			'delay' =>				self::getUpdateFieldRuleInherited('delay', $db_item),
			'allow_traps' =>		['type' => API_INT32, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])],
			'trapper_hosts' =>		self::getUpdateFieldRuleInherited('trapper_hosts', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'url' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'query_fields' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'request_method' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'post_type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'posts' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'headers' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'status_codes' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'follow_redirects' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'retrieve_mode' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'output_format' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'http_proxy' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'interfaceid' =>		self::getUpdateFieldRuleDiscovered('interfaceid'),
			'authtype' =>			self::getUpdateFieldRuleDiscovered('authtype'),
			'username' =>			self::getUpdateFieldRuleDiscovered('username'),
			'password' =>			self::getUpdateFieldRuleDiscovered('password'),
			'verify_peer' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'verify_host' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'ssl_cert_file' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'ssl_key_file' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'ssl_key_password' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'timeout' =>			self::getUpdateFieldRuleDiscovered('timeout'),
			'delay' =>				self::getUpdateFieldRuleDiscovered('delay'),
			'allow_traps' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'trapper_hosts' =>		self::getUpdateFieldRuleDiscovered('trapper_hosts')
		];
	}
}
