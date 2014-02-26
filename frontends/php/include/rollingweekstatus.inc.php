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
	$problemEvent = DBfetch(DBselect(
		'SELECT MAX(e.eventid) as eventid'.
		' FROM events e'.
		' WHERE e.objectid='.$problemTrigger.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
			' AND e.value='.TRIGGER_VALUE_TRUE
	));

	return $problemEvent ? $problemEvent['eventid'] : null;
}
