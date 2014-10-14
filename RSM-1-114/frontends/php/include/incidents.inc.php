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
 * Get failed tests count.
 *
 * @param int 		$itemId
 * @param int 		$endTime
 * @param int 		$incidentStartTime
 * @param int 		$incidentEndTime
 *
 * @return int
 */
function getFailedTestsCount($itemId, $endTime, $incidentStartTime, $incidentEndTime = null) {
	$to = $incidentEndTime ? $incidentEndTime : $endTime;

	$getFailedTestsCount = DBfetch(DBselect(
		'SELECT COUNT(itemid) AS count'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock>='.$incidentStartTime.
			' AND h.clock<='.$to.
			' AND h.value=0'
	));

	return $getFailedTestsCount['count'];
}

/**
 * Get total tests count.
 *
 * @param int 		$itemId
 * @param int 		$startTime
 * @param int 		$endTime
 * @param int 		$incidentStartTime
 * @param int 		$incidentEndTime
 *
 * @return int
 */
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

/**
 * Return incident status.
 *
 * @param int 		$falsePositive
 * @param int 		$status
 *
 * @return string
 */
function getIncidentStatus($falsePositive, $status) {
	if ($falsePositive) {
		$incidentStatus = _('False positive');
	}
	else {
		if ($status == TRIGGER_VALUE_TRUE) {
			$incidentStatus = _('Active');
		}
		elseif ($status == TRIGGER_VALUE_FALSE) {
			$incidentStatus = _('Resolved');
		}
		else {
			$incidentStatus = _('Resolved (no data)');
		}
	}

	return $incidentStatus;
}
