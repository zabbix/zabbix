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
	public static function getCreateValidationRules(array &$item): array {
		return [
			'interfaceid' =>	['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])], 'type' => API_ID],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'delay')],
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) === 0;
							}, 'type' => API_ITEM_DELAY, 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array &$item, array $db_item): array {
		return [
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function (array $data): bool {
								return strncmp($data['key_'], 'mqtt.get', 8) === 0;
							}, 'type' => API_UNEXPECTED],
							['if' => static function () use ($db_item): bool {
								return in_array($db_item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT]);
							}, 'type' => API_ITEM_DELAY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_ITEM_DELAY, 'length' => DB::getFieldLength('items', 'delay')]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array &$item, array $db_item): array {
		return [
			'delay' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => static function () use ($db_item): bool {
								return strncmp($db_item['key_'], 'mqtt.get', 8) !== 0;
							}, 'type' => API_ITEM_DELAY, 'length' => DB::getFieldLength('items', 'delay')],
							['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(array &$item, array $db_item): array {
		return [
			'delay' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		];
	}
}
