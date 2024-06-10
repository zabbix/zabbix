<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\Gauge;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Form blocks.
	public const SHOW_DESCRIPTION = 1;
	public const SHOW_VALUE = 2;
	public const SHOW_NEEDLE = 3;
	public const SHOW_SCALE = 4;
	public const SHOW_VALUE_ARC = 5;

	// Description vertical position.
	public const DESC_V_POSITION_TOP = 0;
	public const DESC_V_POSITION_BOTTOM = 1;

	// Units position.
	public const UNITS_POSITION_BEFORE = 0;
	public const UNITS_POSITION_ABOVE = 1;
	public const UNITS_POSITION_AFTER = 2;
	public const UNITS_POSITION_BELOW = 3;

	public function getDefaultName(): string {
		return _('Gauge');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Actions' => _s('Actions'),
				'Download image' => _s('Download image')
			]
		];
	}
}
