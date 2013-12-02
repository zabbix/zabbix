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


function getCount($itemId, $itemType, $startTime, $endTime, $incidentStartTime = null, $incidentEndTime = null) {
	if (!$incidentStartTime) {
		$from = ($startTime >= $incidentStartTime) ? $startTime : $incidentStartTime;
	}
	else {
		$from = ($startTime >= $incidentStartTime) ? $incidentStartTime : $startTime;
	}

	if (!$incidentEndTime) {
		$to = $endTime;
	}
	else {
		$to = ($endTime >= $incidentEndTime) ? $incidentEndTime : $endTime;
	}

	$value = ($itemType == 'rdds') ? '!=2' : '=0';

	$getCount = DBfetch(DBselect(
		'SELECT COUNT(itemid) AS count'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock>='.zbx_dbstr($from).
			' AND h.clock<='.zbx_dbstr($to).
			' AND h.value'.$value
	));

	return $getCount['count'];
}
