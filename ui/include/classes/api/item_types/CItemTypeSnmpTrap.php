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

class CItemTypeSnmpTrap extends CItemType {

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(string $class_name): array {
		return [
			'interfaceid' =>	['type' => API_ID]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(string $class_name, array $db_item): array {
		return self::getCreateValidationRules($class_name);
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(string $class_name, array $db_item): array {
		return self::getCreateValidationRules($class_name);
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(string $class_name, array $db_item): array {
		return [
			'interfaceid' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		];
	}
}
