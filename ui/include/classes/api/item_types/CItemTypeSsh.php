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

class CItemTypeSsh extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_SSH;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params',
		'timeout', 'delay'
	];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'interfaceid' =>	self::getCreateFieldRule('interfaceid', $item),
			'authtype' =>		self::getCreateFieldRule('authtype', $item),
			'username' =>		self::getCreateFieldRule('username', $item),
			'publickey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'publickey')]
			]],
			'privatekey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'privatekey')]
			]],
			'password' =>		self::getCreateFieldRule('password', $item),
			'params' =>			self::getCreateFieldRule('params', $item),
			'timeout' =>		self::getCreateFieldRule('timeout', $item),
			'delay' =>			self::getCreateFieldRule('delay', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRule('interfaceid', $db_item),
			'authtype' =>		self::getUpdateFieldRule('authtype', $db_item),
			'username' =>		self::getUpdateFieldRule('username', $db_item),
			'publickey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'publickey')]
			]],
			'privatekey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'privatekey')]
			]],
			'password' =>		self::getUpdateFieldRule('password', $db_item),
			'params' =>			self::getUpdateFieldRule('params', $db_item),
			'timeout' =>		self::getUpdateFieldRule('timeout', $db_item),
			'delay' =>			self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleInherited('interfaceid', $db_item),
			'authtype' =>		self::getUpdateFieldRuleInherited('authtype', $db_item),
			'username' =>		self::getUpdateFieldRuleInherited('username', $db_item),
			'publickey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'publickey')]
			]],
			'privatekey' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'privatekey')]
			]],
			'password' =>		self::getUpdateFieldRuleInherited('password', $db_item),
			'params' =>			self::getUpdateFieldRuleInherited('params', $db_item),
			'timeout' =>		self::getUpdateFieldRuleInherited('timeout', $db_item),
			'delay' =>			self::getUpdateFieldRuleInherited('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleDiscovered('interfaceid'),
			'authtype' =>		self::getUpdateFieldRuleDiscovered('authtype'),
			'username' =>		self::getUpdateFieldRuleDiscovered('username'),
			'publickey' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'privatekey' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'password' =>		self::getUpdateFieldRuleDiscovered('password'),
			'params' =>			self::getUpdateFieldRuleDiscovered('params'),
			'timeout' =>		self::getUpdateFieldRuleDiscovered('timeout'),
			'delay' =>			self::getUpdateFieldRuleDiscovered('delay')
		];
	}
}
