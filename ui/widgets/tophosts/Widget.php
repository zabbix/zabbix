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


namespace Widgets\TopHosts;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public const DEFAULT_FILL = '#97AAB3';

	public const ORDER_TOP_N = 2;
	public const ORDER_BOTTOM_N = 3;

	public const TEXT_MAX_LENGTH = 20;

	// Predefined colors for thresholds and highlights. Each next threshold/highlight takes the next sequential value
	// from the palette.
	public const DEFAULT_COLOR_PALETTE = [
		'E65660', 'FCCB1D', '3BC97D', '2ED3B7', '19D0D7', '29C2FA', '58B0FE', '5D98FE', '859AFA', 'E580FA',
		'F773C7', 'FC5F7E', 'FC738E', 'FF6D2E', 'F48D48', 'F89C3A', 'FBB318', 'FECF62', '87CE40', 'A3E86D'
	];

	public function getDefaultName(): string {
		return _('Top hosts');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Empty value.' => _('Empty value.'),
				'Image loading error.' => _('Image loading error.')
			]
		];
	}
}
