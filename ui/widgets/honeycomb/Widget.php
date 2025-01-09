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


namespace Widgets\Honeycomb;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Predefined colors for thresholds. Each next threshold takes the next sequential value from the palette.
	public const DEFAULT_COLOR_PALETTE = [
		'E65660', 'FCCB1D', '3BC97D', '2ED3B7', '19D0D7', '29C2FA', '58B0FE', '5D98FE', '859AFA', 'E580FA',
		'F773C7', 'FC5F7E', 'FC738E', 'FF6D2E', 'F48D48', 'F89C3A', 'FBB318', 'FECF62', '87CE40', 'A3E86D'
	];

	public function getDefaultName(): string {
		return _('Honeycomb');
	}

	public function getTranslationStrings(): array {
		return [
			'class.svghoneycomb.js' => [
				'No data' => _('No data')
			],
			'class.widget.js' => [
				'Actions' => _('Actions'),
				'Download image' => _('Download image')
			]
		];
	}
}
