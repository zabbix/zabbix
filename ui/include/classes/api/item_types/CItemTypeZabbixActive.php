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

class CItemTypeZabbixActive extends CItemType {

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}] + self::getCreateFieldRule('delay', $item),
							['else' => true, 'type' => API_ITEM_DELAY, 'in' => DB::getDefault('items', 'delay')]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		$is_item_prototype = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		return [
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data) use ($db_item): bool {
								return in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
									|| (strncmp($data['key_'], 'mqtt.get', 8) !== 0 && strncmp($db_item['key_'], 'mqtt.get', 8) === 0);
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_ITEM_DELAY, 'in' => DB::getDefault('items', 'delay')]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}] + self::getUpdateFieldRuleInherited('delay', $db_item),
							['else' => true, 'type' => API_ITEM_DELAY, 'in' => DB::getDefault('items', 'delay')]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'delay' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		];
	}
}
