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


namespace Widgets\HostCard\Includes;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldHostSections extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldHostSectionsView::class;
	public const DEFAULT_VALUE = [];

	public const SECTION_HOST_GROUPS = 0;
	public const SECTION_DESCRIPTION = 1;
	public const SECTION_MONITORING = 2;
	public const SECTION_AVAILABILITY = 3;
	public const SECTION_MONITORED_BY = 4;
	public const SECTION_TEMPLATES = 5;
	public const SECTION_INVENTORY = 6;
	public const SECTION_TAGS = 7;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_INTS32, 'flags' => API_NORMALIZE, 'uniq' => true, 'in' => implode(',', [
				self::SECTION_HOST_GROUPS,
				self::SECTION_DESCRIPTION,
				self::SECTION_MONITORING,
				self::SECTION_AVAILABILITY,
				self::SECTION_MONITORED_BY,
				self::SECTION_TEMPLATES,
				self::SECTION_INVENTORY,
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
