<?php declare(strict_types = 0);
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


namespace Widgets\ItemCard\Includes;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldItemSections extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldItemSectionsView::class;
	public const DEFAULT_VALUE = [];

	public const SECTION_DESCRIPTION = 0;
	public const SECTION_ERROR_TEXT = 1;
	public const SECTION_INTERVAL_AND_STORAGE = 2;
	public const SECTION_LATEST_DATA = 3;
	public const SECTION_TYPE_OF_INFORMATION = 4;
	public const SECTION_TRIGGERS = 5;
	public const SECTION_HOST_INTERFACE = 6;
	public const SECTION_TYPE = 7;
	public const SECTION_HOST_INVENTORY = 8;
	public const SECTION_TAGS = 9;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_INTS32, 'flags' => API_NORMALIZE, 'uniq' => true, 'in' => implode(',', [
				self::SECTION_DESCRIPTION,
				self::SECTION_ERROR_TEXT,
				self::SECTION_INTERVAL_AND_STORAGE,
				self::SECTION_LATEST_DATA,
				self::SECTION_TYPE_OF_INFORMATION,
				self::SECTION_TRIGGERS,
				self::SECTION_HOST_INTERFACE,
				self::SECTION_TYPE,
				self::SECTION_HOST_INVENTORY,
				self::SECTION_TAGS
			])]);
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index,
				'value' => $value
			];
		}
	}
}
