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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Clock widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Clock\Widget;

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

				switch ($show) {
					case Widget::SHOW_DATE:
						$div->addClass('clock-date');
						$styles = $data['styles']['date'];
						break;

					case Widget::SHOW_TIME:
						$div->addClass('clock-time');
						$styles = $data['styles']['time'];
						break;

					case Widget::SHOW_TIMEZONE:
						$div->addClass('clock-time-zone');
						$styles = $data['styles']['timezone'];
						break;

					default:
						$styles = null;
				}

				if ($styles !== null) {
					$div->addStyle(sprintf('--widget-clock-font: %1$s;', number_format($styles['size'] / 100, 2)));

					if ($styles['bold']) {
						$div->addClass('bold');
					}

					if ($styles['color'] !== '') {
						$div->addStyle(sprintf('color: #%1$s;', $styles['color']));
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
}

$view
	->addItem($body)
	->show();
