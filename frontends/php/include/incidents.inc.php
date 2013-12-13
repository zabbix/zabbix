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


function getFailedTestsCount($itemId, $itemType, $startTime, $endTime, $incidentStartTime, $incidentEndTime = null) {
	$to = $incidentEndTime ? $incidentEndTime : $endTime;
	$value = ($itemType == 'rdds') ? '!=2' : '=0';

	$getFailedTestsCount = DBfetch(DBselect(
		'SELECT COUNT(itemid) AS count'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock>='.$incidentStartTime.
			' AND h.clock<='.$to.
			' AND h.value'.$value
	));

	return $getFailedTestsCount['count'];
}

function getTotalTestsCount($itemId, $startTime, $endTime, $incidentStartTime = null, $incidentEndTime = null) {
	$from = $incidentStartTime ? $incidentStartTime : $startTime;
	$to = $incidentEndTime ? $incidentEndTime : $endTime;

	$getTotalTestsCount = DBfetch(DBselect(
		'SELECT COUNT(itemid) AS count'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock>='.$from.
			' AND h.clock<='.$to
	));

	return $getTotalTestsCount['count'];
}
