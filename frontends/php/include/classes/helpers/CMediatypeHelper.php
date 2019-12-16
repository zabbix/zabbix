<?php
/*
 ** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	const MSG_TYPE_PROBLEM = 0;
	const MSG_TYPE_RECOVERY = 1;
	const MSG_TYPE_UPDATE = 2;
	const MSG_TYPE_DISCOVERY = 3;
	const MSG_TYPE_AUTOREG = 4;
	const MSG_TYPE_INTERNAL = 5;
	const MSG_TYPE_INTERNAL_RECOVERY = 6;

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
					'html' => 'Problem HTML',
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
					'subject' => 'Resolved: {EVENT.NAME}',
					'html' => 'Problem resolved HTML',
					'sms' => "RESOLVED: {EVENT.NAME}\nHost: {HOST.NAME}\n{EVENT.DATE} {EVENT.TIME}",
					'text' => "Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
						"Problem name: {EVENT.NAME}\nHost: {HOST.NAME}\nSeverity: {EVENT.SEVERITY}\n\n".
						"Original problem ID: {EVENT.ID}\n{TRIGGER.URL}"
				]
			],
			self::MSG_TYPE_UPDATE => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_ACKNOWLEDGE_OPERATION,
				'name' => _('Problem update'),
				'template' => [
					'subject' => 'Updated problem: {EVENT.NAME}',
					'html' => 'Problem update HTML',
					'sms' => '{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}',
					'text' =>
						"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
						"{EVENT.UPDATE.MESSAGE}\n\n".
						"Current problem status is {EVENT.STATUS}, acknowledged: {EVENT.ACK.STATUS}."
				]
			],
			self::MSG_TYPE_DISCOVERY => [
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'recovery' => ACTION_OPERATION,
				'name' => _('Discovery'),
				'template' => [
					'subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
					'html' => 'Discovery HTML',
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
					'html' => 'Autoregistration HTML',
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
	 * Returns an array of all message type names.
	 *
	 * @return array
	 */
	public static function getAllMessageTypeNames() {
		return array_map(function($message_type) {
			return $message_type['name'];
		}, self::messageTemplates());
	}

	/**
	 * Gets an array of event source and operation mode from the specified message type.
	 *
	 * @param int|string $message_type  Message type.
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
	 * @param int|string $eventsource  Event source.
	 * @param int|string $recovery     Operation mode.
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
	 * @param int|string $media_type      Media type.
	 * @param int|string $message_type    Message type.
	 * @param int        $message_format  Message format. Used by Email media type.
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
