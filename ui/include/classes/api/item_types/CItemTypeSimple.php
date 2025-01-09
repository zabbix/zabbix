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

class CItemTypeSimple extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_SIMPLE;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['interfaceid', 'username', 'password', 'timeout', 'delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'interfaceid' =>	self::getCreateFieldRule('interfaceid', $item),
			'username' =>		self::getCreateFieldRule('username', $item),
			'password' =>		self::getCreateFieldRule('password', $item),
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
			'username' =>		self::getUpdateFieldRule('username', $db_item),
			'password' =>		self::getUpdateFieldRule('password', $db_item),
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
			'username' =>		self::getUpdateFieldRuleInherited('username', $db_item),
			'password' =>		self::getUpdateFieldRuleInherited('password', $db_item),
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
			'username' =>		self::getUpdateFieldRuleDiscovered('username'),
			'password' =>		self::getUpdateFieldRuleDiscovered('password'),
			'timeout' =>		self::getUpdateFieldRuleDiscovered('timeout'),
			'delay' =>			self::getUpdateFieldRuleDiscovered('delay')
		];
	}
}
