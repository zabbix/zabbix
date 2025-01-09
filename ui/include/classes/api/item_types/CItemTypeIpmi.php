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

class CItemTypeIpmi extends CItemType {

	/**
	 * @inheritDoc
	 */
	const TYPE = ITEM_TYPE_IPMI;

	/**
	 * @inheritDoc
	 */
	const FIELD_NAMES = ['interfaceid', 'ipmi_sensor', 'delay'];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'interfaceid' =>	self::getCreateFieldRule('interfaceid', $item),
			'ipmi_sensor' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'key_', 'in' => 'ipmi.get'], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ipmi_sensor')],
									['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'ipmi_sensor')]
			]],
			'delay' =>			self::getCreateFieldRule('delay', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRule('interfaceid', $db_item),
			'ipmi_sensor' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'key_', 'in' => 'ipmi.get'], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ipmi_sensor')],
									['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'ipmi_sensor')]
			]],
			'delay' =>			self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleInherited('interfaceid', $db_item),
			'ipmi_sensor' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'delay' =>			self::getUpdateFieldRuleInherited('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'interfaceid' =>	self::getUpdateFieldRuleDiscovered('interfaceid'),
			'ipmi_sensor' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'delay' =>			self::getUpdateFieldRuleDiscovered('delay')
		];
	}
}
