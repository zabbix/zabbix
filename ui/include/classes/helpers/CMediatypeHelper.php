<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	 * Email type providers.
	 */
	public const EMAIL_PROVIDER_SMTP = 0;
	public const EMAIL_PROVIDER_GMAIL = 1;
	public const EMAIL_PROVIDER_GMAIL_RELAY = 2;
	public const EMAIL_PROVIDER_OFFICE365 = 3;
	public const EMAIL_PROVIDER_OFFICE365_RELAY = 4;

	/**
	 * Returns an array of Email providers default settings.
	 *
	 * @return array
	 */
	public static function getEmailProviders($provider = null) {
		$providers = [
			self::EMAIL_PROVIDER_SMTP => [
				'name' => 'Generic SMTP',
				'smtp_server' => 'mail.example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => 25,
				'smtp_security' => SMTP_SECURITY_NONE,
				'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
				'smtp_verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'smtp_verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'message_format' => ZBX_MEDIA_MESSAGE_FORMAT_HTML
			],
			self::EMAIL_PROVIDER_GMAIL => [
				'name' => 'Gmail',
				'smtp_server' => 'smtp.gmail.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => 587,
				'smtp_security' => SMTP_SECURITY_STARTTLS,
				'smtp_authentication' => SMTP_AUTHENTICATION_NORMAL,
				'smtp_verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'smtp_verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'message_format' => ZBX_MEDIA_MESSAGE_FORMAT_HTML
			],
			self::EMAIL_PROVIDER_GMAIL_RELAY => [
				'name' => 'Gmail relay',
				'smtp_server' => 'smtp-relay.gmail.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => 587,
				'smtp_security' => SMTP_SECURITY_STARTTLS,
				'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
				'smtp_verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'smtp_verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'message_format' => ZBX_MEDIA_MESSAGE_FORMAT_HTML
			],
			self::EMAIL_PROVIDER_OFFICE365 => [
				'name' => 'Office365',
				'smtp_server' => 'smtp.office365.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => 587,
				'smtp_security' => SMTP_SECURITY_STARTTLS,
				'smtp_authentication' => SMTP_AUTHENTICATION_NORMAL,
				'smtp_verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'smtp_verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'message_format' => ZBX_MEDIA_MESSAGE_FORMAT_HTML
			],
			self::EMAIL_PROVIDER_OFFICE365_RELAY => [
				'name' => 'Office365 relay',
				'smtp_server' => '.mail.protection.outlook.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => 25,
				'smtp_security' => SMTP_SECURITY_STARTTLS,
				'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
				'smtp_verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'smtp_verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'message_format' => ZBX_MEDIA_MESSAGE_FORMAT_HTML
			]
		];

		if ($provider === null) {
			return $providers;
		}

		return $providers[$provider];
	}

	/**
	 * Returns all providers names.
	 *
	 * @return array
	 */
	public static function getAllEmailProvidersNames() {
		return array_column(self::getEmailProviders(), 'name');
	}

	/**
	 * Returns media types names.
	 *
	 * @return array
	 */
	public static function getMediaTypes($type = null) {
		$types = [
			MEDIA_TYPE_EMAIL => _('Email'),
			MEDIA_TYPE_EXEC => _('Script'),
			MEDIA_TYPE_SMS => _('SMS'),
			MEDIA_TYPE_WEBHOOK => _('Webhook')
		];

		if ($type === null) {
			natsort($types);

			return $types;
		}

		return $types[$type];
	}

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
					'html' => '<b>Problem started</b> at {{EVENT.TIME}.htmlencode()} on {{EVENT.DATE}.htmlencode()}<br>'.
						'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br><b>Host:</b> {{HOST.NAME}.htmlencode()}<br>'.
						'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br><b>Operational data:</b> {{EVENT.OPDATA}.htmlencode()}<br>'.
						'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>{{TRIGGER.URL}.htmlencode()}',
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
					'html' => '<b>Problem has been resolved</b> at {{EVENT.RECOVERY.TIME}.htmlencode()} on {{EVENT.RECOVERY.DATE}.htmlencode()}<br>'.
						'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br><b>Problem duration:</b> {{EVENT.DURATION}.htmlencode()}<br><b>Host:</b> {{HOST.NAME}.htmlencode()}<br>'.
						'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br><b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>{{TRIGGER.URL}.htmlencode()}',
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
						'<b>{{USER.FULLNAME}.htmlencode()} {{EVENT.UPDATE.ACTION}.htmlencode()} problem</b> at {{EVENT.UPDATE.DATE}.htmlencode()} {{EVENT.UPDATE.TIME}.htmlencode()}.<br>'.
						'{{EVENT.UPDATE.MESSAGE}.htmlencode()}<br><br><b>Current problem status:</b> {{EVENT.STATUS}.htmlencode()}<br>'.
						'<b>Age:</b> {{EVENT.AGE}.htmlencode()}<br><b>Acknowledged:</b> {{EVENT.ACK.STATUS}.htmlencode()}.',
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
						'<b>Service problem started</b> at {{EVENT.TIME}.htmlencode()} on {{EVENT.DATE}.htmlencode()}<br>'.
						'<b>Service problem name:</b> {{EVENT.NAME}.htmlencode()}<br>'.
						'<b>Service:</b> {{SERVICE.NAME}.htmlencode()}<br>'.
						'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br>'.
						'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>'.
						'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}<br><br>'.
						'{{SERVICE.ROOTCAUSE}.htmlencode()}',
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
						'<b>Service "{{SERVICE.NAME}.htmlencode()}" has been resolved</b> at {{EVENT.RECOVERY.TIME}.htmlencode()} on {{EVENT.RECOVERY.DATE}.htmlencode()}<br>'.
						'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br>'.
						'<b>Problem duration:</b> {{EVENT.DURATION}.htmlencode()}<br>'.
						'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br>'.
						'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>'.
						'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}',
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
						'<b>Changed "{{SERVICE.NAME}.htmlencode()}" service status</b> to {{EVENT.UPDATE.SEVERITY}.htmlencode()} at {{EVENT.UPDATE.DATE}.htmlencode()} {{EVENT.UPDATE.TIME}.htmlencode()}.<br>'.
						'<b>Current problem age</b> is {{EVENT.AGE}.htmlencode()}.<br>'.
						'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}<br><br>'.
						'{{SERVICE.ROOTCAUSE}.htmlencode()}',
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
					'html' => '<b>Discovery rule:</b> {{DISCOVERY.RULE.NAME}.htmlencode()}<br><br>'.
						'<b>Device IP:</b> {{DISCOVERY.DEVICE.IPADDRESS}.htmlencode()}<br>'.
						'<b>Device DNS:</b> {{DISCOVERY.DEVICE.DNS}.htmlencode()}<br>'.
						'<b>Device status:</b> {{DISCOVERY.DEVICE.STATUS}.htmlencode()}<br>'.
						'<b>Device uptime:</b> {{DISCOVERY.DEVICE.UPTIME}.htmlencode()}<br><br>'.
						'<b>Device service name:</b> {{DISCOVERY.SERVICE.NAME}.htmlencode()}<br>'.
						'<b>Device service port:</b> {{DISCOVERY.SERVICE.PORT}.htmlencode()}<br>'.
						'<b>Device service status:</b> {{DISCOVERY.SERVICE.STATUS}.htmlencode()}<br>'.
						'<b>Device service uptime:</b> {{DISCOVERY.SERVICE.UPTIME}.htmlencode()}',
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
					'html' => '<b>Host name:</b> {{HOST.HOST}.htmlencode()}<br><b>Host IP:</b> {{HOST.IP}.htmlencode()}<br><b>Agent port:</b> {{HOST.PORT}.htmlencode()}',
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

		if ($media_type == MEDIA_TYPE_EMAIL && $message_format == ZBX_MEDIA_MESSAGE_FORMAT_HTML) {
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
