<?php declare(strict_types = 0);
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


namespace Widgets\Map;

use Zabbix\Core\CWidget;

use Zabbix\Widgets\Fields\CWidgetFieldReference;

class Widget extends CWidget {

	public const SOURCETYPE_MAP = 1;
	public const SOURCETYPE_FILTER = 2;

	public function hasPadding(array $fields_values, int $view_mode): bool {
		return true;
	}

	public function getDefaults(): array {
		return parent::getDefaults() + [
			'reference_field' => CWidgetFieldReference::FIELD_NAME,
			'foreign_reference_fields' => ['filter_widget_reference']
		];
	}
}
