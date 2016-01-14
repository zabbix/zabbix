<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CScreenEvents extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$options = [
			'monitored' => true,
			'value' => [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE],
			'triggerLimit' => $this->screenitem['elements'],
			'eventLimit' => $this->screenitem['elements']
		];

		$table = (new CTableInfo())->setHeader([_('Time'), _('Host'), _('Description'), _('Value'), _('Severity')]);

		$events = getLastEvents($options);

		$config = select_config();

		foreach ($events as $event) {
			$trigger = $event['trigger'];
			$host = $event['host'];

			$statusSpan = new CSpan(trigger_value2str($event['value']));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle($statusSpan, $event['value'], $event['clock'], $event['acknowledged']);

			$table->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				$host['name'],
				new CLink(
					$trigger['description'],
					'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
				),
				$statusSpan,
				getSeverityCell($trigger['priority'], $config)
			]);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput((new CUiWidget(uniqid(), [$table, $footer]))->setHeader(_('History of events')));
	}
}
