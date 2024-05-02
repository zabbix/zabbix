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


namespace Widgets\HostNavigator;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public function getDefaultName(): string {
		return _('Host navigator');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Unexpected server error.' => _('Unexpected server error.')
			],
			'class.hostnavigator.js' => [
				'Uncategorized' => _('Uncategorized'),
				'%1$d of %1$d+ hosts are shown' => _('%1$d of %1$d+ hosts are shown'),
				'No data found' => _('No data found'),
				'Host group' => _('Host group'),
				'Severity' => _('Severity')
			]
		];
	}
}
