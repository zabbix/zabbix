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
require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

define("ALL_PRINTABLE_ASCII", ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~');

define("REDUCTED_PRINTABLE_ASCII", '!"#$%&\'()*+,-./0123456789:;<=>?@[\\]^_`{|}~');

/**
 * Test suite for expression macros
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup items,actions,triggers
 * @hosts test_macros
 */
class testExpressionMacros extends CIntegrationTest {

	private static $hostid;
	private static $triggerid;
	private static $trigger_actionid;
	private static $alert_response;
	private static $event_response;

	private static $event_tags_json;
	private static $trigger_expression_explain;
	private static $trigger_recovery_expression_explain;
	private static $trigger_expression;
	private static $trigger_recovery_expression;
	private static $event_name;
	private static $event_name_resolved;

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


	// COMMON means common between main, recovery and update
	const USER_MACROS_CONSISTENT_RESOLVE_COMMON = "ACTION.NAME -> {ACTION.NAME} <-
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
TRIGGER.VALUE -> {TRIGGER.VALUE} <-";

	const USER_MACROS_CONSISTENT_RESOLVE_ONLY_MAIN_MESSAGE = "EVENT.AGE -> {EVENT.AGE} <-
EVENT.DURATION -> {EVENT.DURATION} <-";

	const USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY = "EVENT.RECOVERY.DATE -> {EVENT.RECOVERY.DATE} <-
EVENT.RECOVERY.NAME -> {EVENT.RECOVERY.NAME} <-
EVENT.RECOVERY.STATUS -> {EVENT.RECOVERY.STATUS} <-
EVENT.RECOVERY.TAGS -> {EVENT.RECOVERY.TAGS} <-
EVENT.RECOVERY.TAGSJSON -> {EVENT.RECOVERY.TAGSJSON} <-
EVENT.RECOVERY.VALUE -> {EVENT.RECOVERY.VALUE} <-";



			const USER_MACROS_INCONSISTENT_RESOLVE = "ACTION.ID -> {ACTION.ID} <-
ESC.HISTORY -> {ESC.HISTORY} <-
DATE -> {DATE} <-
TIME -> {TIME} <-
EVENT.DATE -> {EVENT.DATE} <-
EVENT.ID -> {EVENT.ID} <-
EVENT.TIME -> {EVENT.TIME} <-
HOST.ID -> {HOST.ID} <-
{ITEM.ID} -> {ITEM.ID} <-
TRIGGER.ID -> {TRIGGER.ID} <-";

	const USER_MACROS_INCONSISTENT_RESOLVE_ONLY_RECOVERY = "
EVENT.RECOVERY.ID -> {EVENT.RECOVERY.ID} <-
EVENT.RECOVERY.TIME -> {EVENT.RECOVERY.TIME} <-";

	const USER_MACROS_UNKNOWN = "EVENT.CAUSE.ACK.STATUS -> {EVENT.CAUSE.ACK.STATUS} <-
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
INVENTORY.HW.DATE.EXPIRY -> {INVENTORY.HW.DATE.EXPIRY}  <-
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
ITEM.LOG.AGE -> {ITEM.LOG.AGE} <-
ITEM.LOG.DATE -> {ITEM.LOG.DATE} <-
ITEM.LOG.EVENTID -> {ITEM.LOG.EVENTID} <-
ITEM.LOG.NSEVERITY -> {ITEM.LOG.NSEVERITY} <-
ITEM.LOG.SEVERITY -> {ITEM.LOG.SEVERITY} <-
ITEM.LOG.SOURCE -> {ITEM.LOG.SOURCE} <-
ITEM.LOG.TIME -> {ITEM.LOG.TIME} <-
TRIGGER.TEMPLATE.NAME -> {TRIGGER.TEMPLATE.NAME} <-";

		const  USER_MACROS_UNKNOWN_RESOLVED = "EVENT.CAUSE.ACK.STATUS -> *UNKNOWN* <-
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
INVENTORY.ALIAS -> *UNKNOWN* <-
INVENTORY.ASSET.TAG -> *UNKNOWN* <-
INVENTORY.CHASSIS -> *UNKNOWN* <-
INVENTORY.CONTACT-> *UNKNOWN* <-
INVENTORY.CONTRACT.NUMBER -> *UNKNOWN* <-
INVENTORY.DEPLOYMENT.STATUS -> *UNKNOWN* <-
INVENTORY.HARDWARE -> *UNKNOWN* <-
INVENTORY.HARDWARE.FULL -> *UNKNOWN* <-
INVENTORY.HOST.NETMASK -> *UNKNOWN* <-
INVENTORY.HOST.NETWORKS -> *UNKNOWN* <-
INVENTORY.HOST.ROUTER -> *UNKNOWN* <-
INVENTORY.HW.ARCH -> *UNKNOWN* <-
INVENTORY.HW.DATE.DECOMM -> *UNKNOWN* <-
INVENTORY.HW.DATE.EXPIRY -> *UNKNOWN*  <-
INVENTORY.HW.DATE.INSTALL -> *UNKNOWN* <-
INVENTORY.HW.DATE.PURCHASE-> *UNKNOWN* <-
INVENTORY.INSTALLER.NAME -> *UNKNOWN* <-
INVENTORY.LOCATION -> *UNKNOWN* <-
INVENTORY.LOCATION.LAT -> *UNKNOWN* <-
INVENTORY.LOCATION.LON -> *UNKNOWN* <-
INVENTORY.MACADDRESS.A -> *UNKNOWN* <-
INVENTORY.MACADDRESS.B -> *UNKNOWN* <-
INVENTORY.MODEL -> *UNKNOWN* <-
INVENTORY.NAME -> *UNKNOWN* <-
INVENTORY.NOTES -> *UNKNOWN* <-
INVENTORY.OOB.IP -> *UNKNOWN* <-
INVENTORY.OOB.NETMASK -> *UNKNOWN* <-
INVENTORY.OOB.ROUTER -> *UNKNOWN* <-
INVENTORY.OS -> *UNKNOWN* <-
INVENTORY.OS.FULL -> *UNKNOWN* <-
INVENTORY.OS.SHORT -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.CELL -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.EMAIL -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.NAME -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.NOTES -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.PHONE.A -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.PHONE.B -> *UNKNOWN* <-
INVENTORY.POC.PRIMARY.SCREEN -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.CELL -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.EMAIL -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.NAME -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.NOTES -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.PHONE.A -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.PHONE.B -> *UNKNOWN* <-
INVENTORY.POC.SECONDARY.SCREEN -> *UNKNOWN* <-
INVENTORY.SERIALNO.A -> *UNKNOWN* <-
INVENTORY.SERIALNO.B -> *UNKNOWN* <-
INVENTORY.SITE.ADDRESS.A -> *UNKNOWN* <-
INVENTORY.SITE.ADDRESS.B -> *UNKNOWN* <-
INVENTORY.SITE.ADDRESS.C -> *UNKNOWN* <-
INVENTORY.SITE.CITY -> *UNKNOWN* <-
INVENTORY.SITE.COUNTRY -> *UNKNOWN* <-
INVENTORY.SITE.NOTES -> *UNKNOWN* <-
INVENTORY.SITE.RACK -> *UNKNOWN* <-
INVENTORY.SITE.STATE -> *UNKNOWN* <-
INVENTORY.SITE.ZIP -> *UNKNOWN* <-
INVENTORY.SOFTWARE -> *UNKNOWN* <-
INVENTORY.SOFTWARE.APP.A -> *UNKNOWN* <-
INVENTORY.SOFTWARE.APP.B -> *UNKNOWN* <-
INVENTORY.SOFTWARE.APP.C -> *UNKNOWN* <-
INVENTORY.SOFTWARE.APP.D -> *UNKNOWN* <-
INVENTORY.SOFTWARE.APP.E -> *UNKNOWN* <-
INVENTORY.SOFTWARE.FULL -> *UNKNOWN* <-
INVENTORY.TAG -> *UNKNOWN* <-
INVENTORY.TYPE -> *UNKNOWN* <-
INVENTORY.TYPE.FULL -> *UNKNOWN* <-
INVENTORY.URL.A -> *UNKNOWN* <-
INVENTORY.URL.B -> *UNKNOWN* <-
INVENTORY.URL.C} -> *UNKNOWN* <-
INVENTORY.VENDOR -> *UNKNOWN* <-
ITEM.LOG.AGE -> *UNKNOWN* <-
ITEM.LOG.DATE -> *UNKNOWN* <-
ITEM.LOG.EVENTID -> *UNKNOWN* <-
ITEM.LOG.NSEVERITY -> *UNKNOWN* <-
ITEM.LOG.SEVERITY -> *UNKNOWN* <-
ITEM.LOG.SOURCE -> *UNKNOWN* <-
ITEM.LOG.TIME -> *UNKNOWN* <-
TRIGGER.TEMPLATE.NAME -> *UNKNOWN* <-";



	const USER_MACROS_NON_REPLACEABLE = "{ALERT.MESSAGE}
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
{USER.USERNAME}";


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


		// Create host "test_macros".
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);

		// Create trapper items
		$items = [];
		for ($i = 1; $i < 3; $i++) {
			$items[] = [
				'hostid' => self::$hostid,
				'name' => self::TRAPPER_ITEM_NAME.$i,
				'key_' => self::TRAPPER_ITEM_KEY.$i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));


			//'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)='.self::VALUE_TO_FIRE_TRIGGER.' or '.
			//		'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'2)='.self::VALUE_TO_FIRE_TRIGGER,
			//'recovery_expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)='.self::VALUE_TO_RECOVER_TRIGGER,

			//'event_name' => self::EVENT_PREFIX.'{?last(/{HOST.HOST}/'.self::TRAPPER_ITEM_KEY.'1)}',


		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'trigger_trap',
			'expression' => self::$trigger_expression,
			'recovery_expression' => self::$trigger_recovery_expression,
			'recovery_mode' => self::TRIGGER_RECOVERY_MODE,
			'event_name' => self::$event_name,
			'priority' => self::TRIGGER_PRIORITY,
			'status' => self::TRIGGER_STATUS_ENABLED,
			'comments' => self::TRIGGER_COMMENTS,
			'url' => self::TRIGGER_URL,
			'url_name' => self::TRIGGER_URL_NAME,
			'type' => self::TRIGGER_TYPE,
			'recovery_mode' => self::TRIGGER_RECOVERY_MODE,
			'correlation_mode' => self::TRIGGER_CORRELATION_MODE,
			'correlation_tag' => self::TAG_NAME,
			'manual_close' => self::TRIGGER_MANUAL_CLOSE,
			'opdata' => self::TRIGGER_OPDATA,
			'tags' => [
				[
					'tag' => self::TAG_NAME,
					'value' => self::TAG_VALUE
				]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		// Create trigger action
		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
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
							self::USER_MACROS_CONSISTENT_RESOLVE_COMMON . "\n" .
							'===2===' . "\n" .
							self::USER_MACROS_CONSISTENT_RESOLVE_ONLY_MAIN_MESSAGE . "\n" .
							'===3===' . "\n" .
							self::USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===4===' . "\n" .
							self::USER_MACROS_UNKNOWN . "\n" .
							'===5===' . "\n" .
							self::USER_MACROS_NON_REPLACEABLE
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
						'message' => self::MESSAGE_PREFIX.'{?last(/{HOST.HOST}/'.self::TRAPPER_ITEM_KEY.'1,1h)}'.
						'===1===' . "\n" .
								'/host/macro:{?last(/'.self::HOST_NAME.'/{ITEM.KEY})}'.
								'/empty/macro:{?last(//{ITEM.KEY})}'.
								'/macro/macro:{?last(/{HOST.HOST}/{ITEM.KEY})}'.
								'/macroN/macro:{?last(/{HOST.HOST1}/{ITEM.KEY})}'.
								'/macro/macroN:{?last(/{HOST.HOST}/{ITEM.KEY2})}'.
								'/empty/macroN:{?last(//{ITEM.KEY2})}'. "\n" .
							'===2===' . "\n" .
							self::USER_MACROS_CONSISTENT_RESOLVE_COMMON .
							'===3===' . "\n" .
							self::USER_MACROS_UNKNOWN .
							'===4===' . "\n" .
							self::USER_MACROS_NON_REPLACEABLE

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
							self::USER_MACROS_CONSISTENT_RESOLVE_COMMON . "\n" .
							'===2===' . "\n" .
							self::USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY . "\n" .
							'===3===' . "\n" .
							self::USER_MACROS_UNKNOWN . "\n" .
							'===4===' . "\n" .
							self::USER_MACROS_NON_REPLACEABLE
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$trigger_actionid = $response['result']['actionids'][0];

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

	/**
	 * Get data
	 *
	 * @backup alerts,events,history_uint
	 */
	public function testExpressionMacros_getData() {
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'2', self::VALUE_TO_RECOVER_TRIGGER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_RECOVER_TRIGGER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_FIRE_TRIGGER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		self::$event_response = $this->callUntilDataIsPresent('event.get', [
			'hostids' => [self::$hostid]
		], 5, 2);
		$this->assertCount(1, self::$event_response['result']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::VALUE_TO_RECOVER_TRIGGER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true, 10, 3);

		self::$alert_response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid'
		], 5, 2);
		$this->assertCount(3, self::$alert_response['result']);
	}

	/**
	 * Test expression macro in problem message
	 */
	public function testExpressionMacros_checkProblemMessage() {
		self::$event_name_resolved = self::EVENT_PREFIX . self::VALUE_TO_FIRE_TRIGGER;
		//			self::MESSAGE_PREFIX . self::VALUE_TO_FIRE_TRIGGER	. "\n" .

		$USER_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "							. self::ACTION_NAME									. " <-\n" .
			"EVENT.ACK.STATUS -> "						. 'No'												. " <-\n" .
			"EVENT.NAME -> "							. self::$event_name_resolved						. " <-\n" .
			"EVENT.NSEVERITY -> "						. self::TRIGGER_PRIORITY							. " <-\n" .
			"EVENT.OBJECT -> "							. '0'												. " <-\n" . // 0 -> Trigger
			"EVENT.OPDATA -> "							. self::TRIGGER_OPDATA								. " <-\n" .
			"EVENT.SEVERITY -> "						. 'Average'											. " <-\n" . // self::TRIGGER_PRIORITY
			"EVENT.SOURCE -> "							. '0'												. " <-\n" . // 0 -> Trigger
			"EVENT.STATUS -> "							. 'PROBLEM'											. " <-\n" . //  1 -> PROBLEM
			"EVENT.TAGS -> "							. self::TAG_NAME . ':' . self::TAG_VALUE			. " <-\n" .
			"EVENT.TAGSJSON -> "						. self::$event_tags_json							. " <-\n" .
			"EVENT.UPDATE.HISTORY -> "					. ''												. " <-\n" .
			"EVENT.UPDATE.STATUS -> "					. '0'												. " <-\n" .
			"EVENT.VALUE -> "							. '1'												. " <-\n" . // 0 -> Problem
			"HOST.CONN -> "								. '127.0.0.1'										. " <-\n" .
			"HOST.DESCRIPTION -> "						. ''												. " <-\n" .
			"HOST.DNS -> "								. ''												. " <-\n" .
			"HOST.HOST -> "								. self::HOST_NAME									. " <-\n" .
			"HOST.IP -> "								. '127.0.0.1'										. " <-\n" .
			"HOST.NAME -> "								. self::HOST_NAME									. " <-\n" .
			"HOST.PORT -> "								. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX		. " <-\n" .
			"ITEM.DESCRIPTION -> "						. ''												. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "					. ''												. " <-\n" .
			"ITEM.KEY -> "								. self::TRAPPER_ITEM_KEY . '1'						. " <-\n" .
			"ITEM.KEY.ORIG -> "							. self::TRAPPER_ITEM_KEY . '1'						. " <-\n" .
			"ITEM.LASTVALUE -> "						. self::VALUE_TO_FIRE_TRIGGER						. " <-\n" .
			"ITEM.NAME -> "								. self::TRAPPER_ITEM_NAME . '1'						. " <-\n" .
			"ITEM.NAME.ORIG -> "						. self::TRAPPER_ITEM_NAME . '1'						. " <-\n" .
			"ITEM.VALUE -> "							. self::VALUE_TO_FIRE_TRIGGER						. " <-\n" .
			"ITEM.VALUETYPE -> "						. ITEM_VALUE_TYPE_UINT64							. " <-\n" .
			"PROXY.DESCRIPTION -> "						. ''												. " <-\n" .
			"PROXY.NAME -> "							. ''												. " <-\n" .
			"TRIGGER.DESCRIPTION -> "					. self::TRIGGER_COMMENTS							. " <-\n" .
			"TRIGGER.EXPRESSION.EXPLAIN -> "			. self::$trigger_expression_explain					. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY.EXPLAIN -> "	. self::$trigger_recovery_expression_explain		. " <-\n" .
			"TRIGGER.EVENTS.ACK -> "					. '0'												. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.ACK -> "			. '0'												. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.UNACK -> "			. '1'												. " <-\n" .
			"TRIGGER.EVENTS.UNACK -> "					. '1'												. " <-\n" .
			"TRIGGER.HOSTGROUP.NAME -> "				. 'Zabbix servers'									. " <-\n" . // 4 -> 'Zabbix servers'
			"TRIGGER.EXPRESSION -> "					. self::$trigger_expression							. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY -> "			. self::$trigger_recovery_expression				. " <-\n" .
			"TRIGGER.NAME -> "							. 'trigger_trap'									. " <-\n" .
			"TRIGGER.NAME.ORIG -> "						. 'trigger_trap'									. " <-\n" .
			"TRIGGER.NSEVERITY -> "						. self::TRIGGER_PRIORITY							. " <-\n" .
			"TRIGGER.SEVERITY -> "						. 'Average'											. " <-\n" . // 3 -> Average
			"TRIGGER.STATUS -> "						. 'PROBLEM'											. " <-\n" .
			"TRIGGER.URL -> "							. self::TRIGGER_URL									. " <-\n" .
			"TRIGGER.URL.NAME -> "						. self::TRIGGER_URL_NAME							. " <-\n" .
			"TRIGGER.VALUE -> "							. '1' . " <-";													// 1 -> Problem

		$USER_MACROS_CONSISTENT_RESOLVE_ONLY_MAIN_MESSAGE_RESOLVED =
			"EVENT.AGE -> "								. '0s' 												. " <-\n" .
			"EVENT.DURATION -> "						. '0s'												. " <-" ;


		$USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED = "EVENT.RECOVERY.DATE -> {EVENT.RECOVERY.DATE} <-
EVENT.RECOVERY.NAME -> {EVENT.RECOVERY.NAME} <-
EVENT.RECOVERY.STATUS -> {EVENT.RECOVERY.STATUS} <-
EVENT.RECOVERY.TAGS -> {EVENT.RECOVERY.TAGS} <-
EVENT.RECOVERY.TAGSJSON -> {EVENT.RECOVERY.TAGSJSON} <-
EVENT.RECOVERY.VALUE -> {EVENT.RECOVERY.VALUE} <-";

		$message_expect = self::MESSAGE_PREFIX . self::VALUE_TO_FIRE_TRIGGER . "\n" .
			'===1===' . "\n" .
			$USER_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			$USER_MACROS_CONSISTENT_RESOLVE_ONLY_MAIN_MESSAGE_RESOLVED . "\n" .
			'===3===' . "\n" .
			$USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED . "\n" .
			'===4===' . "\n" .
			self::USER_MACROS_UNKNOWN_RESOLVED . "\n" .
			'===5===' . "\n" .
			self::USER_MACROS_NON_REPLACEABLE;

		$this->assertEquals($message_expect, self::$alert_response['result'][0]['message']);
	}

	/**
	 * Test expression macro with empty hostname
	 */
	public function testExpressionMacros_checkEmptyHostname() {
		$this->assertEquals(self::SUBJECT_PREFIX.self::VALUE_TO_FIRE_TRIGGER, self::$alert_response['result'][0]['subject']);
	}

	/**
	 * Test expression macro in function with argument
	 */
	public function testExpressionMacros_checkFunctionArgument() {
		$this->assertEquals(self::SUBJECT_PREFIX.self::VALUE_TO_RECOVER_TRIGGER, self::$alert_response['result'][1]['subject']);
	}

	// /**
	//  * Test expression macro with {HOST.HOST} and {ITEM.KEY} macros
	//  */
	// public function testExpressionMacros_checkMacros() {
	// 	$this->assertEquals(self::MESSAGE_PREFIX.self::VALUE_TO_FIRE_TRIGGER.
	// 			'/host/macro:'.self::VALUE_TO_FIRE_TRIGGER.
	// 			'/empty/macro:'.self::VALUE_TO_FIRE_TRIGGER.
	// 			'/macro/macro:'.self::VALUE_TO_FIRE_TRIGGER.
	// 			'/macroN/macro:'.self::VALUE_TO_FIRE_TRIGGER.
	// 			'/macro/macroN:'.self::VALUE_TO_RECOVER_TRIGGER.
	// 			'/empty/macroN:'.self::VALUE_TO_RECOVER_TRIGGER,
	// 			self::$alert_response['result'][1]['message']);
	// }

	/**
	 * Test expression macro in recovery message
	 */
	public function testExpressionMacros_checkRecoveryMessage() {

		$trigger_expression_explain = self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER .
				' or ' .
				self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER;

		$trigger_recovery_expression_explain = self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_RECOVER_TRIGGER;




		$USER_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED =
			"ACTION.NAME -> "							. self::ACTION_NAME									. " <-\n" .
			"EVENT.ACK.STATUS -> "						. 'No'												. " <-\n" .
			"EVENT.NAME -> "							. self::$event_name_resolved						. " <-\n" .
			"EVENT.NSEVERITY -> "						. self::TRIGGER_PRIORITY							. " <-\n" .
			"EVENT.OBJECT -> "							. '0'												. " <-\n" . // 0 -> Trigger
			"EVENT.OPDATA -> "							. self::TRIGGER_OPDATA								. " <-\n" .
			"EVENT.SEVERITY -> "						. 'Average'											. " <-\n" . // self::TRIGGER_PRIORITY
			"EVENT.SOURCE -> "							. '0'												. " <-\n" . // 0 -> Trigger
			"EVENT.STATUS -> "							. 'RESOLVED'										. " <-\n" . //  0 -> RESOLVED
			"EVENT.TAGS -> "							. self::TAG_NAME . ':' . self::TAG_VALUE			. " <-\n" .
			"EVENT.TAGSJSON -> "						. self::$event_tags_json							. " <-\n" .
			"EVENT.UPDATE.HISTORY -> "					. ''												. " <-\n" .
			"EVENT.UPDATE.STATUS -> "					. '0'												. " <-\n" .
			"EVENT.VALUE -> "							. '0'												. " <-\n" . // 0 -> Resolved
			"HOST.CONN -> "								. '127.0.0.1'										. " <-\n" .
			"HOST.DESCRIPTION -> "						. ''												. " <-\n" .
			"HOST.DNS -> "								. ''												. " <-\n" .
			"HOST.HOST -> "								. self::HOST_NAME									. " <-\n" .
			"HOST.IP -> "								. '127.0.0.1'										. " <-\n" .
			"HOST.NAME -> "								. self::HOST_NAME									. " <-\n" .
			"HOST.PORT -> "								. PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX		. " <-\n" .
			"ITEM.DESCRIPTION -> "						. ''												. " <-\n" .
			"ITEM.DESCRIPTION.ORIG -> "					. ''												. " <-\n" .
			"ITEM.KEY -> "								. self::TRAPPER_ITEM_KEY . '1'						. " <-\n" .
			"ITEM.KEY.ORIG -> "							. self::TRAPPER_ITEM_KEY . '1'						. " <-\n" .
			"ITEM.LASTVALUE -> "						. self::VALUE_TO_RECOVER_TRIGGER					. " <-\n" .
			"ITEM.NAME -> "								. self::TRAPPER_ITEM_NAME . '1'						. " <-\n" .
			"ITEM.NAME.ORIG -> "						. self::TRAPPER_ITEM_NAME . '1'						. " <-\n" .
			"ITEM.VALUE -> "							. self::VALUE_TO_RECOVER_TRIGGER					. " <-\n" .
			"ITEM.VALUETYPE -> "						. ITEM_VALUE_TYPE_UINT64							. " <-\n" .
			"PROXY.DESCRIPTION -> "						. ''												. " <-\n" .
			"PROXY.NAME -> "							. ''												. " <-\n" .
			"TRIGGER.DESCRIPTION -> "					. self::TRIGGER_COMMENTS							. " <-\n" .
			"TRIGGER.EXPRESSION.EXPLAIN -> "			. $trigger_expression_explain						. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY.EXPLAIN -> "	. $trigger_recovery_expression_explain				. " <-\n" .
			"TRIGGER.EVENTS.ACK -> "					. '0'												. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.ACK -> "			. '0'												. " <-\n" .
			"TRIGGER.EVENTS.PROBLEM.UNACK -> "			. '1'												. " <-\n" .
			"TRIGGER.EVENTS.UNACK -> "					. '2'												. " <-\n" .
			"TRIGGER.HOSTGROUP.NAME -> "				. 'Zabbix servers'									. " <-\n" . // 4 -> 'Zabbix servers'
			"TRIGGER.EXPRESSION -> "					. self::$trigger_expression							. " <-\n" .
			"TRIGGER.EXPRESSION.RECOVERY -> "			. self::$trigger_recovery_expression				. " <-\n" .
			"TRIGGER.NAME -> "							. 'trigger_trap'									. " <-\n" .
			"TRIGGER.NAME.ORIG -> "						. 'trigger_trap'									. " <-\n" .
			"TRIGGER.NSEVERITY -> "						. self::TRIGGER_PRIORITY							. " <-\n" .
			"TRIGGER.SEVERITY -> "						. 'Average'											. " <-\n" . // 3 -> Average
			"TRIGGER.STATUS -> "						. 'OK'												. " <-\n" .
			"TRIGGER.URL -> "							. self::TRIGGER_URL									. " <-\n" .
			"TRIGGER.URL.NAME -> "						. self::TRIGGER_URL_NAME							. " <-\n" .
			"TRIGGER.VALUE -> "							. '0' . " <-";													// 0 -> No problem

		$recovery_event_name_resolved = self::EVENT_PREFIX . self::VALUE_TO_RECOVER_TRIGGER;

		$USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED =
			"EVENT.RECOVERY.DATE -> "					. date('Y.m.d')										. " <-\n" .
			"EVENT.RECOVERY.NAME -> "					. $recovery_event_name_resolved						. " <-\n" .
			"EVENT.RECOVERY.STATUS -> "					. 'RESOLVED'										. " <-\n" .
			"EVENT.RECOVERY.TAGS -> "					. self::TAG_NAME . ':' . self::TAG_VALUE			. " <-\n" .
			"EVENT.RECOVERY.TAGSJSON -> "				. self::$event_tags_json							. " <-\n" .
			"EVENT.RECOVERY.VALUE -> "					. '0'												. " <-";

		//$USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED_SOMETHING = "EVENT.RECOVERY.ID -> {EVENT.RECOVEVERY.ID} <- EVENT.RECOVERY.TIME -> {EVENT.RECOVERY.TIME} <-"

		$recovery_message_expect = self::MESSAGE_PREFIX_RECOVERY . self::VALUE_TO_FIRE_TRIGGER . "\n" .
			'===1===' . "\n" .
			$USER_MACROS_CONSISTENT_RESOLVE_COMMON_RESOLVED . "\n" .
			'===2===' . "\n" .
			$USER_MACROS_CONSISTENT_RESOLVE_ONLY_RECOVERY_RESOLVED . "\n" .
			'===3===' . "\n" .
			self::USER_MACROS_UNKNOWN_RESOLVED . "\n" .
			'===4===' . "\n" .
			self::USER_MACROS_NON_REPLACEABLE;


		$this->assertEquals(self::SUBJECT_PREFIX_RECOVERY.self::VALUE_TO_RECOVER_TRIGGER, self::$alert_response['result'][2]['subject']);
		$this->assertEquals($recovery_message_expect, self::$alert_response['result'][2]['message']);
	}

	/**
	 * Test expression macro in event name
	 */
	public function testExpressionMacros_checkEventName() {
		$this->assertEquals(self::EVENT_PREFIX.self::VALUE_TO_FIRE_TRIGGER, self::$event_response['result'][0]['name']);
	}
}
