<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

if ($data['clock_data']['critical_error'] !== null) {
	$item = (new CTableInfo())->setNoDataMessage($data['clock_data']['critical_error']);

	$output = [
		'name' => $data['name'],
		'body' => $item->toString()
	];
}
else {
	if ($data['clock_data']['type'] == WIDGET_CLOCK_TYPE_DIGITAL) {
		$rows = [];
		$clock = $data['clock_data'];
		$show = $clock['show'];
		$styles = $data['styles'];

		if ($clock['is_enabled']) {
			foreach ($show as $field) {
				$div = new CDiv();

				switch ($field) {
					case WIDGET_CLOCK_SHOW_DATE:
						$div->addClass('clock-date');
						if ($clock['date'] !== null) {
							$div->addItem($clock['date']);
						}
						$style_group = 'date';
						break;
					case WIDGET_CLOCK_SHOW_TIME:
						$div->addClass('clock-time');
						$div->addItem('00:00:00');
						$style_group = 'time';
						break;
					case WIDGET_CLOCK_SHOW_TIMEZONE:
						$div->addClass('clock-time-zone');
						if ($clock['time_zone'] !== null && $clock['time_zone'] !== TIMEZONE_DEFAULT_LOCAL) {
							$div->addItem($clock['time_zone']);
						}
						$style_group = 'timezone';
						break;
				}

				if (array_key_exists($style_group, $styles)) {
					$div = addTextFormatting($div, $styles[$style_group]);
				}

				$rows[] = $div;
			}
		}
		else {
			$rows[] = (new CDiv())->addItem('00:00:00')->addClass('clock-disabled');
		}

		$content = (new CDiv($rows))->addClass('digital-clock');

		if ($clock['bg_color'] !== '') {
			$content->addStyle('background-color: #'.$clock['bg_color']);
		}

		$body = $content->toString();
	}
	else {
		$body = (new CClock())
			->setEnabled($data['clock_data']['is_enabled'])
			->toString();
	}
	$output = [
		'name' => $data['name'],
		'body' => $body,
		'clock_data' => $data['clock_data']
	];
}

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

function addTextFormatting(CDiv $div, array $styles): CDiv {
	$div->addStyle(sprintf('--widget-clock-font: %1$s;', number_format($styles['size'] / 100, 2)));

	if ($styles['bold']) {
		$div->addClass('bold');
	}

	if ($styles['color'] !== '') {
		$div->addStyle(sprintf('color: #%1$s;', $styles['color']));
	}

	return $div;
}
