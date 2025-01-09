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


namespace Widgets\HostNavigator;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public function getDefaultName(): string {
		return _('Host navigator');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'No data found' => _('No data found'),
				'Unexpected server error.' => _('Unexpected server error.')
			],
			'class.hostnavigator.js' => [
				'Uncategorized' => _('Uncategorized'),
				'%1$d of %1$d+ hosts are shown' => _('%1$d of %1$d+ hosts are shown'),
				'Host group' => _('Host group'),
				'Severity' => _('Severity')
			]
		];
	}
}
