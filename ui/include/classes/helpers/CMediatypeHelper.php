<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	public const OAUTH_URL_SCHEMES = ['http', 'https'];

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
				'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
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
				'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
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
			MEDIA_TYPE_WEBHOOK => _('Webhook'),
			MEDIA_TYPE_PUSH => _('Push')
		];

		if ($type === null) {
			natsort($types);

			return $types;
		}

		return $types[$type];
	}

	/**
	 * Returns supported media types values.
	 *
	 * @return array
	 */
	public static function getSupportedMediaTypes(): array {
		global $ZBX_FEATURE_FLAGS;

		$types = [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK, MEDIA_TYPE_PUSH];
		$media_type_denylist = $ZBX_FEATURE_FLAGS['media_type_denylist'];

		return array_diff($types, $media_type_denylist);
	}

	/**
	 * Returns an array of message templates.
	 */
	protected static function messageTemplates(): array {
		return [
			self::MSG_TYPE_PROBLEM => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_OPERATION,
				'name' => _('Problem'),
				'template' => [
					'default' => [
						'subject' => 'Problem: {EVENT.NAME}',
						'message' =>
							"Problem started at {EVENT.TIME} on {EVENT.DATE}\n".
							"Problem name: {EVENT.NAME}\nHost: {HOST.NAME}\nSeverity: {EVENT.SEVERITY}\n".
							"Operational data: {EVENT.OPDATA}\nOriginal problem ID: {EVENT.ID}\n{TRIGGER.URL}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Problem: {EVENT.NAME}',
						'message' =>
							'<b>Problem started</b> at {{EVENT.TIME}.htmlencode()} on {{EVENT.DATE}.htmlencode()}<br>'.
							'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br><b>Host:</b> {{HOST.NAME}.htmlencode()}<br>'.
							'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br><b>Operational data:</b> {{EVENT.OPDATA}.htmlencode()}<br>'.
							'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>{{TRIGGER.URL}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "{EVENT.SEVERITY}: {EVENT.NAME}\nHost: {HOST.NAME}\n{EVENT.DATE} {EVENT.TIME}"
					],
					MEDIA_TYPE_PUSH => [
						'subject' => '{HOST.NAME} - {EVENT.NAME}',
						'message' => "Started on {{EVENT.TIMESTAMP}.fmttime(\"%x %X\")}\nData: {EVENT.OPDATA}"
					]
				]
			],
			self::MSG_TYPE_RECOVERY => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Problem recovery'),
				'template' => [
					'default' => [
						'subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' =>
							"Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
							"Problem name: {EVENT.NAME}\nProblem duration: {EVENT.DURATION}\nHost: {HOST.NAME}\nSeverity: {EVENT.SEVERITY}\n".
							"Original problem ID: {EVENT.ID}\n{TRIGGER.URL}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' =>
							'<b>Problem has been resolved</b> at {{EVENT.RECOVERY.TIME}.htmlencode()} on {{EVENT.RECOVERY.DATE}.htmlencode()}<br>'.
							'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br><b>Problem duration:</b> {{EVENT.DURATION}.htmlencode()}<br><b>Host:</b> {{HOST.NAME}.htmlencode()}<br>'.
							'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br><b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>{{TRIGGER.URL}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "Resolved in {EVENT.DURATION}: {EVENT.NAME}\nHost: {HOST.NAME}\n{EVENT.DATE} {EVENT.TIME}"
					],
					MEDIA_TYPE_PUSH => [
						'subject' => '[RESOLVED] {HOST.NAME} - {EVENT.NAME}',
						'message' => "Resolved on {{EVENT.RECOVERY.TIMESTAMP}.fmttime(\"%x %X\")}\nDuration: {EVENT.DURATION}"
					]
				]
			],
			self::MSG_TYPE_UPDATE => [
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'recovery' => ACTION_UPDATE_OPERATION,
				'name' => _('Problem update'),
				'template' => [
					'default' => [
						'subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
						'message' =>
							"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
							"{EVENT.UPDATE.MESSAGE}\n\n".
							"Current problem status is {EVENT.STATUS}, age is {EVENT.AGE}, acknowledged: {EVENT.ACK.STATUS}."
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
						'message' =>
							'<b>{{USER.FULLNAME}.htmlencode()} {{EVENT.UPDATE.ACTION}.htmlencode()} problem</b> at {{EVENT.UPDATE.DATE}.htmlencode()} {{EVENT.UPDATE.TIME}.htmlencode()}.<br>'.
							'{{EVENT.UPDATE.MESSAGE}.htmlencode()}<br><br><b>Current problem status:</b> {{EVENT.STATUS}.htmlencode()}<br>'.
							'<b>Age:</b> {{EVENT.AGE}.htmlencode()}<br><b>Acknowledged:</b> {{EVENT.ACK.STATUS}.htmlencode()}.'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => '{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem in {EVENT.AGE} at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}'
					],
					MEDIA_TYPE_PUSH => [
						'subject' => '[UPDATED] {HOST.NAME} - {EVENT.NAME}',
						'message' => "{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem on {{EVENT.UPDATE.TIMESTAMP}.fmttime(\"%x %X\")}\n{EVENT.UPDATE.MESSAGE}"
					]
				]
			],
			self::MSG_TYPE_SERVICE => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_OPERATION,
				'name' => _('Service'),
				'template' => [
					'default' => [
						'subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
						'message' =>
							"Service problem started at {EVENT.TIME} on {EVENT.DATE}\n".
							"Service problem name: {EVENT.NAME}\n".
							"Service: {SERVICE.NAME}\n".
							"Severity: {EVENT.SEVERITY}\n".
							"Original problem ID: {EVENT.ID}\n".
							"Service description: {SERVICE.DESCRIPTION}\n\n".
							"{SERVICE.ROOTCAUSE}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
						'message' =>
							'<b>Service problem started</b> at {{EVENT.TIME}.htmlencode()} on {{EVENT.DATE}.htmlencode()}<br>'.
							'<b>Service problem name:</b> {{EVENT.NAME}.htmlencode()}<br>'.
							'<b>Service:</b> {{SERVICE.NAME}.htmlencode()}<br>'.
							'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br>'.
							'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>'.
							'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}<br><br>'.
							'{{SERVICE.ROOTCAUSE}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}"
					]
				]
			],
			self::MSG_TYPE_SERVICE_RECOVERY => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Service recovery'),
				'template' => [
					'default' => [
						'subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' =>
							"Service \"{SERVICE.NAME}\" has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
							"Problem name: {EVENT.NAME}\n".
							"Problem duration: {EVENT.DURATION}\n".
							"Severity: {EVENT.SEVERITY}\n".
							"Original problem ID: {EVENT.ID}\n".
							"Service description: {SERVICE.DESCRIPTION}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' =>
							'<b>Service "{{SERVICE.NAME}.htmlencode()}" has been resolved</b> at {{EVENT.RECOVERY.TIME}.htmlencode()} on {{EVENT.RECOVERY.DATE}.htmlencode()}<br>'.
							'<b>Problem name:</b> {{EVENT.NAME}.htmlencode()}<br>'.
							'<b>Problem duration:</b> {{EVENT.DURATION}.htmlencode()}<br>'.
							'<b>Severity:</b> {{EVENT.SEVERITY}.htmlencode()}<br>'.
							'<b>Original problem ID:</b> {{EVENT.ID}.htmlencode()}<br>'.
							'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}"
					]
				]
			],
			self::MSG_TYPE_SERVICE_UPDATE => [
				'eventsource' => EVENT_SOURCE_SERVICE,
				'recovery' => ACTION_UPDATE_OPERATION,
				'name' => _('Service update'),
				'template' => [
					'default' => [
						'subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
						'message' =>
							"Changed \"{SERVICE.NAME}\" service status to {EVENT.UPDATE.SEVERITY} at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
							"Current problem age is {EVENT.AGE}.\n".
							"Service description: {SERVICE.DESCRIPTION}\n\n".
							"{SERVICE.ROOTCAUSE}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
						'message' =>
							'<b>Changed "{{SERVICE.NAME}.htmlencode()}" service status</b> to {{EVENT.UPDATE.SEVERITY}.htmlencode()} at {{EVENT.UPDATE.DATE}.htmlencode()} {{EVENT.UPDATE.TIME}.htmlencode()}.<br>'.
							'<b>Current problem age</b> is {{EVENT.AGE}.htmlencode()}.<br>'.
							'<b>Service description:</b> {{SERVICE.DESCRIPTION}.htmlencode()}<br><br>'.
							'{{SERVICE.ROOTCAUSE}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "{EVENT.NAME}\n{EVENT.DATE} {EVENT.TIME}"
					]
				]
			],
			self::MSG_TYPE_DISCOVERY => [
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'recovery' => ACTION_OPERATION,
				'name' => _('Discovery'),
				'template' => [
					'default' => [
						'subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
						'message' =>
							"Discovery rule: {DISCOVERY.RULE.NAME}\n\n".
							"Device IP: {DISCOVERY.DEVICE.IPADDRESS}\nDevice DNS: {DISCOVERY.DEVICE.DNS}\n".
							"Device status: {DISCOVERY.DEVICE.STATUS}\n".
							"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\n".
							"Device service name: {DISCOVERY.SERVICE.NAME}\n".
							"Device service port: {DISCOVERY.SERVICE.PORT}\n".
							"Device service status: {DISCOVERY.SERVICE.STATUS}\n".
							"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
						'message' =>
							'<b>Discovery rule:</b> {{DISCOVERY.RULE.NAME}.htmlencode()}<br><br>'.
							'<b>Device IP:</b> {{DISCOVERY.DEVICE.IPADDRESS}.htmlencode()}<br>'.
							'<b>Device DNS:</b> {{DISCOVERY.DEVICE.DNS}.htmlencode()}<br>'.
							'<b>Device status:</b> {{DISCOVERY.DEVICE.STATUS}.htmlencode()}<br>'.
							'<b>Device uptime:</b> {{DISCOVERY.DEVICE.UPTIME}.htmlencode()}<br><br>'.
							'<b>Device service name:</b> {{DISCOVERY.SERVICE.NAME}.htmlencode()}<br>'.
							'<b>Device service port:</b> {{DISCOVERY.SERVICE.PORT}.htmlencode()}<br>'.
							'<b>Device service status:</b> {{DISCOVERY.SERVICE.STATUS}.htmlencode()}<br>'.
							'<b>Device service uptime:</b> {{DISCOVERY.SERVICE.UPTIME}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}'
					]
				]
			],
			self::MSG_TYPE_AUTOREG => [
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'recovery' => ACTION_OPERATION,
				'name' => _('Autoregistration'),
				'template' => [
					'default' => [
						'subject' => 'Autoregistration: {HOST.HOST}',
						'message' => "Host name: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}"
					],
					MEDIA_TYPE_EMAIL.'_'.ZBX_MEDIA_MESSAGE_FORMAT_HTML => [
						'subject' => 'Autoregistration: {HOST.HOST}',
						'message' => '<b>Host name:</b> {{HOST.HOST}.htmlencode()}<br><b>Host IP:</b> {{HOST.IP}.htmlencode()}<br><b>Agent port:</b> {{HOST.PORT}.htmlencode()}'
					],
					MEDIA_TYPE_SMS => [
						'subject' => '',
						'message' => "Autoregistration: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}"
					]
				]
			],
			self::MSG_TYPE_INTERNAL => [
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'recovery' => ACTION_OPERATION,
				'name' => _('Internal problem'),
				'template' => [
					'default' => [
						'subject' => '',
						'message' => ''
					]
				]
			],
			self::MSG_TYPE_INTERNAL_RECOVERY => [
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'recovery' => ACTION_RECOVERY_OPERATION,
				'name' => _('Internal problem recovery'),
				'template' => [
					'default' => [
						'subject' => '',
						'message' => ''
					]
				]
			]
		];
	}

	/**
	 * Returns all message templates.
	 */
	public static function getAllMessageTemplates(): array {
		return self::messageTemplates();
	}

	/**
	 * Returns all message types.
	 */
	public static function getAllMessageTypes(): array {
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

		if (array_key_exists($media_type.'_'.$message_format, $message_templates[$message_type]['template'])) {
			return $message_templates[$message_type]['template'][$media_type.'_'.$message_format];
		}
		elseif (array_key_exists($media_type, $message_templates[$message_type]['template'])) {
			return $message_templates[$message_type]['template'][$media_type];
		}

		return $message_templates[$message_type]['template']['default'];
	}

	/**
	 * Get OAuth defaults as array with provider as a key and oauth defaults as value.
	 *
	 * @return array
	 */
	public static function getOauthDefaultsByProvider(): array {
		$redirection_url = '';
		$base_url = rtrim(CSettingsHelper::get(CSettingsHelper::URL), '/');

		if ($base_url !== '') {
			$url = new CUrl($base_url.'/zabbix.php');
			$url->setArgument('action', 'oauth.authorize');
			$redirection_url = $url->getUrl();
		}

		return [
			self::EMAIL_PROVIDER_SMTP => [
				'redirection_url' => $redirection_url
			],
			self::EMAIL_PROVIDER_GMAIL => [
				'redirection_url' => $redirection_url,
				'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&scope=https://mail.google.com/&prompt=consent&access_type=offline',
				'token_url' => 'https://oauth2.googleapis.com/token?grant_type=authorization_code'
			],
			self::EMAIL_PROVIDER_GMAIL_RELAY => [
				'redirection_url' => $redirection_url,
				'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&scope=https://mail.google.com/&prompt=consent&access_type=offline',
				'token_url' => 'https://oauth2.googleapis.com/token?grant_type=authorization_code'
			],
			self::EMAIL_PROVIDER_OFFICE365 => [
				'redirection_url' => $redirection_url,
				'authorization_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?response_type=code&scope=https://outlook.office.com/SMTP.Send offline_access',
				'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token?grant_type=authorization_code'
			]
		];
	}
}
