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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * Get last eventid from the events.
 *
 * @param int $problemTrigger
 *
 * @return int
 */
function getLastEvent($problemTrigger) {
	$result = null;

	$lastProblemEvent = DBfetch(DBselect(
		'SELECT e.eventid,e.clock,e.false_positive'.
		' FROM events e'.
		' WHERE e.objectid='.$problemTrigger.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.value='.TRIGGER_VALUE_TRUE.
		' ORDER BY e.clock DESC',
		1
	));

	if ($lastProblemEvent && $lastProblemEvent['false_positive'] == INCIDENT_FLAG_NORMAL) {
		$result = getPreEvents($problemTrigger, $lastProblemEvent['clock'], $lastProblemEvent['eventid']);
	}

	return $result;
}

/**
 * Get previos open event
 *
 * @param int $objectid
 * @param int $clock
 * @param int $eventid
 *
 * @return int
 */
function getPreEvents($objectid, $clock, $eventid) {
	$result = $eventid;

	$beforeEvent = DBfetch(DBselect(
		'SELECT e.eventid,e.clock,e.value'.
		' FROM events e'.
		' WHERE e.objectid='.$objectid.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock<='.$clock.
			' AND e.eventid!='.$eventid.
		' ORDER BY e.clock DESC',
		1
	));

	if ($beforeEvent && $beforeEvent['value'] == TRIGGER_VALUE_TRUE) {
		$result = getPreEvents($objectid, $beforeEvent['clock'], $beforeEvent['eventid']);
	}

	return $result;
}


/**
 * Convert SLA service name.
 *
 * @param string $name
 *
 * @return int
 */
function convertSlaServiceName($name) {
	$services = array(
		'dns' => RSM_DNS,
		'dnssec' => RSM_DNSSEC,
		'rdds' => RSM_RDDS,
		'epp' => RSM_EPP
	);

	return $services[$name];
}
