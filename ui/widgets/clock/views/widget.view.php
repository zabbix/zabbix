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


/**
 * Clock widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Clock\Widget;

const CLOCK_CLASSES = [
	Widget::SHOW_DATE => 'clock-date',
	Widget::SHOW_TIME => 'clock-time',
	Widget::SHOW_TIMEZONE => 'clock-time-zone'
];

$view = new CWidgetView($data);

if ($data['clock_data']['critical_error'] !== null) {
	$body = (new CTableInfo())->setNoDataMessage($data['clock_data']['critical_error']);
}
else {
	if ($data['clock_data']['type'] == Widget::TYPE_DIGITAL) {
		$clock_data = $data['clock_data'];

		$rows = [];

		if ($clock_data['is_enabled']) {
			foreach ($clock_data['show'] as $show) {
				$div = new CDiv();

				if (array_key_exists($show, CLOCK_CLASSES)) {
					$div->addClass(CLOCK_CLASSES[$show]);

					if ($show == Widget::SHOW_TIMEZONE && $clock_data['tzone_format'] == Widget::TIMEZONE_FULL) {
						$div->addItem([new CSpan(), new CSpan()]);
					}

					if ($data['styles'][$show]['bold']) {
						$div->addClass('bold');
					}

					if ($data['styles'][$show]['color'] !== '') {
						$div->addStyle(sprintf('color: #%1$s;', $data['styles'][$show]['color']));
					}
				}

				$rows[] = $div;
			}
		}
		else {
			$rows[] = (new CDiv())
				->addItem(_('No data'))
				->addClass('clock-disabled');
		}

		$body = (new CDiv($rows))->addClass('clock-digital');

		if ($clock_data['bg_color'] !== '') {
			$body->addStyle('background-color: #'.$clock_data['bg_color']);
		}
	}
	else {
		$body = (new CClock())->setEnabled($data['clock_data']['is_enabled']);
	}

	$view->setVar('clock_data', $data['clock_data']);
	$view->setVar('styles', $data['styles']);
	$view->setVar('classes', CLOCK_CLASSES);
}

$view
	->addItem($body)
	->show();
