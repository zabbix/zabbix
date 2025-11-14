<?php declare(strict_types = 1);
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

define("ALL_PRINTABLE_ASCII", ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~');

define("REDUCTED_PRINTABLE_ASCII", '!"#$%&\'()*+,-./0123456789:;<=>?@[\\]^_`{|}~');

/**
 * Test suite for:
 *     1) built-in macros
 *     2) user macros
 *     3) expression macros
 *     4) macro functions
 *     This suite provides test coverage for macro resolution and substitution
 *     across events caused by:
 *
 *     - Triggers
 *     - Internal actions
 *     - Autoregistration actions
 *     - Service actions
 *     - Discovery actions
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_macros
 * @onAfter clearData
 */
class testMacros extends CIntegrationTest {

	private static $host_id;
	private static $trigger_id;
	private static $trigger_action_id;
	private static $alert_response;
	private static $event_response;

	private static $item_ids = [];
	private static $usermacro_ids = [];
	private static $globalmacro_ids = [];
	private static $action_ids = [];

	private static $event_tags_json;
	private static $trigger_expression_explain;
	private static $trigger_recovery_expression_explain;
	private static $trigger_expression;
	private static $trigger_recovery_expression;
	private static $event_name;
	private static $event_name_resolved;
	private static $BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED;
	private static $eventids;
	private static $metadata_file;

	const TRAPPER_ITEM_NAME = 'trap' . ALL_PRINTABLE_ASCII;
	const TRAPPER_ITEM_KEY = 'trap';
	const HOST_NAME = 'test_macros_host';
	const MESSAGE_PREFIX = 'message with expression macro ' . ALL_PRINTABLE_ASCII . ": ";
	const SUBJECT_PREFIX = 'subject with expression macro ' . ALL_PRINTABLE_ASCII . ": ";
	const MESSAGE_PREFIX_RECOVERY = 'recovery message with expression macro ' . ALL_PRINTABLE_ASCII . ": ";
	const SUBJECT_PREFIX_RECOVERY = 'recovery subject with expression macro ' . ALL_PRINTABLE_ASCII . ": ";
	const EVENT_PREFIX = 'event name with expression macro ' . ALL_PRINTABLE_ASCII . ": ";

	const VALUE_TO_FIRE_TRIGGER = 3;
	const VALUE_TO_RECOVER_TRIGGER = 2147483647;
	const TAG_NAME = 'tag_name_' . ALL_PRINTABLE_ASCII;
	const TAG_VALUE = 'tag_value_' . ALL_PRINTABLE_ASCII;
	const ACTION_NAME = 'action_name_' . ALL_PRINTABLE_ASCII;

	const TRIGGER_PRIORITY = 3;
	const TRIGGER_STATUS_ENABLED = 0;
	const TRIGGER_COMMENTS = 'strata_trigger_comment_' . ALL_PRINTABLE_ASCII;
	const TRIGGER_URL = 'strata_trigger_url_' . ALL_PRINTABLE_ASCII;
	const TRIGGER_URL_NAME = 'url_name_' . REDUCTED_PRINTABLE_ASCII;
	const TRIGGER_TYPE = 1;
	const TRIGGER_RECOVERY_MODE = 1;
	const TRIGGER_CORRELATION_MODE = 1;
	const TRIGGER_MANUAL_CLOSE = 1;
	const TRIGGER_OPDATA = 'strata_trigger_opdata' . ALL_PRINTABLE_ASCII;
	const TRIGGER_EVENT_NAME = 'strata_trigger_event_name' . ALL_PRINTABLE_ASCII;

	const TIME_BUILDIN_MACRO_SIM = '23:12:55';
	const SAMPLE_DOUBLE_VALUE = '0.1234567890123456789';

	const INVENTORY_HOST_NETMASK = "255.255.255.255";
	const INVENTORY_HOST_ROUTER = "127.0.0.1";
	const INVENTORY_HW_ARCH = "TEST_ARCH";
	const INVENTORY_LOCATION_LAT = "0";
	const INVENTORY_LOCATION_LON = "9999999999999999";
	const INVENTORY_OOB_IP = "127.0.0.1";
	const INVENTORY_OOB_NETMASK = "255.255.255.255";
	const INVENTORY_OOB_ROUTER = "127.0.0.1";

	/* COMMON means common between 1) operations 2) recovery operations 3) update operations               */
	/* That does NOT mean that all macros resolved to the same values for different operations.            */
	/* That means they all get resolved for all operations - to something consistent that can be compared. */
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.ACK.STATUS -> {EVENT.ACK.STATUS} <-
		EVENT.NAME -> {EVENT.NAME} <-
		EVENT.NSEVERITY -> {EVENT.NSEVERITY} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.OPDATA -> {EVENT.OPDATA} <-
		EVENT.SEVERITY -> {EVENT.SEVERITY} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		EVENT.STATUS -> {EVENT.STATUS} <-
		EVENT.TAGS -> {EVENT.TAGS} <-
		EVENT.TAGSJSON -> {EVENT.TAGSJSON} <-
		EVENT.UPDATE.HISTORY -> {EVENT.UPDATE.HISTORY} <-
		EVENT.UPDATE.STATUS -> {EVENT.UPDATE.STATUS} <-
		EVENT.VALUE -> {EVENT.VALUE} <-
		HOST.CONN -> {HOST.CONN} <-
		HOST.DESCRIPTION -> {HOST.DESCRIPTION} <-
		HOST.DNS -> {HOST.DNS} <-
		HOST.HOST -> {HOST.HOST} <-
		HOST.IP -> {HOST.IP} <-
		HOST.NAME -> {HOST.NAME} <-
		HOST.PORT -> {HOST.PORT} <-
		ITEM.DESCRIPTION -> {ITEM.DESCRIPTION} <-
		ITEM.DESCRIPTION.ORIG -> {ITEM.DESCRIPTION.ORIG} <-
		ITEM.KEY -> {ITEM.KEY} <-
		ITEM.KEY.ORIG -> {ITEM.KEY.ORIG} <-
		ITEM.LASTVALUE -> {ITEM.LASTVALUE} <-
		ITEM.NAME -> {ITEM.NAME} <-
		ITEM.NAME.ORIG -> {ITEM.NAME.ORIG} <-
		ITEM.VALUE -> {ITEM.VALUE} <-
		ITEM.VALUETYPE -> {ITEM.VALUETYPE} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		TRIGGER.DESCRIPTION -> {TRIGGER.DESCRIPTION} <-
		TRIGGER.EXPRESSION.EXPLAIN -> {TRIGGER.EXPRESSION.EXPLAIN} <-
		TRIGGER.EXPRESSION.RECOVERY.EXPLAIN -> {TRIGGER.EXPRESSION.RECOVERY.EXPLAIN} <-
		TRIGGER.EVENTS.ACK -> {TRIGGER.EVENTS.ACK} <-
		TRIGGER.EVENTS.PROBLEM.ACK -> {TRIGGER.EVENTS.PROBLEM.ACK} <-
		TRIGGER.EVENTS.PROBLEM.UNACK -> {TRIGGER.EVENTS.PROBLEM.UNACK} <-
		TRIGGER.EVENTS.UNACK -> {TRIGGER.EVENTS.UNACK} <-
		TRIGGER.HOSTGROUP.NAME -> {TRIGGER.HOSTGROUP.NAME} <-
		TRIGGER.EXPRESSION -> {TRIGGER.EXPRESSION} <-
		TRIGGER.EXPRESSION.RECOVERY -> {TRIGGER.EXPRESSION.RECOVERY} <-
		TRIGGER.NAME -> {TRIGGER.NAME} <-
		TRIGGER.NAME.ORIG -> {TRIGGER.NAME.ORIG} <-
		TRIGGER.NSEVERITY -> {TRIGGER.NSEVERITY} <-
		TRIGGER.SEVERITY -> {TRIGGER.SEVERITY} <-
		TRIGGER.STATUS -> {TRIGGER.STATUS} <-
		TRIGGER.URL -> {TRIGGER.URL} <-
		TRIGGER.URL.NAME -> {TRIGGER.URL.NAME} <-
		TRIGGER.VALUE -> {TRIGGER.VALUE} <-
		INVENTORY.ALIAS -> {INVENTORY.ALIAS} <-
		INVENTORY.ASSET.TAG -> {INVENTORY.ASSET.TAG} <-
		INVENTORY.CHASSIS -> {INVENTORY.CHASSIS} <-
		INVENTORY.CONTACT-> {INVENTORY.CONTACT} <-
		INVENTORY.CONTRACT.NUMBER -> {INVENTORY.CONTRACT.NUMBER} <-
		INVENTORY.DEPLOYMENT.STATUS -> {INVENTORY.DEPLOYMENT.STATUS} <-
		INVENTORY.HARDWARE -> {INVENTORY.HARDWARE} <-
		INVENTORY.HARDWARE.FULL -> {INVENTORY.HARDWARE.FULL} <-
		INVENTORY.HOST.NETMASK -> {INVENTORY.HOST.NETMASK} <-
		INVENTORY.HOST.NETWORKS -> {INVENTORY.HOST.NETWORKS} <-
		INVENTORY.HOST.ROUTER -> {INVENTORY.HOST.ROUTER} <-
		INVENTORY.HW.ARCH -> {INVENTORY.HW.ARCH} <-
		INVENTORY.HW.DATE.DECOMM -> {INVENTORY.HW.DATE.DECOMM} <-
		INVENTORY.HW.DATE.EXPIRY -> {INVENTORY.HW.DATE.EXPIRY} <-
		INVENTORY.HW.DATE.INSTALL -> {INVENTORY.HW.DATE.INSTALL} <-
		INVENTORY.HW.DATE.PURCHASE-> {INVENTORY.HW.DATE.PURCHASE} <-
		INVENTORY.INSTALLER.NAME -> {INVENTORY.INSTALLER.NAME} <-
		INVENTORY.LOCATION -> {INVENTORY.LOCATION} <-
		INVENTORY.LOCATION.LAT -> {INVENTORY.LOCATION.LAT} <-
		INVENTORY.LOCATION.LON -> {INVENTORY.LOCATION.LON} <-
		INVENTORY.MACADDRESS.A -> {INVENTORY.MACADDRESS.A} <-
		INVENTORY.MACADDRESS.B -> {INVENTORY.MACADDRESS.B} <-
		INVENTORY.MODEL -> {INVENTORY.MODEL} <-
		INVENTORY.NAME -> {INVENTORY.NAME} <-
		INVENTORY.NOTES -> {INVENTORY.NOTES} <-
		INVENTORY.OOB.IP -> {INVENTORY.OOB.IP} <-
		INVENTORY.OOB.NETMASK -> {INVENTORY.OOB.NETMASK} <-
		INVENTORY.OOB.ROUTER -> {INVENTORY.OOB.ROUTER} <-
		INVENTORY.OS -> {INVENTORY.OS} <-
		INVENTORY.OS.FULL -> {INVENTORY.OS.FULL} <-
		INVENTORY.OS.SHORT -> {INVENTORY.OS.SHORT} <-
		INVENTORY.POC.PRIMARY.CELL -> {INVENTORY.POC.PRIMARY.CELL} <-
		INVENTORY.POC.PRIMARY.EMAIL -> {INVENTORY.POC.PRIMARY.EMAIL} <-
		INVENTORY.POC.PRIMARY.NAME -> {INVENTORY.POC.PRIMARY.NAME} <-
		INVENTORY.POC.PRIMARY.NOTES -> {INVENTORY.POC.PRIMARY.NOTES} <-
		INVENTORY.POC.PRIMARY.PHONE.A -> {INVENTORY.POC.PRIMARY.PHONE.A} <-
		INVENTORY.POC.PRIMARY.PHONE.B -> {INVENTORY.POC.PRIMARY.PHONE.B} <-
		INVENTORY.POC.PRIMARY.SCREEN -> {INVENTORY.POC.PRIMARY.SCREEN} <-
		INVENTORY.POC.SECONDARY.CELL -> {INVENTORY.POC.SECONDARY.CELL} <-
		INVENTORY.POC.SECONDARY.EMAIL -> {INVENTORY.POC.SECONDARY.EMAIL} <-
		INVENTORY.POC.SECONDARY.NAME -> {INVENTORY.POC.SECONDARY.NAME} <-
		INVENTORY.POC.SECONDARY.NOTES -> {INVENTORY.POC.SECONDARY.NOTES} <-
		INVENTORY.POC.SECONDARY.PHONE.A -> {INVENTORY.POC.SECONDARY.PHONE.A} <-
		INVENTORY.POC.SECONDARY.PHONE.B -> {INVENTORY.POC.SECONDARY.PHONE.B} <-
		INVENTORY.POC.SECONDARY.SCREEN -> {INVENTORY.POC.SECONDARY.SCREEN} <-
		INVENTORY.SERIALNO.A -> {INVENTORY.SERIALNO.A} <-
		INVENTORY.SERIALNO.B -> {INVENTORY.SERIALNO.B} <-
		INVENTORY.SITE.ADDRESS.A -> {INVENTORY.SITE.ADDRESS.A} <-
		INVENTORY.SITE.ADDRESS.B -> {INVENTORY.SITE.ADDRESS.B} <-
		INVENTORY.SITE.ADDRESS.C -> {INVENTORY.SITE.ADDRESS.C} <-
		INVENTORY.SITE.CITY -> {INVENTORY.SITE.CITY} <-
		INVENTORY.SITE.COUNTRY -> {INVENTORY.SITE.COUNTRY} <-
		INVENTORY.SITE.NOTES -> {INVENTORY.SITE.NOTES} <-
		INVENTORY.SITE.RACK -> {INVENTORY.SITE.RACK} <-
		INVENTORY.SITE.STATE -> {INVENTORY.SITE.STATE} <-
		INVENTORY.SITE.ZIP -> {INVENTORY.SITE.ZIP} <-
		INVENTORY.SOFTWARE -> {INVENTORY.SOFTWARE} <-
		INVENTORY.SOFTWARE.APP.A -> {INVENTORY.SOFTWARE.APP.A} <-
		INVENTORY.SOFTWARE.APP.B -> {INVENTORY.SOFTWARE.APP.B} <-
		INVENTORY.SOFTWARE.APP.C -> {INVENTORY.SOFTWARE.APP.C} <-
		INVENTORY.SOFTWARE.APP.D -> {INVENTORY.SOFTWARE.APP.D} <-
		INVENTORY.SOFTWARE.APP.E -> {INVENTORY.SOFTWARE.APP.E} <-
		INVENTORY.SOFTWARE.FULL -> {INVENTORY.SOFTWARE.FULL} <-
		INVENTORY.TAG -> {INVENTORY.TAG} <-
		INVENTORY.TYPE -> {INVENTORY.TYPE} <-
		INVENTORY.TYPE.FULL -> {INVENTORY.TYPE.FULL} <-
		INVENTORY.URL.A -> {INVENTORY.URL.A} <-
		INVENTORY.URL.B -> {INVENTORY.URL.B} <-
		INVENTORY.URL.C} -> {INVENTORY.URL.C} <-
		INVENTORY.VENDOR -> {INVENTORY.VENDOR} <-
		EOT;

	/* There macros resolve to some consistent values */
	/* ONLY during the recovery operations. For other */
	/* operations they are not resolved.              */
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY = <<<'EOT'
		EVENT.RECOVERY.DATE -> {EVENT.RECOVERY.DATE} <-
		EVENT.RECOVERY.NAME -> {EVENT.RECOVERY.NAME} <-
		EVENT.RECOVERY.STATUS -> {EVENT.RECOVERY.STATUS} <-
		EVENT.RECOVERY.TAGS -> {EVENT.RECOVERY.TAGS} <-
		EVENT.RECOVERY.TAGSJSON -> {EVENT.RECOVERY.TAGSJSON} <-
		EVENT.RECOVERY.VALUE -> {EVENT.RECOVERY.VALUE} <-
		EOT;

	/* These macros resolve to new values on every test run - like time or id. */
	/* So, their resulting values are not checked with assertEquals().         */
	/* It is still important to have them in the test, since they can          */
	/* cause memory issues (that would be detected with sanitizers).           */
	const BUILTIN_MACROS_INCONSISTENT_RESOLVE = <<<'EOT'
		ACTION.ID -> {ACTION.ID} <-
		ESC.HISTORY -> {ESC.HISTORY} <-
		DATE -> {DATE} <-
		TIME -> {TIME} <-
		EVENT.AGE -> {EVENT.AGE} <-
		EVENT.DATE -> {EVENT.DATE} <-
		EVENT.DURATION -> {EVENT.DURATION} <-
		EVENT.ID -> {EVENT.ID} <-
		EVENT.TIME -> {EVENT.TIME} <-
		HOST.ID -> {HOST.ID} <-
		ITEM.ID -> {ITEM.ID} <-
		TRIGGER.ID -> {TRIGGER.ID} <-
		TIMESTAMP -> {TIMESTAMP} <-
		EOT;

	/* These macros resolve to new values on every test run - like time or id  */
	/* ONLY during the recovery operations. For other operations they are not  */
	/* resolved.                                                               */
	const BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY = <<<'EOT'
		EVENT.RECOVERY.ID -> {EVENT.RECOVERY.ID} <-
		EVENT.RECOVERY.TIME -> {EVENT.RECOVERY.TIME} <-
		EOT;

	/* There macros resolve to a value of UNKNOWN.*/
	const BUILTIN_MACROS_UNKNOWN = <<<'EOT'
		EVENT.CAUSE.ACK.STATUS -> {EVENT.CAUSE.ACK.STATUS} <-
		EVENT.CAUSE.AGE -> {EVENT.CAUSE.AGE} <-
		EVENT.CAUSE.DATE -> {EVENT.CAUSE.DATE} <-
		EVENT.CAUSE.DURATION -> {EVENT.CAUSE.DURATION} <-
		EVENT.CAUSE.ID -> {EVENT.CAUSE.ID} <-
		EVENT.CAUSE.NAME -> {EVENT.CAUSE.NAME} <-
		EVENT.CAUSE.NSEVERITY -> {EVENT.CAUSE.NSEVERITY} <-
		EVENT.CAUSE.OBJECT -> {EVENT.CAUSE.OBJECT} <-
		EVENT.CAUSE.OPDATA -> {EVENT.CAUSE.OPDATA} <-
		EVENT.CAUSE.SEVERITY -> {EVENT.CAUSE.SEVERITY} <-
		EVENT.CAUSE.SOURCE -> {EVENT.CAUSE.SOURCE} <-
		EVENT.CAUSE.STATUS -> {EVENT.CAUSE.STATUS} <-
		EVENT.CAUSE.TAGS -> {EVENT.CAUSE.TAGS} <-
		EVENT.CAUSE.TAGSJSON -> {EVENT.CAUSE.TAGSJSON} <-
		EVENT.CAUSE.TIME -> {EVENT.CAUSE.TIME} <-
		EVENT.CAUSE.UPDATE.HISTORY -> {EVENT.CAUSE.UPDATE.HISTORY} <-
		EVENT.CAUSE.VALUE -> {EVENT.CAUSE.VALUE} <-
		EVENT.SYMPTOMS -> {EVENT.SYMPTOMS} <-
		ITEM.LOG.AGE -> {ITEM.LOG.AGE} <-
		ITEM.LOG.DATE -> {ITEM.LOG.DATE} <-
		ITEM.LOG.EVENTID -> {ITEM.LOG.EVENTID} <-
		ITEM.LOG.NSEVERITY -> {ITEM.LOG.NSEVERITY} <-
		ITEM.LOG.SEVERITY -> {ITEM.LOG.SEVERITY} <-
		ITEM.LOG.SOURCE -> {ITEM.LOG.SOURCE} <-
		ITEM.LOG.TIME -> {ITEM.LOG.TIME} <-
		TRIGGER.TEMPLATE.NAME -> {TRIGGER.TEMPLATE.NAME} <-
		EOT;

	/* Resolved self::BUILTIN_MACROS_UNKNOWN. */
	const  BUILTIN_MACROS_UNKNOWN_RESOLVED = <<<'EOT'
		EVENT.CAUSE.ACK.STATUS -> *UNKNOWN* <-
		EVENT.CAUSE.AGE -> *UNKNOWN* <-
		EVENT.CAUSE.DATE -> *UNKNOWN* <-
		EVENT.CAUSE.DURATION -> *UNKNOWN* <-
		EVENT.CAUSE.ID -> *UNKNOWN* <-
		EVENT.CAUSE.NAME -> *UNKNOWN* <-
		EVENT.CAUSE.NSEVERITY -> *UNKNOWN* <-
		EVENT.CAUSE.OBJECT -> *UNKNOWN* <-
		EVENT.CAUSE.OPDATA -> *UNKNOWN* <-
		EVENT.CAUSE.SEVERITY -> *UNKNOWN* <-
		EVENT.CAUSE.SOURCE -> *UNKNOWN* <-
		EVENT.CAUSE.STATUS -> *UNKNOWN* <-
		EVENT.CAUSE.TAGS -> *UNKNOWN* <-
		EVENT.CAUSE.TAGSJSON -> *UNKNOWN* <-
		EVENT.CAUSE.TIME -> *UNKNOWN* <-
		EVENT.CAUSE.UPDATE.HISTORY -> *UNKNOWN* <-
		EVENT.CAUSE.VALUE -> *UNKNOWN* <-
		EVENT.SYMPTOMS -> *UNKNOWN* <-
		ITEM.LOG.AGE -> *UNKNOWN* <-
		ITEM.LOG.DATE -> *UNKNOWN* <-
		ITEM.LOG.EVENTID -> *UNKNOWN* <-
		ITEM.LOG.NSEVERITY -> *UNKNOWN* <-
		ITEM.LOG.SEVERITY -> *UNKNOWN* <-
		ITEM.LOG.SOURCE -> *UNKNOWN* <-
		ITEM.LOG.TIME -> *UNKNOWN* <-
		TRIGGER.TEMPLATE.NAME -> *UNKNOWN* <-
		EOT;

	/* These macros are not resolved. */
	const BUILTIN_MACROS_NON_RESOLVABLE = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.DEVICE.UPTIME}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.SERVICE.NAME}
		{DISCOVERY.SERVICE.PORT}
		{DISCOVERY.SERVICE.STATUS}
		{DISCOVERY.SERVICE.UPTIME}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{LLDRULE.DESCRIPTION}
		{LLDRULE.DESCRIPTION.ORIG}
		{LLDRULE.ID}
		{LLDRULE.KEY}
		{LLDRULE.KEY.ORIG}
		{LLDRULE.NAME}
		{LLDRULE.NAME.ORIG}
		{LLDRULE.STATE}
		{LLDRULE.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.ROOTCAUSE}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		{USER.USERNAME}"
		EOT;

	const MACRO_FUNCS = <<<'EOT'
		ACTION.NAME.btoa() -> {{ACTION.NAME}.btoa()} <-
		USER_MACRO_GLOBAL_DOUBLE.fmtnum(15) -> {{$USER_MACRO_GLOBAL_DOUBLE}.fmtnum(15)} <-
		USER_MACRO_GLOBAL_TIME.fmttime(%H) -> {{$USER_MACRO_GLOBAL_TIME}.fmttime(%H)} <-
		ACTION.NAME.htmldecode() -> {{ACTION.NAME}.htmldecode()} <-
		ACTION.NAME.htmlencode() -> {{ACTION.NAME}.htmlencode()} <-
		ACTION.NAME.iregsub("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "*") -> {{ACTION.NAME}.iregsub("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "*")} <-
		ACTION.NAME.lowercase() -> {{ACTION.NAME}.lowercase()} <-
		ACTION.NAME.regrepl("ABCDEFGHIJKLMNOPQRSTUVWXYZ","*") -> {{ACTION.NAME}.regrepl("ABCDEFGHIJKLMNOPQRSTUVWXYZ","*")} <-
		USER_MACRO_GLOBAL_TIME.regsub("^([0-9]+)", "\1") -> {{$USER_MACRO_GLOBAL_TIME}.regsub("^([0-9]+)", "\1")} <-
		USER_MACRO_GLOBAL_DOUBLE.tr(0,a) -> {{$USER_MACRO_GLOBAL_DOUBLE}.tr(0,a)} <-
		ACTION.NAME.uppercase() -> {{ACTION.NAME}.uppercase()} <-
		ACTION.NAME.urldecode() -> {{ACTION.NAME}.urldecode()} <-
		ACTION.NAME.urlencode() -> {{ACTION.NAME}.urlencode()} <-
		EOT;

	const MACRO_FUNCS_RESOLVED =
		"ACTION.NAME.btoa() -> YWN0aW9uX25hbWVfICEiIyQlJicoKSorLC0uLzAxMjM0NTY3ODk6Ozw9Pj9AQUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVpbXF1eX2BhYmNkZWZnaGlqa2xtbm9wcXJzdHV2d3h5ent8fX4= <-\n" .
		"USER_MACRO_GLOBAL_DOUBLE.fmtnum(15) -> 0.123456789012346 <-\n" . 	// NOTE last 12346, not 123456 !
		"USER_MACRO_GLOBAL_TIME.fmttime(%H) -> 23 <-\n" .
		"ACTION.NAME.htmldecode() -> " . self::ACTION_NAME . " <-\n" .
		"ACTION.NAME.htmlencode() -> action_name_ !&quot;#$%&amp;&#39;()*+,-./0123456789:;&lt;=&gt;?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~ <-\n" .	// NOTE, \' -> \ disappears
		"ACTION.NAME.iregsub(\"ABCDEFGHIJKLMNOPQRSTUVWXYZ\", \"*\") -> * <-\n" .
		"ACTION.NAME.lowercase() -> action_name_ !\"#$%&'()*+,-./0123456789:;<=>?@abcdefghijklmnopqrstuvwxyz[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~ <-\n" .	// NOTE, \' -> \ disappears
		"ACTION.NAME.regrepl(\"ABCDEFGHIJKLMNOPQRSTUVWXYZ\",\"*\") -> action_name_ !\"#$%&'()*+,-./0123456789:;<=>?@*[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~ <-\n" .
		"USER_MACRO_GLOBAL_TIME.regsub(\"^([0-9]+)\", \"\\1\") -> 23 <-\n" .
		"USER_MACRO_GLOBAL_DOUBLE.tr(0,a) -> a.123456789a123456789 <-\n" .
		"ACTION.NAME.uppercase() -> ACTION_NAME_ !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`ABCDEFGHIJKLMNOPQRSTUVWXYZ{|}~ <-\n" .
		"ACTION.NAME.urldecode() -> " . self::ACTION_NAME . " <-" . "\n" .
		"ACTION.NAME.urlencode() -> action_name_%20%21%22%23%24%25%26%27%28%29%2A%2B%2C-.%2F0123456789%3A%3B%3C%3D%3E%3F%40ABCDEFGHIJKLMNOPQRSTUVWXYZ%5B%5C%5D%5E_%60abcdefghijklmnopqrstuvwxyz%7B%7C%7D~ <-";

	const INVENTORY_RESOLVED =
		"INVENTORY.ALIAS -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.ASSET.TAG -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.CHASSIS -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.CONTACT-> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.CONTRACT.NUMBER -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.DEPLOYMENT.STATUS -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HARDWARE -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HARDWARE.FULL -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HOST.NETMASK -> "			. self::INVENTORY_HOST_NETMASK	. " <-\n" .
		"INVENTORY.HOST.NETWORKS -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HOST.ROUTER -> "				. self::INVENTORY_HOST_ROUTER	. " <-\n" .
		"INVENTORY.HW.ARCH -> "					. self::INVENTORY_HW_ARCH		. " <-\n" .
		"INVENTORY.HW.DATE.DECOMM -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HW.DATE.EXPIRY -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HW.DATE.INSTALL -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.HW.DATE.PURCHASE-> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.INSTALLER.NAME -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.LOCATION -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.LOCATION.LAT -> "			. self::INVENTORY_LOCATION_LAT	. " <-\n" .
		"INVENTORY.LOCATION.LON -> "			. self::INVENTORY_LOCATION_LON	. " <-\n" .
		"INVENTORY.MACADDRESS.A -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.MACADDRESS.B -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.MODEL -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.NAME -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.NOTES -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.OOB.IP -> "					. self::INVENTORY_OOB_IP		. " <-\n" .
		"INVENTORY.OOB.NETMASK -> "				. self::INVENTORY_OOB_NETMASK	. " <-\n" .
		"INVENTORY.OOB.ROUTER -> "				. self::INVENTORY_OOB_ROUTER	. " <-\n" .
		"INVENTORY.OS -> "						. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.OS.FULL -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.OS.SHORT -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.CELL -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.EMAIL -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.NAME -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.NOTES -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.PHONE.A -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.PHONE.B -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.PRIMARY.SCREEN -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.CELL -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.EMAIL -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.NAME -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.NOTES -> "		. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.PHONE.A -> "	. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.PHONE.B -> "	. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.POC.SECONDARY.SCREEN -> "	. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SERIALNO.A -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SERIALNO.B -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.ADDRESS.A -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.ADDRESS.B -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.ADDRESS.C -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.CITY -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.COUNTRY -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.NOTES -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.RACK -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.STATE -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SITE.ZIP -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.APP.A -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.APP.B -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.APP.C -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.APP.D -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.APP.E -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.SOFTWARE.FULL -> "			. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.TAG -> "						. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.TYPE -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.TYPE.FULL -> "				. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.URL.A -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.URL.B -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.URL.C} -> "					. REDUCTED_PRINTABLE_ASCII		. " <-\n" .
		"INVENTORY.VENDOR -> "					. REDUCTED_PRINTABLE_ASCII		. " <-";

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::$event_tags_json = json_encode(array(['tag' => self::TAG_NAME, 'value' => self::TAG_VALUE]), JSON_UNESCAPED_SLASHES);

		self::$trigger_expression_explain = self::VALUE_TO_FIRE_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER .
				' or ' .
				self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER;
		self::$trigger_recovery_expression_explain = self::VALUE_TO_FIRE_TRIGGER . '=' . self::VALUE_TO_RECOVER_TRIGGER;

		self::$trigger_expression = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)='.self::VALUE_TO_FIRE_TRIGGER.' or '.
				'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'2)='.self::VALUE_TO_FIRE_TRIGGER;

		self::$trigger_recovery_expression = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)='.self::VALUE_TO_RECOVER_TRIGGER;

		self::$event_name = self::EVENT_PREFIX.'{?last(/{HOST.HOST}/'.self::TRAPPER_ITEM_KEY.'1)}';

		self::$event_name_resolved = self::EVENT_PREFIX . self::VALUE_TO_FIRE_TRIGGER;

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "							. self::ACTION_NAME								. " <-\n" .
			"EVENT.ACK.STATUS -> "						. 'No'											. " <-\n" .
			"EVENT.NAME -> "							. self::$event_name_resolved					. " <-\n" .
			"EVENT.NSEVERITY -> "						. self::TRIGGER_PRIORITY						. " <-\n" .
			"EVENT.OBJECT -> "							. '0'											. " <-\n" . // 0 -> Trigger
			"EVENT.OPDATA -> "							. self::TRIGGER_OPDATA							. " <-\n" .
			"EVENT.SEVERITY -> "						. 'Average'										. " <-\n" . // self::TRIGGER_PRIORITY
			"EVENT.SOURCE -> "							. '0'											. " <-\n" . // 0 -> Trigger
			"EVENT.STATUS -> "							. 'PROBLEM'										. " <-\n" . // 1 -> PROBLEM
			"EVENT.TAGS -> "							. self::TAG_NAME . ':' . self::TAG_VALUE		. " <-\n" .
			"EVENT.TAGSJSON -> "						. self::$event_tags_json						. " <-\n" .
			"EVENT.UPDATE.HISTORY -> "					. ''											. " <-\n" .
			"EVENT.UPDATE.STATUS -> "					. '0'											. " <-\n" .
			"EVENT.VALUE -> "							. '1'											. " <-\n" . // 0 -> Problem
			"HOST.CONN -> "								. '127.0.0.1'									. " <-\n" .
			"HOST.DESCRIPTION -> "						. ''											. " <-\n" .
			"HOST.DNS -> "								. ''											. " <-\n" .
			"HOST.HOST -> "								. self::HOST_NAME								. " <-\n" .
			"HOST.IP -> "								. '127.0.0.1'									. " <-\n" .
			"HOST.NAME -> "								. self::HOST_NAME								. " <-\n" .
			"HOST.PORT -> "								. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX	. " <-\n" .
			"ITEM.DESCRIPTION -> "						. ''											. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "					. ''											. " <-\n" .
			"ITEM.KEY -> "								. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.KEY.ORIG -> "							. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.LASTVALUE -> "						. self::VALUE_TO_FIRE_TRIGGER					. " <-\n" .
			"ITEM.NAME -> "								. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.NAME.ORIG -> "						. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.VALUE -> "							. self::VALUE_TO_FIRE_TRIGGER					. " <-\n" .
			"ITEM.VALUETYPE -> "						. ITEM_VALUE_TYPE_UINT64						. " <-\n" .
			"PROXY.DESCRIPTION -> "						. ''											. " <-\n" .
			"PROXY.NAME -> "							. ''											. " <-\n" .
			"TRIGGER.DESCRIPTION -> "					. self::TRIGGER_COMMENTS						. " <-\n" .
			"TRIGGER.EXPRESSION.EXPLAIN -> "			. self::$trigger_expression_explain				. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY.EXPLAIN -> "	. self::$trigger_recovery_expression_explain	. " <-\n" .
			"TRIGGER.EVENTS.ACK -> "					. '0'											. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.ACK -> "			. '0'											. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.UNACK -> "			. '1'											. " <-\n" .
			"TRIGGER.EVENTS.UNACK -> "					. '1'											. " <-\n" .
			"TRIGGER.HOSTGROUP.NAME -> "				. 'Zabbix servers'								. " <-\n" . // 4 -> 'Zabbix servers'
			"TRIGGER.EXPRESSION -> "					. self::$trigger_expression						. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY -> "			. self::$trigger_recovery_expression			. " <-\n" .
			"TRIGGER.NAME -> "							. 'trigger_trap'								. " <-\n" .
			"TRIGGER.NAME.ORIG -> "						. 'trigger_trap'								. " <-\n" .
			"TRIGGER.NSEVERITY -> "						. self::TRIGGER_PRIORITY						. " <-\n" .
			"TRIGGER.SEVERITY -> "						. 'Average'										. " <-\n" . // 3 -> Average
			"TRIGGER.STATUS -> "						. 'PROBLEM'										. " <-\n" .
			"TRIGGER.URL -> "							. self::TRIGGER_URL								. " <-\n" .
			"TRIGGER.URL.NAME -> "						. self::TRIGGER_URL_NAME						. " <-\n" .
			"TRIGGER.VALUE -> "							. '1'											. " <-\n" .  // 1 -> Problem
			self::INVENTORY_RESOLVED;

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			],
			'inventory_mode' => HOST_INVENTORY_MANUAL,
			'inventory' => [
				'alias'				=> REDUCTED_PRINTABLE_ASCII,
				'asset_tag'			=> REDUCTED_PRINTABLE_ASCII,
				'chassis'			=> REDUCTED_PRINTABLE_ASCII,
				'contact'			=> REDUCTED_PRINTABLE_ASCII,
				'contract_number'	=> REDUCTED_PRINTABLE_ASCII,
				'date_hw_decomm'	=> REDUCTED_PRINTABLE_ASCII,
				'date_hw_expiry'	=> REDUCTED_PRINTABLE_ASCII,
				'date_hw_install'	=> REDUCTED_PRINTABLE_ASCII,
				'date_hw_purchase'	=> REDUCTED_PRINTABLE_ASCII,
				'deployment_status'	=> REDUCTED_PRINTABLE_ASCII,
				'hardware'			=> REDUCTED_PRINTABLE_ASCII,
				'hardware_full'		=> REDUCTED_PRINTABLE_ASCII,
				'host_netmask'		=> self::INVENTORY_HOST_NETMASK,
				'host_networks'		=> REDUCTED_PRINTABLE_ASCII,
				'host_router'		=> self::INVENTORY_HOST_ROUTER,
				'hw_arch'			=> self::INVENTORY_HW_ARCH,
				'installer_name'	=> REDUCTED_PRINTABLE_ASCII,
				'location'			=> REDUCTED_PRINTABLE_ASCII,
				'location_lat'		=> self::INVENTORY_LOCATION_LAT,
				'location_lon'		=> self::INVENTORY_LOCATION_LON,
				'macaddress_a'		=> REDUCTED_PRINTABLE_ASCII,
				'macaddress_b'		=> REDUCTED_PRINTABLE_ASCII,
				'model'				=> REDUCTED_PRINTABLE_ASCII,
				'name'				=> REDUCTED_PRINTABLE_ASCII,
				'notes'				=> REDUCTED_PRINTABLE_ASCII,
				'oob_ip'			=> self::INVENTORY_OOB_IP,
				'oob_netmask'		=> self::INVENTORY_OOB_NETMASK,
				'oob_router'		=> self::INVENTORY_OOB_ROUTER,
				'os'				=> REDUCTED_PRINTABLE_ASCII,
				'os_full'			=> REDUCTED_PRINTABLE_ASCII,
				'os_short'			=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_cell'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_email'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_name'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_notes'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_phone_a'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_phone_b'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_1_screen'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_cell'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_email'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_name'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_notes'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_phone_a'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_phone_b'		=> REDUCTED_PRINTABLE_ASCII,
				'poc_2_screen'		=> REDUCTED_PRINTABLE_ASCII,
				'serialno_a'		=> REDUCTED_PRINTABLE_ASCII,
				'serialno_b'		=> REDUCTED_PRINTABLE_ASCII,
				'site_address_a'	=> REDUCTED_PRINTABLE_ASCII,
				'site_address_b'	=> REDUCTED_PRINTABLE_ASCII,
				'site_address_c'	=> REDUCTED_PRINTABLE_ASCII,
				'site_city'			=> REDUCTED_PRINTABLE_ASCII,
				'site_country'		=> REDUCTED_PRINTABLE_ASCII,
				'site_notes'		=> REDUCTED_PRINTABLE_ASCII,
				'site_rack'			=> REDUCTED_PRINTABLE_ASCII,
				'site_state'		=> REDUCTED_PRINTABLE_ASCII,
				'site_zip'			=> REDUCTED_PRINTABLE_ASCII,
				'software'			=> REDUCTED_PRINTABLE_ASCII,
				'software_app_a'	=> REDUCTED_PRINTABLE_ASCII,
				'software_app_b'	=> REDUCTED_PRINTABLE_ASCII,
				'software_app_c'	=> REDUCTED_PRINTABLE_ASCII,
				'software_app_d'	=> REDUCTED_PRINTABLE_ASCII,
				'software_app_e'	=> REDUCTED_PRINTABLE_ASCII,
				'software_full'		=> REDUCTED_PRINTABLE_ASCII,
				'tag'				=> REDUCTED_PRINTABLE_ASCII,
				'type'				=> REDUCTED_PRINTABLE_ASCII,
				'type_full'			=> REDUCTED_PRINTABLE_ASCII,
				'url_a'				=> REDUCTED_PRINTABLE_ASCII,
				'url_b'				=> REDUCTED_PRINTABLE_ASCII,
				'url_c'				=> REDUCTED_PRINTABLE_ASCII,
				'vendor'			=> REDUCTED_PRINTABLE_ASCII
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$host_id = $response['result']['hostids'][0];

		$response = $this->call('usermacro.create', [
			'hostid' => self::$host_id,
			'macro' => '{$USER_MACRO_HOST}',
			'value' => 'HOST_LEVEL_' . ALL_PRINTABLE_ASCII
		]);

		array_push(self::$usermacro_ids, $response['result']['hostmacroids'][0]);

		$response = $this->call('usermacro.createglobal', [
			[
				'macro' => '{$USER_MACRO_GLOBAL}',
				'value' => 'GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII
			],
			[
				'macro' => '{$USER_MACRO_GLOBAL_DOUBLE}',
				'value' => self::SAMPLE_DOUBLE_VALUE
			],
			[
				'macro' => '{$USER_MACRO_GLOBAL_TIME}',
				'value' => self::TIME_BUILDIN_MACRO_SIM
			]
		]);

		self::$globalmacro_ids = array_merge(self::$globalmacro_ids, $response['result']['globalmacroids']);

		$items = [];
		for ($i = 1; $i < 3; $i++) {
			$items[] = [
				'hostid' => self::$host_id,
				'name' => self::TRAPPER_ITEM_NAME.$i,
				'key_' => self::TRAPPER_ITEM_KEY.$i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			];
		}

		$response = $this->call('item.create', $items);
		self::$item_ids = $response['result']['itemids'];
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count(self::$item_ids), count($response['result']['itemids']));

		$response = $this->call('trigger.create', [
			'description'			=> 'trigger_trap',
			'expression'			=> self::$trigger_expression,
			'recovery_expression'	=> self::$trigger_recovery_expression,
			'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
			'event_name'			=> self::$event_name,
			'priority'				=> self::TRIGGER_PRIORITY,
			'status'				=> self::TRIGGER_STATUS_ENABLED,
			'comments'				=> self::TRIGGER_COMMENTS,
			'url'					=> self::TRIGGER_URL,
			'url_name'				=> self::TRIGGER_URL_NAME,
			'type'					=> self::TRIGGER_TYPE,
			'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
			'correlation_mode'		=> self::TRIGGER_CORRELATION_MODE,
			'correlation_tag'		=> self::TAG_NAME,
			'manual_close'			=> self::TRIGGER_MANUAL_CLOSE,
			'opdata'				=>	self::TRIGGER_OPDATA,
			'tags' => [
				[
					'tag' => self::TAG_NAME,
					'value' => self::TAG_VALUE
				]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$trigger_id = $response['result']['triggerids'][0];

		// Create trigger action
		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$trigger_id
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => self::ACTION_NAME,
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 1,
						'subject' => self::SUBJECT_PREFIX.'{?last(//'.self::TRAPPER_ITEM_KEY.'1)}',
						'message' => self::MESSAGE_PREFIX.'{?last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)}' . "\n" .
							'===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_UNKNOWN . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
							'===5===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===7===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===8===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'esc_period' => 0,
					'esc_step_from' => 2,
					'esc_step_to' => 2,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_PREFIX.'{?first(//'.self::TRAPPER_ITEM_KEY.'1,1h)}',
						'message' => self::MESSAGE_PREFIX.'{?last(/{HOST.HOST}/'.self::TRAPPER_ITEM_KEY.'1,1h)}' . "\n" .
							'===1===' . "\n" .
								'/host/macro:{?last(/'.self::HOST_NAME.'/{ITEM.KEY})}'.
								'/empty/macro:{?last(//{ITEM.KEY})}'.
								'/macro/macro:{?last(/{HOST.HOST}/{ITEM.KEY})}'.
								'/macroN/macro:{?last(/{HOST.HOST1}/{ITEM.KEY})}'.
								'/macro/macroN:{?last(/{HOST.HOST}/{ITEM.KEY2})}'.
								'/empty/macroN:{?last(//{ITEM.KEY2})}'. "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_UNKNOWN . "\n" .
							'===5===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
							'===6===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===7===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===8===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===9===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'esc_period' => 0,
					'esc_step_from' => 3,
					'esc_step_to' => 3,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "",
						'message' => self::BUILTIN_MACROS_INCONSISTENT_RESOLVE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'subject' => self::SUBJECT_PREFIX_RECOVERY.'{?last(//'.self::TRAPPER_ITEM_KEY.'1)}',
						'message' => self::MESSAGE_PREFIX_RECOVERY.'{?last(//'.self::TRAPPER_ITEM_KEY.'1,#2)}' . "\n" .
							'===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_UNKNOWN . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$trigger_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		return true;
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'AllowUnsupportedDBVersions' => 1
			]
		];
	}

	public function testMacros_getData() {
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'2', self::VALUE_TO_RECOVER_TRIGGER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_RECOVER_TRIGGER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_FIRE_TRIGGER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		self::$event_response = $this->callUntilDataIsPresent('event.get', [
			'hostids' => [self::$host_id]
		], 5, 2);
		$this->assertCount(1, self::$event_response['result']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_RECOVER_TRIGGER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true, 10, 3);

		self::$alert_response = $this->callUntilDataIsPresent('alert.get', [
			'hostids' => [self::$host_id],
			'actionids' => [self::$trigger_action_id],
			'sortfield' => 'alertid'
		], 5, 2);
		$this->assertCount(4, self::$alert_response['result']);

		foreach (self::$alert_response['result'] as $n)
			self::$eventids[] = $n['eventid'];
	}

	/**
	 * Test macros resolution during the initial operation (when trigger fire first time).
	 */
	public function testMacros_checkProblemMessage() {
		$message_expect = self::MESSAGE_PREFIX . self::VALUE_TO_FIRE_TRIGGER . "\n" .
			'===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_UNKNOWN_RESOLVED . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
			'===5===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===6===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===8===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

		$this->assertEquals($message_expect, self::$alert_response['result'][0]['message']);

		/* Test expression macro with empty hostname. */
		$this->assertEquals(self::SUBJECT_PREFIX.self::VALUE_TO_FIRE_TRIGGER, self::$alert_response['result'][0]['subject']);

		/* Test expression macro in function with argument. */
		$this->assertEquals(self::SUBJECT_PREFIX.self::VALUE_TO_RECOVER_TRIGGER, self::$alert_response['result'][1]['subject']);
	}

	/**
	 * Test macro resolution during the first escalation step (1 minute passed after trigger was fired).
	 */
	public function testMacros_checkProblemMessage2() {
		$message_expect = self::MESSAGE_PREFIX.self::VALUE_TO_FIRE_TRIGGER . "\n" .
			'===1===' . "\n" .
				'/host/macro:' . self::VALUE_TO_FIRE_TRIGGER .
				'/empty/macro:' . self::VALUE_TO_FIRE_TRIGGER .
				'/macro/macro:' . self::VALUE_TO_FIRE_TRIGGER .
				'/macroN/macro:' . self::VALUE_TO_FIRE_TRIGGER .
				'/macro/macroN:' . self::VALUE_TO_RECOVER_TRIGGER .
				'/empty/macroN:' . self::VALUE_TO_RECOVER_TRIGGER . "\n" .
			'===2===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_UNKNOWN_RESOLVED . "\n" .
			'===5===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
			'===6===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===7===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===8===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===9===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

		$this->assertEquals($message_expect, self::$alert_response['result'][1]['message']);
	}

	/**
	 * Test macro resolution during the second escalation step (2 minutes passed after trigger was fired).
	 */
	public function testMacros_checkProblemMessage3_InconsistentMacros() {
		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"ESC.HISTORY[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.DURATION[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"HOST.ID[\s\S]*" .
			"ITEM.ID[\s\S]*" .
			"TRIGGER.ID[\s\S]*" .
			"TIMESTAMP[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, self::$alert_response['result'][2]['message']);
	}

	public function testMacros_checkRecoveryMessage() {
		$trigger_expression_explain = self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER .
				' or ' .
				self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER;

		$trigger_recovery_expression_explain = self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_RECOVER_TRIGGER;

		$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "							. self::ACTION_NAME								. " <-\n" .
			"EVENT.ACK.STATUS -> "						. 'No'											. " <-\n" .
			"EVENT.NAME -> "							. self::$event_name_resolved					. " <-\n" .
			"EVENT.NSEVERITY -> "						. self::TRIGGER_PRIORITY						. " <-\n" .
			"EVENT.OBJECT -> "							. '0'											. " <-\n" . // 0 -> Trigger
			"EVENT.OPDATA -> "							. self::TRIGGER_OPDATA							. " <-\n" .
			"EVENT.SEVERITY -> "						. 'Average'										. " <-\n" . // self::TRIGGER_PRIORITY
			"EVENT.SOURCE -> "							. '0'											. " <-\n" . // 0 -> Trigger
			"EVENT.STATUS -> "							. 'RESOLVED'									. " <-\n" . // 0 -> RESOLVED
			"EVENT.TAGS -> "							. self::TAG_NAME . ':' . self::TAG_VALUE		. " <-\n" .
			"EVENT.TAGSJSON -> "						. self::$event_tags_json						. " <-\n" .
			"EVENT.UPDATE.HISTORY -> "					. ''											. " <-\n" .
			"EVENT.UPDATE.STATUS -> "					. '0'											. " <-\n" .
			"EVENT.VALUE -> "							. '0'											. " <-\n" . // 0 -> Resolved
			"HOST.CONN -> "								. '127.0.0.1'									. " <-\n" .
			"HOST.DESCRIPTION -> "						. ''											. " <-\n" .
			"HOST.DNS -> "								. ''											. " <-\n" .
			"HOST.HOST -> "								. self::HOST_NAME								. " <-\n" .
			"HOST.IP -> "								. '127.0.0.1'									. " <-\n" .
			"HOST.NAME -> "								. self::HOST_NAME								. " <-\n" .
			"HOST.PORT -> "								. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX	. " <-\n" .
			"ITEM.DESCRIPTION -> "						. ''											. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "					. ''											. " <-\n" .
			"ITEM.KEY -> "								. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.KEY.ORIG -> "							. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.LASTVALUE -> "						. self::VALUE_TO_RECOVER_TRIGGER				. " <-\n" .
			"ITEM.NAME -> "								. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.NAME.ORIG -> "						. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.VALUE -> "							. self::VALUE_TO_RECOVER_TRIGGER				. " <-\n" .
			"ITEM.VALUETYPE -> "						. ITEM_VALUE_TYPE_UINT64						. " <-\n" .
			"PROXY.DESCRIPTION -> "						. ''											. " <-\n" .
			"PROXY.NAME -> "							. ''											. " <-\n" .
			"TRIGGER.DESCRIPTION -> "					. self::TRIGGER_COMMENTS						. " <-\n" .
			"TRIGGER.EXPRESSION.EXPLAIN -> "			. $trigger_expression_explain					. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY.EXPLAIN -> "	. $trigger_recovery_expression_explain			. " <-\n" .
			"TRIGGER.EVENTS.ACK -> "					. '0'											. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.ACK -> "			. '0'											. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.UNACK -> "			. '1'											. " <-\n" .
			"TRIGGER.EVENTS.UNACK -> "					. '2'											. " <-\n" .
			"TRIGGER.HOSTGROUP.NAME -> "				. 'Zabbix servers'								. " <-\n" . // 4 -> 'Zabbix servers'
			"TRIGGER.EXPRESSION -> "					. self::$trigger_expression						. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY -> "			. self::$trigger_recovery_expression			. " <-\n" .
			"TRIGGER.NAME -> "							. 'trigger_trap'								. " <-\n" .
			"TRIGGER.NAME.ORIG -> "						. 'trigger_trap'								. " <-\n" .
			"TRIGGER.NSEVERITY -> "						. self::TRIGGER_PRIORITY						. " <-\n" .
			"TRIGGER.SEVERITY -> "						. 'Average'										. " <-\n" . // 3 -> Average
			"TRIGGER.STATUS -> "						. 'OK'											. " <-\n" .
			"TRIGGER.URL -> "							. self::TRIGGER_URL								. " <-\n" .
			"TRIGGER.URL.NAME -> "						. self::TRIGGER_URL_NAME						. " <-\n" .
			"TRIGGER.VALUE -> "							. '0'											. " <-\n" . // 0 -> No problem
			self::INVENTORY_RESOLVED;

		$recovery_event_name_resolved = self::EVENT_PREFIX . self::VALUE_TO_RECOVER_TRIGGER;

		$BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED =
			"EVENT.RECOVERY.DATE -> "			. date('Y.m.d')					. " <-\n" .
			"EVENT.RECOVERY.NAME -> "			. $recovery_event_name_resolved			. " <-\n" .
			"EVENT.RECOVERY.STATUS -> "			. 'RESOLVED'					. " <-\n" .
			"EVENT.RECOVERY.TAGS -> "			. self::TAG_NAME . ':' . self::TAG_VALUE	. " <-\n" .
			"EVENT.RECOVERY.TAGSJSON -> "			. self::$event_tags_json			. " <-\n" .
			"EVENT.RECOVERY.VALUE -> "			. '0'						. " <-";

		$recovery_message_expect = self::MESSAGE_PREFIX_RECOVERY . self::VALUE_TO_FIRE_TRIGGER . "\n" .
			'===1===' . "\n" .
			$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			$BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_UNKNOWN_RESOLVED . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;


		$this->assertEquals(self::SUBJECT_PREFIX_RECOVERY.self::VALUE_TO_RECOVER_TRIGGER, self::$alert_response['result'][3]['subject']);
		$this->assertEquals($recovery_message_expect, self::$alert_response['result'][3]['message']);

		/* Test expression macro in event name. */
		$this->assertEquals(self::EVENT_PREFIX.self::VALUE_TO_FIRE_TRIGGER, self::$event_response['result'][0]['name']);
	}

	private function removeValue(array &$array, $value): void {
		$key = array_search($value, $array, true);
		if ($key !== false) {
			unset($array[$key]);
		}
	}
	/***************************************************************************************************************

		Test macro resolution (source: internal event).

	***************************************************************************************************************/
private static $internal_action_id;
private static $host_id_disable;
private $alert_response_internal_LLD;
const SUBJECT_INTERNAL = "Internal";

	/*Subset of COMMON macros that are consistently resolved for internal events only.*/
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL =<<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.NAME -> {EVENT.NAME} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		EVENT.STATUS -> {EVENT.STATUS} <-
		EVENT.TAGS -> {EVENT.TAGS} <-
		EVENT.TAGSJSON -> {EVENT.TAGSJSON} <-
		EVENT.VALUE -> {EVENT.VALUE} <-
		HOST.CONN -> {HOST.CONN} <-
		HOST.DESCRIPTION -> {HOST.DESCRIPTION} <-
		HOST.DNS -> {HOST.DNS} <-
		HOST.HOST -> {HOST.HOST} <-
		HOST.IP -> {HOST.IP} <-
		HOST.NAME -> {HOST.NAME} <-
		HOST.PORT -> {HOST.PORT} <-
		ITEM.DESCRIPTION -> {ITEM.DESCRIPTION} <-
		ITEM.DESCRIPTION.ORIG -> {ITEM.DESCRIPTION.ORIG} <-
		ITEM.KEY -> {ITEM.KEY} <-
		ITEM.KEY.ORIG -> {ITEM.KEY.ORIG} <-
		ITEM.NAME -> {ITEM.NAME} <-
		ITEM.NAME.ORIG -> {ITEM.NAME.ORIG} <-
		ITEM.STATE -> {ITEM.STATE} <-
		EOT . "\n" .
		"ITEM.STATE.ERROR -> {ITEM.STATE.ERROR} <-\n" . // added item-based internal notification
		<<<'EOT'
		ITEM.VALUETYPE -> {ITEM.VALUETYPE} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		INVENTORY.ALIAS -> {INVENTORY.ALIAS} <-
		INVENTORY.ASSET.TAG -> {INVENTORY.ASSET.TAG} <-
		INVENTORY.CHASSIS -> {INVENTORY.CHASSIS} <-
		INVENTORY.CONTACT-> {INVENTORY.CONTACT} <-
		INVENTORY.CONTRACT.NUMBER -> {INVENTORY.CONTRACT.NUMBER} <-
		INVENTORY.DEPLOYMENT.STATUS -> {INVENTORY.DEPLOYMENT.STATUS} <-
		INVENTORY.HARDWARE -> {INVENTORY.HARDWARE} <-
		INVENTORY.HARDWARE.FULL -> {INVENTORY.HARDWARE.FULL} <-
		INVENTORY.HOST.NETMASK -> {INVENTORY.HOST.NETMASK} <-
		INVENTORY.HOST.NETWORKS -> {INVENTORY.HOST.NETWORKS} <-
		INVENTORY.HOST.ROUTER -> {INVENTORY.HOST.ROUTER} <-
		INVENTORY.HW.ARCH -> {INVENTORY.HW.ARCH} <-
		INVENTORY.HW.DATE.DECOMM -> {INVENTORY.HW.DATE.DECOMM} <-
		INVENTORY.HW.DATE.EXPIRY -> {INVENTORY.HW.DATE.EXPIRY} <-
		INVENTORY.HW.DATE.INSTALL -> {INVENTORY.HW.DATE.INSTALL} <-
		INVENTORY.HW.DATE.PURCHASE-> {INVENTORY.HW.DATE.PURCHASE} <-
		INVENTORY.INSTALLER.NAME -> {INVENTORY.INSTALLER.NAME} <-
		INVENTORY.LOCATION -> {INVENTORY.LOCATION} <-
		INVENTORY.LOCATION.LAT -> {INVENTORY.LOCATION.LAT} <-
		INVENTORY.LOCATION.LON -> {INVENTORY.LOCATION.LON} <-
		INVENTORY.MACADDRESS.A -> {INVENTORY.MACADDRESS.A} <-
		INVENTORY.MACADDRESS.B -> {INVENTORY.MACADDRESS.B} <-
		INVENTORY.MODEL -> {INVENTORY.MODEL} <-
		INVENTORY.NAME -> {INVENTORY.NAME} <-
		INVENTORY.NOTES -> {INVENTORY.NOTES} <-
		INVENTORY.OOB.IP -> {INVENTORY.OOB.IP} <-
		INVENTORY.OOB.NETMASK -> {INVENTORY.OOB.NETMASK} <-
		INVENTORY.OOB.ROUTER -> {INVENTORY.OOB.ROUTER} <-
		INVENTORY.OS -> {INVENTORY.OS} <-
		INVENTORY.OS.FULL -> {INVENTORY.OS.FULL} <-
		INVENTORY.OS.SHORT -> {INVENTORY.OS.SHORT} <-
		INVENTORY.POC.PRIMARY.CELL -> {INVENTORY.POC.PRIMARY.CELL} <-
		INVENTORY.POC.PRIMARY.EMAIL -> {INVENTORY.POC.PRIMARY.EMAIL} <-
		INVENTORY.POC.PRIMARY.NAME -> {INVENTORY.POC.PRIMARY.NAME} <-
		INVENTORY.POC.PRIMARY.NOTES -> {INVENTORY.POC.PRIMARY.NOTES} <-
		INVENTORY.POC.PRIMARY.PHONE.A -> {INVENTORY.POC.PRIMARY.PHONE.A} <-
		INVENTORY.POC.PRIMARY.PHONE.B -> {INVENTORY.POC.PRIMARY.PHONE.B} <-
		INVENTORY.POC.PRIMARY.SCREEN -> {INVENTORY.POC.PRIMARY.SCREEN} <-
		INVENTORY.POC.SECONDARY.CELL -> {INVENTORY.POC.SECONDARY.CELL} <-
		INVENTORY.POC.SECONDARY.EMAIL -> {INVENTORY.POC.SECONDARY.EMAIL} <-
		INVENTORY.POC.SECONDARY.NAME -> {INVENTORY.POC.SECONDARY.NAME} <-
		INVENTORY.POC.SECONDARY.NOTES -> {INVENTORY.POC.SECONDARY.NOTES} <-
		INVENTORY.POC.SECONDARY.PHONE.A -> {INVENTORY.POC.SECONDARY.PHONE.A} <-
		INVENTORY.POC.SECONDARY.PHONE.B -> {INVENTORY.POC.SECONDARY.PHONE.B} <-
		INVENTORY.POC.SECONDARY.SCREEN -> {INVENTORY.POC.SECONDARY.SCREEN} <-
		INVENTORY.SERIALNO.A -> {INVENTORY.SERIALNO.A} <-
		INVENTORY.SERIALNO.B -> {INVENTORY.SERIALNO.B} <-
		INVENTORY.SITE.ADDRESS.A -> {INVENTORY.SITE.ADDRESS.A} <-
		INVENTORY.SITE.ADDRESS.B -> {INVENTORY.SITE.ADDRESS.B} <-
		INVENTORY.SITE.ADDRESS.C -> {INVENTORY.SITE.ADDRESS.C} <-
		INVENTORY.SITE.CITY -> {INVENTORY.SITE.CITY} <-
		INVENTORY.SITE.COUNTRY -> {INVENTORY.SITE.COUNTRY} <-
		INVENTORY.SITE.NOTES -> {INVENTORY.SITE.NOTES} <-
		INVENTORY.SITE.RACK -> {INVENTORY.SITE.RACK} <-
		INVENTORY.SITE.STATE -> {INVENTORY.SITE.STATE} <-
		INVENTORY.SITE.ZIP -> {INVENTORY.SITE.ZIP} <-
		INVENTORY.SOFTWARE -> {INVENTORY.SOFTWARE} <-
		INVENTORY.SOFTWARE.APP.A -> {INVENTORY.SOFTWARE.APP.A} <-
		INVENTORY.SOFTWARE.APP.B -> {INVENTORY.SOFTWARE.APP.B} <-
		INVENTORY.SOFTWARE.APP.C -> {INVENTORY.SOFTWARE.APP.C} <-
		INVENTORY.SOFTWARE.APP.D -> {INVENTORY.SOFTWARE.APP.D} <-
		INVENTORY.SOFTWARE.APP.E -> {INVENTORY.SOFTWARE.APP.E} <-
		INVENTORY.SOFTWARE.FULL -> {INVENTORY.SOFTWARE.FULL} <-
		INVENTORY.TAG -> {INVENTORY.TAG} <-
		INVENTORY.TYPE -> {INVENTORY.TYPE} <-
		INVENTORY.TYPE.FULL -> {INVENTORY.TYPE.FULL} <-
		INVENTORY.URL.A -> {INVENTORY.URL.A} <-
		INVENTORY.URL.B -> {INVENTORY.URL.B} <-
		INVENTORY.URL.C} -> {INVENTORY.URL.C} <-
		INVENTORY.VENDOR -> {INVENTORY.VENDOR} <-
		EOT;

	/* These macros are not resolved (internal event) */
	const BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.DEVICE.UPTIME}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.SERVICE.NAME}
		{DISCOVERY.SERVICE.PORT}
		{DISCOVERY.SERVICE.STATUS}
		{DISCOVERY.SERVICE.UPTIME}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{LLDRULE.DESCRIPTION}
		{LLDRULE.DESCRIPTION.ORIG}
		{LLDRULE.ID}
		{LLDRULE.KEY}
		{LLDRULE.KEY.ORIG}
		{LLDRULE.NAME}
		{LLDRULE.NAME.ORIG}
		{LLDRULE.STATE}
		{LLDRULE.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.ROOTCAUSE}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		EOT . "\n" .
		"{USER.USERNAME}\n" . // trigger macros moved to unresolved, as not supported in internal source event
		<<<'EOT'
		{EVENT.ACK.STATUS}
		{EVENT.NSEVERITY}
		{EVENT.OPDATA}
		{EVENT.SEVERITY}
		{EVENT.UPDATE.HISTORY}
		{EVENT.UPDATE.STATUS}
		{ITEM.LASTVALUE}
		{ITEM.VALUE}
		{TRIGGER.DESCRIPTION}
		{TRIGGER.EXPRESSION.EXPLAIN}
		{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.HOSTGROUP.NAME}
		{TRIGGER.EXPRESSION}
		{TRIGGER.EXPRESSION.RECOVERY}
		{TRIGGER.NAME}
		{TRIGGER.NAME.ORIG}
		{TRIGGER.NSEVERITY}
		{TRIGGER.SEVERITY}
		{TRIGGER.STATUS}
		{TRIGGER.URL}
		{TRIGGER.URL.NAME}
		{TRIGGER.VALUE}
		{TRIGGER.ID}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.SYMPTOMS}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		{TRIGGER.TEMPLATE.NAME}
		EOT;

/* Inconsistent macros (internal event) */
	const BUILTIN_MACROS_INCONSISTENT_RESOLVE_INTERNAL = <<<'EOT'
		ACTION.ID -> {ACTION.ID} <-
		ESC.HISTORY -> {ESC.HISTORY} <-
		DATE -> {DATE} <-
		TIME -> {TIME} <-
		EVENT.AGE -> {EVENT.AGE} <-
		EVENT.DATE -> {EVENT.DATE} <-
		EVENT.DURATION -> {EVENT.DURATION} <-
		EVENT.ID -> {EVENT.ID} <-
		EVENT.TIME -> {EVENT.TIME} <-
		HOST.ID -> {HOST.ID} <-
		ITEM.ID -> {ITEM.ID} <-
		EOT;

	private function disableHost(){

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => ['Zabbix server']
			]
		]);
		$this->assertArrayHasKey('hostid', $response['result'][0]);

		//disable default host to not interrupt testing via not supported items
		self::$host_id_disable = $response['result'][0]['hostid'];

		$response = $this->call('host.update', [
			'hostid' => self::$host_id_disable,
			'status' => ITEM_STATUS_DISABLED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
	}

	public function testMacros_InternalEvent() {

		CDataHelper::call('action.delete', [self::$trigger_action_id]); // delete previous action
		$this->removeValue(self::$action_ids, self::$trigger_action_id);

		$this->disableHost();

		// Create internal action
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => EVENT_TYPE_ITEM_NOTSUPPORTED
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_INTERNAL,
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "",
						'message' => self::BUILTIN_MACROS_INCONSISTENT_RESOLVE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$internal_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::TRAPPER_ITEM_NAME);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true);

		//get alert messages
		$alert_response_internal = $this->callUntilDataIsPresent('alert.get', [
			'hostids' => [self::$host_id],
			'actionids' => [self::$internal_action_id],
			'sortfield' => 'alertid',
			'eventobject' => EVENT_OBJECT_ITEM,
			'eventsource' => EVENT_SOURCE_INTERNAL
		], 4, 2);
		$this->assertCount(2, $alert_response_internal['result'], json_encode($alert_response_internal));

		// Test inconsistent macro resolution.

		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"ESC.HISTORY[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.DURATION[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"HOST.ID[\s\S]*" .
			"ITEM.ID[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, $alert_response_internal['result'][0]['message']);

		// setup resolved builtin consistent macros

		$event_name = "Value of type \"string\" is not suitable for value type \"Numeric (unsigned)\". Value \"trap !\"#\$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~\"";

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "			. self::ACTION_NAME								. " <-\n" .
			"EVENT.NAME -> "			. $event_name									. " <-\n" .
			"EVENT.OBJECT -> "			. '4'											. " <-\n" . // -Item
			"EVENT.SOURCE -> "			. '3'											. " <-\n" .
			"EVENT.STATUS -> "			. 'Not supported'								. " <-\n" .
			"EVENT.TAGS -> "			. ''											. " <-\n" .
			"EVENT.TAGSJSON -> "		. '[]'											. " <-\n" .
			"EVENT.VALUE -> "			. '1'											. " <-\n" .
			"HOST.CONN -> "				. '127.0.0.1'									. " <-\n" .
			"HOST.DESCRIPTION -> "		. ''											. " <-\n" .
			"HOST.DNS -> "				. ''											. " <-\n" .
			"HOST.HOST -> "				. self::HOST_NAME								. " <-\n" .
			"HOST.IP -> "				. '127.0.0.1'									. " <-\n" .
			"HOST.NAME -> "				. self::HOST_NAME								. " <-\n" .
			"HOST.PORT -> "				. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX	. " <-\n" .
			"ITEM.DESCRIPTION -> "		. ''											. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "	. ''											. " <-\n" .
			"ITEM.KEY -> "				. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.KEY.ORIG -> "			. self::TRAPPER_ITEM_KEY . '1'					. " <-\n" .
			"ITEM.NAME -> "				. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.NAME.ORIG -> "		. self::TRAPPER_ITEM_NAME . '1'					. " <-\n" .
			"ITEM.STATE -> "			. "Not supported"								. " <-\n" .
			"ITEM.STATE.ERROR -> "		. $event_name									. " <-\n" .
			"ITEM.VALUETYPE -> "		. ITEM_VALUE_TYPE_UINT64						. " <-\n" .
			"PROXY.DESCRIPTION -> "		. ''											. " <-\n" .
			"PROXY.NAME -> "			. ''											. " <-\n" .
			self::INVENTORY_RESOLVED;

		// Test builtin consistent macro resolution during the initial operation (item became not supported).

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

			$this->assertEquals($message_expect, $alert_response_internal['result'][1]['message']);

			// enable default host
			$response = $this->call('host.update', [
				'hostid' => self::$host_id_disable,
				'status' => ITEM_STATUS_ACTIVE
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			$this->assertEquals(1, count($response['result']['hostids']));
	}

	/***********************************************************************************************************************

		Test macro resolution (source: internal event LLD).

	***********************************************************************************************************************/

	private static $internal_lld_action_id;
	const LLD_RULE_NAME = "LLD";
	const LLD_KEY = "vfs.file.contents[/tmp/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz]";

	/*Subset of COMMON macros that are consistently resolved for internal events(LLD) only.*/
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL_LLD = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.NAME -> {EVENT.NAME} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		EVENT.STATUS -> {EVENT.STATUS} <-
		EVENT.TAGS -> {EVENT.TAGS} <-
		EVENT.TAGSJSON -> {EVENT.TAGSJSON} <-
		EVENT.VALUE -> {EVENT.VALUE} <-
		HOST.CONN -> {HOST.CONN} <-
		HOST.DESCRIPTION -> {HOST.DESCRIPTION} <-
		HOST.DNS -> {HOST.DNS} <-
		HOST.HOST -> {HOST.HOST} <-
		HOST.IP -> {HOST.IP} <-
		HOST.NAME -> {HOST.NAME} <-
		HOST.PORT -> {HOST.PORT} <-
		LLDRULE.DESCRIPTION -> {LLDRULE.DESCRIPTION} <-
		LLDRULE.DESCRIPTION.ORIG -> {LLDRULE.DESCRIPTION.ORIG} <-
		LLDRULE.KEY -> {LLDRULE.KEY} <-
		LLDRULE.KEY.ORIG -> {LLDRULE.KEY.ORIG} <-
		LLDRULE.NAME -> {LLDRULE.NAME} <-
		LLDRULE.NAME.ORIG -> {LLDRULE.NAME.ORIG} <-
		LLDRULE.STATE -> {LLDRULE.STATE} <-
		LLDRULE.STATE.ERROR -> {LLDRULE.STATE.ERROR} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		INVENTORY.ALIAS -> {INVENTORY.ALIAS} <-
		INVENTORY.ASSET.TAG -> {INVENTORY.ASSET.TAG} <-
		INVENTORY.CHASSIS -> {INVENTORY.CHASSIS} <-
		INVENTORY.CONTACT-> {INVENTORY.CONTACT} <-
		INVENTORY.CONTRACT.NUMBER -> {INVENTORY.CONTRACT.NUMBER} <-
		INVENTORY.DEPLOYMENT.STATUS -> {INVENTORY.DEPLOYMENT.STATUS} <-
		INVENTORY.HARDWARE -> {INVENTORY.HARDWARE} <-
		INVENTORY.HARDWARE.FULL -> {INVENTORY.HARDWARE.FULL} <-
		INVENTORY.HOST.NETMASK -> {INVENTORY.HOST.NETMASK} <-
		INVENTORY.HOST.NETWORKS -> {INVENTORY.HOST.NETWORKS} <-
		INVENTORY.HOST.ROUTER -> {INVENTORY.HOST.ROUTER} <-
		INVENTORY.HW.ARCH -> {INVENTORY.HW.ARCH} <-
		INVENTORY.HW.DATE.DECOMM -> {INVENTORY.HW.DATE.DECOMM} <-
		INVENTORY.HW.DATE.EXPIRY -> {INVENTORY.HW.DATE.EXPIRY} <-
		INVENTORY.HW.DATE.INSTALL -> {INVENTORY.HW.DATE.INSTALL} <-
		INVENTORY.HW.DATE.PURCHASE-> {INVENTORY.HW.DATE.PURCHASE} <-
		INVENTORY.INSTALLER.NAME -> {INVENTORY.INSTALLER.NAME} <-
		INVENTORY.LOCATION -> {INVENTORY.LOCATION} <-
		INVENTORY.LOCATION.LAT -> {INVENTORY.LOCATION.LAT} <-
		INVENTORY.LOCATION.LON -> {INVENTORY.LOCATION.LON} <-
		INVENTORY.MACADDRESS.A -> {INVENTORY.MACADDRESS.A} <-
		INVENTORY.MACADDRESS.B -> {INVENTORY.MACADDRESS.B} <-
		INVENTORY.MODEL -> {INVENTORY.MODEL} <-
		INVENTORY.NAME -> {INVENTORY.NAME} <-
		INVENTORY.NOTES -> {INVENTORY.NOTES} <-
		INVENTORY.OOB.IP -> {INVENTORY.OOB.IP} <-
		INVENTORY.OOB.NETMASK -> {INVENTORY.OOB.NETMASK} <-
		INVENTORY.OOB.ROUTER -> {INVENTORY.OOB.ROUTER} <-
		INVENTORY.OS -> {INVENTORY.OS} <-
		INVENTORY.OS.FULL -> {INVENTORY.OS.FULL} <-
		INVENTORY.OS.SHORT -> {INVENTORY.OS.SHORT} <-
		INVENTORY.POC.PRIMARY.CELL -> {INVENTORY.POC.PRIMARY.CELL} <-
		INVENTORY.POC.PRIMARY.EMAIL -> {INVENTORY.POC.PRIMARY.EMAIL} <-
		INVENTORY.POC.PRIMARY.NAME -> {INVENTORY.POC.PRIMARY.NAME} <-
		INVENTORY.POC.PRIMARY.NOTES -> {INVENTORY.POC.PRIMARY.NOTES} <-
		INVENTORY.POC.PRIMARY.PHONE.A -> {INVENTORY.POC.PRIMARY.PHONE.A} <-
		INVENTORY.POC.PRIMARY.PHONE.B -> {INVENTORY.POC.PRIMARY.PHONE.B} <-
		INVENTORY.POC.PRIMARY.SCREEN -> {INVENTORY.POC.PRIMARY.SCREEN} <-
		INVENTORY.POC.SECONDARY.CELL -> {INVENTORY.POC.SECONDARY.CELL} <-
		INVENTORY.POC.SECONDARY.EMAIL -> {INVENTORY.POC.SECONDARY.EMAIL} <-
		INVENTORY.POC.SECONDARY.NAME -> {INVENTORY.POC.SECONDARY.NAME} <-
		INVENTORY.POC.SECONDARY.NOTES -> {INVENTORY.POC.SECONDARY.NOTES} <-
		INVENTORY.POC.SECONDARY.PHONE.A -> {INVENTORY.POC.SECONDARY.PHONE.A} <-
		INVENTORY.POC.SECONDARY.PHONE.B -> {INVENTORY.POC.SECONDARY.PHONE.B} <-
		INVENTORY.POC.SECONDARY.SCREEN -> {INVENTORY.POC.SECONDARY.SCREEN} <-
		INVENTORY.SERIALNO.A -> {INVENTORY.SERIALNO.A} <-
		INVENTORY.SERIALNO.B -> {INVENTORY.SERIALNO.B} <-
		INVENTORY.SITE.ADDRESS.A -> {INVENTORY.SITE.ADDRESS.A} <-
		INVENTORY.SITE.ADDRESS.B -> {INVENTORY.SITE.ADDRESS.B} <-
		INVENTORY.SITE.ADDRESS.C -> {INVENTORY.SITE.ADDRESS.C} <-
		INVENTORY.SITE.CITY -> {INVENTORY.SITE.CITY} <-
		INVENTORY.SITE.COUNTRY -> {INVENTORY.SITE.COUNTRY} <-
		INVENTORY.SITE.NOTES -> {INVENTORY.SITE.NOTES} <-
		INVENTORY.SITE.RACK -> {INVENTORY.SITE.RACK} <-
		INVENTORY.SITE.STATE -> {INVENTORY.SITE.STATE} <-
		INVENTORY.SITE.ZIP -> {INVENTORY.SITE.ZIP} <-
		INVENTORY.SOFTWARE -> {INVENTORY.SOFTWARE} <-
		INVENTORY.SOFTWARE.APP.A -> {INVENTORY.SOFTWARE.APP.A} <-
		INVENTORY.SOFTWARE.APP.B -> {INVENTORY.SOFTWARE.APP.B} <-
		INVENTORY.SOFTWARE.APP.C -> {INVENTORY.SOFTWARE.APP.C} <-
		INVENTORY.SOFTWARE.APP.D -> {INVENTORY.SOFTWARE.APP.D} <-
		INVENTORY.SOFTWARE.APP.E -> {INVENTORY.SOFTWARE.APP.E} <-
		INVENTORY.SOFTWARE.FULL -> {INVENTORY.SOFTWARE.FULL} <-
		INVENTORY.TAG -> {INVENTORY.TAG} <-
		INVENTORY.TYPE -> {INVENTORY.TYPE} <-
		INVENTORY.TYPE.FULL -> {INVENTORY.TYPE.FULL} <-
		INVENTORY.URL.A -> {INVENTORY.URL.A} <-
		INVENTORY.URL.B -> {INVENTORY.URL.B} <-
		INVENTORY.URL.C} -> {INVENTORY.URL.C} <-
		INVENTORY.VENDOR -> {INVENTORY.VENDOR} <-
		EOT;

	/* These macros are not resolved (internal event LLD) */
	const BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_LLD = <<<'EOT'
		"{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.DEVICE.UPTIME}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.SERVICE.NAME}
		{DISCOVERY.SERVICE.PORT}
		{DISCOVERY.SERVICE.STATUS}
		{DISCOVERY.SERVICE.UPTIME}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{ITEM.DESCRIPTION}
		{ITEM.DESCRIPTION.ORIG}
		{ITEM.KEY}
		{ITEM.KEY.ORIG}
		{ITEM.NAME}
		{ITEM.NAME.ORIG}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{ITEM.VALUETYPE}
		{MAP.ID}
		{MAP.NAME}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.ROOTCAUSE}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		EOT . "\n" .
		"{USER.USERNAME}\n" . // all inventory macros added to not resolvable as not supported for service events
		<<<'EOT'
		"{EVENT.ACK.STATUS}
		{EVENT.NSEVERITY}
		{EVENT.OPDATA}
		{EVENT.SEVERITY}
		{EVENT.UPDATE.HISTORY}
		{EVENT.UPDATE.STATUS}
		{ITEM.LASTVALUE}
		{ITEM.VALUE}
		{TRIGGER.DESCRIPTION}
		{TRIGGER.EXPRESSION.EXPLAIN}
		{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.HOSTGROUP.NAME}
		{TRIGGER.EXPRESSION}
		{TRIGGER.EXPRESSION.RECOVERY}
		{TRIGGER.NAME}
		{TRIGGER.NAME.ORIG}
		{TRIGGER.NSEVERITY}
		{TRIGGER.SEVERITY}
		{TRIGGER.STATUS}
		{TRIGGER.URL}
		{TRIGGER.URL.NAME}
		{TRIGGER.VALUE}
		{TRIGGER.ID}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.SYMPTOMS}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		{TRIGGER.TEMPLATE.NAME}
		EOT;

	/**
	* Component configuration provider for agent related tests.
	*
	* @return array
	*/
	public function agentConfigurationProvider() {
		self::$metadata_file = "/tmp/zabbix_agent_metadata_file_" . time() . ".txt";

		return [
			self::COMPONENT_AGENT => [
				'Hostname'		=>	self::HOST_NAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadataItem'	=>	'vfs.file.contents['.self::$metadata_file.']'
			]
		];
	}

	/**
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent
	 */

	public function testMacros_InternalEventLLD() {

		CDataHelper::call('action.delete', [self::$internal_action_id]); // delete previous action
		$this->removeValue(self::$action_ids, self::$internal_action_id);

		// Create internal action for LLD
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => EVENT_TYPE_LLDRULE_NOTSUPPORTED
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_INTERNAL,
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL_LLD . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_LLD . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "",
						'message' => self::BUILTIN_MACROS_INCONSISTENT_RESOLVE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$internal_lld_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$response = $this->call('hostinterface.get', [
			'hostids' => self::$host_id,
			'output' => ['interfaceid']
			]);
		$interface_id = $response['result'][0]['interfaceid'];

		//Create LLD rule
		$response = $this->call('discoveryrule.create', [
		'hostid' => self::$host_id,
			'name' => self::LLD_RULE_NAME,
			'key_' => self::LLD_KEY,
			'type' => ITEM_TYPE_ZABBIX,
			'delay'=> "3s",
			'interfaceid' => $interface_id
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of add_message_alert()', true);

		//get alert messages
		$alert_response_internal_LLD = $this->callUntilDataIsPresent('alert.get', [
			'hostids' => [self::$host_id],
			'actionids' => [self::$internal_lld_action_id],
			'sortfield' => 'alertid',
			'eventobject' => EVENT_OBJECT_LLDRULE,
			'eventsource' => EVENT_SOURCE_INTERNAL
		], 4, 2);
		$this->assertCount(2, $alert_response_internal_LLD['result']);

		//test inconsistent macros
		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"ESC.HISTORY[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.DURATION[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"HOST.ID[\s\S]*" .
			"ITEM.ID[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, $alert_response_internal_LLD['result'][0]['message']);

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "				. self::ACTION_NAME																			. " <-\n" .
			"EVENT.NAME -> "				. "{EVENT.NAME}"																			. " <-\n" .
			"EVENT.OBJECT -> "				. '5'																						. " <-\n" . // LLD Rule
			"EVENT.SOURCE -> "				. '3'																						. " <-\n" .
			"EVENT.STATUS -> "				. 'Not supported'																			. " <-\n" .
			"EVENT.TAGS -> "				. ''																						. " <-\n" .
			"EVENT.TAGSJSON -> "			. '[]'																						. " <-\n" .
			"EVENT.VALUE -> "				. '1'																						. " <-\n" .
			"HOST.CONN -> "					. '127.0.0.1'																				. " <-\n" .
			"HOST.DESCRIPTION -> "			. ''																						. " <-\n" .
			"HOST.DNS -> "					. ''																						. " <-\n" .
			"HOST.HOST -> "					. self::HOST_NAME																			. " <-\n" .
			"HOST.IP -> "					. '127.0.0.1'																				. " <-\n" .
			"HOST.NAME -> "					. self::HOST_NAME																			. " <-\n" .
			"HOST.PORT -> "					. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX												. " <-\n" .
			"LLDRULE.DESCRIPTION -> "		. ''																						. " <-\n" .
			"LLDRULE.DESCRIPTION.ORIG -> "	. ''																						. " <-\n" .
			"LLDRULE.KEY -> "				. 'vfs.file.contents[/tmp/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz]'	. " <-\n" .
			"LLDRULE.KEY.ORIG -> "			. 'vfs.file.contents[/tmp/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz]'	. " <-\n" .
			"LLDRULE.NAME -> "				. 'LLD'																						. " <-\n" .
			"LLDRULE.NAME.ORIG -> "			. 'LLD'																						. " <-\n" .
			"LLDRULE.STATE -> "				. 'Not supported'																			. " <-\n" .
			"LLDRULE.STATE.ERROR -> "		. 'Cannot open file: [2] No such file or directory'											. " <-\n" .
			"PROXY.DESCRIPTION -> "			. ''																						. " <-\n" .
			"PROXY.NAME -> "				. ''																						. " <-\n" .
			self::INVENTORY_RESOLVED;

		// Test builtin consistent macro resolution during the initial operation (LLD became not supported).

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_LLD . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

			$this->assertEquals($message_expect, $alert_response_internal_LLD['result'][1]['message']);
	}

	/***********************************************************************************************************************

	Test macro resolution (source: internal event trigger).

	***********************************************************************************************************************/

	private static $internal_trigger_action_id;

	/*Subset of COMMON macros that are consistently resolved for internal events(LLD) only.*/
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL_TRIGGER = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.NAME -> {EVENT.NAME} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		EVENT.STATUS -> {EVENT.STATUS} <-
		EVENT.TAGS -> {EVENT.TAGS} <-
		EVENT.TAGSJSON -> {EVENT.TAGSJSON} <-
		EVENT.VALUE -> {EVENT.VALUE} <-
		HOST.CONN -> {HOST.CONN} <-
		HOST.DESCRIPTION -> {HOST.DESCRIPTION} <-
		HOST.DNS -> {HOST.DNS} <-
		HOST.HOST -> {HOST.HOST} <-
		HOST.IP -> {HOST.IP} <-
		HOST.NAME -> {HOST.NAME} <-
		HOST.PORT -> {HOST.PORT} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		ITEM.DESCRIPTION -> {ITEM.DESCRIPTION} <-
		ITEM.DESCRIPTION.ORIG -> {ITEM.DESCRIPTION.ORIG} <-
		ITEM.KEY -> {ITEM.KEY} <-
		ITEM.KEY.ORIG -> {ITEM.KEY.ORIG} <-
		ITEM.NAME -> {ITEM.NAME} <-
		ITEM.NAME.ORIG -> {ITEM.NAME.ORIG} <-
		ITEM.VALUETYPE -> {ITEM.VALUETYPE} <-
		TRIGGER.STATE -> {TRIGGER.STATE} <-
		TRIGGER.STATE.ERROR -> {TRIGGER.STATE.ERROR} <-
		TRIGGER.DESCRIPTION -> {TRIGGER.DESCRIPTION} <-
		TRIGGER.HOSTGROUP.NAME -> {TRIGGER.HOSTGROUP.NAME} <-
		TRIGGER.EXPRESSION -> {TRIGGER.EXPRESSION} <-
		TRIGGER.EXPRESSION.RECOVERY -> {TRIGGER.EXPRESSION.RECOVERY} <-
		TRIGGER.NAME -> {TRIGGER.NAME} <-
		TRIGGER.NAME.ORIG -> {TRIGGER.NAME.ORIG} <-
		TRIGGER.NSEVERITY -> {TRIGGER.NSEVERITY} <-
		TRIGGER.SEVERITY -> {TRIGGER.SEVERITY} <-
		TRIGGER.URL -> {TRIGGER.URL} <-
		TRIGGER.URL.NAME -> {TRIGGER.URL.NAME} <-
		INVENTORY.ALIAS -> {INVENTORY.ALIAS} <-
		INVENTORY.ASSET.TAG -> {INVENTORY.ASSET.TAG} <-
		INVENTORY.CHASSIS -> {INVENTORY.CHASSIS} <-
		INVENTORY.CONTACT-> {INVENTORY.CONTACT} <-
		INVENTORY.CONTRACT.NUMBER -> {INVENTORY.CONTRACT.NUMBER} <-
		INVENTORY.DEPLOYMENT.STATUS -> {INVENTORY.DEPLOYMENT.STATUS} <-
		INVENTORY.HARDWARE -> {INVENTORY.HARDWARE} <-
		INVENTORY.HARDWARE.FULL -> {INVENTORY.HARDWARE.FULL} <-
		INVENTORY.HOST.NETMASK -> {INVENTORY.HOST.NETMASK} <-
		INVENTORY.HOST.NETWORKS -> {INVENTORY.HOST.NETWORKS} <-
		INVENTORY.HOST.ROUTER -> {INVENTORY.HOST.ROUTER} <-
		INVENTORY.HW.ARCH -> {INVENTORY.HW.ARCH} <-
		INVENTORY.HW.DATE.DECOMM -> {INVENTORY.HW.DATE.DECOMM} <-
		INVENTORY.HW.DATE.EXPIRY -> {INVENTORY.HW.DATE.EXPIRY} <-
		INVENTORY.HW.DATE.INSTALL -> {INVENTORY.HW.DATE.INSTALL} <-
		INVENTORY.HW.DATE.PURCHASE-> {INVENTORY.HW.DATE.PURCHASE} <-
		INVENTORY.INSTALLER.NAME -> {INVENTORY.INSTALLER.NAME} <-
		INVENTORY.LOCATION -> {INVENTORY.LOCATION} <-
		INVENTORY.LOCATION.LAT -> {INVENTORY.LOCATION.LAT} <-
		INVENTORY.LOCATION.LON -> {INVENTORY.LOCATION.LON} <-
		INVENTORY.MACADDRESS.A -> {INVENTORY.MACADDRESS.A} <-
		INVENTORY.MACADDRESS.B -> {INVENTORY.MACADDRESS.B} <-
		INVENTORY.MODEL -> {INVENTORY.MODEL} <-
		INVENTORY.NAME -> {INVENTORY.NAME} <-
		INVENTORY.NOTES -> {INVENTORY.NOTES} <-
		INVENTORY.OOB.IP -> {INVENTORY.OOB.IP} <-
		INVENTORY.OOB.NETMASK -> {INVENTORY.OOB.NETMASK} <-
		INVENTORY.OOB.ROUTER -> {INVENTORY.OOB.ROUTER} <-
		INVENTORY.OS -> {INVENTORY.OS} <-
		INVENTORY.OS.FULL -> {INVENTORY.OS.FULL} <-
		INVENTORY.OS.SHORT -> {INVENTORY.OS.SHORT} <-
		INVENTORY.POC.PRIMARY.CELL -> {INVENTORY.POC.PRIMARY.CELL} <-
		INVENTORY.POC.PRIMARY.EMAIL -> {INVENTORY.POC.PRIMARY.EMAIL} <-
		INVENTORY.POC.PRIMARY.NAME -> {INVENTORY.POC.PRIMARY.NAME} <-
		INVENTORY.POC.PRIMARY.NOTES -> {INVENTORY.POC.PRIMARY.NOTES} <-
		INVENTORY.POC.PRIMARY.PHONE.A -> {INVENTORY.POC.PRIMARY.PHONE.A} <-
		INVENTORY.POC.PRIMARY.PHONE.B -> {INVENTORY.POC.PRIMARY.PHONE.B} <-
		INVENTORY.POC.PRIMARY.SCREEN -> {INVENTORY.POC.PRIMARY.SCREEN} <-
		INVENTORY.POC.SECONDARY.CELL -> {INVENTORY.POC.SECONDARY.CELL} <-
		INVENTORY.POC.SECONDARY.EMAIL -> {INVENTORY.POC.SECONDARY.EMAIL} <-
		INVENTORY.POC.SECONDARY.NAME -> {INVENTORY.POC.SECONDARY.NAME} <-
		INVENTORY.POC.SECONDARY.NOTES -> {INVENTORY.POC.SECONDARY.NOTES} <-
		INVENTORY.POC.SECONDARY.PHONE.A -> {INVENTORY.POC.SECONDARY.PHONE.A} <-
		INVENTORY.POC.SECONDARY.PHONE.B -> {INVENTORY.POC.SECONDARY.PHONE.B} <-
		INVENTORY.POC.SECONDARY.SCREEN -> {INVENTORY.POC.SECONDARY.SCREEN} <-
		INVENTORY.SERIALNO.A -> {INVENTORY.SERIALNO.A} <-
		INVENTORY.SERIALNO.B -> {INVENTORY.SERIALNO.B} <-
		INVENTORY.SITE.ADDRESS.A -> {INVENTORY.SITE.ADDRESS.A} <-
		INVENTORY.SITE.ADDRESS.B -> {INVENTORY.SITE.ADDRESS.B} <-
		INVENTORY.SITE.ADDRESS.C -> {INVENTORY.SITE.ADDRESS.C} <-
		INVENTORY.SITE.CITY -> {INVENTORY.SITE.CITY} <-
		INVENTORY.SITE.COUNTRY -> {INVENTORY.SITE.COUNTRY} <-
		INVENTORY.SITE.NOTES -> {INVENTORY.SITE.NOTES} <-
		INVENTORY.SITE.RACK -> {INVENTORY.SITE.RACK} <-
		INVENTORY.SITE.STATE -> {INVENTORY.SITE.STATE} <-
		INVENTORY.SITE.ZIP -> {INVENTORY.SITE.ZIP} <-
		INVENTORY.SOFTWARE -> {INVENTORY.SOFTWARE} <-
		INVENTORY.SOFTWARE.APP.A -> {INVENTORY.SOFTWARE.APP.A} <-
		INVENTORY.SOFTWARE.APP.B -> {INVENTORY.SOFTWARE.APP.B} <-
		INVENTORY.SOFTWARE.APP.C -> {INVENTORY.SOFTWARE.APP.C} <-
		INVENTORY.SOFTWARE.APP.D -> {INVENTORY.SOFTWARE.APP.D} <-
		INVENTORY.SOFTWARE.APP.E -> {INVENTORY.SOFTWARE.APP.E} <-
		INVENTORY.SOFTWARE.FULL -> {INVENTORY.SOFTWARE.FULL} <-
		INVENTORY.TAG -> {INVENTORY.TAG} <-
		INVENTORY.TYPE -> {INVENTORY.TYPE} <-
		INVENTORY.TYPE.FULL -> {INVENTORY.TYPE.FULL} <-
		INVENTORY.URL.A -> {INVENTORY.URL.A} <-
		INVENTORY.URL.B -> {INVENTORY.URL.B} <-
		INVENTORY.URL.C} -> {INVENTORY.URL.C} <-
		INVENTORY.VENDOR -> {INVENTORY.VENDOR} <-
		EOT;

	/* These macros are not resolved (internal event trigger) */
	const BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_TRIGGER = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.DEVICE.UPTIME}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.SERVICE.NAME}
		{DISCOVERY.SERVICE.PORT}
		{DISCOVERY.SERVICE.STATUS}
		{DISCOVERY.SERVICE.UPTIME}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.ROOTCAUSE}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		{USER.USERNAME}
		{EVENT.ACK.STATUS}
		{EVENT.NSEVERITY}
		{EVENT.OPDATA}
		{EVENT.SEVERITY}
		{EVENT.UPDATE.HISTORY}
		{EVENT.UPDATE.STATUS}
		{ITEM.LASTVALUE}
		{ITEM.VALUE}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.STATUS}
		{TRIGGER.VALUE}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.SYMPTOMS}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		EOT;

	public function testMacros_InternalEventTrigger() {

		CDataHelper::call('action.delete', [self::$internal_lld_action_id]); // delete previous action
		$this->removeValue(self::$action_ids, self::$internal_lld_action_id);
		CDataHelper::call('trigger.delete', [self::$trigger_id]); //delete previous trigger
		$this->disableHost();

		// Create internal action for internal trigger event
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => EVENT_TYPE_TRIGGER_UNKNOWN
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "Internal Trigger",
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_INTERNAL_TRIGGER . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_TRIGGER . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "",
						'message' => self::BUILTIN_MACROS_INCONSISTENT_RESOLVE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$internal_trigger_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$trigger_expression_internal = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'2)='.self::VALUE_TO_FIRE_TRIGGER;

		//create trigger
		$response = $this->call('trigger.create', [
			'description'			=> 'triger_internal',
			'expression' 			=> $trigger_expression_internal,
			'recovery_expression' 	=> self::$trigger_recovery_expression,
			'recovery_mode' 		=> self::TRIGGER_RECOVERY_MODE,
			'event_name' 			=> self::$event_name,
			'priority' 				=> self::TRIGGER_PRIORITY,
			'status' 				=> self::TRIGGER_STATUS_ENABLED,
			'comments' 				=> self::TRIGGER_COMMENTS,
			'url' 					=> self::TRIGGER_URL,
			'url_name' 				=> self::TRIGGER_URL_NAME,
			'type' 					=> self::TRIGGER_TYPE,
			'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
			'correlation_mode'		=> self::TRIGGER_CORRELATION_MODE,
			'correlation_tag'		=> self::TAG_NAME,
			'manual_close' 			=> self::TRIGGER_MANUAL_CLOSE,
			'opdata'				=> self::TRIGGER_OPDATA,
			'tags' => [
				[
					'tag' => self::TAG_NAME_SERVICE,
					'value' => self::TAG_VALUE_SERVICE
				]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		// mark item as unsupported to force trigger into an unknown state
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'2', self::TRAPPER_ITEM_NAME);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of add_message_alert()', true);

		$alert_response_internal_trigger = $this->callUntilDataIsPresent('alert.get', [
			'hostids'		=>  [self::$host_id],
			'actionids'		=> [self::$internal_trigger_action_id],
			'sortfield'		=> 'alertid',
			'eventobject'	=> EVENT_OBJECT_TRIGGER,
			'eventsource'	=> EVENT_SOURCE_INTERNAL
		], 2, 2);
		$this->assertCount(2, $alert_response_internal_trigger['result'], json_encode($alert_response_internal_trigger));

		//test inconsistent macros
		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"ESC.HISTORY[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.DURATION[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"HOST.ID[\s\S]*" .
			"ITEM.ID[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, $alert_response_internal_trigger['result'][0]['message']);

		// setup resolved builtin consistent macros
		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "					. self::ACTION_NAME																	. " <-\n" .
			"EVENT.NAME -> "					. 'Cannot evaluate function last(/test_macros_host/trap2): item is not supported.'	. " <-\n" .
			"EVENT.OBJECT -> "					. '0'																				. " <-\n" . // trigger
			"EVENT.SOURCE -> "					. '3'																				. " <-\n" . // internal event
			"EVENT.STATUS -> "					. 'Unknown'																			. " <-\n" .
			"EVENT.TAGS -> "					. 'tag_name:tag_value'																. " <-\n" .
			"EVENT.TAGSJSON -> "				. '[{"tag":"tag_name","value":"tag_value"}]'										. " <-\n" .
			"EVENT.VALUE -> "					. '1'																				. " <-\n" .
			"HOST.CONN -> "						. '127.0.0.1'																		. " <-\n" .
			"HOST.DESCRIPTION -> "				. ''																				. " <-\n" .
			"HOST.DNS -> "						. ''																				. " <-\n" .
			"HOST.HOST -> "						. self::HOST_NAME																	. " <-\n" .
			"HOST.IP -> "						. '127.0.0.1'																		. " <-\n" .
			"HOST.NAME -> "						. self::HOST_NAME																	. " <-\n" .
			"HOST.PORT -> "						. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX										. " <-\n" .
			"PROXY.DESCRIPTION -> "				. ''																				. " <-\n" .
			"PROXY.NAME -> "					. ''																				. " <-\n" .
			"ITEM.DESCRIPTION -> "				. ''																				. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "			. ''																				. " <-\n" .
			"ITEM.KEY -> "						. 'trap2'																			. " <-\n" .
			"ITEM.KEY.ORIG -> "					. 'trap2'																			. " <-\n" .
			"ITEM.NAME -> "						. 'trap' . ALL_PRINTABLE_ASCII . "2"												. " <-\n" .
			"ITEM.NAME.ORIG -> "				. 'trap' . ALL_PRINTABLE_ASCII . "2"												. " <-\n" .
			"ITEM.VALUETYPE -> "				. '3'																				. " <-\n" .
			"TRIGGER.STATE -> "					. 'Unknown'																			. " <-\n" .
			"TRIGGER.STATE.ERROR -> "			. 'Cannot evaluate function last(/test_macros_host/trap2): item is not supported.'	. " <-\n" .
			"TRIGGER.DESCRIPTION -> "			. 'strata_trigger_comment_' . ALL_PRINTABLE_ASCII									. " <-\n" .
			"TRIGGER.HOSTGROUP.NAME -> "		. 'Zabbix servers'																	. " <-\n" .
			"TRIGGER.EXPRESSION -> "			. 'last(/test_macros_host/trap2)=3'													. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY -> "	. 'last(/test_macros_host/trap1)=2147483647'										. " <-\n" .
			"TRIGGER.NAME -> "					. 'triger_internal'																	. " <-\n" .
			"TRIGGER.NAME.ORIG -> "				. 'triger_internal'																	. " <-\n" .
			"TRIGGER.NSEVERITY -> "				. '3'																				. " <-\n" .
			"TRIGGER.SEVERITY -> "				. 'Average'																			. " <-\n" .
			"TRIGGER.URL -> "					. 'strata_trigger_url_' . ALL_PRINTABLE_ASCII										. " <-\n" .
			"TRIGGER.URL.NAME -> "				. 'url_name_' . REDUCTED_PRINTABLE_ASCII											. " <-\n" .
			self::INVENTORY_RESOLVED;

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_INTERNAL_TRIGGER . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> HOST_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

			$this->assertEquals($message_expect, $alert_response_internal_trigger['result'][1]['message']);

			// enable default host
			$response = $this->call('host.update', [
				'hostid' => self::$host_id_disable,
				'status' => ITEM_STATUS_ACTIVE
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			$this->assertEquals(1, count($response['result']['hostids']));
	}

	/***************************************************************************************************************

		Test macro resolution (source: service event).

	***************************************************************************************************************/
	private static $service_id;
	private static $service_action_id;
	const TRAPPER_ITEM_KEY_SERVICE = 'trap_service';
	const TAG_NAME_SERVICE = 'tag_name';
	const TAG_VALUE_SERVICE = 'tag_value';
	private static $alert_response_service;
	const SERVICE_NAME = 'Server1';
	const SUBJECT_SERVICE = 'Service';

	/* These macros are not resolved(service event). */
	const BUILTIN_MACROS_NON_RESOLVABLE_SERVICE = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.DEVICE.UPTIME}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.SERVICE.NAME}
		{DISCOVERY.SERVICE.PORT}
		{DISCOVERY.SERVICE.STATUS}
		{DISCOVERY.SERVICE.UPTIME}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{LLDRULE.DESCRIPTION}
		{LLDRULE.DESCRIPTION.ORIG}
		{LLDRULE.ID}
		{LLDRULE.KEY}
		{LLDRULE.KEY.ORIG}
		{LLDRULE.NAME}
		{LLDRULE.NAME.ORIG}
		{LLDRULE.STATE}
		{LLDRULE.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		{USER.USERNAME}
		EOT . "\n" .
		"{INVENTORY.ALIAS}\n" . // all inventory macros added to not resolvable as not supported for service events
		<<<'EOT'
		{INVENTORY.ASSET.TAG}
		{INVENTORY.CHASSIS}
		{INVENTORY.CONTACT}
		{INVENTORY.CONTRACT.NUMBER}
		{INVENTORY.DEPLOYMENT.STATUS}
		{INVENTORY.HARDWARE}
		{INVENTORY.HARDWARE.FULL}
		{INVENTORY.HOST.NETMASK}
		{INVENTORY.HOST.NETWORKS}
		{INVENTORY.HOST.ROUTER}
		{INVENTORY.HW.ARCH}
		{INVENTORY.HW.DATE.DECOMM}
		{INVENTORY.HW.DATE.EXPIRY}
		{INVENTORY.HW.DATE.INSTALL}
		{INVENTORY.HW.DATE.PURCHASE}
		{INVENTORY.INSTALLER.NAME}
		{INVENTORY.LOCATION}
		{INVENTORY.LOCATION.LAT}
		{INVENTORY.LOCATION.LON}
		{INVENTORY.MACADDRESS.A}
		{INVENTORY.MACADDRESS.B}
		{INVENTORY.MODEL}
		{INVENTORY.NAME}
		{INVENTORY.NOTES}
		{INVENTORY.OOB.IP}
		{INVENTORY.OOB.NETMASK}
		{INVENTORY.OOB.ROUTER}
		{INVENTORY.OS}
		{INVENTORY.OS.FULL}
		{INVENTORY.OS.SHORT}
		{INVENTORY.POC.PRIMARY.CELL}
		{INVENTORY.POC.PRIMARY.EMAIL}
		{INVENTORY.POC.PRIMARY.NAME}
		{INVENTORY.POC.PRIMARY.NOTES}
		{INVENTORY.POC.PRIMARY.PHONE.A}
		{INVENTORY.POC.PRIMARY.PHONE.B}
		{INVENTORY.POC.PRIMARY.SCREEN}
		{INVENTORY.POC.SECONDARY.CELL}
		{INVENTORY.POC.SECONDARY.EMAIL}
		{INVENTORY.POC.SECONDARY.NAME}
		{INVENTORY.POC.SECONDARY.NOTES}
		{INVENTORY.POC.SECONDARY.PHONE.A}
		{INVENTORY.POC.SECONDARY.PHONE.B}
		{INVENTORY.POC.SECONDARY.SCREEN}
		{INVENTORY.SERIALNO.A}
		{INVENTORY.SERIALNO.B}
		{INVENTORY.SITE.ADDRESS.A}
		{INVENTORY.SITE.ADDRESS.B}
		{INVENTORY.SITE.ADDRESS.C}
		{INVENTORY.SITE.CITY}
		{INVENTORY.SITE.COUNTRY}
		{INVENTORY.SITE.NOTES}
		{INVENTORY.SITE.RACK}
		{INVENTORY.SITE.STATE}
		{INVENTORY.SITE.ZIP}
		{INVENTORY.SOFTWARE}
		{INVENTORY.SOFTWARE.APP.A}
		{INVENTORY.SOFTWARE.APP.B}
		{INVENTORY.SOFTWARE.APP.C}
		{INVENTORY.SOFTWARE.APP.D}
		{INVENTORY.SOFTWARE.APP.E}
		{INVENTORY.SOFTWARE.FULL}
		{INVENTORY.TAG}
		{INVENTORY.TYPE}
		{INVENTORY.TYPE.FULL}
		{INVENTORY.URL.A}
		{INVENTORY.URL.B}
		{INVENTORY.URL.C}
		{INVENTORY.VENDOR}
		{EVENT.ACK.STATUS}
		{EVENT.OPDATA}
		{EVENT.UPDATE.HISTORY}
		{HOST.CONN}
		{HOST.DESCRIPTION}
		{HOST.DNS}
		{HOST.HOST}
		{HOST.IP}
		{HOST.NAME}
		{HOST.PORT}
		{ITEM.DESCRIPTION}
		{ITEM.DESCRIPTION.ORIG}
		{ITEM.KEY}
		{ITEM.KEY.ORIG}
		{ITEM.LASTVALUE}
		{ITEM.NAME}
		{ITEM.NAME.ORIG}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{ITEM.VALUE}
		{ITEM.VALUETYPE}
		{PROXY.DESCRIPTION}
		{PROXY.NAME}
		{TRIGGER.DESCRIPTION}
		{TRIGGER.EXPRESSION.EXPLAIN}
		{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.HOSTGROUP.NAME}
		{TRIGGER.EXPRESSION}
		{TRIGGER.EXPRESSION.RECOVERY}
		{TRIGGER.NAME}
		{TRIGGER.NAME.ORIG}
		{TRIGGER.NSEVERITY}
		{TRIGGER.SEVERITY}
		{TRIGGER.STATUS}
		{TRIGGER.URL}
		{TRIGGER.URL.NAME}
		{TRIGGER.VALUE}
		{HOST.ID}
		{ITEM.ID}
		{TRIGGER.ID}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.SYMPTOMS}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		{TRIGGER.TEMPLATE.NAME}
		EOT;

	/* Subset of COMMON macros that are consistently resolved for service events only. */
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_SERVICE = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.NAME -> {EVENT.NAME} <-
		EVENT.NSEVERITY -> {EVENT.NSEVERITY} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SEVERITY -> {EVENT.SEVERITY} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		EVENT.STATUS -> {EVENT.STATUS} <-
		EVENT.TAGS -> {EVENT.TAGS} <-
		EVENT.TAGSJSON -> {EVENT.TAGSJSON} <-
		EVENT.UPDATE.STATUS -> {EVENT.UPDATE.STATUS} <-
		EVENT.VALUE -> {EVENT.VALUE} <-
		EOT . "\n" .
		"SERVICE.DESCRIPTION -> {SERVICE.DESCRIPTION} <-\n" .	// added service macros (Service-based notification)
		<<<'EOT'
		SERVICE.NAME -> {SERVICE.NAME} <-
		SERVICE.TAGS -> {SERVICE.TAGS} <-
		SERVICE.TAGSJSON -> {SERVICE.TAGSJSON} <-
		EOT;

	/* Inconsistent macros (service event) */
	const BUILTIN_MACROS_INCONSISTENT_RESOLVE_SERVICE = <<<'EOT'
		ACTION.ID -> {ACTION.ID} <-
		ESC.HISTORY -> {ESC.HISTORY} <-
		DATE -> {DATE} <-
		TIME -> {TIME} <-
		EVENT.AGE -> {EVENT.AGE} <-
		EVENT.DATE -> {EVENT.DATE} <-
		EVENT.DURATION -> {EVENT.DURATION} <-
		EVENT.ID -> {EVENT.ID} <-
		EVENT.TIME -> {EVENT.TIME} <-
		EOT . "\n" .
		"SERVICE.ROOTCAUSE -> {SERVICE.ROOTCAUSE} <-";	// added to inconsistens - Age: field (seconds)

	public function testMacros_ServiceEvent() {

		CDataHelper::call('action.delete', [self::$internal_trigger_action_id]); // delete previous action
		$this->removeValue(self::$action_ids, self::$internal_trigger_action_id);

		$response = $this->call('item.create', [
			'hostid'		=> self::$host_id,
			'name'			=> self::TRAPPER_ITEM_KEY_SERVICE,
			'key_'			=> self::TRAPPER_ITEM_KEY_SERVICE,
			'type'			=> ITEM_TYPE_TRAPPER,
			'value_type'	=> ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$trigger_expression = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY_SERVICE.')='.self::VALUE_TO_FIRE_TRIGGER;

		//create trigger
		$response = $this->call('trigger.create', [
			'description'			=> 'trigger_trap',
			'expression'			=> self::$trigger_expression,
			'recovery_expression'	=> self::$trigger_recovery_expression,
			'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
			'event_name'			=> self::$event_name,
			'priority'				=> self::TRIGGER_PRIORITY,
			'status'				=> self::TRIGGER_STATUS_ENABLED,
			'comments'				=> self::TRIGGER_COMMENTS,
			'url'					=> self::TRIGGER_URL,
			'url_name'				=> self::TRIGGER_URL_NAME,
			'type'					=> self::TRIGGER_TYPE,
			'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
			'correlation_mode'		=> self::TRIGGER_CORRELATION_MODE,
			'correlation_tag'		=> self::TAG_NAME,
			'manual_close'			=> self::TRIGGER_MANUAL_CLOSE,
			'opdata'				=> self::TRIGGER_OPDATA,
			'tags' => [
				[
					'tag'	=> self::TAG_NAME_SERVICE,
					'value'	=> self::TAG_VALUE_SERVICE
				]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$trigger_id = $response['result']['triggerids'][0];

		// Create service on that trigger
		$response = $this->call('service.create', [
			'name' => self::SERVICE_NAME,
			'algorithm'		=> 1,
			'sortorder'		=> 1,
			'problem_tags'	=> [
				[
					'tag'	=> self::TAG_NAME_SERVICE,
					'value' => self::TAG_VALUE_SERVICE
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertEquals(1, count($response['result']['serviceids']));
		self::$service_id = $response['result']['serviceids'][0];

		// Create service action
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_SERVICE,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_SERVICE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$service_id
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_SERVICE,
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_SERVICE . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_SERVICE . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => "",
						'message' => self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_SERVICE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$service_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY_SERVICE, self::VALUE_TO_FIRE_TRIGGER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true);

		self::$alert_response_service = $this->callUntilDataIsPresent('alert.get', [
			'hostids'		=>  [self::$host_id],
			'actionids'		=> [self::$service_action_id],
			'sortfield'		=> 'alertid',
			'eventobject'	=> EVENT_OBJECT_SERVICE,
			'eventsource'	=> EVENT_SOURCE_SERVICE
		], 4, 2);
		$this->assertCount(2, self::$alert_response_service['result']);

		// setup resolved builtin consistent macros

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "			. self::ACTION_NAME										. " <-\n" .
			"EVENT.NAME -> "			. "Status of service \"Server1\" changed to Average"	. " <-\n" .
			"EVENT.NSEVERITY -> "		. '3'													. " <-\n" .
			"EVENT.OBJECT -> "			. '6'													. " <-\n" . // service
			"EVENT.SEVERITY -> "		. 'Average'												. " <-\n" .
			"EVENT.SOURCE -> "			. '4'													. " <-\n" . //created on service status update
			"EVENT.STATUS -> "			. 'PROBLEM'												. " <-\n" .
			"EVENT.TAGS -> "			. ''													. " <-\n" .
			"EVENT.TAGSJSON -> "		. '[]'													. " <-\n" .
			"EVENT.UPDATE.STATUS -> "	. '0'													. " <-\n" .
			"EVENT.VALUE -> "			. '1'													. " <-\n" .
			"SERVICE.DESCRIPTION -> "	. ''													. " <-\n" .
			"SERVICE.NAME -> "			. self::SERVICE_NAME									. " <-\n" .
			"SERVICE.TAGS -> "			. ''													. " <-\n" .
			"SERVICE.TAGSJSON -> "		. '[]'													. " <-";

		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"ESC.HISTORY[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.DURATION[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"SERVICE.ROOTCAUSE[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, self::$alert_response_service['result'][0]['message']);

		// Test builtin consistent macro resolution during the initial operation (A problem occurred on the service).

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_SERVICE . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED;

		$this->assertEquals($message_expect, self::$alert_response_service['result'][1]['message']);
	}

	/***************************************************************************************************************

		Test macro resolution (source: event created by a discovery rule).

	***************************************************************************************************************/
	private static $drule_id;
	private static $discovery_action_id;
	const DRULE_NAME = "Discovery1";
	const SUBJECT_DISCOVERY = 'Discovery';

	/*Subset of COMMON macros that are consistently resolved for Discovery rule events only.*/
	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_DISCOVERY = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		DISCOVERY.DEVICE.IPADDRESS -> {DISCOVERY.DEVICE.IPADDRESS} <-
		DISCOVERY.DEVICE.DNS -> {DISCOVERY.DEVICE.DNS} <-
		DISCOVERY.DEVICE.STATUS -> {DISCOVERY.DEVICE.STATUS} <-
		DISCOVERY.RULE.NAME -> {DISCOVERY.RULE.NAME} <-
		EOT;

	/* These macros are not resolved(Discovery rule event). */
	const BUILTIN_MACROS_NON_RESOLVABLE_DRULE = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.METADATA}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{LLDRULE.DESCRIPTION}
		{LLDRULE.DESCRIPTION.ORIG}
		{LLDRULE.ID}
		{LLDRULE.KEY}
		{LLDRULE.KEY.ORIG}
		{LLDRULE.NAME}
		{LLDRULE.NAME.ORIG}
		{LLDRULE.STATE}
		{LLDRULE.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGER.DESCRIPTION}
		{TRIGGER.EXPRESSION.EXPLAIN}
		{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.HOSTGROUP.NAME}
		{TRIGGER.EXPRESSION}
		{TRIGGER.EXPRESSION.RECOVERY}
		{TRIGGER.NAME}
		{TRIGGER.NAME.ORIG}
		{TRIGGER.NSEVERITY}
		{TRIGGER.SEVERITY}
		{TRIGGER.STATUS}
		{TRIGGER.URL}
		{TRIGGER.URL.NAME}
		{TRIGGER.VALUE}
		{TRIGGER.ID}
		{TRIGGER.TEMPLATE.NAME}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		{USER.USERNAME}
		EOT . "\n" .
		"{INVENTORY.ALIAS}\n" . // all inventory macros added to not resolvable as not supported for discovery events
		<<<'EOT'
		{INVENTORY.ASSET.TAG}
		{INVENTORY.CHASSIS}
		{INVENTORY.CONTACT}
		{INVENTORY.CONTRACT.NUMBER}
		{INVENTORY.DEPLOYMENT.STATUS}
		{INVENTORY.HARDWARE}
		{INVENTORY.HARDWARE.FULL}
		{INVENTORY.HOST.NETMASK}
		{INVENTORY.HOST.NETWORKS}
		{INVENTORY.HOST.ROUTER}
		{INVENTORY.HW.ARCH}
		{INVENTORY.HW.DATE.DECOMM}
		{INVENTORY.HW.DATE.EXPIRY}
		{INVENTORY.HW.DATE.INSTALL}
		{INVENTORY.HW.DATE.PURCHASE}
		{INVENTORY.INSTALLER.NAME}
		{INVENTORY.LOCATION}
		{INVENTORY.LOCATION.LAT}
		{INVENTORY.LOCATION.LON}
		{INVENTORY.MACADDRESS.A}
		{INVENTORY.MACADDRESS.B}
		{INVENTORY.MODEL}
		{INVENTORY.NAME}
		{INVENTORY.NOTES}
		{INVENTORY.OOB.IP}
		{INVENTORY.OOB.NETMASK}
		{INVENTORY.OOB.ROUTER}
		{INVENTORY.OS}
		{INVENTORY.OS.FULL}
		{INVENTORY.OS.SHORT}
		{INVENTORY.POC.PRIMARY.CELL}
		{INVENTORY.POC.PRIMARY.EMAIL}
		{INVENTORY.POC.PRIMARY.NAME}
		{INVENTORY.POC.PRIMARY.NOTES}
		{INVENTORY.POC.PRIMARY.PHONE.A}
		{INVENTORY.POC.PRIMARY.PHONE.B}
		{INVENTORY.POC.PRIMARY.SCREEN}
		{INVENTORY.POC.SECONDARY.CELL}
		{INVENTORY.POC.SECONDARY.EMAIL}
		{INVENTORY.POC.SECONDARY.NAME}
		{INVENTORY.POC.SECONDARY.NOTES}
		{INVENTORY.POC.SECONDARY.PHONE.A}
		{INVENTORY.POC.SECONDARY.PHONE.B}
		{INVENTORY.POC.SECONDARY.SCREEN}
		{INVENTORY.SERIALNO.A}
		{INVENTORY.SERIALNO.B}
		{INVENTORY.SITE.ADDRESS.A}
		{INVENTORY.SITE.ADDRESS.B}
		{INVENTORY.SITE.ADDRESS.C}
		{INVENTORY.SITE.CITY}
		{INVENTORY.SITE.COUNTRY}
		{INVENTORY.SITE.NOTES}
		{INVENTORY.SITE.RACK}
		{INVENTORY.SITE.STATE}
		{INVENTORY.SITE.ZIP}
		{INVENTORY.SOFTWARE}
		{INVENTORY.SOFTWARE.APP.A}
		{INVENTORY.SOFTWARE.APP.B}
		{INVENTORY.SOFTWARE.APP.C}
		{INVENTORY.SOFTWARE.APP.D}
		{INVENTORY.SOFTWARE.APP.E}
		{INVENTORY.SOFTWARE.FULL}
		{INVENTORY.TAG}
		{INVENTORY.TYPE}
		{INVENTORY.TYPE.FULL}
		{INVENTORY.URL.A}
		{INVENTORY.URL.B}
		{INVENTORY.URL.C}
		{INVENTORY.VENDOR}
		{EVENT.ACK.STATUS}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.DURATION}
		{EVENT.NAME}
		{EVENT.NSEVERITY}
		{EVENT.OPDATA}
		{EVENT.SEVERITY}
		{EVENT.STATUS}
		{EVENT.SYMPTOMS}
		{EVENT.TAGS}
		{EVENT.TAGSJSON}
		{EVENT.UPDATE.HISTORY}
		{EVENT.UPDATE.STATUS}
		{EVENT.VALUE}
		{HOST.CONN}
		{HOST.DESCRIPTION}
		{HOST.DNS}
		{HOST.HOST}
		{HOST.ID}
		{HOST.IP}
		{HOST.NAME}
		{HOST.PORT}
		{ITEM.ID}
		{ITEM.DESCRIPTION}
		{ITEM.DESCRIPTION.ORIG}
		{ITEM.KEY}
		{ITEM.KEY.ORIG}
		{ITEM.LASTVALUE}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		{ITEM.NAME}
		{ITEM.NAME.ORIG}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{ITEM.VALUE}
		{ITEM.VALUETYPE}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{ESC.HISTORY}
		EOT;

	/* Inconsistent macros (Discovery event) */
	const BUILTIN_MACROS_INCONSISTENT_RESOLVE_DRULE = <<<'EOT'
		ACTION.ID -> {ACTION.ID} <-
		DATE -> {DATE} <-
		TIME -> {TIME} <-
		EVENT.AGE -> {EVENT.AGE} <-
		EVENT.DATE -> {EVENT.DATE} <-
		EVENT.ID -> {EVENT.ID} <-
		EVENT.TIME -> {EVENT.TIME} <-
		DISCOVERY.DEVICE.UPTIME -> {DISCOVERY.DEVICE.UPTIME} <-
		EOT;

	/* There macros resolve to a value of UNKNOWN(Discovery rule).*/
	const BUILTIN_MACROS_UNKNOWN_DRULE = <<<'EOT'
		DISCOVERY.SERVICE.NAME -> {DISCOVERY.SERVICE.NAME} <-
		DISCOVERY.SERVICE.PORT -> {DISCOVERY.SERVICE.PORT} <-
		DISCOVERY.SERVICE.STATUS -> {DISCOVERY.SERVICE.STATUS} <-
		DISCOVERY.SERVICE.UPTIME -> {DISCOVERY.SERVICE.UPTIME} <-
		EOT;

	const BUILTIN_MACROS_UNKNOWN_RESOLVED_DRULE = <<<'EOT'
		DISCOVERY.SERVICE.NAME -> *UNKNOWN* <-
		DISCOVERY.SERVICE.PORT -> *UNKNOWN* <-
		DISCOVERY.SERVICE.STATUS -> *UNKNOWN* <-
		DISCOVERY.SERVICE.UPTIME -> *UNKNOWN* <-
		EOT;

	public function testMacros_DiscoveryRuleEvent() {

		CDataHelper::call('action.delete', [self::$service_action_id]);
		$this->removeValue(self::$action_ids, self::$service_action_id);

		$response = $this->call('drule.create', [
			'name' => self::DRULE_NAME,
			'delay' => '1s',
			'iprange' => '127.0.0.1',
			'dchecks' => [
				[
					'type' => SVC_HTTP,
					'ports' => '80'
				]
			],
			'concurrency_max' =>  ZBX_DISCOVERY_CHECKS_ONE
		]);

		$this->assertArrayHasKey('druleids', $response['result']);
		$this->assertEquals(1, count($response['result']['druleids']));
		self::$drule_id = $response['result']['druleids'][0];

		// Create discovery rule action
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DHOST_IP,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '127.0.0.1-10'
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_DISCOVERY,
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_DISCOVERY . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_UNKNOWN_DRULE . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_DRULE . "\n" .
							'===5===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===7===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===8===' . "\n" .
							self::MACRO_FUNCS . "\n" .
							'===9===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_DRULE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$discovery_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of add_object_msg()', true);

		$response = $this->call('alert.get', [
			'hostids' => [self::$host_id],
			'actionids' => self::$discovery_action_id,
			'sortfield' => 'alertid',
			'eventobject' => EVENT_OBJECT_DHOST,
			'eventsource' => EVENT_SOURCE_DISCOVERY
		]);
		$this->assertArrayHasKey('message', $response['result'][0]);
		$message_got = $response['result'][0]['message'];

		$macros = explode('==9==', $message_got);

		// setup resolved builtin consistent macros

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "					. self::ACTION_NAME	. " <-\n" .
			"EVENT.OBJECT -> "					. '1'				. " <-\n" . // discovered host
			"EVENT.SOURCE -> "					. '1'				. " <-\n" . // created by a discovery rule
			"PROXY.DESCRIPTION -> "				. ''				. " <-\n" .
			"PROXY.NAME -> "					. ''				. " <-\n" .
			"DISCOVERY.DEVICE.IPADDRESS -> "	. '127.0.0.1'		. " <-\n" .
			"DISCOVERY.DEVICE.DNS -> "			. 'localhost'		. " <-\n" .
			"DISCOVERY.DEVICE.STATUS -> "		. 'UP'				. " <-\n" .
			"DISCOVERY.RULE.NAME -> "			. 'Discovery1'		. " <-";

		// Test builtin consistent macro resolution during the initial operation (A new host discovered).

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_UNKNOWN_RESOLVED_DRULE . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_DRULE . "\n" .
			'===5===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===6===' . "\n" .
			'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
			'===7===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===8===' . "\n" .
			self::MACRO_FUNCS_RESOLVED . "\n=";

		$this->assertEquals($message_expect, $macros[0]);

		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"DISCOVERY.DEVICE.UPTIME[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, $macros[1]);

		CDataHelper::call('action.delete', [self::$discovery_action_id]);
		$this->removeValue(self::$action_ids, self::$discovery_action_id);
	}

	/***************************************************************************************************************

		Test macro resolution (source: event created by an autoregestration).

	***************************************************************************************************************/
	const SUBJECT_AUTOREG = 'Autoregistration';
	private static $autoregistration_action_id;
	private static $host_deleted;
	const BUILTIN_MACROS_NON_RESOLVABLE_AUTOREG = <<<'EOT'
		{ALERT.MESSAGE}
		{ALERT.SENDTO}
		{ALERT.SUBJECT}
		{EVENT.UPDATE.ACTION}
		{EVENT.UPDATE.DATE}
		{EVENT.UPDATE.MESSAGE}
		{EVENT.UPDATE.NSEVERITY}
		{EVENT.UPDATE.SEVERITY}
		{EVENT.UPDATE.TIME}
		{HOST.TARGET.CONN}
		{HOST.TARGET.DNS}
		{HOST.TARGET.HOST}
		{HOST.TARGET.IP}
		{HOST.TARGET.NAME}
		{HOSTGROUP.ID}
		{LLDRULE.DESCRIPTION}
		{LLDRULE.DESCRIPTION.ORIG}
		{LLDRULE.ID}
		{LLDRULE.KEY}
		{LLDRULE.KEY.ORIG}
		{LLDRULE.NAME}
		{LLDRULE.NAME.ORIG}
		{LLDRULE.STATE}
		{LLDRULE.STATE.ERROR}
		{MAP.ID}
		{MAP.NAME}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}
		{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}
		{TRIGGER.STATE}
		{TRIGGER.STATE.ERROR}
		{TRIGGER.DESCRIPTION}
		{TRIGGER.EXPRESSION.EXPLAIN}
		{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		{TRIGGER.EVENTS.ACK}
		{TRIGGER.EVENTS.PROBLEM.ACK}
		{TRIGGER.EVENTS.PROBLEM.UNACK}
		{TRIGGER.EVENTS.UNACK}
		{TRIGGER.HOSTGROUP.NAME}
		{TRIGGER.EXPRESSION}
		{TRIGGER.EXPRESSION.RECOVERY}
		{TRIGGER.NAME}
		{TRIGGER.NAME.ORIG}
		{TRIGGER.NSEVERITY}
		{TRIGGER.SEVERITY}
		{TRIGGER.STATUS}
		{TRIGGER.URL}
		{TRIGGER.URL.NAME}
		{TRIGGER.VALUE}
		{TRIGGER.ID}
		{TRIGGER.TEMPLATE.NAME}
		{TRIGGERS.UNACK}
		{TRIGGERS.PROBLEM.UNACK}
		{TRIGGERS.ACK}
		{TRIGGERS.PROBLEM.ACK}
		{USER.FULLNAME}
		{USER.NAME}
		{USER.SURNAME}
		{USER.USERNAME}
		EOT . "\n" .
		"{INVENTORY.ALIAS}\n" .	// all inventory macros added to not resolvable as not supported for discovery events
		<<<'EOT'
		{INVENTORY.ASSET.TAG}
		{INVENTORY.CHASSIS}
		{INVENTORY.CONTACT}
		{INVENTORY.CONTRACT.NUMBER}
		{INVENTORY.DEPLOYMENT.STATUS}
		{INVENTORY.HARDWARE}
		{INVENTORY.HARDWARE.FULL}
		{INVENTORY.HOST.NETMASK}
		{INVENTORY.HOST.NETWORKS}
		{INVENTORY.HOST.ROUTER}
		{INVENTORY.HW.ARCH}
		{INVENTORY.HW.DATE.DECOMM}
		{INVENTORY.HW.DATE.EXPIRY}
		{INVENTORY.HW.DATE.INSTALL}
		{INVENTORY.HW.DATE.PURCHASE}
		{INVENTORY.INSTALLER.NAME}
		{INVENTORY.LOCATION}
		{INVENTORY.LOCATION.LAT}
		{INVENTORY.LOCATION.LON}
		{INVENTORY.MACADDRESS.A}
		{INVENTORY.MACADDRESS.B}
		{INVENTORY.MODEL}
		{INVENTORY.NAME}
		{INVENTORY.NOTES}
		{INVENTORY.OOB.IP}
		{INVENTORY.OOB.NETMASK}
		{INVENTORY.OOB.ROUTER}
		{INVENTORY.OS}
		{INVENTORY.OS.FULL}
		{INVENTORY.OS.SHORT}
		{INVENTORY.POC.PRIMARY.CELL}
		{INVENTORY.POC.PRIMARY.EMAIL}
		{INVENTORY.POC.PRIMARY.NAME}
		{INVENTORY.POC.PRIMARY.NOTES}
		{INVENTORY.POC.PRIMARY.PHONE.A}
		{INVENTORY.POC.PRIMARY.PHONE.B}
		{INVENTORY.POC.PRIMARY.SCREEN}
		{INVENTORY.POC.SECONDARY.CELL}
		{INVENTORY.POC.SECONDARY.EMAIL}
		{INVENTORY.POC.SECONDARY.NAME}
		{INVENTORY.POC.SECONDARY.NOTES}
		{INVENTORY.POC.SECONDARY.PHONE.A}
		{INVENTORY.POC.SECONDARY.PHONE.B}
		{INVENTORY.POC.SECONDARY.SCREEN}
		{INVENTORY.SERIALNO.A}
		{INVENTORY.SERIALNO.B}
		{INVENTORY.SITE.ADDRESS.A}
		{INVENTORY.SITE.ADDRESS.B}
		{INVENTORY.SITE.ADDRESS.C}
		{INVENTORY.SITE.CITY}
		{INVENTORY.SITE.COUNTRY}
		{INVENTORY.SITE.NOTES}
		{INVENTORY.SITE.RACK}
		{INVENTORY.SITE.STATE}
		{INVENTORY.SITE.ZIP}
		{INVENTORY.SOFTWARE}
		{INVENTORY.SOFTWARE.APP.A}
		{INVENTORY.SOFTWARE.APP.B}
		{INVENTORY.SOFTWARE.APP.C}
		{INVENTORY.SOFTWARE.APP.D}
		{INVENTORY.SOFTWARE.APP.E}
		{INVENTORY.SOFTWARE.FULL}
		{INVENTORY.TAG}
		{INVENTORY.TYPE}
		{INVENTORY.TYPE.FULL}
		{INVENTORY.URL.A}
		{INVENTORY.URL.B}
		{INVENTORY.URL.C}
		{INVENTORY.VENDOR}
		{EVENT.ACK.STATUS}
		{EVENT.CAUSE.ACK.STATUS}
		{EVENT.CAUSE.AGE}
		{EVENT.CAUSE.DATE}
		{EVENT.CAUSE.DURATION}
		{EVENT.CAUSE.ID}
		{EVENT.CAUSE.NAME}
		{EVENT.CAUSE.NSEVERITY}
		{EVENT.CAUSE.OBJECT}
		{EVENT.CAUSE.OPDATA}
		{EVENT.CAUSE.SEVERITY}
		{EVENT.CAUSE.SOURCE}
		{EVENT.CAUSE.STATUS}
		{EVENT.CAUSE.TAGS}
		{EVENT.CAUSE.TAGSJSON}
		{EVENT.CAUSE.TIME}
		{EVENT.CAUSE.UPDATE.HISTORY}
		{EVENT.CAUSE.VALUE}
		{EVENT.DURATION}
		{EVENT.NAME}
		{EVENT.NSEVERITY}
		{EVENT.OPDATA}
		{EVENT.SEVERITY}
		{EVENT.STATUS}
		{EVENT.SYMPTOMS}
		{EVENT.TAGS}
		{EVENT.TAGSJSON}
		{EVENT.UPDATE.HISTORY}
		{EVENT.UPDATE.STATUS}
		{EVENT.VALUE}
		{HOST.CONN}
		{HOST.DESCRIPTION}
		{HOST.DNS}
		{HOST.ID}
		{HOST.NAME}
		{ITEM.ID}
		{ITEM.DESCRIPTION}
		{ITEM.DESCRIPTION.ORIG}
		{ITEM.KEY}
		{ITEM.KEY.ORIG}
		{ITEM.LASTVALUE}
		{ITEM.LOG.AGE}
		{ITEM.LOG.DATE}
		{ITEM.LOG.EVENTID}
		{ITEM.LOG.NSEVERITY}
		{ITEM.LOG.SEVERITY}
		{ITEM.LOG.SOURCE}
		{ITEM.LOG.TIME}
		{ITEM.NAME}
		{ITEM.NAME.ORIG}
		{ITEM.STATE}
		{ITEM.STATE.ERROR}
		{ITEM.VALUE}
		{ITEM.VALUETYPE}
		{SERVICE.DESCRIPTION}
		{SERVICE.NAME}
		{SERVICE.TAGS}
		{SERVICE.TAGSJSON}
		{ESC.HISTORY}
		{DISCOVERY.DEVICE.IPADDRESS}
		{DISCOVERY.DEVICE.DNS}
		{DISCOVERY.DEVICE.STATUS}
		{DISCOVERY.RULE.NAME}
		{DISCOVERY.DEVICE.UPTIME}
		EOT;

	const BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_AUTOREG = <<<'EOT'
		ACTION.NAME -> {ACTION.NAME} <-
		EVENT.OBJECT -> {EVENT.OBJECT} <-
		EVENT.SOURCE -> {EVENT.SOURCE} <-
		PROXY.DESCRIPTION -> {PROXY.DESCRIPTION} <-
		PROXY.NAME -> {PROXY.NAME} <-
		HOST.HOST -> {HOST.HOST} <-
		HOST.IP -> {HOST.IP} <-
		EOT;

	const BUILTIN_MACROS_INCONSISTENT_RESOLVE_AUTOREG = <<<'EOT'
		ACTION.ID -> {ACTION.ID} <-
		DATE -> {DATE} <-
		TIME -> {TIME} <-
		EVENT.AGE -> {EVENT.AGE} <-
		EVENT.DATE -> {EVENT.DATE} <-
		EVENT.ID -> {EVENT.ID} <-
		EVENT.TIME -> {EVENT.TIME} <-
		HOST.METADATA -> {HOST.METADATA} <-
		HOST.PORT -> {HOST.PORT} <-
		EOT;

	/**
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent
	 */

	public function testMacros_AutoregestrationEvent() {

		if (file_exists(self::$metadata_file)) {
			unlink(self::$metadata_file);
		}

		if (file_put_contents(self::$metadata_file, "\\".time()) === false) {
			throw new Exception('Failed to create metadata_file');
		}

		CDataHelper::call('host.delete', [self::$host_id]);
		self::$host_deleted = true;

		$this->stopComponent(self::COMPONENT_AGENT);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		// Create autoregistration action
		$response = $this->call('action.create', [
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'name' => self::ACTION_NAME,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 4,
						'subject' => self::SUBJECT_AUTOREG,
						'message' => '===1===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_AUTOREG . "\n" .
							'===2===' . "\n" .
							self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::BUILTIN_MACROS_NON_RESOLVABLE_AUTOREG . "\n" .
							'===4===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===5===' . "\n" .
							'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
							'===6===' . "\n" .
							'USER_MACRO_GLOBAL -> {$USER_MACRO_GLOBAL} <-' . "\n" .
							'===7===' . "\n" .
							self::MACRO_FUNCS . "\n" .
							'===8===' . "\n" .
							self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_AUTOREG
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$autoregistration_action_id = $response['result']['actionids'][0];
		self::$action_ids = array_merge(self::$action_ids, [$response['result']['actionids'][0]]);

		$this->startComponent(self::COMPONENT_AGENT);
		// "In ..." is not a mistake here and "End ..." should not be used here because result of
		// zbx_substitute_macros_args() can contain secret data and must not be logged.
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_substitute_macros_args() data:'Autoregistration'", true, 120);

		$response = $this->call('alert.get', [
			'hostids' => [self::$host_id],
			'actionids' => self::$autoregistration_action_id,
			'sortfield' => 'alertid',
			'eventobject' => EVENT_OBJECT_AUTOREGHOST,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION
		]);
		$this->assertArrayHasKey('message', $response['result'][0]);
		$message_got = $response['result'][0]['message'];

		$macros = explode('==8==', $message_got);

		self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "		. self::ACTION_NAME		. " <-\n" .
			"EVENT.OBJECT -> "		. '3'					. " <-\n" . // auto-registered host
			"EVENT.SOURCE -> "		. '2'					. " <-\n" . // created by active agent autoregistration
			"PROXY.DESCRIPTION -> "	. ''					. " <-\n" .
			"PROXY.NAME -> "		. ''					. " <-\n" .
			"HOST.HOST -> "			. 'test_macros_host'	. " <-\n" .
			"HOST.IP -> "			. '127.0.0.1'			. " <-";

		// Test builtin consistent macro resolution during the initial operation (A new host registered).

		$message_expect = '===1===' . "\n" .
			self::$BUILTIN_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			self::BUILTIN_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===3===' . "\n" .
			self::BUILTIN_MACROS_NON_RESOLVABLE_AUTOREG . "\n" .
			'===4===' . "\n" .
			self::BUILTIN_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
			'===5===' . "\n" .
			'USER_MACRO_HOST -> {$USER_MACRO_HOST} <-' . "\n" .
			'===6===' . "\n" .
			'USER_MACRO_GLOBAL -> GLOBAL_LEVEL_' . ALL_PRINTABLE_ASCII . " <-\n" .
			'===7===' . "\n" .
			self::MACRO_FUNCS_RESOLVED . "\n=";

		$this->assertEquals($message_expect, $macros[0]);

		$inconsistent_macros_resolved = "/ACTION.ID[\s\S]*" .
			"DATE[\s\S]*" .
			"TIME[\s\S]*" .
			"EVENT.AGE[\s\S]*" .
			"EVENT.DATE[\s\S]*" .
			"EVENT.ID[\s\S]*" .
			"EVENT.TIME[\s\S]*" .
			"HOST.METADATA[\s\S]*" .
			"HOST.PORT[\s\S]*/";

		$this->assertRegExp($inconsistent_macros_resolved, $macros[1]);
	}

	public static function clearData(): void {

		if (!empty(self::$action_ids)) {
			CDataHelper::call('action.delete', self::$action_ids);
		}

		if (!empty(self::$globalmacro_ids)) {
			CDataHelper::call('usermacro.deleteglobal', self::$globalmacro_ids);
		}

		if (!self::$host_deleted) {
			CDataHelper::call('host.delete', [self::$host_id]);
		}

		CDataHelper::call('drule.delete', [self::$drule_id]);
		CDataHelper::call('service.delete', [self::$service_id]);

		DB::delete('events', ['eventid' => self::$eventids]);
	}
}
