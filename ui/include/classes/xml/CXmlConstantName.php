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


/**
 * Constant names what we used in schema.
 */
class CXmlConstantName {

	const ENABLED = 'ENABLED';
	const DISABLED = 'DISABLED';

	const XML_DEFAULT = 'DEFAULT';
	const NONE = 'NONE';
	const MD2 = 'MD2';
	const MD5 = 'MD5';
	const STRAIGHT = 'STRAIGHT';
	const OEM = 'OEM';
	const RMCP_PLUS = 'RMCP_PLUS';

	const CALLBACK = 'CALLBACK';
	const USER = 'USER';
	const OPERATOR = 'OPERATOR';
	const ADMIN = 'ADMIN';
	// const OEM = 'OEM'; // Duplicate.

	const NO_ENCRYPTION = 'NO_ENCRYPTION';
	const TLS_PSK = 'TLS_PSK';
	const TLS_CERTIFICATE = 'TLS_CERTIFICATE';

	const NO = 'NO';
	const YES = 'YES';

	const ZABBIX = 'ZABBIX';
	const SNMP = 'SNMP';
	const IPMI = 'IPMI';
	const JMX = 'JMX';

	// const DISABLED = 'DISABLED'; // Duplicate.
	const MANUAL = 'MANUAL';
	const AUTOMATIC = 'AUTOMATIC';

	const EXPRESSION = 'EXPRESSION';
	const RECOVERY_EXPRESSION = 'RECOVERY_EXPRESSION';
	// const NONE = 'NONE'; // Duplicate.

	// const DISABLED = 'DISABLED'; // Duplicate.
	const TAG_VALUE = 'TAG_VALUE';

	const NOT_CLASSIFIED = 'NOT_CLASSIFIED';
	const INFO = 'INFO';
	const WARNING = 'WARNING';
	const AVERAGE = 'AVERAGE';
	const HIGH = 'HIGH';
	const DISASTER = 'DISASTER';

	const SINGLE = 'SINGLE';
	const MULTIPLE = 'MULTIPLE';

	const CALCULATED = 'CALCULATED';
	const FIXED = 'FIXED';
	const ITEM = 'ITEM';

	const NORMAL = 'NORMAL';
	const STACKED = 'STACKED';
	const PIE = 'PIE';
	const EXPLODED = 'EXPLODED';

	const SINGLE_LINE = 'SINGLE_LINE';
	const FILLED_REGION = 'FILLED_REGION';
	const BOLD_LINE = 'BOLD_LINE';
	const DOTTED_LINE = 'DOTTED_LINE';
	const DASHED_LINE = 'DASHED_LINE';
	const GRADIENT_LINE = 'GRADIENT_LINE';

	const LEFT = 'LEFT';
	const RIGHT = 'RIGHT';

	const MIN = 'MIN';
	const AVG = 'AVG';
	const MAX = 'MAX';
	const ALL = 'ALL';
	const LAST = 'LAST';

	const SIMPLE = 'SIMPLE';
	const GRAPH_SUM = 'GRAPH_SUM';

	const PASSWORD = 'PASSWORD';
	const PUBLIC_KEY = 'PUBLIC_KEY';

	// const NONE = 'NONE'; // Duplicate.
	const BASIC = 'BASIC';
	const NTLM = 'NTLM';
	const KERBEROS = 'KERBEROS';
	const DIGEST = 'DIGEST';

	const ALIAS = 'ALIAS';
	const ASSET_TAG = 'ASSET_TAG';
	const CHASSIS = 'CHASSIS';
	const CONTACT = 'CONTACT';
	const CONTRACT_NUMBER = 'CONTRACT_NUMBER';
	const DATE_HW_DECOMM = 'DATE_HW_DECOMM';
	const DATE_HW_EXPIRY = 'DATE_HW_EXPIRY';
	const DATE_HW_INSTALL = 'DATE_HW_INSTALL';
	const DATE_HW_PURCHASE = 'DATE_HW_PURCHASE';
	const DEPLOYMENT_STATUS = 'DEPLOYMENT_STATUS';
	const HARDWARE = 'HARDWARE';
	const HARDWARE_FULL = 'HARDWARE_FULL';
	const HOST_NETMASK = 'HOST_NETMASK';
	const HOST_NETWORKS = 'HOST_NETWORKS';
	const HOST_ROUTER = 'HOST_ROUTER';
	const HW_ARCH = 'HW_ARCH';
	const INSTALLER_NAME = 'INSTALLER_NAME';
	const LOCATION = 'LOCATION';
	const LOCATION_LAT = 'LOCATION_LAT';
	const LOCATION_LON = 'LOCATION_LON';
	const MACADDRESS_A = 'MACADDRESS_A';
	const MACADDRESS_B = 'MACADDRESS_B';
	const MODEL = 'MODEL';
	const NAME = 'NAME';
	const NOTES = 'NOTES';
	const OOB_IP = 'OOB_IP';
	const OOB_NETMASK = 'OOB_NETMASK';
	const OOB_ROUTER = 'OOB_ROUTER';
	const OS = 'OS';
	const OS_FULL = 'OS_FULL';
	const OS_SHORT = 'OS_SHORT';
	const POC_1_CELL = 'POC_1_CELL';
	const POC_1_EMAIL = 'POC_1_EMAIL';
	const POC_1_NAME = 'POC_1_NAME';
	const POC_1_NOTES = 'POC_1_NOTES';
	const POC_1_PHONE_A = 'POC_1_PHONE_A';
	const POC_1_PHONE_B = 'POC_1_PHONE_B';
	const POC_1_SCREEN = 'POC_1_SCREEN';
	const POC_2_CELL = 'POC_2_CELL';
	const POC_2_EMAIL = 'POC_2_EMAIL';
	const POC_2_NAME = 'POC_2_NAME';
	const POC_2_NOTES = 'POC_2_NOTES';
	const POC_2_PHONE_A = 'POC_2_PHONE_A';
	const POC_2_PHONE_B = 'POC_2_PHONE_B';
	const POC_2_SCREEN = 'POC_2_SCREEN';
	const SERIALNO_A = 'SERIALNO_A';
	const SERIALNO_B = 'SERIALNO_B';
	const SITE_ADDRESS_A = 'SITE_ADDRESS_A';
	const SITE_ADDRESS_B = 'SITE_ADDRESS_B';
	const SITE_ADDRESS_C = 'SITE_ADDRESS_C';
	const SITE_CITY = 'SITE_CITY';
	const SITE_COUNTRY = 'SITE_COUNTRY';
	const SITE_NOTES = 'SITE_NOTES';
	const SITE_RACK = 'SITE_RACK';
	const SITE_STATE = 'SITE_STATE';
	const SITE_ZIP = 'SITE_ZIP';
	const SOFTWARE = 'SOFTWARE';
	const SOFTWARE_APP_A = 'SOFTWARE_APP_A';
	const SOFTWARE_APP_B = 'SOFTWARE_APP_B';
	const SOFTWARE_APP_C = 'SOFTWARE_APP_C';
	const SOFTWARE_APP_D = 'SOFTWARE_APP_D';
	const SOFTWARE_APP_E = 'SOFTWARE_APP_E';
	const SOFTWARE_FULL = 'SOFTWARE_FULL';
	const TAG = 'TAG';
	const TYPE = 'TYPE';
	const TYPE_FULL = 'TYPE_FULL';
	const URL_A = 'URL_A';
	const URL_B = 'URL_B';
	const URL_C = 'URL_C';
	const VENDOR = 'VENDOR';

	const RAW = 'RAW';
	const JSON = 'JSON';

	const XML = 'XML';

	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const HEAD = 'HEAD';

	const BODY = 'BODY';
	const HEADERS = 'HEADERS';
	const BOTH = 'BOTH';


	// const MD5 = 'MD5'; // Duplicate.
	const SHA = 'SHA'; // Used before version 5.4 as interface "authprotocol" invariant.
	const SHA1 = 'SHA1';
	const SHA224 = 'SHA224';
	const SHA256 = 'SHA256';
	const SHA384 = 'SHA384';
	const SHA512 = 'SHA512';

	const DES = 'DES';
	const AES = 'AES'; // Used in version 5.2 as interface "privprotocol" invariant.
	const AES128 = 'AES128';
	const AES192 = 'AES192';
	const AES256 = 'AES256';
	const AES192C = 'AES192C';
	const AES256C = 'AES256C';

	const NOAUTHNOPRIV = 'NOAUTHNOPRIV';
	const AUTHNOPRIV = 'AUTHNOPRIV';
	const AUTHPRIV = 'AUTHPRIV';

	const ZABBIX_PASSIVE = 'ZABBIX_PASSIVE';
	const SNMPV1 = 'SNMPV1'; // Used by item type in 4.4 and as interface SNMP version in 5.0.
	const TRAP = 'TRAP';
	// const SIMPLE = 'SIMPLE'; // Duplicate.
	const SNMPV2 = 'SNMPV2'; // Used by item type in 4.4 and as interface SNMP version in 5.0.
	const INTERNAL = 'INTERNAL';
	const SNMPV3 = 'SNMPV3'; // Used by item type in 4.4 and as interface SNMP version in 5.0.
	const ZABBIX_ACTIVE = 'ZABBIX_ACTIVE';
	const AGGREGATE = 'AGGREGATE';
	const EXTERNAL = 'EXTERNAL';
	const ODBC = 'ODBC';
	// const IPMI = 'IPMI'; // Duplicate.
	const SSH = 'SSH';
	const TELNET = 'TELNET';
	// const CALCULATED = 'CALCULATED'; // Duplicate.
	// const JMX = 'JMX'; // Duplicate.
	const SNMP_TRAP = 'SNMP_TRAP';
	const DEPENDENT = 'DEPENDENT';
	const HTTP_AGENT = 'HTTP_AGENT';
	const SNMP_AGENT = 'SNMP_AGENT';

	const FLOAT = 'FLOAT';
	const CHAR = 'CHAR';
	const LOG = 'LOG';
	const UNSIGNED = 'UNSIGNED';
	const TEXT = 'TEXT';

	const ORIGINAL_ERROR = 'ORIGINAL_ERROR';
	const DISCARD_VALUE = 'DISCARD_VALUE';
	const CUSTOM_VALUE = 'CUSTOM_VALUE';
	const CUSTOM_ERROR = 'CUSTOM_ERROR';

	const MULTIPLIER = 'MULTIPLIER';
	const RTRIM = 'RTRIM';
	const LTRIM = 'LTRIM';
	const TRIM = 'TRIM';
	const REGEX = 'REGEX';
	const BOOL_TO_DECIMAL = 'BOOL_TO_DECIMAL';
	const OCTAL_TO_DECIMAL = 'OCTAL_TO_DECIMAL';
	const HEX_TO_DECIMAL = 'HEX_TO_DECIMAL';
	const SIMPLE_CHANGE = 'SIMPLE_CHANGE';
	const CHANGE_PER_SECOND = 'CHANGE_PER_SECOND';
	const XMLPATH = 'XMLPATH';
	const JSONPATH = 'JSONPATH';
	const IN_RANGE = 'IN_RANGE';
	const MATCHES_REGEX = 'MATCHES_REGEX';
	const NOT_MATCHES_REGEX = 'NOT_MATCHES_REGEX';
	const EXISTS = 'EXISTS';
	const NOT_EXISTS = 'NOT_EXISTS';
	const CHECK_JSON_ERROR = 'CHECK_JSON_ERROR';
	const CHECK_XML_ERROR = 'CHECK_XML_ERROR';
	const CHECK_REGEX_ERROR = 'CHECK_REGEX_ERROR';
	const CHECK_NOT_SUPPORTED = 'CHECK_NOT_SUPPORTED';
	const DISCARD_UNCHANGED = 'DISCARD_UNCHANGED';
	const DISCARD_UNCHANGED_HEARTBEAT = 'DISCARD_UNCHANGED_HEARTBEAT';
	const JAVASCRIPT = 'JAVASCRIPT';
	const PROMETHEUS_PATTERN = 'PROMETHEUS_PATTERN';
	const PROMETHEUS_TO_JSON = 'PROMETHEUS_TO_JSON';
	const CSV_TO_JSON = 'CSV_TO_JSON';
	const STR_REPLACE = 'STR_REPLACE';
	const XML_TO_JSON = 'XML_TO_JSON';

	const AND_OR = 'AND_OR';
	const XML_AND = 'AND';
	const XML_OR = 'OR';
	const FORMULA = 'FORMULA';

	// const MATCHES_REGEX = 'MATCHES_REGEX'; // Duplicate.
	// const NOT_MATCHES_REGEX = 'NOT_MATCHES_REGEX'; // Duplicate.

	const EMAIL = 'EMAIL';
	const SCRIPT = 'SCRIPT';
	const SMS = 'SMS';
	const WEBHOOK = 'WEBHOOK';

	// const NONE = 'NONE'; // Duplicate.
	const STARTTLS = 'STARTTLS';
	const SSL_OR_TLS = 'SSL_OR_TLS';

	const SMTP_AUTHENTICATION_NONE = 'NONE';
	const SMTP_AUTHENTICATION_PASSWORD = 'PASSWORD';

	const CONTENT_TYPE_TEXT = 'TEXT'; // Duplicate.
	const CONTENT_TYPE_HTML = 'HTML';

	const TRIGGERS = 'TRIGGERS';
	const DISCOVERY = 'DISCOVERY';
	const AUTOREGISTRATION = 'AUTOREGISTRATION';
	// const INTERNAL = 'INTERNAL'; // Duplicate.
	const SERVICE = 'SERVICE';

	const PROBLEM = 'PROBLEM';
	const RECOVERY = 'RECOVERY';
	const UPDATE = 'UPDATE';

	const MACRO_TYPE_TEXT = 'TEXT';
	const MACRO_TYPE_SECRET = 'SECRET_TEXT';
	const MACRO_TYPE_VAULT = 'VAULT';

	// Constants for low-level discovery rule overrides.
	const LLD_OVERRIDE_STOP_NO = 'NO_STOP';
	const LLD_OVERRIDE_STOP_YES = 'STOP';
	const LLD_OVERRIDE_OPERATION_OBJECT_ITEM_PROTOTYPE = 'ITEM_PROTOTYPE';
	const LLD_OVERRIDE_OPERATION_OBJECT_TRIGGER_PROTOTYPE = 'TRIGGER_PROTOTYPE';
	const LLD_OVERRIDE_OPERATION_OBJECT_GRAPH_PROTOTYPE = 'GRAPH_PROTOTYPE';
	const LLD_OVERRIDE_OPERATION_OBJECT_HOST_PROTOTYPE = 'HOST_PROTOTYPE';
	const CONDITION_OPERATOR_EQUAL = 'EQUAL';
	const CONDITION_OPERATOR_NOT_EQUAL = 'NOT_EQUAL';
	const CONDITION_OPERATOR_LIKE = 'LIKE';
	const CONDITION_OPERATOR_NOT_LIKE = 'NOT_LIKE';
	const CONDITION_OPERATOR_REGEXP = 'REGEXP';
	const CONDITION_OPERATOR_NOT_REGEXP = 'NOT_REGEXP';
	const DISCOVER = 'DISCOVER';
	const NO_DISCOVER = 'NO_DISCOVER';

	// Constants for widget types.
	const DASHBOARD_WIDGET_TYPE_CLOCK = 'CLOCK';
	const DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC = 'GRAPH_CLASSIC';
	const DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE = 'GRAPH_PROTOTYPE';
	const DASHBOARD_WIDGET_TYPE_ITEM = 'ITEM';
	const DASHBOARD_WIDGET_TYPE_PLAIN_TEXT = 'PLAIN_TEXT';
	const DASHBOARD_WIDGET_TYPE_URL = 'URL';

	// Constants for widget field types.
	const DASHBOARD_WIDGET_FIELD_TYPE_INTEGER = 'INTEGER';
	const DASHBOARD_WIDGET_FIELD_TYPE_STRING = 'STRING';
	const DASHBOARD_WIDGET_FIELD_TYPE_HOST = 'HOST';
	const DASHBOARD_WIDGET_FIELD_TYPE_ITEM = 'ITEM';
	const DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE = 'ITEM_PROTOTYPE';
	const DASHBOARD_WIDGET_FIELD_TYPE_GRAPH = 'GRAPH';
	const DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE = 'GRAPH_PROTOTYPE';

	// Constants for value map mapping type.
	const MAPPING_EQUAL = 'EQUAL';
	const MAPPING_GREATER_EQUAL = 'GREATER_OR_EQUAL';
	const MAPPING_LESS_EQUAL = 'LESS_OR_EQUAL';
	const MAPPING_IN_RANGE = 'IN_RANGE';
	const MAPPING_REGEXP = 'REGEXP';
	const MAPPING_DEFAULT = 'DEFAULT';
}
