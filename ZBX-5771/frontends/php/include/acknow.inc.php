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


function get_last_event_by_triggerid($triggerid) {
	$dbEvents = DBfetch(DBselect(
		'SELECT e.*'.
		' FROM events e'.
		' WHERE e.objectid='.$triggerid.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.value<>2'.
		' ORDER BY e.objectid DESC,e.object DESC,e.eventid DESC',
		1
	));

	return !empty($dbEvents) ? $dbEvents : false;
}

function get_acknowledges_by_eventid($eventid) {
	return DBselect('SELECT a.*,u.alias FROM acknowledges a LEFT JOIN users u ON u.userid=a.userid WHERE a.eventid='.$eventid);
}

function make_acktab_by_eventid($event) {
	$table = new CTableInfo(_('No acknowledges defined.'));
	$table->setHeader(array(_('Time'), _('User'), _('Comments')));

	if (!empty($event['acknowledges']) && is_array($event['acknowledges'])) {
		foreach ($event['acknowledges'] as $ack) {
			$table->addRow(array(
				zbx_date2str(_('d M Y H:i:s'), $ack['clock']),
				$ack['alias'],
				new CCol(zbx_nl2br($ack['message']), 'wraptext')
			));
		}
	}

	return $table;
}
