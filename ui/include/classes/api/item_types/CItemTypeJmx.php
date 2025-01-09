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

class CItemTypeJmx extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_JMX;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'interfaceid' =>	self::getCreateFieldRule('interfaceid', $item),
			'jmx_endpoint' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint'), 'default' => ZBX_DEFAULT_JMX_ENDPOINT],
			'username' =>		self::getCreateFieldRule('username', $item),
			'password' =>		self::getCreateFieldRule('password', $item),
			'delay' =>			self::getCreateFieldRule('delay', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRule('interfaceid', $db_item),
			'jmx_endpoint' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint')],
			'username' =>		self::getUpdateFieldRule('username', $db_item),
			'password' =>		self::getUpdateFieldRule('password', $db_item),
			'delay' =>			self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleInherited('interfaceid', $db_item),
			'jmx_endpoint' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint')],
			'username' =>		self::getUpdateFieldRuleInherited('username', $db_item),
			'password' =>		self::getUpdateFieldRuleInherited('password', $db_item),
			'delay' =>			self::getUpdateFieldRuleInherited('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleDiscovered('interfaceid'),
			'jmx_endpoint' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'username' =>		self::getUpdateFieldRuleDiscovered('username'),
			'password' =>		self::getUpdateFieldRuleDiscovered('password'),
			'delay' =>			self::getUpdateFieldRuleDiscovered('delay')
		];
	}
}
