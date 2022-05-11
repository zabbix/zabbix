<?php
/*
 ** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CMediatypeHelper {

	/**
	 * Message types.
	 */
	public const MSG_TYPE_PROBLEM = 0;
	public const MSG_TYPE_RECOVERY = 1;
	public const MSG_TYPE_UPDATE = 2;
	public const MSG_TYPE_SERVICE = 3;
	public const MSG_TYPE_SERVICE_RECOVERY = 4;
	public const MSG_TYPE_SERVICE_UPDATE = 5;
	public const MSG_TYPE_DISCOVERY = 6;
	public const MSG_TYPE_AUTOREG = 7;
	public const MSG_TYPE_INTERNAL = 8;
	public const MSG_TYPE_INTERNAL_RECOVERY = 9;

	/**
	 * Returns an array of message templates.
	 *
	 * @return array
	 */
	protected static function messageTemplates() {
		return [
			self::MSG_TYPE_PROBLEM => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_OPERATION,
				'name' => _('Problem'),
				'template' => [
					'subject' => 'Problem: {EVENT.NAME}',
					'html' => '<b>Problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br>'.
						'<b>Problem name:</b> {EVENT.NAME}<br><b>Host:</b> {HOST.NAME}<br>'.
						'<b>Severity:</b> {EVENT.SEVERITY}<br><b>Operational data:</b> {EVENT.OPDATA}<br>'.
						'<b>Original problem ID:</b> {EVENT.ID}<br>{TRIGGER.URL}',
					'sms' => "{EVENT.SEVERITY}: {EVENT.NAME}\nHost: {HOST.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' => "Problem started at {EVENT.TIME} on {EVENT.DATE}\n".
						"Problem name: {EVENT.NAME}\nHost: {HOST.NAME}\nSeverity: {EVENT.SEVERITY}\n".
						"Operational data: {EVENT.OPDATA}\nOriginal problem ID: {EVENT.ID}\n{TRIGGER.URL}"
				]
			],
			self::MSG_TYPE_RECOVERY => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Problem recovery'),
				'template' => [
					'subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
					'html' => '<b>Problem has been resolved</b> at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}<br>'.
						'<b>Problem name:</b> {EVENT.NAME}<br><b>Problem duration:</b> {EVENT.DURATION}<br><b>Host:</b> {HOST.NAME}<br>'.
						'<b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>{TRIGGER.URL}',
					'sms' => "Resolved in {EVENT.DURATION}: {EVENT.NAME}\nHost: {HOST.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' => "Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
						"Problem name: {EVENT.NAME}\nProblem duration: {EVENT.DURATION}\nHost: {HOST.NAME}\nSeverity: {EVENT.SEVERITY}\n".
						"Original problem ID: {EVENT.ID}\n{TRIGGER.URL}"
				]
			],
			self::MSG_TYPE_UPDATE => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_UPDATE_OPERATION,
				'name' => _('Problem update'),
				'template' => [
					'subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
					'html' =>
						'<b>{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem</b> at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br>'.
						'{EVENT.UPDATE.MESSAGE}<br><br><b>Current problem status:</b> {EVENT.STATUS}<br>'.
						'<b>Age:</b> {EVENT.AGE}<br><b>Acknowledged:</b> {EVENT.ACK.STATUS}.',
					'sms' => '{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem in {EVENT.AGE} at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}',
					'text' =>
						"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
						"{EVENT.UPDATE.MESSAGE}\n\n".
						"Current problem status is {EVENT.STATUS}, age is {EVENT.AGE}, acknowledged: {EVENT.ACK.STATUS}."
				]
			],
			self::MSG_TYPE_SERVICE => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_OPERATION,
				'name' => _('Service'),
				'template' => [
					'subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
					'html' =>
						'<b>Service problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br>'.
						'<b>Service problem name:</b> {EVENT.NAME}<br>'.
						'<b>Service:</b> {SERVICE.NAME}<br>'.
						'<b>Severity:</b> {EVENT.SEVERITY}<br>'.
						'<b>Original problem ID:</b> {EVENT.ID}<br>'.
						'<b>Service description:</b> {SERVICE.DESCRIPTION}<br><br>'.
						'{SERVICE.ROOTCAUSE}',
					'sms' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' =>
						"Service problem started at {EVENT.TIME} on {EVENT.DATE}\n".
						"Service problem name: {EVENT.NAME}\n".
						"Service: {SERVICE.NAME}\n".
						"Severity: {EVENT.SEVERITY}\n".
						"Original problem ID: {EVENT.ID}\n".
						"Service description: {SERVICE.DESCRIPTION}\n\n".
						"{SERVICE.ROOTCAUSE}"
				]
			],
			self::MSG_TYPE_SERVICE_RECOVERY => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Service recovery'),
				'template' => [
					'subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
					'html' =>
						'<b>Service "{SERVICE.NAME}" has been resolved</b> at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}<br>'.
						'<b>Problem name:</b> {EVENT.NAME}<br>'.
						'<b>Problem duration:</b> {EVENT.DURATION}<br>'.
						'<b>Severity:</b> {EVENT.SEVERITY}<br>'.
						'<b>Original problem ID:</b> {EVENT.ID}<br>'.
						'<b>Service description:</b> {SERVICE.DESCRIPTION}',
					'sms' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' =>
						"Service \"{SERVICE.NAME}\" has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
						"Problem name: {EVENT.NAME}\n".
						"Problem duration: {EVENT.DURATION}\n".
						"Severity: {EVENT.SEVERITY}\n".
						"Original problem ID: {EVENT.ID}\n".
						"Service description: {SERVICE.DESCRIPTION}"
				]
			],
			self::MSG_TYPE_SERVICE_UPDATE => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_UPDATE_OPERATION,
				'name' => _('Service update'),
				'template' => [
					'subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
					'html' =>
						'<b>Changed "{SERVICE.NAME}" service status</b> to {EVENT.UPDATE.SEVERITY} at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br>'.
						'<b>Current problem age</b> is {EVENT.AGE}.<br>'.
						'<b>Service description:</b> {SERVICE.DESCRIPTION}<br><br>'.
						'{SERVICE.ROOTCAUSE}',
					'sms' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' =>
						"Changed \"{SERVICE.NAME}\" service status to {EVENT.UPDATE.SEVERITY} at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
						"Current problem age is {EVENT.AGE}.\n".
						"Service description: {SERVICE.DESCRIPTION}\n\n".
						"{SERVICE.ROOTCAUSE}"
				]
			],
			self::MSG_TYPE_DISCOVERY => [
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'recovery' => ACTION_OPERATION,
				'name' => _('Discovery'),
				'template' => [
					'subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
					'html' => '<b>Discovery rule:</b> {DISCOVERY.RULE.NAME}<br><br>'.
						'<b>Device IP:</b> {DISCOVERY.DEVICE.IPADDRESS}<br>'.
						'<b>Device DNS:</b> {DISCOVERY.DEVICE.DNS}<br>'.
						'<b>Device status:</b> {DISCOVERY.DEVICE.STATUS}<br>'.
						'<b>Device uptime:</b> {DISCOVERY.DEVICE.UPTIME}<br><br>'.
						'<b>Device service name:</b> {DISCOVERY.SERVICE.NAME}<br>'.
						'<b>Device service port:</b> {DISCOVERY.SERVICE.PORT}<br>'.
						'<b>Device service status:</b> {DISCOVERY.SERVICE.STATUS}<br>'.
						'<b>Device service uptime:</b> {DISCOVERY.SERVICE.UPTIME}',
					'sms' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
					'text' => "Discovery rule: {DISCOVERY.RULE.NAME}\n\n".
						"Device IP: {DISCOVERY.DEVICE.IPADDRESS}\nDevice DNS: {DISCOVERY.DEVICE.DNS}\n".
						"Device status: {DISCOVERY.DEVICE.STATUS}\n".
						"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\n".
						"Device service name: {DISCOVERY.SERVICE.NAME}\n".
						"Device service port: {DISCOVERY.SERVICE.PORT}\n".
						"Device service status: {DISCOVERY.SERVICE.STATUS}\n".
						"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
				]
			],
			self::MSG_TYPE_AUTOREG => [
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'recovery' => ACTION_OPERATION,
				'name' => _('Autoregistration'),
				'template' => [
					'subject' => 'Autoregistration: {HOST.HOST}',
					'html' => '<b>Host name:</b> {HOST.HOST}<br><b>Host IP:</b> {HOST.IP}<br><b>Agent port:</b> {HOST.PORT}',
					'sms' => "Autoregistration: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}",
					'text' => "Host name: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}"
				]
			],
			self::MSG_TYPE_INTERNAL => [
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'recovery' => ACTION_OPERATION,
				'name' => _('Internal problem'),
				'template' => [
					'subject' => '',
					'html' => '',
					'sms' => '',
					'text' => ''
				]
			],
			self::MSG_TYPE_INTERNAL_RECOVERY => [
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Internal problem recovery'),
				'template' => [
					'subject' => '',
					'html' => '',
					'sms' => '',
					'text' => ''
				]
			]
		];
	}

	/**
	 * Returns all message templates.
	 *
	 * @return array
	 */
	public static function getAllMessageTemplates() {
		return self::messageTemplates();
	}

	/**
	 * Returns all message types.
	 *
	 * @return array
	 */
	public static function getAllMessageTypes() {
		return array_keys(self::messageTemplates());
	}

	/**
	 * Gets an array of event source and operation mode from the specified message type.
	 *
	 * @param int $message_type  Message type.
	 *
	 * @return array|bool
	 */
	public static function transformFromMessageType($message_type) {
		$message_templates = self::messageTemplates();

		return array_key_exists($message_type, $message_templates) ? $message_templates[$message_type] : false;
	}

	/**
	 * Gets message type form the specified event source and operation mode.
	 *
	 * @param int $eventsource  Event source.
	 * @param int $recovery     Operation mode.
	 *
	 * @return int|bool
	 */
	public static function transformToMessageType($eventsource, $recovery) {
		foreach (self::messageTemplates() as $message_type => $message_template) {
			if ($eventsource == $message_template['eventsource'] && $recovery == $message_template['recovery']) {
				return $message_type;
			}
		}

		return false;
	}

	/**
	 * Returns a message template array with message subject and body.
	 *
	 * @param int $media_type      Media type.
	 * @param int $message_type    Message type.
	 * @param int $message_format  Message format. Used by Email media type.
	 *
	 * @return array
	 */
	public static function getMessageTemplate($media_type, $message_type, $message_format = null) {
		$message_templates = self::messageTemplates();

		if ($media_type == MEDIA_TYPE_SMS) {
			return [
				'subject' => '',
				'message' => $message_templates[$message_type]['template']['sms']
			];
		}

		if ($media_type == MEDIA_TYPE_EMAIL && $message_format == SMTP_MESSAGE_FORMAT_HTML) {
			return [
				'subject' => $message_templates[$message_type]['template']['subject'],
				'message' => $message_templates[$message_type]['template']['html']
			];
		}

		return [
			'subject' => $message_templates[$message_type]['template']['subject'],
			'message' => $message_templates[$message_type]['template']['text']
		];
	}
}
