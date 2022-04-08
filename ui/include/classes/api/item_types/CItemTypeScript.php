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

class CItemTypeScript extends CItemType {

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['parameters', 'params', 'timeout', 'delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array &$item): array {
		$is_item_prototype = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE;

		return [
			'interfaceid' => self::getCreateFieldRule('interfaceid'),
			'parameters' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
								'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
								'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
			]],
			'params' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')],
			'timeout' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')],
			'delay' =>		['type' => API_ITEM_DELAY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'delay')]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array &$item, array $db_item): array {
		return [
			'parameters' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
								'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
								'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
			]],
			'params' =>		self::getUpdateFieldRule('params', $db_item),
			'timeout' =>	self::getUpdateFieldRule('timeout', $db_item),
			'delay' =>		self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array &$item, array $db_item): array {
		return [
			'parameters' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'params' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'timeout' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'delay' =>		['type' => API_ITEM_DELAY, 'length' => DB::getFieldLength('items', 'delay')]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(array &$item, array $db_item): array {
		return [
			'parameters' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'params' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'timeout' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'delay' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		];
	}
}
