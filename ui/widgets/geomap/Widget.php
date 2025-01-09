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


namespace Widgets\Geomap;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public function getDefaultName(): string {
		return _('Geomap');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Actions' => _('Actions'),
				'Set this view as default' => _('Set this view as default'),
				'Reset to initial view' => _('Reset to initial view'),
				'Host' => _('Host'),
				'Navigate to default view' => _('Navigate to default view'),
				'Navigate to initial view' => _('Navigate to initial view')
			]
		];
	}
}
