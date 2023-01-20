<?php declare(strict_types = 1);
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

class CItemTypeDbMonitor extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_DB_MONITOR;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['username', 'password', 'params', 'delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'username' =>	self::getCreateFieldRule('username', $item),
			'password' =>	self::getCreateFieldRule('password', $item),
			'params' =>		self::getCreateFieldRule('params', $item),
			'delay' =>		self::getCreateFieldRule('delay', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'username' =>	self::getUpdateFieldRule('username', $db_item),
			'password' =>	self::getUpdateFieldRule('password', $db_item),
			'params' =>		self::getUpdateFieldRule('params', $db_item),
			'delay' =>		self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'username' =>	self::getUpdateFieldRuleInherited('username', $db_item),
			'password' =>	self::getUpdateFieldRuleInherited('password', $db_item),
			'params' =>		self::getUpdateFieldRuleInherited('params', $db_item),
			'delay' =>		self::getUpdateFieldRuleInherited('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'username' =>	self::getUpdateFieldRuleDiscovered('username'),
			'password' =>	self::getUpdateFieldRuleDiscovered('password'),
			'params' =>		self::getUpdateFieldRuleDiscovered('params'),
			'delay' =>		self::getUpdateFieldRuleDiscovered('delay')
		];
	}
}
