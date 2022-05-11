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
 * Validate import data from Zabbix 6.0.x.
 */
class C60XmlValidator extends CXmlValidatorGeneral {

	private $PREPROCESSING_STEP_TYPE = [
		CXmlConstantValue::MULTIPLIER => CXmlConstantName::MULTIPLIER,
		CXmlConstantValue::RTRIM => CXmlConstantName::RTRIM,
		CXmlConstantValue::LTRIM => CXmlConstantName::LTRIM,
		CXmlConstantValue::TRIM => CXmlConstantName::TRIM,
		CXmlConstantValue::REGEX => CXmlConstantName::REGEX,
		CXmlConstantValue::BOOL_TO_DECIMAL => CXmlConstantName::BOOL_TO_DECIMAL,
		CXmlConstantValue::OCTAL_TO_DECIMAL => CXmlConstantName::OCTAL_TO_DECIMAL,
		CXmlConstantValue::HEX_TO_DECIMAL => CXmlConstantName::HEX_TO_DECIMAL,
		CXmlConstantValue::SIMPLE_CHANGE => CXmlConstantName::SIMPLE_CHANGE,
		CXmlConstantValue::CHANGE_PER_SECOND => CXmlConstantName::CHANGE_PER_SECOND,
		CXmlConstantValue::XMLPATH => CXmlConstantName::XMLPATH,
		CXmlConstantValue::JSONPATH => CXmlConstantName::JSONPATH,
		CXmlConstantValue::IN_RANGE => CXmlConstantName::IN_RANGE,
		CXmlConstantValue::MATCHES_REGEX => CXmlConstantName::MATCHES_REGEX,
		CXmlConstantValue::NOT_MATCHES_REGEX => CXmlConstantName::NOT_MATCHES_REGEX,
		CXmlConstantValue::CHECK_JSON_ERROR => CXmlConstantName::CHECK_JSON_ERROR,
		CXmlConstantValue::CHECK_XML_ERROR => CXmlConstantName::CHECK_XML_ERROR,
		CXmlConstantValue::CHECK_REGEX_ERROR => CXmlConstantName::CHECK_REGEX_ERROR,
		CXmlConstantValue::CHECK_NOT_SUPPORTED => CXmlConstantName::CHECK_NOT_SUPPORTED,
		CXmlConstantValue::DISCARD_UNCHANGED => CXmlConstantName::DISCARD_UNCHANGED,
		CXmlConstantValue::DISCARD_UNCHANGED_HEARTBEAT => CXmlConstantName::DISCARD_UNCHANGED_HEARTBEAT,
		CXmlConstantValue::JAVASCRIPT => CXmlConstantName::JAVASCRIPT,
		CXmlConstantValue::PROMETHEUS_PATTERN => CXmlConstantName::PROMETHEUS_PATTERN,
		CXmlConstantValue::PROMETHEUS_TO_JSON => CXmlConstantName::PROMETHEUS_TO_JSON,
		CXmlConstantValue::CSV_TO_JSON => CXmlConstantName::CSV_TO_JSON,
		CXmlConstantValue::STR_REPLACE => CXmlConstantName::STR_REPLACE,
		CXmlConstantValue::XML_TO_JSON => CXmlConstantName::XML_TO_JSON
	];

	private $PREPROCESSING_STEP_TYPE_DRULE = [
		CXmlConstantValue::REGEX => CXmlConstantName::REGEX,
		CXmlConstantValue::XMLPATH => CXmlConstantName::XMLPATH,
		CXmlConstantValue::JSONPATH => CXmlConstantName::JSONPATH,
		CXmlConstantValue::NOT_MATCHES_REGEX => CXmlConstantName::NOT_MATCHES_REGEX,
		CXmlConstantValue::CHECK_JSON_ERROR => CXmlConstantName::CHECK_JSON_ERROR,
		CXmlConstantValue::CHECK_XML_ERROR => CXmlConstantName::CHECK_XML_ERROR,
		CXmlConstantValue::DISCARD_UNCHANGED_HEARTBEAT => CXmlConstantName::DISCARD_UNCHANGED_HEARTBEAT,
		CXmlConstantValue::JAVASCRIPT => CXmlConstantName::JAVASCRIPT,
		CXmlConstantValue::PROMETHEUS_TO_JSON => CXmlConstantName::PROMETHEUS_TO_JSON,
		CXmlConstantValue::CSV_TO_JSON => CXmlConstantName::CSV_TO_JSON,
		CXmlConstantValue::STR_REPLACE => CXmlConstantName::STR_REPLACE,
		CXmlConstantValue::XML_TO_JSON => CXmlConstantName::XML_TO_JSON
	];

	private $GRAPH_GRAPH_ITEM_CALC_FNC = [
		CXmlConstantValue::MIN => CXmlConstantName::MIN,
		CXmlConstantValue::AVG => CXmlConstantName::AVG,
		CXmlConstantValue::MAX => CXmlConstantName::MAX,
		CXmlConstantValue::ALL => CXmlConstantName::ALL,
		CXmlConstantValue::LAST => CXmlConstantName::LAST
	];

	private $GRAPH_GRAPH_ITEM_DRAWTYPE = [
		CXmlConstantValue::SINGLE_LINE => CXmlConstantName::SINGLE_LINE,
		CXmlConstantValue::FILLED_REGION => CXmlConstantName::FILLED_REGION,
		CXmlConstantValue::BOLD_LINE => CXmlConstantName::BOLD_LINE,
		CXmlConstantValue::DOTTED_LINE => CXmlConstantName::DOTTED_LINE,
		CXmlConstantValue::DASHED_LINE => CXmlConstantName::DASHED_LINE,
		CXmlConstantValue::GRADIENT_LINE => CXmlConstantName::GRADIENT_LINE
	];

	private $GRAPH_TYPE = [
		CXmlConstantValue::NORMAL => CXmlConstantName::NORMAL,
		CXmlConstantValue::STACKED => CXmlConstantName::STACKED,
		CXmlConstantValue::PIE => CXmlConstantName::PIE,
		CXmlConstantValue::EXPLODED => CXmlConstantName::EXPLODED
	];

	private $GRAPH_Y_TYPE = [
		CXmlConstantValue::CALCULATED => CXmlConstantName::CALCULATED,
		CXmlConstantValue::FIXED => CXmlConstantName::FIXED,
		CXmlConstantValue::ITEM => CXmlConstantName::ITEM
	];

	private $GRAPH_GRAPH_ITEM_YAXISSIDE = [
		CXmlConstantValue::LEFT => CXmlConstantName::LEFT,
		CXmlConstantValue::RIGHT => CXmlConstantName::RIGHT
	];

	private $GRAPH_GRAPH_ITEM_TYPE = [
		CXmlConstantValue::SIMPLE => CXmlConstantName::SIMPLE,
		CXmlConstantValue::GRAPH_SUM => CXmlConstantName::GRAPH_SUM
	];

	private $ITEM_INVENTORY_LINK = [
		CXmlConstantValue::NONE => CXmlConstantName::NONE,
		CXmlConstantValue::ALIAS => CXmlConstantName::ALIAS,
		CXmlConstantValue::ASSET_TAG => CXmlConstantName::ASSET_TAG,
		CXmlConstantValue::CHASSIS => CXmlConstantName::CHASSIS,
		CXmlConstantValue::CONTACT => CXmlConstantName::CONTACT,
		CXmlConstantValue::CONTRACT_NUMBER => CXmlConstantName::CONTRACT_NUMBER,
		CXmlConstantValue::DATE_HW_DECOMM => CXmlConstantName::DATE_HW_DECOMM,
		CXmlConstantValue::DATE_HW_EXPIRY => CXmlConstantName::DATE_HW_EXPIRY,
		CXmlConstantValue::DATE_HW_INSTALL => CXmlConstantName::DATE_HW_INSTALL,
		CXmlConstantValue::DATE_HW_PURCHASE => CXmlConstantName::DATE_HW_PURCHASE,
		CXmlConstantValue::DEPLOYMENT_STATUS => CXmlConstantName::DEPLOYMENT_STATUS,
		CXmlConstantValue::HARDWARE => CXmlConstantName::HARDWARE,
		CXmlConstantValue::HARDWARE_FULL => CXmlConstantName::HARDWARE_FULL,
		CXmlConstantValue::HOST_NETMASK => CXmlConstantName::HOST_NETMASK,
		CXmlConstantValue::HOST_NETWORKS => CXmlConstantName::HOST_NETWORKS,
		CXmlConstantValue::HOST_ROUTER => CXmlConstantName::HOST_ROUTER,
		CXmlConstantValue::HW_ARCH => CXmlConstantName::HW_ARCH,
		CXmlConstantValue::INSTALLER_NAME => CXmlConstantName::INSTALLER_NAME,
		CXmlConstantValue::LOCATION => CXmlConstantName::LOCATION,
		CXmlConstantValue::LOCATION_LAT => CXmlConstantName::LOCATION_LAT,
		CXmlConstantValue::LOCATION_LON => CXmlConstantName::LOCATION_LON,
		CXmlConstantValue::MACADDRESS_A => CXmlConstantName::MACADDRESS_A,
		CXmlConstantValue::MACADDRESS_B => CXmlConstantName::MACADDRESS_B,
		CXmlConstantValue::MODEL => CXmlConstantName::MODEL,
		CXmlConstantValue::NAME => CXmlConstantName::NAME,
		CXmlConstantValue::NOTES => CXmlConstantName::NOTES,
		CXmlConstantValue::OOB_IP => CXmlConstantName::OOB_IP,
		CXmlConstantValue::OOB_NETMASK => CXmlConstantName::OOB_NETMASK,
		CXmlConstantValue::OOB_ROUTER => CXmlConstantName::OOB_ROUTER,
		CXmlConstantValue::OS => CXmlConstantName::OS,
		CXmlConstantValue::OS_FULL => CXmlConstantName::OS_FULL,
		CXmlConstantValue::OS_SHORT => CXmlConstantName::OS_SHORT,
		CXmlConstantValue::POC_1_CELL => CXmlConstantName::POC_1_CELL,
		CXmlConstantValue::POC_1_EMAIL => CXmlConstantName::POC_1_EMAIL,
		CXmlConstantValue::POC_1_NAME => CXmlConstantName::POC_1_NAME,
		CXmlConstantValue::POC_1_NOTES => CXmlConstantName::POC_1_NOTES,
		CXmlConstantValue::POC_1_PHONE_A => CXmlConstantName::POC_1_PHONE_A,
		CXmlConstantValue::POC_1_PHONE_B => CXmlConstantName::POC_1_PHONE_B,
		CXmlConstantValue::POC_1_SCREEN => CXmlConstantName::POC_1_SCREEN,
		CXmlConstantValue::POC_2_CELL => CXmlConstantName::POC_2_CELL,
		CXmlConstantValue::POC_2_EMAIL => CXmlConstantName::POC_2_EMAIL,
		CXmlConstantValue::POC_2_NAME => CXmlConstantName::POC_2_NAME,
		CXmlConstantValue::POC_2_NOTES => CXmlConstantName::POC_2_NOTES,
		CXmlConstantValue::POC_2_PHONE_A => CXmlConstantName::POC_2_PHONE_A,
		CXmlConstantValue::POC_2_PHONE_B => CXmlConstantName::POC_2_PHONE_B,
		CXmlConstantValue::POC_2_SCREEN => CXmlConstantName::POC_2_SCREEN,
		CXmlConstantValue::SERIALNO_A => CXmlConstantName::SERIALNO_A,
		CXmlConstantValue::SERIALNO_B => CXmlConstantName::SERIALNO_B,
		CXmlConstantValue::SITE_ADDRESS_A => CXmlConstantName::SITE_ADDRESS_A,
		CXmlConstantValue::SITE_ADDRESS_B => CXmlConstantName::SITE_ADDRESS_B,
		CXmlConstantValue::SITE_ADDRESS_C => CXmlConstantName::SITE_ADDRESS_C,
		CXmlConstantValue::SITE_CITY => CXmlConstantName::SITE_CITY,
		CXmlConstantValue::SITE_COUNTRY => CXmlConstantName::SITE_COUNTRY,
		CXmlConstantValue::SITE_NOTES => CXmlConstantName::SITE_NOTES,
		CXmlConstantValue::SITE_RACK => CXmlConstantName::SITE_RACK,
		CXmlConstantValue::SITE_STATE => CXmlConstantName::SITE_STATE,
		CXmlConstantValue::SITE_ZIP => CXmlConstantName::SITE_ZIP,
		CXmlConstantValue::SOFTWARE => CXmlConstantName::SOFTWARE,
		CXmlConstantValue::SOFTWARE_APP_A => CXmlConstantName::SOFTWARE_APP_A,
		CXmlConstantValue::SOFTWARE_APP_B => CXmlConstantName::SOFTWARE_APP_B,
		CXmlConstantValue::SOFTWARE_APP_C => CXmlConstantName::SOFTWARE_APP_C,
		CXmlConstantValue::SOFTWARE_APP_D => CXmlConstantName::SOFTWARE_APP_D,
		CXmlConstantValue::SOFTWARE_APP_E => CXmlConstantName::SOFTWARE_APP_E,
		CXmlConstantValue::SOFTWARE_FULL => CXmlConstantName::SOFTWARE_FULL,
		CXmlConstantValue::TAG => CXmlConstantName::TAG,
		CXmlConstantValue::TYPE => CXmlConstantName::TYPE,
		CXmlConstantValue::TYPE_FULL => CXmlConstantName::TYPE_FULL,
		CXmlConstantValue::URL_A => CXmlConstantName::URL_A,
		CXmlConstantValue::URL_B => CXmlConstantName::URL_B,
		CXmlConstantValue::URL_C => CXmlConstantName::URL_C,
		CXmlConstantValue::VENDOR => CXmlConstantName::VENDOR
	];

	private $ITEM_POST_TYPE = [
		CXmlConstantValue::RAW => CXmlConstantName::RAW,
		CXmlConstantValue::JSON => CXmlConstantName::JSON,
		CXmlConstantValue::XML => CXmlConstantName::XML
	];

	private $ITEM_PREPROCESSING_ERROR_HANDLER = [
		CXmlConstantValue::ORIGINAL_ERROR => CXmlConstantName::ORIGINAL_ERROR,
		CXmlConstantValue::DISCARD_VALUE => CXmlConstantName::DISCARD_VALUE,
		CXmlConstantValue::CUSTOM_VALUE => CXmlConstantName::CUSTOM_VALUE,
		CXmlConstantValue::CUSTOM_ERROR => CXmlConstantName::CUSTOM_ERROR
	];

	private $ITEM_REQUEST_METHOD = [
		CXmlConstantValue::GET => CXmlConstantName::GET,
		CXmlConstantValue::POST => CXmlConstantName::POST,
		CXmlConstantValue::PUT => CXmlConstantName::PUT,
		CXmlConstantValue::HEAD => CXmlConstantName::HEAD
	];

	private $ITEM_RETRIEVE_MODE = [
		CXmlConstantValue::BODY => CXmlConstantName::BODY,
		CXmlConstantValue::HEADERS => CXmlConstantName::HEADERS,
		CXmlConstantValue::BOTH => CXmlConstantName::BOTH
	];

	private $ITEM_SNMPV3_SECURITYLEVEL = [
		CXmlConstantValue::NOAUTHNOPRIV => CXmlConstantName::NOAUTHNOPRIV,
		CXmlConstantValue::AUTHNOPRIV => CXmlConstantName::AUTHNOPRIV,
		CXmlConstantValue::AUTHPRIV => CXmlConstantName::AUTHPRIV
	];

	private $ITEM_TYPE = [
		CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE => CXmlConstantName::ZABBIX_PASSIVE,
		CXmlConstantValue::ITEM_TYPE_TRAP => CXmlConstantName::TRAP,
		CXmlConstantValue::ITEM_TYPE_SIMPLE => CXmlConstantName::SIMPLE,
		CXmlConstantValue::ITEM_TYPE_INTERNAL => CXmlConstantName::INTERNAL,
		CXmlConstantValue::ITEM_TYPE_ZABBIX_ACTIVE => CXmlConstantName::ZABBIX_ACTIVE,
		CXmlConstantValue::ITEM_TYPE_EXTERNAL => CXmlConstantName::EXTERNAL,
		CXmlConstantValue::ITEM_TYPE_ODBC => CXmlConstantName::ODBC,
		CXmlConstantValue::ITEM_TYPE_IPMI => CXmlConstantName::IPMI,
		CXmlConstantValue::ITEM_TYPE_SSH => CXmlConstantName::SSH,
		CXmlConstantValue::ITEM_TYPE_TELNET => CXmlConstantName::TELNET,
		CXmlConstantValue::ITEM_TYPE_CALCULATED => CXmlConstantName::CALCULATED,
		CXmlConstantValue::ITEM_TYPE_JMX => CXmlConstantName::JMX,
		CXmlConstantValue::ITEM_TYPE_SNMP_TRAP => CXmlConstantName::SNMP_TRAP,
		CXmlConstantValue::ITEM_TYPE_DEPENDENT => CXmlConstantName::DEPENDENT,
		CXmlConstantValue::ITEM_TYPE_HTTP_AGENT => CXmlConstantName::HTTP_AGENT,
		CXmlConstantValue::ITEM_TYPE_SNMP => CXmlConstantName::SNMP_AGENT,
		CXmlConstantValue::ITEM_TYPE_SCRIPT => CXmlConstantName::SCRIPT
	];

	private $ITEM_TYPE_DRULE = [
		CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE => CXmlConstantName::ZABBIX_PASSIVE,
		CXmlConstantValue::ITEM_TYPE_TRAP => CXmlConstantName::TRAP,
		CXmlConstantValue::ITEM_TYPE_SIMPLE => CXmlConstantName::SIMPLE,
		CXmlConstantValue::ITEM_TYPE_INTERNAL => CXmlConstantName::INTERNAL,
		CXmlConstantValue::ITEM_TYPE_ZABBIX_ACTIVE => CXmlConstantName::ZABBIX_ACTIVE,
		CXmlConstantValue::ITEM_TYPE_EXTERNAL => CXmlConstantName::EXTERNAL,
		CXmlConstantValue::ITEM_TYPE_ODBC => CXmlConstantName::ODBC,
		CXmlConstantValue::ITEM_TYPE_IPMI => CXmlConstantName::IPMI,
		CXmlConstantValue::ITEM_TYPE_SSH => CXmlConstantName::SSH,
		CXmlConstantValue::ITEM_TYPE_TELNET => CXmlConstantName::TELNET,
		CXmlConstantValue::ITEM_TYPE_JMX => CXmlConstantName::JMX,
		CXmlConstantValue::ITEM_TYPE_DEPENDENT => CXmlConstantName::DEPENDENT,
		CXmlConstantValue::ITEM_TYPE_HTTP_AGENT => CXmlConstantName::HTTP_AGENT,
		CXmlConstantValue::ITEM_TYPE_SNMP => CXmlConstantName::SNMP_AGENT,
		CXmlConstantValue::ITEM_TYPE_SCRIPT => CXmlConstantName::SCRIPT
	];

	private $ITEM_VALUE_TYPE = [
		CXmlConstantValue::FLOAT => CXmlConstantName::FLOAT,
		CXmlConstantValue::CHAR => CXmlConstantName::CHAR,
		CXmlConstantValue::LOG => CXmlConstantName::LOG,
		CXmlConstantValue::UNSIGNED => CXmlConstantName::UNSIGNED,
		CXmlConstantValue::TEXT => CXmlConstantName::TEXT
	];

	private $TRIGGER_PRIORITY = [
		CXmlConstantValue::NOT_CLASSIFIED => CXmlConstantName::NOT_CLASSIFIED,
		CXmlConstantValue::INFO => CXmlConstantName::INFO,
		CXmlConstantValue::WARNING => CXmlConstantName::WARNING,
		CXmlConstantValue::AVERAGE => CXmlConstantName::AVERAGE,
		CXmlConstantValue::HIGH => CXmlConstantName::HIGH,
		CXmlConstantValue::DISASTER => CXmlConstantName::DISASTER
	];

	private $TRIGGER_RECOVERY_MODE = [
		CXmlConstantValue::TRIGGER_EXPRESSION => CXmlConstantName::EXPRESSION,
		CXmlConstantValue::TRIGGER_RECOVERY_EXPRESSION => CXmlConstantName::RECOVERY_EXPRESSION,
		CXmlConstantValue::TRIGGER_NONE => CXmlConstantName::NONE
	];

	private $MEDIA_TYPE = [
		CXmlConstantValue::MEDIA_TYPE_EMAIL => CXmlConstantName::EMAIL,
		CXmlConstantValue::MEDIA_TYPE_SCRIPT => CXmlConstantName::SCRIPT,
		CXmlConstantValue::MEDIA_TYPE_SMS => CXmlConstantName::SMS,
		CXmlConstantValue::MEDIA_TYPE_WEBHOOK => CXmlConstantName::WEBHOOK
	];

	private $SMTP_AUTHENTICATION = [
		CXmlConstantValue::SMTP_AUTHENTICATION_NONE => CXmlConstantName::SMTP_AUTHENTICATION_NONE,
		CXmlConstantValue::SMTP_AUTHENTICATION_PASSWORD => CXmlConstantName::SMTP_AUTHENTICATION_PASSWORD
	];

	private $SNMPV3_AUTHPROTOCOL = [
		CXmlConstantValue::SNMPV3_MD5 => CXmlConstantName::MD5,
		CXmlConstantValue::SNMPV3_SHA1 => CXmlConstantName::SHA1,
		CXmlConstantValue::SNMPV3_SHA224 => CXmlConstantName::SHA224,
		CXmlConstantValue::SNMPV3_SHA256 => CXmlConstantName::SHA256,
		CXmlConstantValue::SNMPV3_SHA384 => CXmlConstantName::SHA384,
		CXmlConstantValue::SNMPV3_SHA512 => CXmlConstantName::SHA512
	];

	private $SNMPV3_PRIVPROTOCOL = [
		CXmlConstantValue::SNMPV3_DES => CXmlConstantName::DES,
		CXmlConstantValue::SNMPV3_AES128 => CXmlConstantName::AES128,
		CXmlConstantValue::SNMPV3_AES192 => CXmlConstantName::AES192,
		CXmlConstantValue::SNMPV3_AES256 => CXmlConstantName::AES256,
		CXmlConstantValue::SNMPV3_AES192C => CXmlConstantName::AES192C,
		CXmlConstantValue::SNMPV3_AES256C => CXmlConstantName::AES256C
	];

	private $EVENT_SOURCE = [
		CXmlConstantValue::EVENT_SOURCE_TRIGGERS => CXmlConstantName::TRIGGERS,
		CXmlConstantValue::EVENT_SOURCE_DISCOVERY => CXmlConstantName::DISCOVERY,
		CXmlConstantValue::EVENT_SOURCE_AUTOREGISTRATION => CXmlConstantName::AUTOREGISTRATION,
		CXmlConstantValue::EVENT_SOURCE_INTERNAL => CXmlConstantName::INTERNAL,
		CXmlConstantValue::EVENT_SOURCE_SERVICE => CXmlConstantName::SERVICE
	];

	private $OPERATION_MODE = [
		CXmlConstantValue::OPERATION_MODE_PROBLEM => CXmlConstantName::PROBLEM,
		CXmlConstantValue::OPERATION_MODE_RECOVERY => CXmlConstantName::RECOVERY,
		CXmlConstantValue::OPERATION_MODE_UPDATE => CXmlConstantName::UPDATE
	];

	private $LLD_OVERRIDE_STOP = [
		CXmlConstantValue::LLD_OVERRIDE_STOP_NO => CXmlConstantName::LLD_OVERRIDE_STOP_NO,
		CXmlConstantValue::LLD_OVERRIDE_STOP_YES => CXmlConstantName::LLD_OVERRIDE_STOP_YES
	];

	private $EVALTPYE = [
		CXmlConstantValue::AND_OR => CXmlConstantName::AND_OR,
		CXmlConstantValue::XML_AND => CXmlConstantName::XML_AND,
		CXmlConstantValue::XML_OR => CXmlConstantName::XML_OR,
		CXmlConstantValue::FORMULA => CXmlConstantName::FORMULA
	];

	private $FILTER_CONDITION_OPERATOR = [
		CXmlConstantValue::CONDITION_MATCHES_REGEX => CXmlConstantName::MATCHES_REGEX,
		CXmlConstantValue::CONDITION_NOT_MATCHES_REGEX => CXmlConstantName::NOT_MATCHES_REGEX,
		CXmlConstantValue::CONDITION_EXISTS => CXmlConstantName::EXISTS,
		CXmlConstantValue::CONDITION_NOT_EXISTS => CXmlConstantName::NOT_EXISTS
	];

	private $LLD_OVERRIDE_OPERATION_OBJECT = [
		CXmlConstantValue::LLD_OVERRIDE_OPERATION_OBJECT_ITEM_PROTOTYPE => CXmlConstantName::LLD_OVERRIDE_OPERATION_OBJECT_ITEM_PROTOTYPE,
		CXmlConstantValue::LLD_OVERRIDE_OPERATION_OBJECT_TRIGGER_PROTOTYPE => CXmlConstantName::LLD_OVERRIDE_OPERATION_OBJECT_TRIGGER_PROTOTYPE,
		CXmlConstantValue::LLD_OVERRIDE_OPERATION_OBJECT_GRAPH_PROTOTYPE => CXmlConstantName::LLD_OVERRIDE_OPERATION_OBJECT_GRAPH_PROTOTYPE,
		CXmlConstantValue::LLD_OVERRIDE_OPERATION_OBJECT_HOST_PROTOTYPE => CXmlConstantName::LLD_OVERRIDE_OPERATION_OBJECT_HOST_PROTOTYPE
	];

	private $CONDITION_OPERATOR = [
		CXmlConstantValue::CONDITION_OPERATOR_EQUAL => CXmlConstantName::CONDITION_OPERATOR_EQUAL,
		CXmlConstantValue::CONDITION_OPERATOR_NOT_EQUAL => CXmlConstantName::CONDITION_OPERATOR_NOT_EQUAL,
		CXmlConstantValue::CONDITION_OPERATOR_LIKE => CXmlConstantName::CONDITION_OPERATOR_LIKE,
		CXmlConstantValue::CONDITION_OPERATOR_NOT_LIKE => CXmlConstantName::CONDITION_OPERATOR_NOT_LIKE,
		CXmlConstantValue::CONDITION_OPERATOR_REGEXP => CXmlConstantName::CONDITION_OPERATOR_REGEXP,
		CXmlConstantValue::CONDITION_OPERATOR_NOT_REGEXP => CXmlConstantName::CONDITION_OPERATOR_NOT_REGEXP
	];

	private $INVENTORY_MODE = [
		CXmlConstantValue::INV_MODE_DISABLED => CXmlConstantName::DISABLED,
		CXmlConstantValue::INV_MODE_MANUAL => CXmlConstantName::MANUAL,
		CXmlConstantValue::INV_MODE_AUTOMATIC => CXmlConstantName::AUTOMATIC
	];

	private $CUSTOM_INTERFACES = [
		CXmlConstantValue::CUSTOM_INTERFACES_NO => CXmlConstantName::NO,
		CXmlConstantValue::CUSTOM_INTERFACES_YES => CXmlConstantName::YES
	];

	private $DASHBOARD_WIDGET_TYPE = [
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_CLOCK => CXmlConstantName::DASHBOARD_WIDGET_TYPE_CLOCK,
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC => CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC,
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE => CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE,
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_ITEM => CXmlConstantName::DASHBOARD_WIDGET_TYPE_ITEM,
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT => CXmlConstantName::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT,
		CXmlConstantValue::DASHBOARD_WIDGET_TYPE_URL => CXmlConstantName::DASHBOARD_WIDGET_TYPE_URL
	];

	private $DASHBOARD_WIDGET_FIELD_TYPE = [
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_STRING => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_HOST => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_HOST,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_ITEM => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH,
		CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
	];

	private $VALUEMAP_MAPPING_TYPE = [
		CXmlConstantValue::MAPPING_EQUAL => CXmlConstantName::MAPPING_EQUAL,
		CXmlConstantValue::MAPPING_GREATER_EQUAL => CXmlConstantName::MAPPING_GREATER_EQUAL,
		CXmlConstantValue::MAPPING_LESS_EQUAL => CXmlConstantName::MAPPING_LESS_EQUAL,
		CXmlConstantValue::MAPPING_IN_RANGE => CXmlConstantName::MAPPING_IN_RANGE,
		CXmlConstantValue::MAPPING_REGEXP => CXmlConstantName::MAPPING_REGEXP,
		CXmlConstantValue::MAPPING_DEFAULT => CXmlConstantName::MAPPING_DEFAULT
	];

	private $HTTP_TEST_AUTHENTICATION = [
		CXmlConstantValue::NONE => CXmlConstantName::NONE,
		CXmlConstantValue::BASIC => CXmlConstantName::BASIC,
		CXmlConstantValue::NTLM => CXmlConstantName::NTLM,
		CXmlConstantValue::KERBEROS => CXmlConstantName::KERBEROS
	];

	/**
	 * Get validation rules schema.
	 *
	 * @return array
	 */
	public function getSchema() {
		return ['type' => XML_ARRAY, 'rules' => [
			'version' =>				['type' => XML_STRING | XML_REQUIRED],
			'date' =>					['type' => XML_STRING, 'ex_validate' => [$this, 'validateDateTime']],
			'groups' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => [
				'group' =>					['type' => XML_ARRAY, 'rules' => [
					'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
					'name' =>					['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'hosts' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => [
				'host' =>					['type' => XML_ARRAY, 'rules' => [
					'host' =>					['type' => XML_STRING | XML_REQUIRED],
					'name' =>					['type' => XML_STRING, 'default' => ''],
					'description' =>			['type' => XML_STRING, 'default' => ''],
					'proxy' =>					['type' => XML_ARRAY, 'rules' => [
						'name' =>					['type' => XML_STRING | XML_REQUIRED]
					]],
					'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
					'ipmi_authtype' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::XML_DEFAULT, 'in' => [CXmlConstantValue::XML_DEFAULT => CXmlConstantName::XML_DEFAULT, CXmlConstantValue::NONE => CXmlConstantName::NONE, CXmlConstantValue::MD2 => CXmlConstantName::MD2, CXmlConstantValue::MD5 => CXmlConstantName::MD5, CXmlConstantValue::STRAIGHT => CXmlConstantName::STRAIGHT, CXmlConstantValue::OEM => CXmlConstantName::OEM, CXmlConstantValue::RMCP_PLUS => CXmlConstantName::RMCP_PLUS]],
					'ipmi_privilege' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::USER, 'in' => [CXmlConstantValue::CALLBACK => CXmlConstantName::CALLBACK, CXmlConstantValue::USER => CXmlConstantName::USER, CXmlConstantValue::OPERATOR => CXmlConstantName::OPERATOR, CXmlConstantValue::ADMIN => CXmlConstantName::ADMIN, CXmlConstantValue::OEM => CXmlConstantName::OEM]],
					'ipmi_username' =>			['type' => XML_STRING, 'default' => ''],
					'ipmi_password' =>			['type' => XML_STRING, 'default' => ''],
					'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
						'template' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'groups' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group', 'rules' => [
						'group' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'interfaces' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => [
						'interface' =>				['type' => XML_ARRAY, 'rules' => [
							'default' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ZABBIX, 'in' => [CXmlConstantValue::ZABBIX => CXmlConstantName::ZABBIX, CXmlConstantValue::SNMP => CXmlConstantName::SNMP, CXmlConstantValue::IPMI => CXmlConstantName::IPMI, CXmlConstantValue::JMX => CXmlConstantName::JMX]],
							'useip' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ip' =>						['type' => XML_STRING, 'default' => '127.0.0.1'],
							'dns' =>					['type' => XML_STRING, 'default' => ''],
							'port' =>					['type' => XML_STRING, 'default' => '10050'],
							'details' =>				['type' => XML_ARRAY, 'rules' => [
								'version' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SNMP_V2, 'in' => [CXmlConstantValue::SNMP_V1 => CXmlConstantName::SNMPV1, CXmlConstantValue::SNMP_V2 => CXmlConstantName::SNMPV2, CXmlConstantValue::SNMP_V3 => CXmlConstantName::SNMPV3]],
								'community' =>				['type' => XML_STRING, 'default' => ''],
								'contextname' =>			['type' => XML_STRING, 'default' => ''],
								'securityname' =>			['type' => XML_STRING, 'default' => ''],
								'securitylevel' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NOAUTHNOPRIV, 'in' => $this->ITEM_SNMPV3_SECURITYLEVEL],
								'authprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_MD5, 'in' => $this->SNMPV3_AUTHPROTOCOL],
								'authpassphrase' =>			['type' => XML_STRING, 'default' => ''],
								'privprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_DES, 'in' => $this->SNMPV3_PRIVPROTOCOL],
								'privpassphrase' =>			['type' => XML_STRING, 'default' => ''],
								'bulk' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]]
							]],
							'interface_ref' =>			['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'items' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
						'item' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE],
							'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'history' =>				['type' => XML_STRING, 'default' => '90d'],
							'trends' =>					['type' => XML_STRING, 'default' => '365d'],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'value_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::UNSIGNED, 'in' => $this->ITEM_VALUE_TYPE],
							'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
							'units' =>					['type' => XML_STRING, 'default' => ''],
							'params' =>					['type' => XML_STRING, 'default' => ''],
							'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
							'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
							'username' =>				['type' => XML_STRING, 'default' => ''],
							'password' =>				['type' => XML_STRING, 'default' => ''],
							'publickey' =>				['type' => XML_STRING, 'default' => ''],
							'privatekey' =>				['type' => XML_STRING, 'default' => ''],
							'description' =>			['type' => XML_STRING, 'default' => ''],
							'inventory_link' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->ITEM_INVENTORY_LINK],
							'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'logtimefmt' =>				['type' => XML_STRING, 'default' => ''],
							'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE],
									'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
										'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
									]],
									'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
									'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
								]]
							]],
							'interface_ref' =>			['type' => XML_STRING],
							'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
							'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'], 'rules' => [
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
							'url' =>					['type' => XML_STRING, 'default' => ''],
							'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
								'query_field' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
								'parameter' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'posts' =>					['type' => XML_STRING, 'default' => ''],
							'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
							'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
							'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
							'output_format' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::OUTPUT_FORMAT_RAW, 'in' => [CXmlConstantValue::OUTPUT_FORMAT_RAW => CXmlConstantName::RAW, CXmlConstantValue::OUTPUT_FORMAT_JSON => CXmlConstantName::JSON]],
							'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
								'tag' =>					['type' => XML_ARRAY, 'rules' => [
									'tag' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'triggers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
								'trigger' =>				['type' => XML_ARRAY, 'rules' => [
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
									'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
									'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
									'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'event_name' =>				['type' => XML_STRING, 'default' => ''],
									'opdata' =>					['type' => XML_STRING, 'default' => ''],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
									'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
										'dependency' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]]
								]]
							]]
						]]
					]],
					'discovery_rules' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
						'discovery_rule' =>			['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE_DRULE],
							'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
							'params' =>					['type' => XML_STRING, 'default' => ''],
							'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
							'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
							'username' =>				['type' => XML_STRING, 'default' => ''],
							'password' =>				['type' => XML_STRING, 'default' => ''],
							'publickey' =>				['type' => XML_STRING, 'default' => ''],
							'privatekey' =>				['type' => XML_STRING, 'default' => ''],
							'filter' =>					['type' => XML_ARRAY, 'import' => [$this, 'itemFilterImport'], 'rules' => [
								'evaltype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AND_OR, 'in' => $this->EVALTPYE],
								'formula' =>				['type' => XML_STRING, 'default' => ''],
								'conditions' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'condition', 'rules' => [
									'condition' =>				['type' => XML_ARRAY, 'rules' => [
										'macro' =>					['type' => XML_STRING | XML_REQUIRED],
										'value' =>					['type' => XML_STRING, 'default' => ''],
										'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_MATCHES_REGEX, 'in' => $this->FILTER_CONDITION_OPERATOR],
										'formulaid' =>				['type' => XML_STRING | XML_REQUIRED]
									]]
								]]
							]],
							'lifetime' =>				['type' => XML_STRING, 'default' => '30d'],
							'description' =>			['type' => XML_STRING, 'default' => ''],
							'interface_ref' =>			['type' => XML_STRING],
							'item_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'item_prototype', 'rules' => [
								'item_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE],
									'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
									'key' =>					['type' => XML_STRING | XML_REQUIRED],
									'delay' =>					['type' => XML_STRING, 'default' => '1m'],
									'history' =>				['type' => XML_STRING, 'default' => '90d'],
									'trends' =>					['type' => XML_STRING, 'default' => '365d'],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_DISCOVER, 'in' => [CXmlConstantValue::ITEM_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::ITEM_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'value_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::UNSIGNED, 'in' => $this->ITEM_VALUE_TYPE],
									'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
									'units' =>					['type' => XML_STRING, 'default' => ''],
									'params' =>					['type' => XML_STRING, 'default' => ''],
									'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
									'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
									'username' =>				['type' => XML_STRING, 'default' => ''],
									'password' =>				['type' => XML_STRING, 'default' => ''],
									'publickey' =>				['type' => XML_STRING, 'default' => ''],
									'privatekey' =>				['type' => XML_STRING, 'default' => ''],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'inventory_link' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->ITEM_INVENTORY_LINK],
									'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
										'name' =>					['type' => XML_STRING | XML_REQUIRED]
									]],
									'logtimefmt' =>				['type' => XML_STRING, 'default' => ''],
									'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
										'step' =>					['type' => XML_ARRAY, 'rules' => [
											'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE],
											'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
												'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
											]],
											'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
											'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'interface_ref' =>			['type' => XML_STRING],
									'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
									'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'],  'rules' => [
										'key' =>					['type' => XML_STRING | XML_REQUIRED]
									]],
									'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
										'query_field' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
										'parameter' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'posts' =>					['type' => XML_STRING, 'default' => ''],
									'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
									'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
									'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
									'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
										'header' =>					['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
									'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
									'output_format' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::OUTPUT_FORMAT_RAW, 'in' => [CXmlConstantValue::OUTPUT_FORMAT_RAW => CXmlConstantName::RAW, CXmlConstantValue::OUTPUT_FORMAT_JSON => CXmlConstantName::JSON]],
									'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
									'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
									'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
									'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger_prototype', 'rules' => [
										'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
											'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
											'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'event_name' =>				['type' => XML_STRING, 'default' => ''],
											'opdata' =>					['type' => XML_STRING, 'default' => ''],
											'url' =>					['type' => XML_STRING, 'default' => ''],
											'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
											'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISCOVER, 'in' => [CXmlConstantValue::TRIGGER_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::TRIGGER_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
											'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
											'description' =>			['type' => XML_STRING, 'default' => ''],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
											'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
												'dependency' =>				['type' => XML_ARRAY, 'rules' => [
													'name' =>					['type' => XML_STRING | XML_REQUIRED],
													'expression' =>				['type' => XML_STRING | XML_REQUIRED],
													'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
												]]
											]],
											'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
												'tag' =>					['type' => XML_ARRAY, 'rules' => [
													'tag' =>					['type' => XML_STRING | XML_REQUIRED],
													'value' =>					['type' => XML_STRING, 'default' => '']
												]]
											]]
										]]
									]]
								]]
							]],
							'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger_prototype', 'rules' => [
								'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
									'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
									'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
									'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'event_name' =>				['type' => XML_STRING, 'default' => ''],
									'opdata' =>					['type' => XML_STRING, 'default' => ''],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISCOVER, 'in' => [CXmlConstantValue::TRIGGER_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::TRIGGER_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
									'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
										'dependency' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]]
								]]
							]],
							'graph_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'graph_prototype', 'rules' => [
								'graph_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'width' =>					['type' => XML_STRING, 'default' => '900'],
									'height' =>					['type' => XML_STRING, 'default' => '200'],
									'yaxismin' =>				['type' => XML_STRING, 'default' => '0'],
									'yaxismax' =>				['type' => XML_STRING, 'default' => '100'],
									'show_work_period' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'show_triggers' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::NORMAL, 'in' => $this->GRAPH_TYPE],
									'show_legend' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'show_3d' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'percent_left' =>			['type' => XML_STRING, 'default' => '0'],
									'percent_right' =>			['type' => XML_STRING, 'default' => '0'],
									// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
									'ymin_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
									'ymin_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem'], 'export' => [$this, 'graphMinItemExport']],
									// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
									'ymax_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
									'ymax_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem'], 'export' => [$this, 'graphMaxItemExport']],
									'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'ex_validate' => [$this, 'validateGraphItems'], 'rules' => [
										'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
											'sortorder' =>				['type' => XML_STRING, 'default' => '0'],
											'drawtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE_LINE, 'in' => $this->GRAPH_GRAPH_ITEM_DRAWTYPE],
											'color' =>					['type' => XML_STRING, 'default' => '009600'],
											'yaxisside' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::LEFT, 'in' => $this->GRAPH_GRAPH_ITEM_YAXISSIDE],
											'calc_fnc' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AVG, 'in' => $this->GRAPH_GRAPH_ITEM_CALC_FNC],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SIMPLE, 'in' => $this->GRAPH_GRAPH_ITEM_TYPE],
											'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'host' =>					['type' => XML_STRING | XML_REQUIRED],
												'key' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::GRAPH_DISCOVER, 'in' => [CXmlConstantValue::GRAPH_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::GRAPH_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]]
								]]
							]],
							'host_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
								'host_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'host' =>					['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::HOST_DISCOVER, 'in' => [CXmlConstantValue::HOST_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::HOST_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'inventory_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::INV_MODE_MANUAL, 'in' => $this->INVENTORY_MODE],
									'group_links' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'group_link', 'rules' => [
										'group_link' =>				['type' => XML_ARRAY, 'rules' => [
											'group' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'name' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'group_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'group_prototype', 'rules' => [
										'group_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
										'template' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
										'macro' =>					['type' => XML_ARRAY, 'rules' => [
											'macro' =>					['type' => XML_STRING | XML_REQUIRED],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MACRO_TYPE_TEXT, 'in' => [CXmlConstantValue::MACRO_TYPE_TEXT => CXmlConstantName::MACRO_TYPE_TEXT, CXmlConstantValue::MACRO_TYPE_SECRET => CXmlConstantName::MACRO_TYPE_SECRET, CXmlConstantValue::MACRO_TYPE_VAULT => CXmlConstantName::MACRO_TYPE_VAULT]],
											'value' =>					['type' => XML_STRING, 'default' => ''],
											'description' =>			['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'custom_interfaces' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::CUSTOM_INTERFACES_NO, 'in' => $this->CUSTOM_INTERFACES],
									'interfaces' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => [
										'interface' =>				['type' => XML_ARRAY, 'rules' => [
											'default' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ZABBIX, 'in' => [CXmlConstantValue::ZABBIX => CXmlConstantName::ZABBIX, CXmlConstantValue::SNMP => CXmlConstantName::SNMP, CXmlConstantValue::IPMI => CXmlConstantName::IPMI, CXmlConstantValue::JMX => CXmlConstantName::JMX]],
											'useip' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'ip' =>						['type' => XML_STRING, 'default' => '127.0.0.1'],
											'dns' =>					['type' => XML_STRING, 'default' => ''],
											'port' =>					['type' => XML_STRING, 'default' => '10050'],
											'details' =>				['type' => XML_ARRAY, 'rules' => [
												'version' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SNMP_V2, 'in' => [CXmlConstantValue::SNMP_V1 => CXmlConstantName::SNMPV1, CXmlConstantValue::SNMP_V2 => CXmlConstantName::SNMPV2, CXmlConstantValue::SNMP_V3 => CXmlConstantName::SNMPV3]],
												'community' =>				['type' => XML_STRING, 'default' => ''],
												'contextname' =>			['type' => XML_STRING, 'default' => ''],
												'securityname' =>			['type' => XML_STRING, 'default' => ''],
												'securitylevel' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NOAUTHNOPRIV, 'in' => $this->ITEM_SNMPV3_SECURITYLEVEL],
												'authprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_MD5, 'in' => $this->SNMPV3_AUTHPROTOCOL],
												'authpassphrase' =>			['type' => XML_STRING, 'default' => ''],
												'privprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_DES, 'in' => $this->SNMPV3_PRIVPROTOCOL],
												'privpassphrase' =>			['type' => XML_STRING, 'default' => ''],
												'bulk' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]]
											]]
										]]
									]]
								]]
							]],
							'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
							'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'], 'rules' => [
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
							'url' =>					['type' => XML_STRING, 'default' => ''],
							'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
								'query_field' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
								'parameter' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'posts' =>					['type' => XML_STRING, 'default' => ''],
							'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
							'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
							'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
							'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'lld_macro_paths' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'lld_macro_path', 'rules' => [
								'lld_macro_path' =>			['type' => XML_ARRAY, 'rules' => [
									'lld_macro' =>				['type' => XML_STRING | XML_REQUIRED],
									'path' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE_DRULE],
									'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
										'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
									]],
									'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
									'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
								]]
							]],
							'overrides'	=>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'override', 'rules' => [
								'override' =>				['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'step' =>					['type' => XML_STRING | XML_REQUIRED],
									'stop' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::LLD_OVERRIDE_STOP_NO, 'in' => $this->LLD_OVERRIDE_STOP],
									'filter' =>					['type' => XML_ARRAY, 'rules' => [
										'evaltype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AND_OR, 'in' => $this->EVALTPYE],
										'formula' =>				['type' => XML_STRING, 'default' => ''],
										'conditions' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'condition', 'rules' => [
											'condition' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'macro' =>					['type' => XML_STRING | XML_REQUIRED],
												'value' =>					['type' => XML_STRING, 'default' => ''],
												'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_MATCHES_REGEX, 'in' => $this->FILTER_CONDITION_OPERATOR],
												'formulaid' =>				['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'operations' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'operation', 'rules' => [
										'operation' =>				['type' => XML_ARRAY, 'rules' => [
											'operationobject' =>		['type' => XML_STRING, 'in' => $this->LLD_OVERRIDE_OPERATION_OBJECT],
											'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_OPERATOR_EQUAL, 'in' => $this->CONDITION_OPERATOR],
											'value' =>					['type' => XML_STRING, 'default' => ''],
											'status' =>					['type' => XML_STRING, 'in' => [CXmlConstantValue::LLD_OVERRIDE_OPERATION_STATUS_ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::LLD_OVERRIDE_OPERATION_STATUS_DISABLED => CXmlConstantName::DISABLED]],
											'discover' =>				['type' => XML_STRING, 'in' => [CXmlConstantValue::LLD_OVERRIDE_OPERATION_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::LLD_OVERRIDE_OPERATION_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
											'delay' =>					['type' => XML_STRING, 'default' => ''],
											'history' =>				['type' => XML_STRING, 'default' => ''],
											'trends' =>					['type' => XML_STRING, 'default' => ''],
											'severity' =>				['type' => XML_STRING, 'in' => $this->TRIGGER_PRIORITY],
											'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
												'tag' =>					['type' => XML_ARRAY, 'rules' => [
													'tag' =>					['type' => XML_STRING | XML_REQUIRED],
													'value' =>					['type' => XML_STRING, 'default' => '']
												]]
											]],
											'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
												'template' =>				['type' => XML_ARRAY, 'rules' => [
													'name' =>					['type' => XML_STRING | XML_REQUIRED]
												]]
											]],
											'inventory_mode' =>			['type' => XML_STRING, 'in' => $this->INVENTORY_MODE]
										]]
									]]
								]]
							]]
						]]
					]],
					'httptests' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'httptest', 'rules' => [
						'httptest' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'attempts' =>				['type' => XML_STRING, 'default' => '1'],
							'agent' =>					['type' => XML_STRING, 'default' => 'Zabbix'],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'variables' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'variable', 'rules' => [
								'variable' =>				['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'authentication' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->HTTP_TEST_AUTHENTICATION],
							'http_user' =>				['type' => XML_STRING, 'default' => ''],
							'http_password' =>			['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'steps' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED],
									'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
										'query_field' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'posts' =>					['type' => 0, 'ex_validate' => [$this, 'validateHttpPosts']],
									'variables' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'variable', 'rules' => [
										'variable' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
										'header' =>					['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
									'timeout' =>				['type' => XML_STRING, 'default' => '15s'],
									'required' =>				['type' => XML_STRING, 'default' => ''],
									'status_codes' =>			['type' => XML_STRING, 'default' => '']
								]]
							]],
							'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
								'tag' =>					['type' => XML_ARRAY, 'rules' => [
									'tag' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]]
						]]
					]],
					'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
						'tag' =>					['type' => XML_ARRAY, 'rules' => [
							'tag' =>					['type' => XML_STRING | XML_REQUIRED],
							'value' =>					['type' => XML_STRING, 'default' => '']
						]]
					]],
					'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
						'macro' =>					['type' => XML_ARRAY, 'rules' => [
							'macro' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MACRO_TYPE_TEXT, 'in' => [CXmlConstantValue::MACRO_TYPE_TEXT => CXmlConstantName::MACRO_TYPE_TEXT, CXmlConstantValue::MACRO_TYPE_SECRET => CXmlConstantName::MACRO_TYPE_SECRET, CXmlConstantValue::MACRO_TYPE_VAULT => CXmlConstantName::MACRO_TYPE_VAULT]],
							'value' =>					['type' => XML_STRING, 'default' => ''],
							'description' =>			['type' => XML_STRING, 'default' => '']
						]]
					]],
					'inventory' =>				['type' => XML_ARRAY, 'rules' => [
						'type' =>					['type' => XML_STRING, 'default' => ''],
						'type_full' =>				['type' => XML_STRING, 'default' => ''],
						'name' =>					['type' => XML_STRING, 'default' => ''],
						'alias' =>					['type' => XML_STRING, 'default' => ''],
						'os' =>						['type' => XML_STRING, 'default' => ''],
						'os_full' =>				['type' => XML_STRING, 'default' => ''],
						'os_short' =>				['type' => XML_STRING, 'default' => ''],
						'serialno_a' =>				['type' => XML_STRING, 'default' => ''],
						'serialno_b' =>				['type' => XML_STRING, 'default' => ''],
						'tag' =>					['type' => XML_STRING, 'default' => ''],
						'asset_tag' =>				['type' => XML_STRING, 'default' => ''],
						'macaddress_a' =>			['type' => XML_STRING, 'default' => ''],
						'macaddress_b' =>			['type' => XML_STRING, 'default' => ''],
						'hardware' =>				['type' => XML_STRING, 'default' => ''],
						'hardware_full' =>			['type' => XML_STRING, 'default' => ''],
						'software' =>				['type' => XML_STRING, 'default' => ''],
						'software_full' =>			['type' => XML_STRING, 'default' => ''],
						'software_app_a' =>			['type' => XML_STRING, 'default' => ''],
						'software_app_b' =>			['type' => XML_STRING, 'default' => ''],
						'software_app_c' =>			['type' => XML_STRING, 'default' => ''],
						'software_app_d' =>			['type' => XML_STRING, 'default' => ''],
						'software_app_e' =>			['type' => XML_STRING, 'default' => ''],
						'contact' =>				['type' => XML_STRING, 'default' => ''],
						'location' =>				['type' => XML_STRING, 'default' => ''],
						'location_lat' =>			['type' => XML_STRING, 'default' => ''],
						'location_lon' =>			['type' => XML_STRING, 'default' => ''],
						'notes' =>					['type' => XML_STRING, 'default' => ''],
						'chassis' =>				['type' => XML_STRING, 'default' => ''],
						'model' =>					['type' => XML_STRING, 'default' => ''],
						'hw_arch' =>				['type' => XML_STRING, 'default' => ''],
						'vendor' =>					['type' => XML_STRING, 'default' => ''],
						'contract_number' =>		['type' => XML_STRING, 'default' => ''],
						'installer_name' =>			['type' => XML_STRING, 'default' => ''],
						'deployment_status' =>		['type' => XML_STRING, 'default' => ''],
						'url_a' =>					['type' => XML_STRING, 'default' => ''],
						'url_b' =>					['type' => XML_STRING, 'default' => ''],
						'url_c' =>					['type' => XML_STRING, 'default' => ''],
						'host_networks' =>			['type' => XML_STRING, 'default' => ''],
						'host_netmask' =>			['type' => XML_STRING, 'default' => ''],
						'host_router' =>			['type' => XML_STRING, 'default' => ''],
						'oob_ip' =>					['type' => XML_STRING, 'default' => ''],
						'oob_netmask' =>			['type' => XML_STRING, 'default' => ''],
						'oob_router' =>				['type' => XML_STRING, 'default' => ''],
						'date_hw_purchase' =>		['type' => XML_STRING, 'default' => ''],
						'date_hw_install' =>		['type' => XML_STRING, 'default' => ''],
						'date_hw_expiry' =>			['type' => XML_STRING, 'default' => ''],
						'date_hw_decomm' =>			['type' => XML_STRING, 'default' => ''],
						'site_address_a' =>			['type' => XML_STRING, 'default' => ''],
						'site_address_b' =>			['type' => XML_STRING, 'default' => ''],
						'site_address_c' =>			['type' => XML_STRING, 'default' => ''],
						'site_city' =>				['type' => XML_STRING, 'default' => ''],
						'site_state' =>				['type' => XML_STRING, 'default' => ''],
						'site_country' =>			['type' => XML_STRING, 'default' => ''],
						'site_zip' =>				['type' => XML_STRING, 'default' => ''],
						'site_rack' =>				['type' => XML_STRING, 'default' => ''],
						'site_notes' =>				['type' => XML_STRING, 'default' => ''],
						'poc_1_name' =>				['type' => XML_STRING, 'default' => ''],
						'poc_1_email' =>			['type' => XML_STRING, 'default' => ''],
						'poc_1_phone_a' =>			['type' => XML_STRING, 'default' => ''],
						'poc_1_phone_b' =>			['type' => XML_STRING, 'default' => ''],
						'poc_1_cell' =>				['type' => XML_STRING, 'default' => ''],
						'poc_1_screen' =>			['type' => XML_STRING, 'default' => ''],
						'poc_1_notes' =>			['type' => XML_STRING, 'default' => ''],
						'poc_2_name' =>				['type' => XML_STRING, 'default' => ''],
						'poc_2_email' =>			['type' => XML_STRING, 'default' => ''],
						'poc_2_phone_a' =>			['type' => XML_STRING, 'default' => ''],
						'poc_2_phone_b' =>			['type' => XML_STRING, 'default' => ''],
						'poc_2_cell' =>				['type' => XML_STRING, 'default' => ''],
						'poc_2_screen' =>			['type' => XML_STRING, 'default' => ''],
						'poc_2_notes' =>			['type' => XML_STRING, 'default' => '']
					]],
					'inventory_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::INV_MODE_MANUAL, 'in' => $this->INVENTORY_MODE],
					'valuemaps' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'valuemap', 'rules' => [
						'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'mappings' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'mapping', 'rules' => [
								'mapping' =>				['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MAPPING_EQUAL, 'in' => $this->VALUEMAP_MAPPING_TYPE],
									'value' =>					['type' => XML_STRING, 'default' => ''],
									'newvalue' =>				['type' => XML_STRING | XML_REQUIRED]
								]]
							]]
						]]
					]]
				]]
			]],
			'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
				'template' =>				['type' => XML_ARRAY, 'rules' => [
					'uuid' =>				['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
					'template' =>				['type' => XML_STRING | XML_REQUIRED],
					'name' =>					['type' => XML_STRING, 'default' => ''],
					'description' =>			['type' => XML_STRING, 'default' => ''],
					'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
						'template' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'groups' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group', 'rules' => [
						'group' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'items' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
						'item' =>					['type' => XML_ARRAY, 'rules' => [
							'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE],
							'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'history' =>				['type' => XML_STRING, 'default' => '90d'],
							'trends' =>					['type' => XML_STRING, 'default' => '365d'],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'value_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::UNSIGNED, 'in' => $this->ITEM_VALUE_TYPE],
							'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
							'units' =>					['type' => XML_STRING, 'default' => ''],
							'params' =>					['type' => XML_STRING, 'default' => ''],
							'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
							'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
							'username' =>				['type' => XML_STRING, 'default' => ''],
							'password' =>				['type' => XML_STRING, 'default' => ''],
							'publickey' =>				['type' => XML_STRING, 'default' => ''],
							'privatekey' =>				['type' => XML_STRING, 'default' => ''],
							'description' =>			['type' => XML_STRING, 'default' => ''],
							'inventory_link' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->ITEM_INVENTORY_LINK],
							'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'logtimefmt' =>				['type' => XML_STRING, 'default' => ''],
							'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE],
									'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
										'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
									]],
									'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
									'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
								]]
							]],
							'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
							'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'], 'rules' => [
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
							'url' =>					['type' => XML_STRING, 'default' => ''],
							'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
								'query_field' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
								'parameter' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'posts' =>					['type' => XML_STRING, 'default' => ''],
							'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
							'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
							'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
							'output_format' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::OUTPUT_FORMAT_RAW, 'in' => [CXmlConstantValue::OUTPUT_FORMAT_RAW => CXmlConstantName::RAW, CXmlConstantValue::OUTPUT_FORMAT_JSON => CXmlConstantName::JSON]],
							'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
								'tag' =>					['type' => XML_ARRAY, 'rules' => [
									'tag' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'triggers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
								'trigger' =>				['type' => XML_ARRAY, 'rules' => [
									'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
									'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
									'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
									'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'event_name' =>				['type' => XML_STRING, 'default' => ''],
									'opdata' =>					['type' => XML_STRING, 'default' => ''],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
									'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
										'dependency' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]]
								]]
							]]
						]]
					]],
					'discovery_rules' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
						'discovery_rule' =>			['type' => XML_ARRAY, 'rules' => [
							'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE_DRULE],
							'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
							'params' =>					['type' => XML_STRING, 'default' => ''],
							'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
							'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
							'username' =>				['type' => XML_STRING, 'default' => ''],
							'password' =>				['type' => XML_STRING, 'default' => ''],
							'publickey' =>				['type' => XML_STRING, 'default' => ''],
							'privatekey' =>				['type' => XML_STRING, 'default' => ''],
							'filter' =>					['type' => XML_ARRAY, 'import' => [$this, 'itemFilterImport'], 'rules' => [
								'evaltype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AND_OR, 'in' => $this->EVALTPYE],
								'formula' =>				['type' => XML_STRING, 'default' => ''],
								'conditions' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'condition', 'rules' => [
									'condition' =>				['type' => XML_ARRAY, 'rules' => [
										'macro' =>					['type' => XML_STRING | XML_REQUIRED],
										'value' =>					['type' => XML_STRING, 'default' => ''],
										'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_MATCHES_REGEX, 'in' => $this->FILTER_CONDITION_OPERATOR],
										'formulaid' =>				['type' => XML_STRING | XML_REQUIRED]
									]]
								]]
							]],
							'lifetime' =>				['type' => XML_STRING, 'default' => '30d'],
							'description' =>			['type' => XML_STRING, 'default' => ''],
							'item_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'item_prototype', 'rules' => [
								'item_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE, 'in' => $this->ITEM_TYPE],
									'snmp_oid' =>				['type' => XML_STRING, 'default' => ''],
									'key' =>					['type' => XML_STRING | XML_REQUIRED],
									'delay' =>					['type' => XML_STRING, 'default' => '1m'],
									'history' =>				['type' => XML_STRING, 'default' => '90d'],
									'trends' =>					['type' => XML_STRING, 'default' => '365d'],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::ITEM_DISCOVER, 'in' => [CXmlConstantValue::ITEM_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::ITEM_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'value_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::UNSIGNED, 'in' => $this->ITEM_VALUE_TYPE],
									'allowed_hosts' =>			['type' => XML_STRING, 'default' => ''],
									'units' =>					['type' => XML_STRING, 'default' => ''],
									'params' =>					['type' => XML_STRING, 'default' => ''],
									'ipmi_sensor' =>			['type' => XML_STRING, 'default' => ''],
									'authtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'ex_validate' => [$this, 'validateAuthType'], 'ex_rules' => [$this, 'getAuthTypeExtendedRules'], 'export' => [$this, 'itemAuthtypeExport']],
									'username' =>				['type' => XML_STRING, 'default' => ''],
									'password' =>				['type' => XML_STRING, 'default' => ''],
									'publickey' =>				['type' => XML_STRING, 'default' => ''],
									'privatekey' =>				['type' => XML_STRING, 'default' => ''],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'inventory_link' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->ITEM_INVENTORY_LINK],
									'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
										'name' =>					['type' => XML_STRING | XML_REQUIRED]
									]],
									'logtimefmt' =>				['type' => XML_STRING, 'default' => ''],
									'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
										'step' =>					['type' => XML_ARRAY, 'rules' => [
											'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE],
											'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
												'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
											]],
											'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
											'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
									'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'],  'rules' => [
										'key' =>					['type' => XML_STRING | XML_REQUIRED]
									]],
									'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
										'query_field' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
										'parameter' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'posts' =>					['type' => XML_STRING, 'default' => ''],
									'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
									'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
									'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
									'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
										'header' =>					['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
									'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
									'output_format' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::OUTPUT_FORMAT_RAW, 'in' => [CXmlConstantValue::OUTPUT_FORMAT_RAW => CXmlConstantName::RAW, CXmlConstantValue::OUTPUT_FORMAT_JSON => CXmlConstantName::JSON]],
									'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
									'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
									'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
									'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger_prototype', 'rules' => [
										'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
											'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
											'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'event_name' =>				['type' => XML_STRING, 'default' => ''],
											'opdata' =>					['type' => XML_STRING, 'default' => ''],
											'url' =>					['type' => XML_STRING, 'default' => ''],
											'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
											'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISCOVER, 'in' => [CXmlConstantValue::TRIGGER_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::TRIGGER_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
											'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
											'description' =>			['type' => XML_STRING, 'default' => ''],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
											'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
												'dependency' =>				['type' => XML_ARRAY, 'rules' => [
													'name' =>					['type' => XML_STRING | XML_REQUIRED],
													'expression' =>				['type' => XML_STRING | XML_REQUIRED],
													'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
												]]
											]],
											'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
												'tag' =>					['type' => XML_ARRAY, 'rules' => [
													'tag' =>					['type' => XML_STRING | XML_REQUIRED],
													'value' =>					['type' => XML_STRING, 'default' => '']
												]]
											]]
										]]
									]]
								]]
							]],
							'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger_prototype', 'rules' => [
								'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
									'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
									'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
									'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'event_name' =>				['type' => XML_STRING, 'default' => ''],
									'opdata' =>					['type' => XML_STRING, 'default' => ''],
									'url' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISCOVER, 'in' => [CXmlConstantValue::TRIGGER_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::TRIGGER_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
									'description' =>			['type' => XML_STRING, 'default' => ''],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
									'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
										'dependency' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'expression' =>				['type' => XML_STRING | XML_REQUIRED],
											'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]]
								]]
							]],
							'graph_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'graph_prototype', 'rules' => [
								'graph_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'width' =>					['type' => XML_STRING, 'default' => '900'],
									'height' =>					['type' => XML_STRING, 'default' => '200'],
									'yaxismin' =>				['type' => XML_STRING, 'default' => '0'],
									'yaxismax' =>				['type' => XML_STRING, 'default' => '100'],
									'show_work_period' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'show_triggers' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::NORMAL, 'in' => $this->GRAPH_TYPE],
									'show_legend' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'show_3d' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'percent_left' =>			['type' => XML_STRING, 'default' => '0'],
									'percent_right' =>			['type' => XML_STRING, 'default' => '0'],
									// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
									'ymin_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
									'ymin_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem'], 'export' => [$this, 'graphMinItemExport']],
									// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
									'ymax_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
									'ymax_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem'], 'export' => [$this, 'graphMaxItemExport']],
									'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'ex_validate' => [$this, 'validateGraphItems'], 'rules' => [
										'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
											'sortorder' =>				['type' => XML_STRING, 'default' => '0'],
											'drawtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE_LINE, 'in' => $this->GRAPH_GRAPH_ITEM_DRAWTYPE],
											'color' =>					['type' => XML_STRING, 'default' => '009600'],
											'yaxisside' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::LEFT, 'in' => $this->GRAPH_GRAPH_ITEM_YAXISSIDE],
											'calc_fnc' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AVG, 'in' => $this->GRAPH_GRAPH_ITEM_CALC_FNC],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SIMPLE, 'in' => $this->GRAPH_GRAPH_ITEM_TYPE],
											'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'host' =>					['type' => XML_STRING | XML_REQUIRED],
												'key' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::GRAPH_DISCOVER, 'in' => [CXmlConstantValue::GRAPH_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::GRAPH_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]]
								]]
							]],
							'host_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
								'host_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
									'host' =>					['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING, 'default' => ''],
									'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
									'discover' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::HOST_DISCOVER, 'in' => [CXmlConstantValue::HOST_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::HOST_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
									'group_links' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'group_link', 'rules' => [
										'group_link' =>				['type' => XML_ARRAY, 'rules' => [
											'group' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'name' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'group_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'group_prototype', 'rules' => [
										'group_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
										'template' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
										'macro' =>					['type' => XML_ARRAY, 'rules' => [
											'macro' =>					['type' => XML_STRING | XML_REQUIRED],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MACRO_TYPE_TEXT, 'in' => [CXmlConstantValue::MACRO_TYPE_TEXT => CXmlConstantName::MACRO_TYPE_TEXT, CXmlConstantValue::MACRO_TYPE_SECRET => CXmlConstantName::MACRO_TYPE_SECRET, CXmlConstantValue::MACRO_TYPE_VAULT => CXmlConstantName::MACRO_TYPE_VAULT]],
											'value' =>					['type' => XML_STRING, 'default' => ''],
											'description' =>			['type' => XML_STRING, 'default' => '']
										]]
									]],
									'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
										'tag' =>					['type' => XML_ARRAY, 'rules' => [
											'tag' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'custom_interfaces' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::CUSTOM_INTERFACES_NO, 'in' => $this->CUSTOM_INTERFACES],
									'interfaces' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => [
										'interface' =>				['type' => XML_ARRAY, 'rules' => [
											'default' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ZABBIX, 'in' => [CXmlConstantValue::ZABBIX => CXmlConstantName::ZABBIX, CXmlConstantValue::SNMP => CXmlConstantName::SNMP, CXmlConstantValue::IPMI => CXmlConstantName::IPMI, CXmlConstantValue::JMX => CXmlConstantName::JMX]],
											'useip' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'ip' =>						['type' => XML_STRING, 'default' => '127.0.0.1'],
											'dns' =>					['type' => XML_STRING, 'default' => ''],
											'port' =>					['type' => XML_STRING, 'default' => '10050'],
											'details' =>				['type' => XML_ARRAY, 'rules' => [
												'version' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SNMP_V2, 'in' => [CXmlConstantValue::SNMP_V1 => CXmlConstantName::SNMPV1, CXmlConstantValue::SNMP_V2 => CXmlConstantName::SNMPV2, CXmlConstantValue::SNMP_V3 => CXmlConstantName::SNMPV3]],
												'community' =>				['type' => XML_STRING, 'default' => ''],
												'contextname' =>			['type' => XML_STRING, 'default' => ''],
												'securityname' =>			['type' => XML_STRING, 'default' => ''],
												'securitylevel' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NOAUTHNOPRIV, 'in' => $this->ITEM_SNMPV3_SECURITYLEVEL],
												'authprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_MD5, 'in' => $this->SNMPV3_AUTHPROTOCOL],
												'authpassphrase' =>			['type' => XML_STRING, 'default' => ''],
												'privprotocol' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::SNMPV3_DES, 'in' => $this->SNMPV3_PRIVPROTOCOL],
												'privpassphrase' =>			['type' => XML_STRING, 'default' => ''],
												'bulk' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]]
											]]
										]]
									]],
									'inventory_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::INV_MODE_DISABLED, 'in' => $this->INVENTORY_MODE]
								]]
							]],
							'jmx_endpoint' =>			['type' => XML_STRING, 'default' => ''],
							'master_item' =>			['type' => XML_ARRAY, 'ex_validate' => [$this, 'validateMasterItem'], 'rules' => [
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'timeout' =>				['type' => XML_STRING, 'default' => '3s'],
							'url' =>					['type' => XML_STRING, 'default' => ''],
							'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
								'query_field' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'parameters' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
								'parameter' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]],
							'posts' =>					['type' => XML_STRING, 'default' => ''],
							'status_codes' =>			['type' => XML_STRING, 'default' => '200'],
							'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'post_type' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::RAW, 'in' => $this->ITEM_POST_TYPE],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
							'request_method' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::GET, 'in' => $this->ITEM_REQUEST_METHOD],
							'allow_traps' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'lld_macro_paths' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'lld_macro_path', 'rules' => [
								'lld_macro_path' =>			['type' => XML_ARRAY, 'rules' => [
									'lld_macro' =>				['type' => XML_STRING | XML_REQUIRED],
									'path' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'preprocessing' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->PREPROCESSING_STEP_TYPE_DRULE],
									'parameters' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'parameter', 'export' => [$this, 'preprocessingParametersExport'], 'rules' => [
										'parameter' =>				['type' => XML_STRING, 'flags' => CImportDataNormalizer::EOL_LF]
									]],
									'error_handler' =>			['type' => XML_STRING, 'ex_default' => [$this, 'defaultPreprocErrHandler'], 'ex_validate' => [$this, 'validatePreprocErrHandler'], 'in' => $this->ITEM_PREPROCESSING_ERROR_HANDLER],
									'error_handler_params' =>	['type' => XML_STRING, 'default' => '']
								]]
							]],
							'overrides'	=>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'override', 'rules' => [
								'override' =>				['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'step' =>					['type' => XML_STRING | XML_REQUIRED],
									'stop' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::LLD_OVERRIDE_STOP_NO, 'in' => $this->LLD_OVERRIDE_STOP],
									'filter' =>					['type' => XML_ARRAY, 'rules' => [
										'evaltype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AND_OR, 'in' => $this->EVALTPYE],
										'formula' =>				['type' => XML_STRING, 'default' => ''],
										'conditions' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'condition', 'rules' => [
											'condition' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'macro' =>					['type' => XML_STRING | XML_REQUIRED],
												'value' =>					['type' => XML_STRING, 'default' => ''],
												'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_MATCHES_REGEX, 'in' => $this->FILTER_CONDITION_OPERATOR],
												'formulaid' =>				['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'operations' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'operation', 'rules' => [
										'operation' =>				['type' => XML_ARRAY, 'rules' => [
											'operationobject' =>		['type' => XML_STRING, 'in' => $this->LLD_OVERRIDE_OPERATION_OBJECT],
											'operator' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::CONDITION_OPERATOR_EQUAL, 'in' => $this->CONDITION_OPERATOR],
											'value' =>					['type' => XML_STRING, 'default' => ''],
											'status' =>					['type' => XML_STRING, 'in' => [CXmlConstantValue::LLD_OVERRIDE_OPERATION_STATUS_ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::LLD_OVERRIDE_OPERATION_STATUS_DISABLED => CXmlConstantName::DISABLED]],
											'discover' =>				['type' => XML_STRING, 'in' => [CXmlConstantValue::LLD_OVERRIDE_OPERATION_DISCOVER => CXmlConstantName::DISCOVER, CXmlConstantValue::LLD_OVERRIDE_OPERATION_NO_DISCOVER => CXmlConstantName::NO_DISCOVER]],
											'delay' =>					['type' => XML_STRING, 'default' => ''],
											'history' =>				['type' => XML_STRING, 'default' => ''],
											'trends' =>					['type' => XML_STRING, 'default' => ''],
											'severity' =>				['type' => XML_STRING, 'in' => $this->TRIGGER_PRIORITY],
											'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
												'tag' =>					['type' => XML_ARRAY, 'rules' => [
													'tag' =>					['type' => XML_STRING | XML_REQUIRED],
													'value' =>					['type' => XML_STRING, 'default' => '']
												]]
											]],
											'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
												'template' =>				['type' => XML_ARRAY, 'rules' => [
													'name' =>					['type' => XML_STRING | XML_REQUIRED]
												]]
											]],
											'inventory_mode' =>			['type' => XML_STRING, 'in' => $this->INVENTORY_MODE]
										]]
									]]
								]]
							]]
						]]
					]],
					'httptests' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'httptest', 'rules' => [
						'httptest' =>				['type' => XML_ARRAY, 'rules' => [
							'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING, 'default' => '1m'],
							'attempts' =>				['type' => XML_STRING, 'default' => '1'],
							'agent' =>					['type' => XML_STRING, 'default' => 'Zabbix'],
							'http_proxy' =>				['type' => XML_STRING, 'default' => ''],
							'variables' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'variable', 'rules' => [
								'variable' =>				['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
								'header' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
							'authentication' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => $this->HTTP_TEST_AUTHENTICATION],
							'http_user' =>				['type' => XML_STRING, 'default' => ''],
							'http_password' =>			['type' => XML_STRING, 'default' => ''],
							'verify_peer' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'verify_host' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'ssl_cert_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_file' =>			['type' => XML_STRING, 'default' => ''],
							'ssl_key_password' =>		['type' => XML_STRING, 'default' => ''],
							'steps' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'step', 'rules' => [
								'step' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED],
									'query_fields' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'query_field', 'rules' => [
										'query_field' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING, 'default' => '']
										]]
									]],
									'posts' =>					['type' => 0, 'ex_validate' => [$this, 'validateHttpPosts']],
									'variables' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'variable', 'rules' => [
										'variable' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'headers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'header', 'rules' => [
										'header' =>					['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED],
											'value' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'follow_redirects' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
									'retrieve_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::BODY, 'in' => $this->ITEM_RETRIEVE_MODE],
									'timeout' =>				['type' => XML_STRING, 'default' => '15s'],
									'required' =>				['type' => XML_STRING, 'default' => ''],
									'status_codes' =>			['type' => XML_STRING, 'default' => '']
								]]
							]],
							'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
								'tag' =>					['type' => XML_ARRAY, 'rules' => [
									'tag' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING, 'default' => '']
								]]
							]]
						]]
					]],
					'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
						'tag' =>					['type' => XML_ARRAY, 'rules' => [
							'tag' =>					['type' => XML_STRING | XML_REQUIRED],
							'value' =>					['type' => XML_STRING, 'default' => '']
						]]
					]],
					'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
						'macro' =>					['type' => XML_ARRAY, 'rules' => [
							'macro' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MACRO_TYPE_TEXT, 'in' => [CXmlConstantValue::MACRO_TYPE_TEXT => CXmlConstantName::MACRO_TYPE_TEXT, CXmlConstantValue::MACRO_TYPE_SECRET => CXmlConstantName::MACRO_TYPE_SECRET, CXmlConstantValue::MACRO_TYPE_VAULT => CXmlConstantName::MACRO_TYPE_VAULT]],
							'value' =>					['type' => XML_STRING, 'default' => ''],
							'description' =>			['type' => XML_STRING, 'default' => '']
						]]
					]],
					'dashboards' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'dashboard', 'rules' => [
						'dashboard' =>				['type' => XML_ARRAY, 'rules' => [
							'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'display_period' =>				['type' => XML_STRING, 'default' => '30'],
							'auto_start' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
							'pages' =>						['type' => XML_INDEXED_ARRAY, 'prefix' => 'page', 'rules' => [
								'page' =>						['type' => XML_ARRAY, 'rules' => [
									'name' =>						['type' => XML_STRING, 'default' => ''],
									'display_period' =>				['type' => XML_STRING, 'default' => '0'],
									'widgets' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'widget', 'rules' => [
										'widget' =>						['type' => XML_ARRAY, 'rules' => [
											'type' =>						['type' => XML_STRING | XML_REQUIRED, 'in' => $this->DASHBOARD_WIDGET_TYPE],
											'name' =>						['type' => XML_STRING, 'default' => ''],
											'x' =>							['type' => XML_STRING, 'default' => '0'],
											'y' =>							['type' => XML_STRING, 'default' => '0'],
											'width' =>						['type' => XML_STRING, 'default' => '1'],
											'height' =>						['type' => XML_STRING, 'default' => '2'],
											'hide_header' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
											'fields' =>						['type' => XML_INDEXED_ARRAY, 'prefix' => 'field', 'rules' => [
												'field' =>						['type' => XML_ARRAY, 'rules' => [
													'type' =>						['type' => XML_STRING | XML_REQUIRED, 'in' => $this->DASHBOARD_WIDGET_FIELD_TYPE],
													'name' =>						['type' => XML_STRING | XML_REQUIRED],
													'value' =>						['type' => XML_REQUIRED, 'ex_validate' => [$this, 'validateWidgetFieldValue'], 'ex_rules' => [$this, 'getWidgetFieldValueExtendedRules']]
												]]
											]]
										]]
									]]
								]]
							]]
						]]
					]],
					'valuemaps' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'valuemap', 'rules' => [
						'valuemap' =>				['type' => XML_ARRAY, 'rules' => [
							'uuid' =>					['type' => XML_STRING | XML_REQUIRED, 'flags' => CImportDataNormalizer::LOWERCASE],
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'mappings' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'mapping', 'rules' => [
								'mapping' =>				['type' => XML_ARRAY, 'rules' => [
									'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::MAPPING_EQUAL, 'in' => $this->VALUEMAP_MAPPING_TYPE],
									'value' =>					['type' => XML_STRING, 'default' => ''],
									'newvalue' =>				['type' => XML_STRING | XML_REQUIRED]
								]]
							]]
						]]
					]]
				]]
			]],
			'triggers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
				'trigger' =>				['type' => XML_ARRAY, 'rules' => [
					'uuid' =>					['type' => XML_STRING, 'flags' => CImportDataNormalizer::LOWERCASE],
					'expression' =>				['type' => XML_STRING | XML_REQUIRED],
					'recovery_mode' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_EXPRESSION, 'in' => $this->TRIGGER_RECOVERY_MODE],
					'recovery_expression' =>	['type' => XML_STRING, 'default' => ''],
					'correlation_mode' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::TRIGGER_DISABLED, 'in' => [CXmlConstantValue::TRIGGER_DISABLED => CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_TAG_VALUE => CXmlConstantName::TAG_VALUE]],
					'correlation_tag' =>		['type' => XML_STRING, 'default' => ''],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'event_name' =>				['type' => XML_STRING, 'default' => ''],
					'opdata' =>					['type' => XML_STRING, 'default' => ''],
					'url' =>					['type' => XML_STRING, 'default' => ''],
					'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
					'priority' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NOT_CLASSIFIED, 'in' => $this->TRIGGER_PRIORITY],
					'description' =>			['type' => XML_STRING, 'default' => ''],
					'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE, 'in' => [CXmlConstantValue::SINGLE => CXmlConstantName::SINGLE, CXmlConstantValue::MULTIPLE => CXmlConstantName::MULTIPLE]],
					'manual_close' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
						'dependency' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'expression' =>				['type' => XML_STRING | XML_REQUIRED],
							'recovery_expression' =>	['type' => XML_STRING, 'default' => '']
						]]
					]],
					'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
						'tag' =>					['type' => XML_ARRAY, 'rules' => [
							'tag' =>					['type' => XML_STRING | XML_REQUIRED],
							'value' =>					['type' => XML_STRING, 'default' => '']
						]]
					]]
				]]
			]],
			'graphs' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => [
				'graph' =>					['type' => XML_ARRAY, 'rules' => [
					'uuid' =>					['type' => XML_STRING, 'flags' => CImportDataNormalizer::LOWERCASE],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'width' =>					['type' => XML_STRING, 'default' => '900'],
					'height' =>					['type' => XML_STRING, 'default' => '200'],
					'yaxismin' =>				['type' => XML_STRING, 'default' => '0'],
					'yaxismax' =>				['type' => XML_STRING, 'default' => '100'],
					'show_work_period' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'show_triggers' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::NORMAL, 'in' => $this->GRAPH_TYPE],
					'show_legend' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::YES, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'show_3d' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'percent_left' =>			['type' => XML_STRING, 'default' => '0'],
					'percent_right' =>			['type' => XML_STRING, 'default' => '0'],
					// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
					'ymin_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
					'ymin_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem'], 'export' => [$this, 'graphMinItemExport']],
					// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
					'ymax_type_1' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CALCULATED, 'in' => $this->GRAPH_Y_TYPE],
					'ymax_item_1' =>			['type' => 0, 'default' => '0', 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem'], 'export' => [$this, 'graphMaxItemExport']],
					'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'ex_validate' => [$this, 'validateGraphItems'], 'rules' => [
						'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
							'sortorder' =>				['type' => XML_STRING, 'default' => '0'],
							'drawtype' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::SINGLE_LINE, 'in' => $this->GRAPH_GRAPH_ITEM_DRAWTYPE],
							'color' =>					['type' => XML_STRING, 'default' => '009600'],
							'yaxisside' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::LEFT, 'in' => $this->GRAPH_GRAPH_ITEM_YAXISSIDE],
							'calc_fnc' =>				['type' => XML_STRING, 'default' => CXmlConstantValue::AVG, 'in' => $this->GRAPH_GRAPH_ITEM_CALC_FNC],
							'type' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::SIMPLE, 'in' => $this->GRAPH_GRAPH_ITEM_TYPE],
							'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'host' =>					['type' => XML_STRING | XML_REQUIRED],
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]]
						]]
					]]
				]]
			]],
			'images' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'image', 'rules' => [
				'image' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'imagetype' =>				['type' => XML_STRING | XML_REQUIRED],
					'encodedImage' =>			['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'maps' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'map', 'rules' => [
				'map' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'width' =>					['type' => XML_STRING | XML_REQUIRED],
					'height' =>					['type' => XML_STRING | XML_REQUIRED],
					'label_type' =>				['type' => XML_STRING | XML_REQUIRED],
					'label_location' =>			['type' => XML_STRING | XML_REQUIRED],
					'highlight' =>				['type' => XML_STRING | XML_REQUIRED],
					'expandproblem' =>			['type' => XML_STRING | XML_REQUIRED],
					'markelements' =>			['type' => XML_STRING | XML_REQUIRED],
					'show_unack' =>				['type' => XML_STRING | XML_REQUIRED],
					'severity_min' =>			['type' => XML_STRING | XML_REQUIRED],
					'show_suppressed' =>		['type' => XML_STRING | XML_REQUIRED],
					'grid_size' =>				['type' => XML_STRING | XML_REQUIRED],
					'grid_show' =>				['type' => XML_STRING | XML_REQUIRED],
					'grid_align' =>				['type' => XML_STRING | XML_REQUIRED],
					'label_format' =>			['type' => XML_STRING | XML_REQUIRED],
					'label_type_host' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_type_hostgroup' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_type_trigger' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_type_map' =>			['type' => XML_STRING | XML_REQUIRED],
					'label_type_image' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_host' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_hostgroup' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_string_trigger' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_string_map' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_image' =>		['type' => XML_STRING | XML_REQUIRED],
					'expand_macros' =>			['type' => XML_STRING | XML_REQUIRED],
					'background' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
						'name' =>					['type' => XML_STRING]
					]],
					'iconmap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
						'name' =>					['type' => XML_STRING]
					]],
					'urls' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'url', 'rules' => [
						'url' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'url' =>					['type' => XML_STRING | XML_REQUIRED],
							'elementtype' =>			['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'selements' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'selement', 'rules' => [
						'selement' =>				['type' => XML_ARRAY, 'rules' => [
							// The tag 'elementtype' should be validated before the 'elements' because it is used in 'ex_required' and 'ex_validate' methods.
							'elementtype' =>			['type' => XML_STRING | XML_REQUIRED],
							'elements' =>				['type' => 0, 'ex_required' => [$this, 'requiredMapElement'], 'ex_validate' => [$this, 'validateMapElements'], 'ex_rules' => [$this, 'getMapElementsExtendedRules'], 'export' => [$this, 'mapElementsExport']],
							'label' =>					['type' => XML_STRING | XML_REQUIRED],
							'label_location' =>			['type' => XML_STRING | XML_REQUIRED],
							'x' =>						['type' => XML_STRING | XML_REQUIRED],
							'y' =>						['type' => XML_STRING | XML_REQUIRED],
							'elementsubtype' =>			['type' => XML_STRING | XML_REQUIRED],
							'areatype' =>				['type' => XML_STRING | XML_REQUIRED],
							'width' =>					['type' => XML_STRING | XML_REQUIRED],
							'height' =>					['type' => XML_STRING | XML_REQUIRED],
							'viewtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'use_iconmap' =>			['type' => XML_STRING | XML_REQUIRED],
							'selementid' =>				['type' => XML_STRING | XML_REQUIRED],
							'icon_off' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'icon_on' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'icon_disabled' =>			['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'icon_maintenance' =>		['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'urls' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'url', 'rules' => [
								'url' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'evaltype' =>				['type' => XML_STRING],
							'tags' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'tag', 'rules' => [
								'tag' =>					['type' => XML_ARRAY, 'rules' => [
									'tag' =>					['type' => XML_STRING | XML_REQUIRED],
									'value' =>					['type' => XML_STRING],
									'operator' =>				['type' => XML_STRING]
								]]
							]]
						]]
					]],
					'shapes' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'shape', 'rules' => [
						'shape' =>				['type' => XML_ARRAY, 'rules' => [
							'type' =>				['type' => XML_STRING | XML_REQUIRED],
							'x' =>					['type' => XML_STRING | XML_REQUIRED],
							'y' =>					['type' => XML_STRING | XML_REQUIRED],
							'width' =>				['type' => XML_STRING | XML_REQUIRED],
							'height' =>				['type' => XML_STRING | XML_REQUIRED],
							'text' =>				['type' => XML_STRING | XML_REQUIRED],
							'font' =>				['type' => XML_STRING | XML_REQUIRED],
							'font_size' =>			['type' => XML_STRING | XML_REQUIRED],
							'font_color' =>			['type' => XML_STRING | XML_REQUIRED],
							'text_halign' =>		['type' => XML_STRING | XML_REQUIRED],
							'text_valign' =>		['type' => XML_STRING | XML_REQUIRED],
							'border_type' =>		['type' => XML_STRING | XML_REQUIRED],
							'border_width' =>		['type' => XML_STRING | XML_REQUIRED],
							'border_color' =>		['type' => XML_STRING | XML_REQUIRED],
							'background_color' =>	['type' => XML_STRING | XML_REQUIRED],
							'zindex' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'lines' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'line', 'rules' => [
						'line' =>				['type' => XML_ARRAY, 'rules' => [
							'x1' =>					['type' => XML_STRING | XML_REQUIRED],
							'y1' =>					['type' => XML_STRING | XML_REQUIRED],
							'x2' =>					['type' => XML_STRING | XML_REQUIRED],
							'y2' =>					['type' => XML_STRING | XML_REQUIRED],
							'line_type' =>			['type' => XML_STRING | XML_REQUIRED],
							'line_width' =>			['type' => XML_STRING | XML_REQUIRED],
							'line_color' =>			['type' => XML_STRING | XML_REQUIRED],
							'zindex' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'links' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'link', 'rules' => [
						'link' =>					['type' => XML_ARRAY, 'rules' => [
							'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'color' =>					['type' => XML_STRING | XML_REQUIRED],
							'label' =>					['type' => XML_STRING | XML_REQUIRED],
							'selementid1' =>			['type' => XML_STRING | XML_REQUIRED],
							'selementid2' =>			['type' => XML_STRING | XML_REQUIRED],
							'linktriggers' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'linktrigger', 'rules' => [
								'linktrigger' =>            ['type' => XML_ARRAY, 'rules' => [
									'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'color' =>					['type' => XML_STRING | XML_REQUIRED],
									'trigger' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
										'description' =>			['type' => XML_STRING | XML_REQUIRED],
										'expression' =>				['type' => XML_STRING | XML_REQUIRED],
										'recovery_expression' =>	['type' => XML_STRING | XML_REQUIRED]
									]]
								]]
							]]
						]]
					]]
				]]
			]],
			'media_types' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'media_type', 'rules' => [
				'media_type' =>				['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'type' =>					['type' => XML_STRING | XML_REQUIRED, 'in' => $this->MEDIA_TYPE],
					'smtp_server' =>			['type' => XML_STRING, 'default' => ''],
					'smtp_port' =>				['type' => XML_STRING, 'default' => '25'],
					'smtp_helo' =>				['type' => XML_STRING, 'default' => ''],
					'smtp_email' =>				['type' => XML_STRING, 'default' => ''],
					'smtp_security' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => [CXmlConstantValue::NONE => CXmlConstantName::NONE, CXmlConstantValue::STARTTLS => CXmlConstantName::STARTTLS, CXmlConstantValue::SSL_OR_TLS => CXmlConstantName::SSL_OR_TLS]],
					'smtp_verify_host' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'smtp_verify_peer' =>		['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'smtp_authentication' =>	['type' => XML_STRING, 'default' => CXmlConstantValue::SMTP_AUTHENTICATION_NONE, 'in' => $this->SMTP_AUTHENTICATION],
					'username' =>				['type' => XML_STRING, 'default' => ''],
					'password' =>				['type' => XML_STRING, 'default' => ''],
					'content_type' =>			['type' => XML_STRING, 'default' => CXmlConstantValue::CONTENT_TYPE_HTML, 'in' => [CXmlConstantValue::CONTENT_TYPE_TEXT => CXmlConstantName::CONTENT_TYPE_TEXT, CXmlConstantValue::CONTENT_TYPE_HTML => CXmlConstantName::CONTENT_TYPE_HTML]],
					'script_name' =>			['type' => XML_STRING, 'default' => ''],
					'parameters' =>				['type' => 0, 'ex_validate' => [$this, 'validateMediaTypeParameters'], 'ex_rules' => [$this, 'getMediaTypeParametersExtendedRules'], 'export' => [$this, 'mediaTypeParametersExport']],
					'gsm_modem' =>				['type' => XML_STRING, 'default' => ''],
					'status' =>					['type' => XML_STRING, 'default' => CXmlConstantValue::ENABLED, 'in' => [CXmlConstantValue::ENABLED => CXmlConstantName::ENABLED, CXmlConstantValue::DISABLED => CXmlConstantName::DISABLED]],
					'max_sessions' =>			['type' => XML_STRING, 'default' => '1'],
					'attempts' =>				['type' => XML_STRING, 'default' => '3'],
					'attempt_interval' =>		['type' => XML_STRING, 'default' => '10s'],
					'script' => 				['type' => XML_STRING, 'default' => ''],
					'timeout' => 				['type' => XML_STRING, 'default' => '30s'],
					'process_tags' => 			['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'show_event_menu' => 		['type' => XML_STRING, 'default' => CXmlConstantValue::NO, 'in' => [CXmlConstantValue::NO => CXmlConstantName::NO, CXmlConstantValue::YES => CXmlConstantName::YES]],
					'event_menu_url' => 		['type' => XML_STRING, 'default' => ''],
					'event_menu_name' => 		['type' => XML_STRING, 'default' => ''],
					'description' => 			['type' => XML_STRING, 'default' => ''],
					'message_templates' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'message_template', 'rules' => [
						'message_template' =>		['type' => XML_ARRAY, 'rules' => [
							'event_source' =>			['type' => XML_STRING | XML_REQUIRED, 'in' => $this->EVENT_SOURCE],
							'operation_mode' =>			['type' => XML_STRING | XML_REQUIRED, 'in' => $this->OPERATION_MODE],
							'subject' =>				['type' => XML_STRING, 'default' => ''],
							'message' =>				['type' => XML_STRING, 'default' => '']
						]]
					]]
				]]
			]]
		]];
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $data  Import data.
	 * @param string $path  XML path (for error reporting).
	 *
	 * @throws Exception if $data does not correspond to validation rules.
	 * @return array  Validator does some manipulations for the incoming data. For example, converts empty tags to an
	 *                array, if desired. Converted array is returned.
	 */
	public function validate(array $data, $path) {
		return $this->doValidate($this->getSchema(), $data, $path);
	}

	/**
	 * Validate date and time format.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path (for error reporting).
	 *
	 * @throws Exception if the date or time is invalid.
	 * @return string
	 */
	public function validateDateTime($data, ?array $parent_data, $path) {
		if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[01])T(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]Z$/', $data)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('YYYY-MM-DDThh:mm:ssZ', 'XML date and time format'))));
		}

		return $data;
	}

	/**
	 * Checking the map element for requirement.
	 *
	 * @param array|null $parent_data  Data's parent array.
	 *
	 * @return bool
	 */
	public function requiredMapElement(array $parent_data = null) {
		if (zbx_is_int($parent_data['elementtype'])) {
			switch ($parent_data['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
				case SYSMAP_ELEMENT_TYPE_MAP:
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					return true;
			}
		}

		return false;
	}

	/**
	 * Validate map elements.
	 *
	 * @param array|string $data         Import data.
	 * @param array|null   $parent_data  Data's parent array.
	 * @param string       $path         XML path.
	 *
	 * @throws Exception if the map elements are invalid.
	 * @return array|string
	 */
	public function validateMapElements($data, ?array $parent_data, $path) {
		$rules = $this->getMapElementsExtendedRules($parent_data);

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Chooses default value for preprocessing step error handler.
	 *
	 * @param array $parent_data  Data's parent array.
	 *
	 * @return int
	 */
	public function defaultPreprocErrHandler(array $parent_data): int {
		if ($parent_data['type'] == CXmlConstantValue::CHECK_NOT_SUPPORTED) {
			return CXmlConstantValue::DISCARD_VALUE;
		}

		return CXmlConstantValue::ORIGINAL_ERROR;
	}

	/**
	 * Validate "ymin_item_1" tag.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateYMinItem($data, ?array $parent_data, $path) {
		if (array_key_exists('ymin_type_1', $parent_data)) {
			if (($parent_data['ymin_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE || $parent_data['ymin_type_1'] == CXmlConstantName::ITEM)) {
				$rules = ['type' => XML_ARRAY, 'rules' => [
					'host' =>	['type' => XML_STRING | XML_REQUIRED],
					'key' =>	['type' => XML_STRING | XML_REQUIRED]
				]];
			}
			else {
				$rules = ['type' => XML_ARRAY, 'rules' => []];
			}
		}
		else {
			$rules = ['type' => XML_ARRAY, 'rules' => []];
		}

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate "ymax_item_1" tag.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateYMaxItem($data, ?array $parent_data, $path) {
		if (array_key_exists('ymax_type_1', $parent_data)) {
			if (($parent_data['ymax_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE || $parent_data['ymax_type_1'] == CXmlConstantName::ITEM)) {
				$rules = ['type' => XML_ARRAY, 'rules' => [
					'host' =>	['type' => XML_STRING | XML_REQUIRED],
					'key' =>	['type' => XML_STRING | XML_REQUIRED]
				]];
			}
			else {
				$rules = ['type' => XML_ARRAY, 'rules' => []];
			}
		}
		else {
			$rules = ['type' => XML_ARRAY, 'rules' => []];
		}

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate media type "parameters" tag.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateMediaTypeParameters($data, ?array $parent_data, $path) {
		$rules = $this->getMediaTypeParametersExtendedRules($parent_data);

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Transforms tags containing zero into an empty array.
	 *
	 * @param mixed $value
	 *
	 * @return mixed  Converted value.
	 */
	public function transformZero2Array($value) {
		return ($value === '0') ? [] : $value;
	}

	/**
	 * Validate "posts" tag of http test step.
	 *
	 * @param array|string $data         Import data.
	 * @param array|null   $parent_data  Data's parent array.
	 * @param string       $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateHttpPosts($data, ?array $parent_data, $path) {
		if (is_array($data)) {
			// Posts can be an HTTP pair array.
			$rules = ['type' => XML_INDEXED_ARRAY, 'prefix' => 'post_field', 'rules' => [
				'post_field' =>	['type' => XML_ARRAY, 'rules' => [
					'name' =>		['type' => XML_STRING | XML_REQUIRED],
					'value' =>		['type' => XML_STRING | XML_REQUIRED]
				]]
			]];
		}
		else {
			// Posts can be string.
			$rules = ['type' => XML_STRING, 'default' => ''];
		}

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate master item.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateMasterItem($data, ?array $parent_data, $path) {
		$prefix = substr(strrchr($path, '/'), 1);
		$rules = ['type' => XML_ARRAY | XML_REQUIRED, 'prefix' => $prefix, 'rules' => ['key' => ['type' => XML_STRING]]];

		if ($parent_data['type'] == ITEM_TYPE_DEPENDENT) {
			$rules['rules']['key']['type'] |= XML_REQUIRED;
		}

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate authtype.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateAuthType($data, ?array $parent_data, $path) {
		$rules = $this->getAuthTypeExtendedRules($parent_data);

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate graph_items tag.
	 *
	 * @param array      $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function validateGraphItems(array $data, ?array $parent_data, $path) {
		if (!$data) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('the tag "%1$s" is missing', 'graph_item')));
		}

		return $data;
	}

	/**
	 * Validate preprocessing step error handler.
	 *
	 * @param string|array $data         Import data.
	 * @param array|null   $parent_data  Data's parent array.
	 * @param string       $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return string|array
	 */
	public function validatePreprocErrHandler($data, ?array $parent_data, $path) {
		$in = $this->ITEM_PREPROCESSING_ERROR_HANDLER;

		if ($parent_data['type'] == CXmlConstantName::CHECK_NOT_SUPPORTED) {
			unset($in[CXmlConstantValue::ORIGINAL_ERROR]);
		}

		$rules = ['type' => XML_STRING, 'in' => $in];

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate widget field "value" tag.
	 *
	 * @param string|array $data         Import data.
	 * @param array|null   $parent_data  Data's parent array.
	 * @param string       $path         XML path.
	 *
	 * @throws Exception if the element is invalid.
	 * @return string|array
	 */
	public function validateWidgetFieldValue($data, ?array $parent_data, string $path) {
		$rules = $this->getWidgetFieldValueExtendedRules($parent_data);

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Get extended validation rules.
	 *
	 * @param array $data  Import data.
	 *
	 * @return array
	 */
	public function getAuthTypeExtendedRules(array $data) {
		if (array_key_exists('type', $data)) {
			if ($data['type'] == CXmlConstantValue::ITEM_TYPE_SSH || $data['type'] == CXmlConstantName::SSH) {
				return ['type' => XML_STRING, 'default' => CXmlConstantValue::PASSWORD, 'in' => [CXmlConstantValue::PASSWORD => CXmlConstantName::PASSWORD, CXmlConstantValue::PUBLIC_KEY => CXmlConstantName::PUBLIC_KEY]];
			}
		}

		return ['type' => XML_STRING, 'default' => CXmlConstantValue::NONE, 'in' => [CXmlConstantValue::NONE => CXmlConstantName::NONE, CXmlConstantValue::BASIC => CXmlConstantName::BASIC, CXmlConstantValue::NTLM => CXmlConstantName::NTLM, CXmlConstantValue::KERBEROS => CXmlConstantName::KERBEROS, CXmlConstantValue::DIGEST => CXmlConstantName::DIGEST]];
	}

	/**
	 * Get extended validation rules for map elements.
	 *
	 * @param array $data  Import data.
	 *
	 * @return array
	 */
	public function getMapElementsExtendedRules(array $data) {
		if (array_key_exists('elementtype', $data)) {
			switch ($data['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
					return ['type' => XML_INDEXED_ARRAY, 'prefix' => 'element', 'rules' => [
						'element' => ['type' => XML_ARRAY, 'rules' => [
							'host' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]];
					// no break;

				case SYSMAP_ELEMENT_TYPE_MAP:
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					return ['type' => XML_INDEXED_ARRAY, 'prefix' => 'element', 'rules' => [
						'element' => ['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]];
					// no break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					return ['type' => XML_INDEXED_ARRAY, 'prefix' => 'element', 'rules' => [
						'element' => ['type' => XML_ARRAY, 'rules' => [
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'expression' =>				['type' => XML_STRING | XML_REQUIRED],
							'recovery_expression' =>	['type' => XML_STRING | XML_REQUIRED]
						]]
					]];
					// no break;

				case SYSMAP_ELEMENT_TYPE_IMAGE:
					return ['type' => XML_ARRAY, 'rules' => []];
					// no break;
			}
		}

		return ['type' => XML_ARRAY, 'rules' => []];
	}

	/**
	 * Get extended validation rules for media type "parameters" tag.
	 *
	 * @param array $data  Import data.
	 *
	 * @return array
	 */
	public function getMediaTypeParametersExtendedRules(array $data) {
		switch ($data['type']) {
			case CXmlConstantName::SCRIPT:
			case CXmlConstantValue::MEDIA_TYPE_SCRIPT:
				return ['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
					'parameter' => ['type' => XML_STRING]
				]];

			case CXmlConstantName::WEBHOOK:
			case CXmlConstantValue::MEDIA_TYPE_WEBHOOK:
				return ['type' => XML_INDEXED_ARRAY, 'prefix' => 'parameter', 'rules' => [
					'parameter' => ['type' => XML_ARRAY, 'rules' => [
						'name' => ['type' => XML_STRING | XML_REQUIRED],
						'value' => ['type' => XML_STRING, 'default' => '']
					]]
				]];

			default:
				return ['type' => XML_ARRAY, 'rules' => []];
		}
	}

	/**
	 * Get extended validation rules for widget field "value" tag.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function getWidgetFieldValueExtendedRules(array $data): array {
		switch ($data['type']) {
			case CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_HOST:
			case CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_HOST:
				return ['type' => XML_ARRAY, 'rules' => [
					'host' => ['type' => XML_STRING | XML_REQUIRED]
				]];
			case CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM:
			case CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			case CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_ITEM:
			case CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
				return ['type' => XML_ARRAY, 'rules' => [
					'host' => ['type' => XML_STRING | XML_REQUIRED],
					'key' => ['type' => XML_STRING | XML_REQUIRED]
				]];
			case CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH:
			case CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			case CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH:
			case CXmlConstantValue::DASHBOARD_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
				return ['type' => XML_ARRAY, 'rules' => [
					'host' => ['type' => XML_STRING | XML_REQUIRED],
					'name' => ['type' => XML_STRING | XML_REQUIRED]
				]];
			default:
				return ['type' => XML_STRING | XML_REQUIRED];
		}
	}

	/**
	 * Export check for ymax_item_1 if ymax_type_1 === ITEM.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function graphMaxItemExport(array $data) {
		if ($data['ymax_type_1'] == CXmlConstantValue::ITEM
				&& array_key_exists('ymax_item_1', $data)
				&& (!array_key_exists('host', $data['ymax_item_1'])
					|| !array_key_exists('key', $data['ymax_item_1']))) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', 'ymax_item_1', _('an array is expected')));
		}

		return $data['ymax_item_1'];
	}

	/**
	 * Export check for ymin_item_1 if ymin_type_1 === ITEM.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function graphMinItemExport(array $data) {
		if ($data['ymin_type_1'] == CXmlConstantValue::ITEM
				&& array_key_exists('ymin_item_1', $data)
				&& (!array_key_exists('host', $data['ymin_item_1'])
					|| !array_key_exists('key', $data['ymin_item_1']))) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', 'ymin_item_1', _('an array is expected')));
		}

		return $data['ymin_item_1'];
	}

	/**
	 * Export check for authtype tag.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception if the element is invalid.
	 * @return string Tag constant.
	 */
	public function itemAuthtypeExport(array $data) {
		if ($data['type'] != CXmlConstantValue::ITEM_TYPE_HTTP_AGENT
				&& $data['type'] != CXmlConstantValue::ITEM_TYPE_SSH) {
			return CXmlConstantName::NONE;
		}

		$rules = $this->getAuthTypeExtendedRules($data);

		if (!array_key_exists($data['authtype'], $rules['in'])) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.',
				'authtype', _s('unexpected constant value "%1$s"', $data['type'])
			));
		}

		return $rules['in'][$data['authtype']];
	}

	/**
	 * Export transformation for media type "parameters" tag.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception if the element is invalid.
	 * @return array
	 */
	public function mediaTypeParametersExport(array $data) {
		switch ($data['type']) {
			case CXmlConstantValue::MEDIA_TYPE_SCRIPT:
				return $this->scriptParameterExport($data);

			case CXmlConstantValue::MEDIA_TYPE_WEBHOOK:
				return $data['parameters'];

			default:
				return [];
		}
	}

	/**
	 * Import check for filter tag.
	 * API validation throws an error when filter tag is an empty array.
	 *
	 * @param array $data  Import data.
	 *
	 * @return array
	 */
	public function itemFilterImport(array $data) {
		if (!array_key_exists('filter', $data)) {
			return [
				'conditions' => '',
				'evaltype' => CXmlConstantName::AND_OR,
				'formula' => ''
			];
		}

		return $data['filter'];
	}

	/**
	 * Converts script parameters to an array.
	 *
	 * @param array  $data               Export data.
	 * @param string $data['parameters]  Script parameters.
	 *
	 * @return array
	 */
	public function scriptParameterExport(array $data) {
		return explode("\n", substr($data['parameters'], 0, -1));
	}

	/**
	 * Function prepares map element for export builder.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception on invalid input.
	 * @return array
	 */
	public function mapElementsExport(array $data): array {
		$element_types = [SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP, SYSMAP_ELEMENT_TYPE_TRIGGER,
			SYSMAP_ELEMENT_TYPE_HOST_GROUP
		];

		if (!array_key_exists('elementtype', $data) || !in_array($data['elementtype'], $element_types)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.',
				'elementtype', _s('value must be one of %1$s', implode(', ', $element_types))
			));
		}

		if (!array_key_exists('elements', $data) || !is_array($data['elements'])) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', 'elements', _('an array is expected')));
		}

		return ($data['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			? array_map(function($trigger) {
					if (array_key_exists('expression', $trigger)) {
						$trigger['expression'] = str_replace("\r\n", "\n", $trigger['expression']);
					}
					if (array_key_exists('recovery_expression', $trigger)) {
						$trigger['recovery_expression'] = str_replace("\r\n", "\n", $trigger['recovery_expression']);
					}
					return $trigger;
				}, $data['elements'])
			: $data['elements'];
	}

	/**
	 * Function prepares preprocessing parameters for export builder.
	 *
	 * @param array $data  Export data.
	 *
	 * @throws Exception on invalid input.
	 * @return array
	 */
	public function preprocessingParametersExport(array $data): array {
		return $data['parameters'];
	}
}
