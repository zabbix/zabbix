<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\SvgGraph;

use Widgets\SvgGraph\Includes\WidgetForm;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public const LEGEND_ON = 1;
	public const LEGEND_STATISTIC_ON = 1;
	public const LEGEND_AGGREGATION_ON = 1;

	public const LEGEND_LINES_MODE_FIXED = 0;
	public const LEGEND_LINES_MODE_VARIABLE = 1;

	public const LEGEND_LINES_MIN = 1;
	public const LEGEND_LINES_MAX = 10;

	public const LEGEND_COLUMNS_MIN = 1;
	public const LEGEND_COLUMNS_MAX = 4;

	public function getDefaultName(): string {
		return _('Graph');
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
