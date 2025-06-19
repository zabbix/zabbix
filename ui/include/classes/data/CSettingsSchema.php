<?php declare(strict_types = 0);
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


/**
 * Class containing information about system settings.
 */
final class CSettingsSchema {

	public const DB_FIELD_TYPES = [
		/*
		 * Mismatch with TEXT column type intentional. Since fields configurable by the API don't contain long values,
		 * they are validated as CHARs and length is checked.
		 */
		'value_str' => DB::FIELD_TYPE_CHAR,
		'value_int' => DB::FIELD_TYPE_INT,
		'value_usrgrpid' => DB::FIELD_TYPE_ID,
		'value_hostgroupid' => DB::FIELD_TYPE_ID,
		'value_userdirectoryid' => DB::FIELD_TYPE_ID,
		'value_mfaid' => DB::FIELD_TYPE_ID
	];

	public const PARAMETERS = [
		'alert_usrgrpid' => [
			'column' => 'value_usrgrpid'
		],
		'auditlog_enabled' => [
			'column' => 'value_int',
			'default' => 1
		],
		'auditlog_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'authentication_type' => [
			'column' => 'value_int',
			'default' => 0
		],
		'autoreg_tls_accept' => [
			'column' => 'value_int',
			'default' => 1
		],
		'blink_period' => [
			'column' => 'value_str',
			'default' => '2m',
			'length' => 32
		],
		'compress_older' => [
			'column' => 'value_str',
			'default' => '7d',
			'length' => 32
		],
		'compression_status' => [
			'column' => 'value_int',
			'default' => 0
		],
		'connect_timeout' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 32
		],
		'custom_color' => [
			'column' => 'value_int',
			'default' => 0
		],
		'db_extension' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 32
		],
		'dbversion_status' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 65
		],
		'default_inventory_mode' => [
			'column' => 'value_int',
			'default' => HOST_INVENTORY_DISABLED
		],
		'default_lang' => [
			'column' => 'value_str',
			'default' => 'en_US',
			'length' => 5
		],
		'default_theme' => [
			'column' => 'value_str',
			'default' => 'blue-theme',
			'length' => 128
		],
		'default_timezone' => [
			'column' => 'value_str',
			'default' => 'system',
			'length' => 50
		],
		'disabled_usrgrpid' => [
			'column' => 'value_usrgrpid'
		],
		'discovery_groupid' => [
			'column' => 'value_hostgroupid'
		],
		'geomaps_attribution' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 1024
		],
		'geomaps_max_zoom' => [
			'column' => 'value_int',
			'default' => 0
		],
		'geomaps_tile_provider' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 255
		],
		'geomaps_tile_url' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 2048
		],
		'ha_failover_delay' => [
			'column' => 'value_str',
			'default' => '1m',
			'length' => 32
		],
		'history_period' => [
			'column' => 'value_str',
			'default' => '24h',
			'length' => 32
		],
		'hk_audit_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_audit' => [
			'column' => 'value_str',
			'default' => '31d',
			'length' => 32
		],
		'hk_events_autoreg' => [
			'column' => 'value_str',
			'default' => '1d',
			'length' => 32
		],
		'hk_events_discovery' => [
			'column' => 'value_str',
			'default' => '1d',
			'length' => 32
		],
		'hk_events_internal' => [
			'column' => 'value_str',
			'default' => '1d',
			'length' => 32
		],
		'hk_events_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_events_service' => [
			'column' => 'value_str',
			'default' => '1d',
			'length' => 32
		],
		'hk_events_trigger' => [
			'column' => 'value_str',
			'default' => '365d',
			'length' => 32
		],
		'hk_history_global' => [
			'column' => 'value_int',
			'default' => 0
		],
		'hk_history_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_history' => [
			'column' => 'value_str',
			'default' => '31d',
			'length' => 32
		],
		'hk_services_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_services' => [
			'column' => 'value_str',
			'default' => '365d',
			'length' => 32
		],
		'hk_sessions_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_sessions' => [
			'column' => 'value_str',
			'default' => '31d',
			'length' => 32
		],
		'hk_trends_global' => [
			'column' => 'value_int',
			'default' => 0
		],
		'hk_trends_mode' => [
			'column' => 'value_int',
			'default' => 1
		],
		'hk_trends' => [
			'column' => 'value_str',
			'default' => '365d',
			'length' => 32
		],
		'http_auth_enabled' => [
			'column' => 'value_int',
			'default' => 0
		],
		'http_case_sensitive' => [
			'column' => 'value_int',
			'default' => 1
		],
		'http_login_form' => [
			'column' => 'value_int',
			'default' => 0
		],
		'http_strip_domains' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 2048
		],
		'iframe_sandboxing_enabled' => [
			'column' => 'value_int',
			'default' => 1
		],
		'iframe_sandboxing_exceptions' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 255
		],
		'instanceid' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 32
		],
		'item_test_timeout' => [
			'column' => 'value_str',
			'default' => '60s',
			'length' => 32
		],
		'jit_provision_interval' => [
			'column' => 'value_str',
			'default' => '1h',
			'length' => 32
		],
		'ldap_auth_enabled' => [
			'column' => 'value_int',
			'default' => 0
		],
		'ldap_case_sensitive' => [
			'column' => 'value_int',
			'default' => 1
		],
		'ldap_jit_status' => [
			'column' => 'value_int',
			'default' => 0
		],
		'ldap_userdirectoryid' => [
			'column' => 'value_userdirectoryid'
		],
		'login_attempts' => [
			'column' => 'value_int',
			'default' => 5
		],
		'login_block' => [
			'column' => 'value_str',
			'default' => '30s',
			'length' => 32
		],
		'max_in_table' => [
			'column' => 'value_int',
			'default' => 50
		],
		'max_overview_table_size' => [
			'column' => 'value_int',
			'default' => 50
		],
		'max_period' => [
			'column' => 'value_str',
			'default' => '2y',
			'length' => 32
		],
		'media_type_test_timeout' => [
			'column' => 'value_str',
			'default' => '65s',
			'length' => 32
		],
		'mfa_status' => [
			'column' => 'value_int',
			'default' => 0
		],
		'mfaid' => [
			'column' => 'value_mfaid'
		],
		'ok_ack_color' => [
			'column' => 'value_str',
			'default' => '009900',
			'length' => 6
		],
		'ok_ack_style' => [
			'column' => 'value_int',
			'default' => 1
		],
		'ok_period' => [
			'column' => 'value_str',
			'default' => '5m',
			'length' => 32
		],
		'ok_unack_color' => [
			'column' => 'value_str',
			'default' => '009900',
			'length' => 6
		],
		'ok_unack_style' => [
			'column' => 'value_int',
			'default' => 1
		],
		'passwd_check_rules' => [
			'column' => 'value_int',
			'default' => 8
		],
		'passwd_min_length' => [
			'column' => 'value_int',
			'default' => 8
		],
		'period_default' => [
			'column' => 'value_str',
			'default' => '1h',
			'length' => 32
		],
		'problem_ack_color' => [
			'column' => 'value_str',
			'default' => 'CC0000',
			'length' => 6
		],
		'problem_ack_style' => [
			'column' => 'value_int',
			'default' => 1
		],
		'problem_unack_color' => [
			'column' => 'value_str',
			'default' => 'CC0000',
			'length' => 6
		],
		'problem_unack_style' => [
			'column' => 'value_int',
			'default' => 1
		],
		'proxy_secrets_provider' => [
			'column' => 'value_int',
			'default' => 0
		],
		'report_test_timeout' => [
			'column' => 'value_str',
			'default' => '60s',
			'length' => 32
		],
		'saml_auth_enabled' => [
			'column' => 'value_int',
			'default' => 0
		],
		'saml_case_sensitive' => [
			'column' => 'value_int',
			'default' => 0
		],
		'saml_jit_status' => [
			'column' => 'value_int',
			'default' => 0
		],
		'script_timeout' => [
			'column' => 'value_str',
			'default' => '60s',
			'length' => 32
		],
		'search_limit' => [
			'column' => 'value_int',
			'default' => 1000
		],
		'server_check_interval' => [
			'column' => 'value_int',
			'default' => 10
		],
		'server_status' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 65535
		],
		'session_key' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 32
		],
		'severity_color_0' => [
			'column' => 'value_str',
			'default' => '97AAB3',
			'length' => 6
		],
		'severity_color_1' => [
			'column' => 'value_str',
			'default' => '7499FF',
			'length' => 6
		],
		'severity_color_2' => [
			'column' => 'value_str',
			'default' => 'FFC859',
			'length' => 6
		],
		'severity_color_3' => [
			'column' => 'value_str',
			'default' => 'FFA059',
			'length' => 6
		],
		'severity_color_4' => [
			'column' => 'value_str',
			'default' => 'E97659',
			'length' => 6
		],
		'severity_color_5' => [
			'column' => 'value_str',
			'default' => 'E45959',
			'length' => 6
		],
		'severity_name_0' => [
			'column' => 'value_str',
			'default' => 'Not classified',
			'length' => 32
		],
		'severity_name_1' => [
			'column' => 'value_str',
			'default' => 'Information',
			'length' => 32
		],
		'severity_name_2' => [
			'column' => 'value_str',
			'default' => 'Warning',
			'length' => 32
		],
		'severity_name_3' => [
			'column' => 'value_str',
			'default' => 'Average',
			'length' => 32
		],
		'severity_name_4' => [
			'column' => 'value_str',
			'default' => 'High',
			'length' => 32
		],
		'severity_name_5' => [
			'column' => 'value_str',
			'default' => 'Disaster',
			'length' => 32
		],
		'show_technical_errors' => [
			'column' => 'value_int',
			'default' => 0
		],
		'snmptrap_logging' => [
			'column' => 'value_int',
			'default' => 1
		],
		'socket_timeout' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 32
		],
		'software_update_check_data' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 65535
		],
		'software_update_checkid' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 32
		],
		'timeout_browser' => [
			'column' => 'value_str',
			'default' => '60s',
			'length' => 255
		],
		'timeout_db_monitor' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_external_check' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_http_agent' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_script' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_simple_check' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_snmp_agent' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_ssh_agent' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_telnet_agent' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'timeout_zabbix_agent' => [
			'column' => 'value_str',
			'default' => '3s',
			'length' => 255
		],
		'uri_valid_schemes' => [
			'column' => 'value_str',
			'default' => 'http,https,ftp,file,mailto,tel,ssh',
			'length' => 255
		],
		'url' => [
			'column' => 'value_str',
			'default' => '',
			'length' => 2048
		],
		'validate_uri_schemes' => [
			'column' => 'value_int',
			'default' => 1
		],
		'vault_provider' => [
			'column' => 'value_int',
			'default' => 0
		],
		'work_period' => [
			'column' => 'value_str',
			'default' => '1-5,09:00-18:00',
			'length' => 255
		],
		'x_frame_options' => [
			'column' => 'value_str',
			'default' => 'SAMEORIGIN',
			'length' => 255
		]
	];

	public static function getFieldLength(string $parameter): int {
		return self::PARAMETERS[$parameter]['length'];
	}

	public static function getDefault(string $parameter) {
		return self::PARAMETERS[$parameter]['default'];
	}

	public static function getDbType(string $parameter): int {
		return self::DB_FIELD_TYPES[self::PARAMETERS[$parameter]['column']];
	}
}
