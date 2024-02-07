<?php declare(strict_types = 1);
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
