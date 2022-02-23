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
	public static function getCreateValidationRules(string $class_name): array {
		return [
			'parameters' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
								'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
								'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
			]],
			'params' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')],
			'timeout' =>	['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | $class_name === 'CItemPrototype' ? API_ALLOW_LLD_MACRO : 0, 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')],
			'delay' =>		['type' => API_ITEM_DELAY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'delay')]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(string $class_name, array $db_item): array {
		$is_item_prototype = $class_name === 'CItemPrototype';

		return [
			'parameters' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
								'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
								'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
			]],
			'params' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => static function (array $data) use ($db_item): bool {
									return $data['type'] != $db_item['type'];
								}, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')],
								['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')]
			]],
			'timeout' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => static function (array $data) use ($db_item): bool {
									return $data['type'] != $db_item['type'];
								}, 'type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | $is_item_prototype ? API_ALLOW_LLD_MACRO : 0, 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')],
								['else' => true, 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | $is_item_prototype ? API_ALLOW_LLD_MACRO : 0, 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')]
			]],
			'delay' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => static function (array $data) use ($db_item): bool {
									return in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
										|| ($db_item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($db_item['key_'], 'mqtt.get', 8) === 0);
								}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'delay')],
								['else' => true, 'type' => API_ITEM_DELAY, 'length' => DB::getFieldLength('items', 'delay')]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(string $class_name, array $db_item): array {
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
	public static function getUpdateValidationRulesDiscovered(string $class_name, array $db_item): array {
		return [
			'parameters' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'params' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'timeout' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'delay' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		];
	}
}
