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


/**
 * Constant values what we used in schema.
 */
class CXmlConstantValue {

	// Values.
	const ENABLED = 0;
	const DISABLED = 1;

	const XML_DEFAULT = -1;
	const NONE = 0;
	const MD2 = 1;
	const MD5 = 2;
	const STRAIGHT = 4;
	const OEM = 5;
	const RMCP_PLUS = 6;

	const CALLBACK = 1;
	const USER = 2;
	const OPERATOR = 3;
	const ADMIN = 4;
	// const OEM = 5; // Duplicate.

	const NO_ENCRYPTION = 1;
	const TLS_PSK = 2;
	const TLS_CERTIFICATE = 4;

	const NO = 0;
	const YES = 1;

	const ZABBIX = 1;
	const SNMP = 2;
	const IPMI = 3;
	const JMX = 4;

	const INV_MODE_DISABLED = '-1'; // Duplicate.
	const INV_MODE_MANUAL = 0;
	const INV_MODE_AUTOMATIC = 1;

	const TRIGGER_EXPRESSION = 0;
	const TRIGGER_RECOVERY_EXPRESSION = 1;
	const TRIGGER_NONE = 2; // Duplicate.

	const TRIGGER_DISABLED = 0; // Duplicate.
	const TRIGGER_TAG_VALUE = 1;

	const NOT_CLASSIFIED = 0;
	const INFO = 1;
	const WARNING = 2;
	const AVERAGE = 3;
	const HIGH = 4;
	const DISASTER = 5;

	const SINGLE = 0;
	const MULTIPLE = 1;

	const CALCULATED = 0;
	const FIXED = 1;
	const ITEM = 2;

	const NORMAL = 0;
	const STACKED = 1;
	const PIE = 2;
	const EXPLODED = 3;

	const SINGLE_LINE = 0;
	const FILLED_REGION = 1;
	const BOLD_LINE = 2;
	const DOTTED_LINE = 3;
	const DASHED_LINE = 4;
	const GRADIENT_LINE = 5;

	const LEFT = 0;
	const RIGHT = 1;

	const MIN = 1;
	const AVG = 2;
	const MAX = 4;
	const ALL = 7;
	const LAST = 9;

	const SIMPLE = 0;
	const GRAPH_SUM = 2;

	const PASSWORD = 0;
	const PUBLIC_KEY = 1;

	// const NONE = 0; // Duplicate.
	const BASIC = 1;
	const NTLM = 2;

	const ALIAS = 4;
	const ASSET_TAG = 11;
	const CHASSIS = 28;
	const CONTACT = 23;
	const CONTRACT_NUMBER = 32;
	const DATE_HW_DECOMM = 47;
	const DATE_HW_EXPIRY = 46;
	const DATE_HW_INSTALL = 45;
	const DATE_HW_PURCHASE = 44;
	const DEPLOYMENT_STATUS = 34;
	const HARDWARE = 14;
	const HARDWARE_FULL = 15;
	const HOST_NETMASK = 39;
	const HOST_NETWORKS = 38;
	const HOST_ROUTER = 40;
	const HW_ARCH = 30;
	const INSTALLER_NAME = 33;
	const LOCATION = 24;
	const LOCATION_LAT = 25;
	const LOCATION_LON = 26;
	const MACADDRESS_A = 12;
	const MACADDRESS_B = 13;
	const MODEL = 29;
	const NAME = 3;
	const NOTES = 27;
	const OOB_IP = 41;
	const OOB_NETMASK = 42;
	const OOB_ROUTER = 43;
	const OS = 5;
	const OS_FULL = 6;
	const OS_SHORT = 7;
	const POC_1_CELL = 61;
	const POC_1_EMAIL = 58;
	const POC_1_NAME = 57;
	const POC_1_NOTES = 63;
	const POC_1_PHONE_A = 59;
	const POC_1_PHONE_B = 60;
	const POC_1_SCREEN = 62;
	const POC_2_CELL = 68;
	const POC_2_EMAIL = 65;
	const POC_2_NAME = 64;
	const POC_2_NOTES = 70;
	const POC_2_PHONE_A = 66;
	const POC_2_PHONE_B = 67;
	const POC_2_SCREEN = 69;
	const SERIALNO_A = 8;
	const SERIALNO_B = 9;
	const SITE_ADDRESS_A = 48;
	const SITE_ADDRESS_B = 49;
	const SITE_ADDRESS_C = 50;
	const SITE_CITY = 51;
	const SITE_COUNTRY = 53;
	const SITE_NOTES = 56;
	const SITE_RACK = 55;
	const SITE_STATE = 52;
	const SITE_ZIP = 54;
	const SOFTWARE = 16;
	const SOFTWARE_APP_A = 18;
	const SOFTWARE_APP_B = 19;
	const SOFTWARE_APP_C = 20;
	const SOFTWARE_APP_D = 21;
	const SOFTWARE_APP_E = 22;
	const SOFTWARE_FULL = 17;
	const TAG = 10;
	const TYPE = 1;
	const TYPE_FULL = 2;
	const URL_A = 35;
	const URL_B = 36;
	const URL_C = 37;
	const VENDOR = 31;

	const RAW = 0;
	const JSON = 1;

	const XML = 2;

	const GET = 0;
	const POST = 1;
	const PUT = 2;
	const HEAD = 3;

	const BODY = 0;
	const HEADERS = 1;
	const BOTH = 2;


	const SNMPV3_MD5 = 0;
	const SNMPV3_SHA = 1;

	const DES = 0;
	const AES = 1;

	const NOAUTHNOPRIV = 0;
	const AUTHNOPRIV = 1;
	const AUTHPRIV = 2;

	const ITEM_TYPE_ZABBIX_PASSIVE = 0;
	const ITEM_TYPE_SNMPV1 = 1;
	const ITEM_TYPE_TRAP = 2;
	const ITEM_TYPE_SIMPLE = 3; // Duplicate.
	const ITEM_TYPE_SNMPV2 = 4;
	const ITEM_TYPE_INTERNAL = 5;
	const ITEM_TYPE_SNMPV3 = 6;
	const ITEM_TYPE_ZABBIX_ACTIVE = 7;
	const ITEM_TYPE_AGGREGATE = 8;
	const ITEM_TYPE_EXTERNAL = 10;
	const ITEM_TYPE_ODBC = 11;
	const ITEM_TYPE_IPMI = 12; // Duplicate.
	const ITEM_TYPE_SSH = 13;
	const ITEM_TYPE_TELNET = 14;
	const ITEM_TYPE_CALCULATED = 15; // Duplicate.
	const ITEM_TYPE_JMX = 16; // Duplicate.
	const ITEM_TYPE_SNMP_TRAP = 17;
	const ITEM_TYPE_DEPENDENT = 18;
	const ITEM_TYPE_HTTP_AGENT = 19;

	const FLOAT = 0;
	const CHAR = 1;
	const LOG = 2;
	const UNSIGNED = 3;
	const TEXT = 4;

	const ORIGINAL_ERROR = 0;
	const DISCARD_VALUE = 1;
	const CUSTOM_VALUE = 2;
	const CUSTOM_ERROR = 3;

	const MULTIPLIER = 1;
	const RTRIM = 2;
	const LTRIM = 3;
	const TRIM = 4;
	const REGEX = 5;
	const BOOL_TO_DECIMAL = 6;
	const OCTAL_TO_DECIMAL = 7;
	const HEX_TO_DECIMAL = 8;
	const SIMPLE_CHANGE = 9;
	const CHANGE_PER_SECOND = 10;
	const XMLPATH = 11;
	const JSONPATH = 12;
	const IN_RANGE = 13;
	const MATCHES_REGEX = 14;
	const NOT_MATCHES_REGEX = 15;
	const CHECK_JSON_ERROR = 16;
	const CHECK_XML_ERROR = 17;
	const CHECK_REGEX_ERROR = 18;
	const DISCARD_UNCHANGED = 19;
	const DISCARD_UNCHANGED_HEARTBEAT = 20;
	const JAVASCRIPT = 21;
	const PROMETHEUS_PATTERN = 22;
	const PROMETHEUS_TO_JSON = 23;

	const AND_OR = 0;
	const XML_AND = 1;
	const XML_OR = 2;
	const FORMULA = 3;

	const CONDITION_MATCHES_REGEX = 8; // Duplicate.
	const CONDITION_NOT_MATCHES_REGEX = 9; // Duplicate.

	public static $subtags = [
		'groups' => 'group',
		'templates' => 'template',
		'hosts' => 'host',
		'interfaces' => 'interface',
		'applications' => 'application',
		'items' => 'item',
		'discovery_rules' => 'discovery_rule',
		'conditions' => 'condition',
		'item_prototypes' => 'item_prototype',
		'application_prototypes' => 'application_prototype',
		'trigger_prototypes' => 'trigger_prototype',
		'graph_prototypes' => 'graph_prototype',
		'host_prototypes' => 'host_prototype',
		'group_links' => 'group_link',
		'group_prototypes' => 'group_prototype',
		'triggers' => 'trigger',
		'dependencies' => 'dependency',
		'screen_items' => 'screen_item',
		'macros' => 'macro',
		'screens' => 'screen',
		'images' => 'image',
		'graphs' => 'graph',
		'graph_items' => 'graph_item',
		'maps' => 'map',
		'urls' => 'url',
		'selements' => 'selement',
		'elements' => 'element',
		'links' => 'link',
		'linktriggers' => 'linktrigger',
		'value_maps' => 'value_map',
		'mappings' => 'mapping',
		'httptests' => 'httptest',
		'steps' => 'step',
		'tags' => 'tag',
		'preprocessing' => 'step',
		'headers' => 'header',
		'variables' => 'variable',
		'query_fields' => 'query_field',
		'posts' => 'post_field',
		'shapes' => 'shape',
		'lines' => 'line',
		'headers' => 'header',
		'lld_macro_paths' => 'lld_macro_path',
		'tls_accept' => 'option'
	];
}
