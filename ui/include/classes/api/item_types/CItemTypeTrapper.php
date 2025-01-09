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

class CItemTypeTrapper extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_TRAPPER;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['trapper_hosts'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'trapper_hosts' =>	self::getCreateFieldRule('trapper_hosts', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'trapper_hosts' =>	self::getUpdateFieldRule('trapper_hosts', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'trapper_hosts' =>	self::getUpdateFieldRuleInherited('trapper_hosts', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'trapper_hosts' =>	self::getUpdateFieldRuleDiscovered('trapper_hosts')
		];
	}
}
