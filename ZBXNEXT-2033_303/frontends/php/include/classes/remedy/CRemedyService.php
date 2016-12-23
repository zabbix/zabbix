<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * URL to Remedy Server.
	 *
	 * @var string
	 */
	protected static $webFormUrl;

	/**
	 * Media severity.
	 *
	 * @var string
	 */
	public static $severity;

	/**
	 * Initialize the Remedy service. First check if event trigger severity corresponds to minimum required severity to
	 * create, update or request a ticket. Then check if current user has Remedy service as media type.
	 * If everything so far is ok, in next step check if Zabbix server is online. In case it's not possible to connect
	 * to Zabbix server, it's not possibe to start the Remedy Service and return false with error message from
	 * Zabbix server.
	 *
	 * @param array  $event							An array of event data.
	 * @param string $event['triggerSeverity']		Current event trigger severity.
	 *
	 * @return bool
	 */
	public static function init(array $event) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (array_key_exists('triggerSeverity', $event) && $event['triggerSeverity'] >= self::minTriggerSeverity) {
			$mediatype = API::MediaType()->get([
				'output' => ['mediatypeid', 'smtp_server'],
				'selectMedia' => ['mediaid', 'userid', 'active', 'severity'],
				'userids' => [CWebUser::$data['userid']],
				'filter' => [
					'type' => MEDIA_TYPE_REMEDY,
					'status' => MEDIA_TYPE_STATUS_ACTIVE
				],
				'limit' => 1
			]);

			if (!$mediatype) {
				return false;
			}

			// Since limit is 1, get only one media type.
			$mediatype = reset($mediatype);

			// Check if there are any medias at all.
			if (!$mediatype['media']) {
				return false;
			}

			// Get first enabled media for this user.
			$media_active = false;
			foreach ($mediatype['media'] as $media) {
				if ($media['userid'] == CWebUser::$data['userid'] && $media['active'] == MEDIA_TYPE_STATUS_ACTIVE) {
					$media_active = true;
					self::$severity = $media['severity'];
					break;
				}
			}

			// at least one media should be active
			if (!$media_active) {
				return false;
			}

			/*
			 * If trigger severity is valid, media type is set up as Remedy Service and is enabled, then next check if
			 * Remedy Service URL is valid.
			 */
			$server = parse_url($mediatype['smtp_server']);

			// check if Remedy Servcie URL is valid
			if (isset($server['scheme'])) {
				self::$webFormUrl = $server['scheme'].'://';

				if (isset($server['user']) && $server['user'] && isset($server['pass']) && $server['pass']) {
					self::$webFormUrl .= $server['user'].':'.$server['pass'].'@';
				}

				self::$webFormUrl .= $server['host'];

				if (isset($server['port']) && $server['port']) {
					self::$webFormUrl .= ':'.$server['port'];
				}

				// Link to web form in Remedy Service.
				self::$webFormUrl .= '/arsys/forms/onbmc-s/SHR%3ALandingConsole/Default+Administrator+View/'.
					'?mode=search&F304255500=HPD%3AHelp+Desk&F1000000076=FormOpenNoAppList'.
					'&F303647600=SearchTicketWithQual&F304255610=\'1000000161\'%3D';
			}

			// Check if server is online to do further requests to it.
			$zabbixServer = new CZabbixServer(
				$ZBX_SERVER,
				$ZBX_SERVER_PORT,
				ZBX_SOCKET_REMEDY_TIMEOUT,
				ZBX_SOCKET_BYTES_LIMIT
			);

			self::$enabled = $zabbixServer->isRunning();

			if (!self::$enabled) {
				if (!CSession::keyExists('messageError')) {
					CSession::setValue('messageError', _('Cannot start Remedy Service'));
				}

				if (!CSession::keyExists('messages')) {
					CSession::setValue('messages', [['type' => 'error', 'message' => $zabbixServer->getError()]]);
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
	 * @param int    $eventid
	 *
	 * @return bool|array
	 */
	public static function mediaQuery($eventid = null) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (!self::$enabled || $eventid === null) {
			return false;
		}

		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_REMEDY_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		$ticket = $zabbixServer->mediaQuery([$eventid], get_cookie('zbx_sessionid'));

		$zabbixServerError = $zabbixServer->getError();
		if ($zabbixServerError) {
			CSession::setValue('messages', [['type' => 'error', 'message' => $zabbixServerError]]);

			self::$enabled = false;

			return self::$enabled;
		}
		else {
			$ticket = zbx_toHash($ticket, 'eventid');

			if ($ticket[$eventid]['error']) {
				CSession::setValue('messages', [['type' => 'error', 'message' => $ticket[$eventid]['error']]]);

				self::$enabled = false;

				return self::$enabled;
			}
			elseif ($ticket[$eventid]['externalid']) {
				return self::getDetails($ticket[$eventid]);
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
	 * @param string $event['eventid']		An existing event ID.
	 * @param string $event['message']		User message when acknowledging event.
	 * @param string $event['subject']		Trigger status 'OK' or 'PROBLEM'
	 *
	 * @return bool|array
	 */
	public static function mediaAcknowledge(array $event = []) {
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

		$tickets = $zabbixServer->mediaAcknowledge([$event], get_cookie('zbx_sessionid'));

		$zabbixServerError = $zabbixServer->getError();

		if ($zabbixServerError) {
			CSession::setValue('messages', [['type' => 'error', 'message' => $zabbixServerError]]);

			self::$enabled = false;

			return self::$enabled;
		}
		else {
			$tickets = zbx_toHash($tickets, 'eventid');
			$eventid = $event['eventid'];

			if ($tickets[$eventid]['error']) {
				CSession::setValue('messages', [['type' => 'error', 'message' => $tickets[$eventid]['error']]]);

				self::$enabled = false;

				return self::$enabled;
			}
			elseif ($tickets[$eventid]['externalid']) {
				$messageSuccess = $tickets[$eventid]['new']
					? _s('Ticket "%1$s" has been created.', $tickets[$eventid]['externalid'])
					: _s('Ticket "%1$s" has been updated.', $tickets[$eventid]['externalid']);

				CSession::setValue('messages', [['type' => 'info', 'message' => $messageSuccess]]);

				return self::getDetails($tickets[$eventid]);
			}
		}
	}

	/**
	 * Creates Remedy ticket link and converts clock to readable time format and returns array of ticket data.
	 *
	 * @param array $data		Remedy ticket data.
	 *
	 * @return array
	 */
	protected static function getDetails(array $data) {
		$ticketid = $data['externalid'];

		$link = new CLink($ticketid, self::$webFormUrl.'"'.$ticketid.'"', null, null, true);
		$link->setTarget('_blank');

		return [
			'ticketId' => $ticketid,
			'link' => $link,
			'created' => zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']),
			'status' => $data['status'],
			'assignee' => $data['assignee']
		];
	}
}
