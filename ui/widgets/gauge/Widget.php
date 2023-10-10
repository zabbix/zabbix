<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
