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


	const USER_MACROS_CONSISTENT_RESOLVE = "
{ACTION.NAME}
{EVENT.ACK.STATUS}
{EVENT.AGE}
{EVENT.DURATION}
{EVENT.NAME}
{EVENT.NSEVERITY}
{EVENT.OBJECT}
{EVENT.OPDATA}
{EVENT.SEVERITY}
{EVENT.SOURCE}
{EVENT.STATUS}
{EVENT.TAGS}
{EVENT.TAGSJSON}
{EVENT.UPDATE.HISTORY}
{EVENT.UPDATE.STATUS}
{EVENT.VALUE}
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
{TRIGGER.VALUE}";



			const USER_MACROS_INCONSISTENT_RESOLVE = "{ACTION.ID}
{ESC.HISTORY}
{DATE}
{TIME}
{EVENT.DATE}
{EVENT.ID}
{EVENT.TIME}
{HOST.ID}
{ITEM.ID}
{TRIGGER.ID}";

		const USER_MACROS_UNKNOWN = "{EVENT.CAUSE.ACK.STATUS}
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
{INVENTORY.ALIAS}
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
{ITEM.LASTVALUE}
{ITEM.LOG.AGE}
{ITEM.LOG.DATE}
{ITEM.LOG.EVENTID}
{ITEM.LOG.NSEVERITY}
{ITEM.LOG.SEVERITY}
{ITEM.LOG.SOURCE}
{ITEM.LOG.TIME}
{TRIGGER.TEMPLATE.NAME}";


	const USER_MACROS_UNKNOWN_RESOLVED = "*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*
*UNKNOWN*";


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
{EVENT.RECOVERY.DATE}
{EVENT.RECOVERY.ID}
{EVENT.RECOVERY.NAME}
{EVENT.RECOVERY.STATUS}
{EVENT.RECOVERY.TAGS}
{EVENT.RECOVERY.TAGSJSON}
{EVENT.RECOVERY.TIME}
{EVENT.RECOVERY.VALUE}
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

		self::$event_tags_json = json_encode(['tag_name' => self::TAG_NAME, 'tag_value' => self::TAG_VALUE]);

		self::$trigger_expression_explain = self::VALUE_TO_FIRE_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER .
				'or' .
				self::VALUE_TO_RECOVER_TRIGGER . '=' . self::VALUE_TO_FIRE_TRIGGER;
		self::$trigger_recovery_expression_explain = self::VALUE_TO_FIRE_TRIGGER . self::VALUE_TO_RECOVER_TRIGGER;

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
						'message' => self::MESSAGE_PREFIX.'{?last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'1)}' .
							self::USER_MACROS_CONSISTENT_RESOLVE . self::USER_MACROS_UNKNOWN . self::USER_MACROS_NON_REPLACEABLE
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
								'/host/macro:{?last(/'.self::HOST_NAME.'/{ITEM.KEY})}'.
								'/empty/macro:{?last(//{ITEM.KEY})}'.
								'/macro/macro:{?last(/{HOST.HOST}/{ITEM.KEY})}'.
								'/macroN/macro:{?last(/{HOST.HOST1}/{ITEM.KEY})}'.
								'/macro/macroN:{?last(/{HOST.HOST}/{ITEM.KEY2})}'.
								'/empty/macroN:{?last(//{ITEM.KEY2})}'.
							self::USER_MACROS_CONSISTENT_RESOLVE . self::USER_MACROS_UNKNOWN . self::USER_MACROS_NON_REPLACEABLE

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
						'message' => self::MESSAGE_PREFIX_RECOVERY.'{?last(//'.self::TRAPPER_ITEM_KEY.'1,#2)}' .
							self::USER_MACROS_CONSISTENT_RESOLVE . self::USER_MACROS_UNKNOWN . self::USER_MACROS_NON_REPLACEABLE
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

	$USER_MACROS_CONSISTENT_RESOLVE =
		self::MESSAGE_PREFIX . self::VALUE_TO_FIRE_TRIGGER	. "\n" .
		self::ACTION_NAME									. "\n" . // {ACTION.NAME}
		'No'												. "\n" . // {EVENT.ACK.STATUS}
		'0s'												. "\n" . // {EVENT.AGE}
		'0s'												. "\n" . // {EVENT.DURATION}
		self::$event_name									. "\n" . // {EVENT.NAME}
		self::TRIGGER_PRIORITY								. "\n" . // {EVENT.NSEVERITY}
		'0'													. "\n" . // {EVENT.OBJECT}  0 -> Trigger
		self::TRIGGER_OPDATA								. "\n" . // {EVENT.OPDATA}
		'Average'											. "\n" . // {EVENT.SEVERITY}  self::TRIGGER_PRIORITY
		'0'													. "\n" . // {EVENT.SOURCE}  0 -> Trigger
		'PROBLEM'											. "\n" . // {EVENT.STATUS}  1 -> PROBLEM
		self::TAG_NAME . ':' . self::TAG_VALUE				. "\n" . // {EVENT.TAGS}
		self::$event_tags_json								. "\n" . // {EVENT.TAGSJSON}
		''													. "\n" . // {EVENT.UPDATE.HISTORY}
		'0'													. "\n" . // {EVENT.UPDATE.STATUS}
		'1'													. "\n" . // {EVENT.VALUE}  1 -> Problem
		'127.0.0.1'											. "\n" . // {HOST.CONN}
		''													. "\n" . // {HOST.DESCRIPTION}
		''													. "\n" . // {HOST.DNS}
		self::HOST_NAME										. "\n" . // {HOST.HOST}
		'127.0.0.1'											. "\n" . // {HOST.IP}
		self::HOST_NAME										. "\n" . // {HOST.NAME}
		'10050'												. "\n" . // {HOST.PORT}
		''													. "\n" . // {ITEM.DESCRIPTION}
		''													. "\n" . // {ITEM.DESCRIPTION.ORIG}
		self::TRAPPER_ITEM_KEY								. "\n" . // {ITEM.KEY}
		self::TRAPPER_ITEM_KEY								. "\n" . // {ITEM.KEY.ORIG}
		self::VALUE_TO_FIRE_TRIGGER							. "\n" . // {ITEM.LASTVALUE}
		self::TRAPPER_ITEM_NAME								. "\n" . // {ITEM.NAME}
		self::TRAPPER_ITEM_NAME								. "\n" . // {ITEM.NAME.ORIG}
		self::VALUE_TO_FIRE_TRIGGER							. "\n" . // {ITEM.VALUE}
		ITEM_VALUE_TYPE_UINT64								. "\n" . // {ITEM.VALUETYPE}
		''													. "\n" . // {PROXY.DESCRIPTION}
		''													. "\n" . // {PROXY.NAME}
		self::TRIGGER_COMMENTS								. "\n" . // {TRIGGER.DESCRIPTION}
		self::$trigger_expression_explain					. "\n" . // {TRIGGER.EXPRESSION.EXPLAIN}
		self::$trigger_recovery_expression_explain			. "\n" . // {TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}
		'0'													. "\n" . // {TRIGGER.EVENTS.ACK}
		'0'													. "\n" . // {TRIGGER.EVENTS.PROBLEM.ACK}
		'1'													. "\n" . // {TRIGGER.EVENTS.PROBLEM.UNACK}
		'1'													. "\n" . // {TRIGGER.EVENTS.UNACK}
		'Zabbix servers'									. "\n" . // {TRIGGER.HOSTGROUP.NAME}  4 -> 'Zabbix servers'
		self::$trigger_expression							. "\n" . // {TRIGGER.EXPRESSION}
		self::$trigger_recovery_expression					. "\n" . // {TRIGGER.EXPRESSION.RECOVERY}
		'trigger_trap'										. "\n" . // {TRIGGER.NAME}
		'trigger_trap'										. "\n" . // {TRIGGER.NAME.ORIG}
		self::TRIGGER_PRIORITY								. "\n" . // {TRIGGER.NSEVERITY}
		'Average'											. "\n" . // {TRIGGER.SEVERITY} 3 -> Average
		'PROBLEM'											. "\n" . // {TRIGGER.STATUS}
		self::TRIGGER_URL									. "\n" . // {TRIGGER.URL}
		self::TRIGGER_URL_NAME								. "\n" . // {TRIGGER.URL.NAME}
		'1';														 // {TRIGGER.VALUE} 1 -> Problem

		$message_expect = self::MESSAGE_PREFIX . self::VALUE_TO_FIRE_TRIGGER .
			$USER_MACROS_CONSISTENT_RESOLVE .
			self::USER_MACROS_UNKNOWN_RESOLVED .
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

	/**
	 * Test expression macro with {HOST.HOST} and {ITEM.KEY} macros
	 */
	public function testExpressionMacros_checkMacros() {
		$this->assertEquals(self::MESSAGE_PREFIX.self::VALUE_TO_FIRE_TRIGGER.
				'/host/macro:'.self::VALUE_TO_FIRE_TRIGGER.
				'/empty/macro:'.self::VALUE_TO_FIRE_TRIGGER.
				'/macro/macro:'.self::VALUE_TO_FIRE_TRIGGER.
				'/macroN/macro:'.self::VALUE_TO_FIRE_TRIGGER.
				'/macro/macroN:'.self::VALUE_TO_RECOVER_TRIGGER.
				'/empty/macroN:'.self::VALUE_TO_RECOVER_TRIGGER,
				self::$alert_response['result'][1]['message']);
	}

	/**
	 * Test expression macro in recovery message
	 */
	public function testExpressionMacros_checkRecoveryMessage() {
		$this->assertEquals(self::SUBJECT_PREFIX_RECOVERY.self::VALUE_TO_RECOVER_TRIGGER, self::$alert_response['result'][2]['subject']);
		$this->assertEquals(self::MESSAGE_PREFIX_RECOVERY.self::VALUE_TO_FIRE_TRIGGER, self::$alert_response['result'][2]['message']);
	}

	/**
	 * Test expression macro in event name
	 */
	public function testExpressionMacros_checkEventName() {
		$this->assertEquals(self::EVENT_PREFIX.self::VALUE_TO_FIRE_TRIGGER, self::$event_response['result'][0]['name']);
	}
}
