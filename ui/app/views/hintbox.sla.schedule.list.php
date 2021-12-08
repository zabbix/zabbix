<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


$output = [];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if (array_key_exists('weekly_schedule', $data)) {
	$table = (new CTableInfo())->setHeader([_('Schedule'), _('Time period')]);

	foreach ($data['weekly_schedule'] as $weekday => $periods) {
		foreach ($periods as &$period) {
			$period = zbx_date2str(TIME_FORMAT, $period['period_from']).
					' - '.zbx_date2str(TIME_FORMAT, $period['period_to']);
		}
		unset($period);

		if (!$periods) {
			$periods = ['-'];
		}

		$table->addRow([
			getDayOfWeekCaption($weekday),
			implode(', ', $periods)
		]);
	}

	$output['data'] = $table->toString();
}

echo json_encode($output);
