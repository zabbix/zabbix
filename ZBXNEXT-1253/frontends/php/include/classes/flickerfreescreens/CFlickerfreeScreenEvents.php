<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFlickerfreeScreenEvents extends CFlickerfreeScreenItem {

	public function __construct(array $options = array()) {
		parent::__construct($options);
	}

	public function get() {
		$options = array(
			'monitored' => true,
			'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
			'limit' => $this->screenitem['elements']
		);

		$showUnknown = CProfile::get('web.events.filter.showUnknown', 0);
		if ($showUnknown) {
			$options['value'] = array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE);
		}

		$output = new CTableInfo(_('No events defined.'));
		$output->setHeader(array(
			_('Time'),
			is_show_all_nodes() ? _('Node') : null,
			_('Host'),
			_('Description'),
			_('Value'),
			_('Severity')
		));

		$events = getLastEvents($options);
		foreach ($events as $event) {
			$trigger = $event['trigger'];
			$host = $event['host'];

			$statusSpan = new CSpan(trigger_value2str($event['value']));

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle($statusSpan, $event['value'], $event['clock'], $event['acknowledged']);

			$output->addRow(array(
				zbx_date2str(_('d M Y H:i:s'), $event['clock']),
				get_node_name_by_elid($event['objectid']),
				$host['host'],
				new CLink(
					$trigger['description'],
					'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
				),
				$statusSpan,
				getSeverityCell($trigger['priority'])
			));
		}

		$output = array($output);
		if ($this->mode == SCREEN_MODE_EDIT) {
			array_push($output, new CLink(_('Change'), $this->action));
		}

		return $output;
	}
}
