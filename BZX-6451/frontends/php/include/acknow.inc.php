<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


function get_last_event_by_triggerid($triggerId) {
	$dbEvents = DBfetch(DBselect(
		'SELECT e.*'.
		' FROM events e'.
		' WHERE e.objectid='.$triggerId.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
		' ORDER BY e.objectid DESC,e.object DESC,e.eventid DESC',
		1
	));

	return $dbEvents ? $dbEvents : false;
}

/**
 * Get acknowledgement table.
 *
 * @param array $event
 * @param array $event['acknowledges']
 * @param array $event['acknowledges']['clock']
 * @param array $event['acknowledges']['alias']
 * @param array $event['acknowledges']['message']
 *
 * @return CTableInfo
 */
function makeAckTab($event) {
	$acknowledgeTable = new CTableInfo(_('No acknowledges defined.'));
	$acknowledgeTable->setHeader(array(_('Time'), _('User'), _('Comments')));

	if (!empty($event['acknowledges']) && is_array($event['acknowledges'])) {
		foreach ($event['acknowledges'] as $acknowledge) {
			$acknowledgeTable->addRow(array(
				zbx_date2str(_('d M Y H:i:s'), $acknowledge['clock']),
				$acknowledge['alias'],
				new CCol(zbx_nl2br($acknowledge['message']), 'wraptext')
			));
		}
	}

	return $acknowledgeTable;
}
