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

	/**
	 * Minimum required event trigger severity to enable Remedy service.
	 *
	 * @constant
	 */
	const minTriggerSeverity = TRIGGER_SEVERITY_WARNING;

	/**
	 * Remedy service status.
	 *
	 * @var bool
	 */
	public static $enabled = false;

	/**
	 * Initialize the Remedy service. First check if event trigger severity corresponds to minimum required severity to
	 * create, update or request a ticket. Then check if current user has Remedy service as media type.
	 * If everything so far is ok, in next step check if Zabbix server is online. In case it's not possible to connect
	 * to Zabbix server, it's not possibe to start the Remedy Service and return false with error message from
	 * Zabbix server.
	 *
	 * @param string $event['triggerSeverity']		current event trigger severity
	 *
	 * @return bool
	 */
	public static function init(array $event) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (isset($event['triggerSeverity']) && $event['triggerSeverity'] >= self::minTriggerSeverity) {
			self::$enabled = (bool) API::MediaType()->get(array(
				'userids' => CWebUser::$data['userid'],
				'filter' => array('type' => MEDIA_TYPE_REMEDY),
				'output' => array('mediatypeid'),
				'limit' => 1
			));

			// if trigger severity is valid, media type is set up as Remedy Service
			// then next check if server is online to do futher requests.
			if (self::$enabled) {
				$zabbixServer = new CZabbixServer(
					$ZBX_SERVER,
					$ZBX_SERVER_PORT,
					ZBX_SOCKET_REMEDY_TIMEOUT,
					ZBX_SOCKET_BYTES_LIMIT
				);

				self::$enabled = $zabbixServer->isRunning();

				if (!self::$enabled) {
					show_error_message(_('Cannot start Remedy Service'));

					error($zabbixServer->getError());
				}
			}
		}

		return self::$enabled;
	}

	/**
	 * Query Zabbix server about an existing event.
	 * Returns false if Remedy service is not enabled, no event data was passed, error connecting to Zabbix server or
	 * something went wrong with actual ticket.
	 * If query was success, receive array of raw ticket data from Zabbix server and then process each field.
	 * Returns array of processed ticket data (link to ticket, correct time format etc).
	 *
	 * @global string $ZBX_SERVER
	 * @global string $ZBX_SERVER_PORT
	 *
	 * @param int    $eventId
	 *
	 * @return bool|array
	 */
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

			self::$enabled = false;

			return self::$enabled;
		}
		else {
			$ticket = zbx_toHash($ticket, 'eventid');

			if ($ticket[$eventId]['error']) {
				error($ticket[$eventId]['error']);

				self::$enabled = false;

				return self::$enabled;
			}
			elseif ($ticket[$eventId]['externalid']) {
				return self::getDetails($ticket[$eventId]);
			}
		}
	}

	/**
	 * Send event data to Remedy service to create or update a ticket.
	 * Returns false if Remedy service is not enabled, no event data was passed, error connecting to Zabbix server or
	 * something went wrong with actual ticket.
	 * If operation was success, receive array of raw ticket data from Zabbix server and then process each field.
	 * Returns array of processed ticket data (link to ticket, correct time format etc).
	 *
	 * @global string $ZBX_SERVER
	 * @global string $ZBX_SERVER_PORT
	 *
	 * @param string $event['eventid']		an existing event ID
	 * @param string $event['message']		user message when acknowledging event
	 * @param string $event['subject']		trigger status 'OK' or 'PROBLEM'
	 *
	 * @return bool|array
	 */
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

		$tickets = $zabbixServer->mediaAcknowledge(array($event), get_cookie('zbx_sessionid'));

		$zabbixServerError = $zabbixServer->getError();
		if ($zabbixServerError) {
			error($zabbixServerError);

			self::$enabled = false;

			return self::$enabled;
		}
		else {
			$tickets = zbx_toHash($tickets, 'eventid');
			$eventId = $event['eventid'];

			if ($tickets[$eventId]['error']) {
				error($tickets[$eventId]['error']);

				self::$enabled = false;

				return self::$enabled;
			}
			elseif ($tickets[$eventId]['externalid']) {
				$messageSuccess = $tickets[$eventId]['new']
					? _s('Ticket "%1$s" has been created.', $tickets[$eventId]['externalid'])
					: _s('Ticket "%1$s" has been updated.', $tickets[$eventId]['externalid']);
				info($messageSuccess);

				return self::getDetails($tickets[$eventId]);
			}
		}
	}

	/**
	 * Creates Remedy ticket link and converts clock to readable time format and returns array of ticket data.
	 *
	 * @param array $data		Remedy ticket data
	 *
	 * @return array
	 */
	protected static function getDetails(array $data) {
		$ticketId = $data['externalid'];

		$link = new CLink($ticketId, REMEDY_SERVICE_WEB_URL.'"'.$ticketId.'"', null, null, true);
		$link->setTarget('_blank');

		return array(
			'ticketId' => $ticketId,
			'link' => $link,
			'created' => zbx_date2str(_('d M Y H:i:s'), $data['clock']),
			'status' => $data['status'],
			'assignee' => $data['assignee']
		);
	}
}
