<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CRemedyService {

	const minTriggerSeverity = TRIGGER_SEVERITY_WARNING;

	public static $enabled = false;
	//protected $data;

	public static function init(array $data) {
		try {
			if (isset($data['eventTriggerSeverity'])
				&& $data['eventTriggerSeverity'] >= self::minTriggerSeverity) {

				// check if current user has Remedy Service media type set up
				self::$enabled = (bool) API::MediaType()->get(array(
					'userids' => CWebUser::$data['userid'],
					'filter' => array('type' => MEDIA_TYPE_REMEDY),
					'output' => array('mediatypeid'),
					'limit' => 1
				));

				return self::$enabled;
			}
		}
		catch (APIException $e) {
			error($e->getMessage());
		}
	}

	public static function mediaQuery($eventId = null) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (!self::$enabled || $eventId === null) {
			return false;
		}

		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_REMEDY_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		$ticket = $zabbixServer->mediaQuery(array($eventId), get_cookie('zbx_sessionid'));

		$zabbixServerError = $zabbixServer->getError();
		if ($zabbixServerError) {
			error($zabbixServerError);

			return false;
		}
		else {
			$ticket = zbx_toHash($ticket, 'eventid');

			// something went wrong getting that ticket
			if ($ticket[$eventId]['error']) {
				error($ticket[$eventId]['error']);

				return false;
			}
			// ticket exists. Create link to ticket and label "Update ticket"
			elseif ($ticket[$eventId]['externalid']) {
				return self::processRemedyTicketDetails($ticket[$eventId]);
			}
		}
	}

	public static function mediaAcknowledge(array $event = array()) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (!self::$enabled || !$event) {
			return false;
		}

		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_REMEDY_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		$ticket = $zabbixServer->mediaAcknowledge(array($event), get_cookie('zbx_sessionid'));

		$zabbixServerError = $zabbixServer->getError();
		if ($zabbixServerError) {
			error($zabbixServerError);

			return false;
		}
		else {
			$ticket = zbx_toHash($ticket, 'eventid');
			$eventId = $event['eventid'];

			if ($ticket[$eventId]['error']) {
				error($ticket[$eventId]['error']);

				return false;
			}
			// externalid for creating link to Remedy and check status if new, then show it as new
			elseif ($ticket[$eventId]['externalid']) {
				$messageSuccess = $ticket[$eventId]['new']
					? _s('Ticket "%1$s" has been created.', $ticket[$eventId]['externalid'])
					: _s('Ticket "%1$s" has been updated.', $ticket[$eventId]['externalid']);
				info($messageSuccess);

				return self::processRemedyTicketDetails($ticket[$eventId]);
			}
		}
	}

	protected static function processRemedyTicketDetails(array $ticketData) {
		$ticketId = $ticketData['externalid'];

		$ticketLink = new CLink($ticketId, REMEDY_SERVICE_WEB_URL.'"'.$ticketId.'"', null, null, true);
		$ticketLink->setTarget('_blank');

		return array(
			'ticketId' => $ticketId,
			'ticketLink' => $ticketLink,
			'created' => isset($ticketData['clock']) ? zbx_date2str(_('d M Y H:i:s'), $ticketData['clock']) : null,
			'status' => $ticketData['status'],
			'assignee' => isset($ticketData['assignee']) ? $ticketData['assignee'] : null,
		);
	}
}
