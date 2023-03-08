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


namespace Widgets\DataOver\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$groupids = $this->fields_values['groupids'] ? getSubGroups($this->fields_values['groupids']) : null;
		$hostids = $this->fields_values['hostids'] ?: null;

		[$items, $hosts, $has_hidden_data] = getDataOverview($groupids, $hostids, $this->fields_values);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'groupids' => getSubGroups($this->fields_values['groupids']),
			'show_suppressed' => $this->fields_values['show_suppressed'],
			'style' => $this->fields_values['style'],
			'items' => $items,
			'hosts' => $hosts,
			'has_hidden_data' => $has_hidden_data,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
