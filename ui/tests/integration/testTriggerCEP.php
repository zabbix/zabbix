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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/CCepRuleHelper.php';

/**
 * Test suite to check if trigger CEP (Correlation Event Processing) works properly
 * when item state toggles between normal and unsupported.
 *
 * All item values are delivered to the server impersonating an active proxy (PROXY_NAME) that the host
 * (and, by inheritance, the discovered host) is assigned to, rather than as direct sender/trapper data, so
 * the whole suite exercises the proxy-delivery path. See dispatchSenderValues()/dispatchValues().
 *
 * @required-components server
 * @suite-components-reuse true
 * @onAfter clearData
 * @hosts test
 */
class testTriggerCEP extends CIntegrationTest {
	// Scale knobs for how much load each scenario generates. They are intentionally tiny here so the suite
	// runs quickly in CI, and small values are also handy while debugging to reach a failure fast; when
	// running locally to actually stress CEP, raise them to the recommended values noted below (or higher).
	// Increasing them makes the tests slower but far more thorough.
	const LLD_DISCOVERY_COUNT = 10;	// discovered items/triggers per rule; use at least 4000 to stress CEP
	const LOG_EVENT_COUNT = 10;		// log values pushed at the single-trigger stream; use at least 10000
	const RECOVERY_CYCLES_COUNT = 10;	// PROBLEM/recovery cycles in the rapid burst; use at least 1000
	const MAINTENANCE_COUNT = 40;		// number of maintenances to create; change to any number
	const MAINTENANCE_COUNT_EXTRA = 10;
	const SKIP_RESTART_TESTS = true;

	// Leave null to decide randomly based on the current time; set to true or false to force a path.
	const SKIP_SERVICES_TESTS = null;

	const HOST_NAME = 'test';
	const TEMPLATE_NAME = 'template_trigger_cep';
	const LLD_RULE_KEY = 'lld.cep.trapper';
	const HOST_LLD_RULE_KEY = 'host.lld.trapper';
	const HOST_DISC_VALUE = 'discovered_host1';
	const LLD_MACRO = '{#COMPONENT}';
	// LLD macro carrying each discovered component's parity ('1' for odd index, '0' for even); used
	// by the parity-based global correlation scenario to tag every problem with an 'odd' tag.
	const PARITY_MACRO = '{#PARITY}';
	const ITEM_PROTO_KEY = 'cep.trap';
	const ITEM_PROTO_KEY2 = 'cep.trap2';
	const COMPONENT_VALUE = 'sensor1';
	// Stable per-trigger tag (fixed name, value resolved from the LLD macro to the component, e.g.
	// 'sensor1') used to map each discovered trigger to its own service. Unlike 'component_{ITEM.VALUE}'
	// the tag name does not contain {ITEM.VALUE}, so it is not rewritten at event time and the service
	// problem-tag match is stable across all scenarios.
	const SERVICE_TAG = 'cep_service';
	// Extra tag added to problem events by a webhook (see createExtraTagWebhookAction), used to verify that
	// tags returned by a media type are applied to the events they were generated for.
	const WEB_SERVICE_TAG = 'web_service';
	// Tag added by the second, independent webhook created alongside the first one (see
	// createExtraTagWebhookAction): a different tag name whose value is the same trailing number prefixed
	// with 'second_', verifying that tags returned by two separate media types both land on the same event.
	const WEB_SERVICE_TAG2 = 'web_service2';
	// Second webhook-applied tag carrying the event's component (copied from the 'component' event tag by
	// the same webhook). The web-tag services (see createWebTagServices) match problems only on this tag,
	// so they can go into problem state only via tags applied by the webhook, never via a trigger tag.
	const WEB_COMPONENT_TAG = 'web_component';

	// Name prefix shared by every CEP rule (ceprule API) these scenarios create. deleteCepRules() removes
	// every rule whose name starts with it, so a rule left behind by an aborted test cannot keep closing
	// the problems of the scenarios that run afterwards.
	const CEP_RULE_NAME_PREFIX = 'CEP rule ';
	// The single CEP rule used by the close-on-up complex event processing scenario, see
	// buildCloseOnUpCepRuleParams().
	const CEP_RULE_CLOSE_ON_UP = self::CEP_RULE_NAME_PREFIX.'tag correlation close on up';
	// Extra trigger tag used by the tag-exists flavour of that scenario, whose NAME (not value) carries the
	// state: the tag name contains {ITEM.VALUE}, which is resolved at event time, so a "down_N" event gets a
	// 'state_down' tag and an "up_N" event a 'state_up' tag. That lets the close-window operation single out
	// the "up" events with a plain tag-exists condition on CEP_STATE_TAG_UP, without comparing tag values.
	const CEP_STATE_TAG = 'state_{{ITEM.VALUE}.regsub("^([a-z]+)", "\\1")}';
	const CEP_STATE_TAG_UP = 'state_up';

	// The twelve CEP rules of the windowless scenario, see getWindowNoneRules(): none of them has a window
	// (WINDOW_NONE) and each one tests the 'service' id of the event with a different operator, so which rules
	// match an event is decided by that id alone. The operators come in six opposite pairs, so every event is
	// matched by exactly one rule of every pair. Eight rules test the id through the event tags and four
	// through the event name; each one is named after - and tags the events it matched with - the operator it
	// applies, so a tagged event names the rules that matched it.
	const CEP_TAG_SERVICE_EQUALS = 'service_equals';
	const CEP_TAG_SERVICE_NOT_EQUALS = 'service_not_equals';
	const CEP_TAG_SERVICE_CONTAINS = 'service_contains';
	const CEP_TAG_SERVICE_NOT_CONTAINS = 'service_not_contains';
	const CEP_TAG_SERVICE_MORE_EQUAL = 'service_more_equal';
	const CEP_TAG_SERVICE_LESS_EQUAL = 'service_less_equal';
	const CEP_TAG_SERVICE_EXISTS = 'service_exists';
	const CEP_TAG_SERVICE_NOT_EXISTS = 'service_not_exists';
	const CEP_TAG_EVENT_NAME_EQUALS = 'event_name_equals';
	const CEP_TAG_EVENT_NAME_NOT_EQUALS = 'event_name_not_equals';
	const CEP_TAG_EVENT_NAME_CONTAINS = 'event_name_contains';
	const CEP_TAG_EVENT_NAME_NOT_CONTAINS = 'event_name_not_contains';
	// The 'service' ids (the trailing number of the item value, see prepareCloseOnUpTriggerPrototypes) the
	// rules test against. The Equals, Contains and Exists pairs all work on "0"; the scenario also sends an id
	// that contains it without being equal to it ("10"), which is what tells the Equals pair from the Contains
	// pair. The numeric pair splits the same ids from either side - "is less than or equal 0" and "is more
	// than or equal 1" - so it needs the id right above CEP_RULE_WINDOW_NONE_SERVICE as its second value.
	const CEP_RULE_WINDOW_NONE_SERVICE = '0';
	const CEP_RULE_WINDOW_NONE_SERVICE_NEXT = '1';
	// Extra trigger tag the windowless scenario adds to the prototypes, whose NAME (not value) carries the
	// service id: like CEP_STATE_TAG the name contains {ITEM.VALUE} and is resolved at event time, so a
	// "down_0" event gets a 'service_0' tag and a "down_10" event a 'service_10' one. Existence is a property
	// of the tag name, so this is what the Exists / Does not exist pair tests for (CEP_SERVICE_TAG_PREFIX
	// concatenated with the id gives the name a rule looks for).
	const CEP_SERVICE_TAG_PREFIX = 'service_';
	const CEP_SERVICE_TAG = self::CEP_SERVICE_TAG_PREFIX.'{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}';
	// What the event name rules test against. The prototypes name every event "CEP trigger <component>
	// <item value>" (see prepareCloseOnUpTriggerPrototypes), and the scenario drives the first discovered
	// component, so the full name of the "down_1" event is known up front - that is what the Equals pair
	// compares with, while the Contains pair only looks for the item value inside the name. The two pairs
	// therefore disagree on "down_10", whose name contains "down_1" without being equal to that name.
	const CEP_RULE_WINDOW_NONE_VALUE_NEXT = 'down_'.self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
	const CEP_RULE_WINDOW_NONE_EVENT_NAME = 'CEP trigger '.self::COMPONENT_VALUE.' '
		.self::CEP_RULE_WINDOW_NONE_VALUE_NEXT;

	// Separate template used to stress single-trigger event generation. The template (linked directly to
	// the HOST_NAME host) carries a master log item plus an LLD rule with a dependent log item prototype.
	// The trigger prototype references the dependent discovered item and has multiple problem event
	// generation enabled, so every log value pushed to the master item is propagated to the dependent
	// item and opens a new problem on the one discovered trigger.
	const LOG_TEMPLATE_NAME = 'template_trigger_cep_log';
	const LOG_LLD_RULE_KEY = 'lld.cep.log.trapper';
	const LOG_LLD_MACRO = '{#LOGNUM}';
	const LOG_MASTER_ITEM_KEY = 'cep.log.master';
	const LOG_ITEM_PROTO_KEY = 'cep.log.proto';
	const LOG_COMPONENT_VALUE = 'logsensor1';
	const WAIT_ITERATIONS = 30;
	const WAIT_ITERATION_DELAY = 1;

	// change iterations to fail faster when debugging
	const STATE_CHANGE_WAIT_ITERATIONS = 30;

	// When true, prepareData() disables every internal-source action instead of enabling the built-in
	// "Report not supported items" / "Report unknown triggers" actions for the whole suite. The *Unknown
	// tests then enable those two actions for their own run only and disable them again afterwards, so the
	// rest of the suite runs without the server generating internal item-not-supported / trigger-unknown
	// events. When false the historic behaviour (enabled for the whole suite) is used.
	const SCOPED_INTERNAL_ACTIONS = true;

	// Active proxy whose name is spoofed when delivering item values. The host (and, by inheritance, the
	// discovered host) is assigned to this proxy in prepareData(), and every value is sent to the server
	// as a 'proxy data' request impersonating this proxy instead of as direct sender/trapper data, so the
	// whole suite exercises the proxy-delivery path. See dispatchValues()/dispatchSenderValues().
	const PROXY_NAME = 'test_trigger_cep_proxy';


	private static $hostid;
	private static $proxyid = null;

	// host name -> hostid and "host\0key" -> itemid lookup caches used by the proxy dispatch helpers to
	// translate the host/key based test values into the itemid based entries the proxy data protocol
	// requires. Populated lazily and reset in clearData().
	private static $hostid_cache = [];
	private static $itemid_cache = [];
	private static $disc_hostid;
	private static $templateid;
	private static $log_templateid;
	private static $log_lld_ruleid;
	private static $log_master_itemid;
	private static $log_item_prototypeid;
	private static $log_trigger_prototypeid;
	private static $discovered_log_triggerid;
	private static $lld_ruleid;
	private static $item_prototypeid;
	private static $dep_item_prototypeid;
	private static $trigger_prototypeid;
	private static $dep_trigger_prototypeid;
	private static $discovered_triggerid;
	private static $discovered_dep_triggerid;
	private static $discovered_triggerids = [];
	private static $discovered_dep_triggerids = [];
	private static $correlationid;
	private static $correlationid2;
	private static $cep_ruleid;
	private static $disc_maintenanceids = [];
	private static $serviceids = [];
	private static $service_actionid;
	private static $trigger_actionid;
	private static $mediatypeid;
	private static $tag_mediatypeid;
	private static $tag_actionid;
	private static $tag_mediatypeid2;
	private static $tag_actionid2;
	private static $web_tag_serviceids = [];
	private static $sessionid = null;

	// Highest internal-source eventid that exists before the current *Unknown cycle starts generating its
	// own events; captured by runOpenUnknownTest() so runCloseUnknownTest() can restrict the alert wait to
	// exactly this cycle's notifications (the alerts of events with a larger eventid) before disabling the
	// internal actions. @see waitForInternalAlertsCompleted().
	private static $internal_event_baseline_id = 0;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'DebugLevel' => 3,
				'CacheSize' => '128M',
				'HistoryCacheSize' => '32M',
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => 10000,
				'StartEscalators' => 8,
				'MaxHousekeeperDelete' => 0,
				'StartTrappers' => 32,
				'StartAlerters' => 10,
				'StartTimers' => 2
			]
		];
	}

	/**
	 * Lower bound (max eventid captured at the start of the current scenario) used to limit
	 * every event.get to only the events generated during the scenario. Without this bound the
	 * queries would re-fetch the entire, ever-growing event history of all discovered triggers
	 * on every poll iteration, which does not scale with LLD_DISCOVERY_COUNT.
	 */
	private $event_baseline_id = 0;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Disable audit log so the bulk of API operations below do not flood it.
		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);

		// Disable every pre-existing monitored host so they don't interfere with the suite; this
		// suite's own hosts are (re-)set to monitored after prepareData() by onBeforeTestSuite().
		$response = $this->call('host.get', [
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'output' => ['hostid']
		]);
		foreach ($response['result'] as $h) {
			$this->call('host.update', [
				'hostid' => $h['hostid'],
				'status' => HOST_STATUS_NOT_MONITORED
			]);
		}

		// Retrieve template group ID.
		$response = $this->call('templategroup.get', [
			'filter' => ['name' => 'Templates']
		]);
		$this->assertCount(1, $response['result']);
		$templategroupid = $response['result'][0]['groupid'];

		// Create template.
		$response = $this->call('template.create', [
			'host' => self::TEMPLATE_NAME,
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid = $response['result']['templateids'][0];

		// Create LLD rule on the template (trapper type so tests can push data directly).
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$templateid,
			'name' => 'CEP LLD Discovery Rule',
			'key_' => self::LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid = $response['result']['itemids'][0];

		// Create item prototype on the LLD rule.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => self::$lld_ruleid,
			'name' => 'CEP sensor ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_TRIM,
					'params' => ' ',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$item_prototypeid = $response['result']['itemids'][0];

		// Create trigger prototype referencing the item prototype.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'])<>0',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$trigger_prototypeid = $response['result']['triggerids'][0];

		// Create second item prototype on the LLD rule.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => self::$lld_ruleid,
			'name' => 'CEP sensor2 ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY2.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_TRIM,
					'params' => ' ',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$dep_item_prototypeid = $response['result']['itemids'][0];

		// Create second trigger prototype with a dependency on the first trigger prototype.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'])<>0',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'dependencies' => [
				['triggerid' => self::$trigger_prototypeid]
			],
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$dep_trigger_prototypeid = $response['result']['triggerids'][0];

		// Create the separate log template with an LLD rule, a master log item, a dependent log item
		// prototype and a trigger prototype with multiple problem event generation enabled. The value
		// burst is pushed to the master item and propagated to the dependent discovered item, on which the
		// trigger prototype fires. This template is linked directly to the host below so that a single
		// discovered trigger can be exercised with a large value burst.
		$response = $this->call('template.create', [
			'host' => self::LOG_TEMPLATE_NAME,
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$log_templateid = $response['result']['templateids'][0];

		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$log_templateid,
			'name' => 'CEP Log LLD Discovery Rule',
			'key_' => self::LOG_LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_lld_ruleid = $response['result']['itemids'][0];

		// Master (normal) log item that receives the value burst via trapper.
		$response = $this->call('item.create', [
			'hostid' => self::$log_templateid,
			'name' => 'CEP log master item',
			'key_' => self::LOG_MASTER_ITEM_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_LOG
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_master_itemid = $response['result']['itemids'][0];

		// Dependent log item prototype: each value pushed to the master item is propagated here and drives
		// the trigger prototype on the discovered item.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$log_templateid,
			'ruleid' => self::$log_lld_ruleid,
			'name' => 'CEP log item ['.self::LOG_LLD_MACRO.']',
			'key_' => self::LOG_ITEM_PROTO_KEY.'['.self::LOG_LLD_MACRO.']',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => self::$log_master_itemid,
			'value_type' => ITEM_VALUE_TYPE_LOG
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_item_prototypeid = $response['result']['itemids'][0];

		// Multiple problem event generation: every log value matching the pattern opens a new problem,
		// so a burst of N values produces N problem events on the single discovered trigger.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP log trigger for '.self::LOG_LLD_MACRO,
			'expression' => 'find(/'.self::LOG_TEMPLATE_NAME.'/'.self::LOG_ITEM_PROTO_KEY
				.'['.self::LOG_LLD_MACRO.'],,"like","problem")=1',
			'event_name' => 'CEP log trigger '.self::LOG_LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'tags' => [
				['tag' => 'type', 'value' => 'cep-log']
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$log_trigger_prototypeid = $response['result']['triggerids'][0];

		// Create host — template will be linked via host prototype discovery, not directly.
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				['groupid' => 4]
			],
			'templates' => [
				['templateid' => self::$log_templateid]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create the active proxy and assign the host to it. The host prototype discovers its host with the
		// parent host's proxy inherited, so the discovered host is monitored by this proxy too; every value
		// is then delivered to the server impersonating this proxy (see dispatchValues()).
		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				['hostid' => self::$hostid]
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['proxyids']);
		self::$proxyid = $response['result']['proxyids'][0];

		// Create LLD rule on the host for host prototype discovery.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid,
			'name' => 'Host LLD Discovery Rule',
			'key_' => self::HOST_LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$host_disc_ruleid = $response['result']['itemids'][0];

		// Create host prototype linked to the template created above.
		$response = $this->call('hostprototype.create', [
			'ruleid' => $host_disc_ruleid,
			'host' => '{#HOST}',
			'groupLinks' => [
				['groupid' => 4]
			],
			'templates' => [
				['templateid' => self::$templateid]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		// Create the webhook media type used by all action operations and attach it to the Admin user
		// before enabling the internal actions below, so their notifications route through it too.
		$this->prepareWebhookMediaType();

		// Make the server generate internal item-not-supported and trigger-unknown events (verified by the
		// *Unknown tests). With SCOPED_INTERNAL_ACTIONS the internal actions are disabled here and re-enabled
		// only for the *Unknown tests, so the rest of the suite runs without them; otherwise the built-in
		// actions are enabled for the whole suite. Either way clearData() cleans up and the configuration
		// cache is reloaded by the first test (or by enableInternalActions()).
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->disableInternalActions();
		}
		else {
			$this->setInternalActionStatus('Report unknown triggers', ACTION_STATUS_ENABLED);
			$this->setInternalActionStatus('Report not supported items', ACTION_STATUS_ENABLED);
		}

		return true;
	}

	/**
	 * Create a simple webhook media type whose script just returns 1, so the escalator exercises the
	 * alerter end-to-end without contacting anything external, and attach it to the Admin user (a member
	 * of user group 7) so the action operations actually generate alerts. As it is the user's only media,
	 * the built-in internal actions (which send to group 7 with the default "all media types") also route
	 * their notifications through it. Removed in clearData().
	 */
	private function prepareWebhookMediaType(): void {
		$response = $this->call('mediatype.create', [
			'name' => 'CEP webhook',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => 'return 1;',
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$mediatypeid = $response['result']['mediatypeids'][0];

		$this->call('user.update', [
			'userid' => 1,
			'medias' => [
				['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep']
			]
		]);
	}

	/**
	 * Create one service per discovered primary trigger (mapped to its trigger via the stable SERVICE_TAG
	 * problem tag, value '<component>'), a service action firing on those services (service name like
	 * 'CEP service') and a trigger action firing on the discovered triggers (event tag type=cep). Both
	 * actions route through the CEP webhook media type, so the CEP event stream drives the service manager
	 * and the action/escalator pipeline. The configuration cache is reloaded so the server picks up the
	 * new actions. Everything is removed in clearData().
	 */
	private function createServicesAndActions(): void {
		// Re-read the discovered primary triggers with their tags so each service can be mapped to its
		// trigger via the trigger's own SERVICE_TAG value.
		$response = $this->call('trigger.get', [
			'triggerids' => self::$discovered_triggerids,
			'output' => ['triggerid'],
			'selectTags' => 'extend'
		]);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'],
			'Not all discovered triggers were found.');

		$services = [];
		foreach ($response['result'] as $trigger) {
			$service_tag = current(array_filter($trigger['tags'],
				fn($t) => $t['tag'] === self::SERVICE_TAG
			));
			$this->assertNotFalse($service_tag,
				'Discovered trigger '.$trigger['triggerid'].' has no '.self::SERVICE_TAG.' tag.');

			$services[] = [
				'name' => 'CEP service '.$trigger['triggerid'],
				'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
				'sortorder' => 0,
				'problem_tags' => [
					[
						'tag' => $service_tag['tag'],
						'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL,
						'value' => $service_tag['value']
					]
				]
			];
		}

		$response = $this->call('service.create', $services);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']['serviceids'],
			'Not all CEP services were created.');
		self::$serviceids = $response['result']['serviceids'];

		// Service action: problem, recovery and update message operations targeting user group 7 via the
		// CEP webhook media type, so the escalator runs the service action through the alerter.
		$response = $this->call('action.create', [
			'name' => 'CEP service action',
			'eventsource' => EVENT_SOURCE_SERVICE,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_SERVICE_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => 'CEP service'
					]
				]
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Recovery', 'subject' => 'Recovery'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'update_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Update', 'subject' => 'Update'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$service_actionid = $response['result']['actionids'][0];

		// Trigger action firing on the discovered triggers (event tag type=cep), with problem and
		// recovery message operations routed through the same CEP webhook media type.
		$response = $this->call('action.create', [
			'name' => 'CEP trigger action',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 0,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep'
					]
				]
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Recovery', 'subject' => 'Recovery'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$trigger_actionid = $response['result']['actionids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Update all trigger prototypes on the LLD rule to use "None" ok event generation
	 * and resend discovery data so the server picks up the updated prototype configuration.
	 */
	public function prepareDataNoneOkEvent() {
		// Fetch all trigger prototypes belonging to the LLD rule.
		$response = $this->call('triggerprototype.get', [
			'discoveryids' => [self::$lld_ruleid],
			'output' => ['triggerid']
		]);
		$this->assertNotEmpty($response['result'], 'No trigger prototypes found on the LLD rule.');

		// Update each prototype: disable ok event generation.
		foreach ($response['result'] as $prototype) {
			$this->call('triggerprototype.update', [
				'triggerid' => $prototype['triggerid'],
				'recovery_mode' => ZBX_RECOVERY_MODE_NONE
			]);
		}

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_NONE) {
					return false;
				}
			}
			return true;
		});

		// Reload configuration cache so the server is aware of the changed prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Restore all trigger prototypes to expression-based recovery mode and resend
	 * discovery data so the server picks up the restored configuration.
	 */
	public function prepareDataRestoreRecovery() {
		// Fetch all trigger prototypes belonging to the LLD rule.
		$response = $this->call('triggerprototype.get', [
			'discoveryids' => [self::$lld_ruleid],
			'output' => ['triggerid']
		]);
		$this->assertNotEmpty($response['result'], 'No trigger prototypes found on the LLD rule.');

		// Restore each prototype to expression-based recovery.
		foreach ($response['result'] as $prototype) {
			$this->call('triggerprototype.update', [
				'triggerid' => $prototype['triggerid'],
				'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION
			]);
		}

		// Resend LLD discovery data to re-instantiate discovered triggers with the restored config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_EXPRESSION) {
					return false;
				}
			}
			return true;
		});

		// Reload configuration cache so the server is aware of the restored prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to recovery-expression mode, setting the recovery
	 * expression identical to the problem expression, and resend discovery data so
	 * the server picks up the updated configuration.
	 */
	public function prepareDataRecoveryExpression() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION,
			'recovery_expression' => 'min(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],#2)=0'
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION,
			'recovery_expression' => 'min(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],#2)=0'
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Reload configuration cache so the server is aware of the changed prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Verify the discovered triggers reflect the updated recovery mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, $trigger['recovery_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to recovery-expression mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to recovery-expression mode with multiple event generation
	 * enabled, and resend discovery data so the server picks up the updated configuration.
	 */
	public function prepareDataMultipleEventsRecoveryExpression() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered triggers reflect the updated mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'type', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED
						|| (int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
			$this->assertEquals(ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, $trigger['recovery_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to recovery-expression mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to tag-correlation mode: OK events close only
	 * PROBLEM events whose "component" tag value matches, then resend discovery data
	 * so the server picks up the updated configuration.
	 */
	public function prepareDataTagCorrelation() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'type',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'type',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered triggers reflect the updated correlation mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_TAG
						|| $trigger['correlation_tag'] !== 'type'
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_TAG, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to tag-correlation mode.');
			$this->assertEquals('type', $trigger['correlation_tag'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected correlation tag.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both item prototypes to text value type and both trigger prototypes to use
	 * find(regexp,"down") as expression with tag-correlation on the 'service' tag, then verify
	 * that the already-discovered triggers and items reflect the updated configuration.
	 */
	public function prepareDataServiceCorrelation() {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Update trigger prototypes: find(regexp,"down") expression + service tag correlation.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'service',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [
				['triggerid' => self::$trigger_prototypeid]
			],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'service',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO]
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect the updated expression and correlation config.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'manual_close', 'type', 'expression']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_TAG
						|| $trigger['correlation_tag'] !== 'service'
						|| (int) $trigger['manual_close'] !== ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_TAG, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to tag-correlation mode.');
			$this->assertEquals('service', $trigger['correlation_tag'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected correlation tag.');
			$this->assertEquals(ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, $trigger['manual_close'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected manual_close setting.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both item prototypes to text value type and both trigger prototypes to use
	 * find(regexp,"down") as expression with global event correlation:
	 *   - Trigger-level correlation mode = ZBX_TRIGGER_CORRELATION_NONE (trigger recovery
	 *     closes all remaining open problems at once).
	 *   - TRIGGER_MULT_EVENT_ENABLED so new PROBLEM events are generated even while the
	 *     trigger is already TRUE.
	 *   - A global event correlation rule (old event service="down", new event service="up",
	 *     operation = CLOSE_OLD) is created so that a RESOLVED event with service="up" closes
	 *     open problems whose service tag is "down", independently of trigger-level recovery.
	 */
	public function prepareDataGlobalCorrelation($evaltype = CONDITION_EVAL_TYPE_AND_OR) {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Update trigger prototypes: find(regexp,"down") expression + global correlation +
		// multiple event generation so multiple PROBLEM events accumulate before a single
		// recovery event closes them all at once.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}']
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}']
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect global correlation mode and multiple event generation.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_NONE, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to global correlation mode.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		// Start from a clean correlation slate so rules left over from other CEP scenarios (parity
		// "odd"/"even", "close on up", ...) cannot stay active during this test. The rule below is then
		// (re)created as the only CEP correlation rule.
		$this->deleteCepCorrelations();

		// Create a global event correlation rule: close old events whose 'service' tag value
		// is 'down' when a new event arrives with 'service' tag value 'up'.
		// This exercises the global-correlation path independently of trigger-level correlation.
		$conditions = [
			[
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'tag' => 'service',
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => 'down'
			],
			[
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'tag' => 'type',
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => 'cep-dep'
			],
			[
				'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				'oldtag' => 'component',
				'newtag' => 'component'
			]
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			// Assign a formula id to each condition and AND them together so the rule behaves
			// identically to the CONDITION_EVAL_TYPE_AND_OR variant while exercising the
			// custom expression evaluation path.
			$formulaids = ['A', 'B', 'C'];
			foreach ($conditions as $i => &$condition) {
				$condition['formulaid'] = $formulaids[$i];
			}
			unset($condition);

			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => implode(' and ', $formulaids),
				'conditions' => $conditions
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => $conditions
			];
		}

		$corr_params = [
			'name' => 'CEP global event correlation',
			'filter' => $filter,
			'operations' => [
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_OLD
				],
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_NEW
				]
			]
		];

		self::$correlationid = $this->upsertCorrelation($corr_params);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both trigger prototypes for the "close old down when new up" global correlation
	 * scenario. The key difference from prepareDataGlobalCorrelation is that the trigger expression
	 * matches both "down" and "up", so an "up_N" value is itself a PROBLEM event (not a recovery) that
	 * can drive global correlation as the new event. Each trigger carries:
	 *   - 'state'   = the leading letters of the value ("down"/"up"), the old/new discriminator;
	 *   - 'service' = the trailing number of the value ("0"/"1"), the pairing key so "up_1" correlates
	 *                 to "down_1".
	 * A single global correlation rule (old state="down" + new state="up" + service tag pair,
	 * CLOSE_OLD + CLOSE_NEW) is created; it stays silent while only "down" problems are opened and only
	 * fires once "up" problems arrive.
	 */
	public function prepareDataGlobalCorrelationCloseOnUp($evaltype = CONDITION_EVAL_TYPE_AND_OR,
			$extra_tag_via_webhook = false, $recreate_correlation = false) {
		$this->prepareCloseOnUpTriggerPrototypes();

		// Create (or update in place) the single "close old down when new up" rule. When
		// $recreate_correlation is set the existing CEP correlation rules are deleted first, so the rule is
		// built from scratch with this test's evaltype rather than updated on top of the one a previous
		// CloseOnUp variant left behind (which uses a different evaltype).
		if ($recreate_correlation) {
			$this->deleteCepCorrelations();
		}

		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', $evaltype)
		);

		// Optionally add an extra webhook-computed tag to every problem event.
		if ($extra_tag_via_webhook) {
			$this->createExtraTagWebhookAction();
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both trigger prototypes for the "close old down when new up" scenarios and resend LLD so
	 * the discovered items and triggers are re-instantiated with that configuration. Shared by the global
	 * event correlation variants (prepareDataGlobalCorrelationCloseOnUp) and by the complex event processing
	 * variant (prepareDataCepWindowTagCorrelationCloseOnUp), which only differ in which rule engine closes
	 * the problems afterwards. $extra_tags are appended to the tags of both prototypes, letting the
	 * tag-exists flavour of the CEP variant add the state-carrying tag name it matches on (CEP_STATE_TAG).
	 *
	 * Does nothing when the discovered triggers already carry this exact configuration, which is the common
	 * case: the CloseOnUp scenarios share it and every dependent test re-runs its prepareData* method, so
	 * re-sending LLD would only cost another full discovery cycle.
	 */
	private function prepareCloseOnUpTriggerPrototypes(array $extra_tags = []): void {
		// Both prototypes: find(regexp,"down|up") so "up" is a PROBLEM (not a recovery) + multiple event
		// generation + global correlation, plus a 'state' tag ("down"/"up") and a 'service' tag (the
		// trailing number) that pairs an "up_N" problem with its "down_N" problem.
		//
		// When $extra_tag_via_webhook is set, the correlation still uses the 'service' trigger tag as usual;
		// additionally a webhook media type (see createExtraTagWebhookAction) adds a separate WEB_SERVICE_TAG
		// tag to each problem event from JavaScript, so the test can verify that tags returned by a media
		// type are applied to the events they were generated for.
		$common_tags = array_merge([
			['tag' => 'component', 'value' => self::LLD_MACRO],
			['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
			['tag' => 'state', 'value' => '{{ITEM.VALUE}.regsub("^([a-z]+)", "\\1")}'],
			['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
		], $extra_tags);

		// Nothing to re-discover when a discovered trigger already has this configuration. The tag names are
		// what tell the flavours apart, so they must match exactly: $extra_tags present when they are needed
		// and absent when they are not - a CEP_STATE_TAG left behind by the tag-exists flavour would
		// otherwise let the tag-value flavour reuse triggers carrying a tag its condition must not see.
		if ($this->hasCloseOnUpDiscoveredTrigger(array_merge(['type'], array_column($common_tags, 'tag')))) {
			return;
		}

		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down|up")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => array_merge([['tag' => 'type', 'value' => 'cep']], $common_tags)
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down|up")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => array_merge([['tag' => 'type', 'value' => 'cep-dep']], $common_tags)
		]);

		// Resend LLD discovery data to re-instantiate the discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Wait for the discovered items to reflect the text value type and the discovered triggers to
		// reflect global correlation mode + multiple event generation.
		$this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});

		// Filter on the 'state' tag the updated prototype adds (the only genuinely new tag), so requiring
		// both ids back confirms the new config was applied rather than the pre-update defaults.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'tags' => [['tag' => 'state', 'operator' => TAG_OPERATOR_EXISTS]]
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});

		// The wait above filters on the 'state' tag, which the prototypes already carried before this update,
		// so it cannot tell whether $extra_tags have landed on the discovered triggers yet. Wait for them
		// separately: trigger.get AND-s tag filters of distinct names, so a single trigger coming back means
		// every extra tag was applied (the tag names still hold the unresolved {ITEM.VALUE}, which is
		// substituted at event time, not at discovery time). One trigger is enough - the tags of every
		// discovered trigger come from the same two prototypes and are written by the same LLD pass.
		if ($extra_tags) {
			$this->callUntilDataIsPresent('trigger.get', [
				'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
				'output' => ['triggerid'],
				'tags' => array_map(
					fn(array $tag) => ['tag' => $tag['tag'], 'operator' => TAG_OPERATOR_EXISTS],
					$extra_tags
				)
			], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Whether at least one of the two primary discovered triggers is already configured the way
	 * prepareCloseOnUpTriggerPrototypes() would configure it: no trigger-level correlation, multiple event
	 * generation and exactly $tag_names as its tag names.
	 *
	 * One trigger is enough - the tags of every discovered trigger come from the same two prototypes and are
	 * written by the same LLD pass. The tag names alone identify the scenario: 'state' is written by no other
	 * prepareData* method, and the only call that writes it also switches the item prototypes to text value
	 * type, so a match implies the discovered items are text as well.
	 */
	private function hasCloseOnUpDiscoveredTrigger(array $tag_names): bool {
		$response = $this->call('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'selectTags' => 'extend'
		]);

		foreach ($response['result'] as $trigger) {
			if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
					|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
				continue;
			}

			$trigger_tag_names = array_column($trigger['tags'], 'tag');

			// No expected tag name missing and no unexpected one present.
			if (!array_diff($tag_names, $trigger_tag_names) && !array_diff($trigger_tag_names, $tag_names)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepare the "close old down when new up" scenario driven by the new complex event processing (CEP rule)
	 * functionality instead of a global event correlation rule. The trigger/item setup is exactly the one of
	 * prepareDataGlobalCorrelationCloseOnUp (an "up_N" value is a PROBLEM event carrying state="up" and the
	 * 'service' pairing tag); every global correlation rule is removed and the problems are closed by a single
	 * CEP rule with a tag correlation time window instead, see buildCloseOnUpCepRuleParams(). The observable
	 * behaviour is identical, so the scenario is driven by the very same runner
	 * (runEventAssessmentTestGlobalCorrelationCloseOnUp).
	 *
	 * $tag_exists_condition selects how the close-window operation singles out the "up" events: with a plain
	 * tag-exists condition, which additionally needs the state-carrying CEP_STATE_TAG tag name on both
	 * prototypes, or with a tag value comparison (state Equals "up").
	 *
	 * $extra_tag_via_webhook adds the same two tagging webhook media types the global correlation variant sets
	 * up (see createExtraTagWebhookAction), so the CEP flavours of the JS scenarios can assert that tags
	 * returned by a media type land on the events they were generated for.
	 */
	public function prepareDataCepWindowTagCorrelationCloseOnUp(bool $tag_exists_condition = true,
			bool $extra_tag_via_webhook = false) {
		$this->prepareCloseOnUpTriggerPrototypes($tag_exists_condition
			? [['tag' => self::CEP_STATE_TAG, 'value' => '']]
			: []
		);

		// The CEP rule must be the only thing closing problems in this scenario: drop the global correlation
		// rules a previous CloseOnUp variant left behind, otherwise they would close the same problems and
		// the run would pass regardless of what the CEP rule does.
		$this->deleteCepCorrelations();

		self::$cep_ruleid = $this->upsertCepRule(
			$this->buildCloseOnUpCepRuleParams(self::CEP_RULE_CLOSE_ON_UP, $tag_exists_condition)
		);

		// Optionally add an extra webhook-computed tag to every problem event.
		if ($extra_tag_via_webhook) {
			$this->createExtraTagWebhookAction();
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the windowless complex event processing scenario. It reuses the close-on-up trigger prototypes
	 * (prepareCloseOnUpTriggerPrototypes(), so every problem event carries a 'service' tag holding the
	 * trailing number of the item value), but instead of the single tag correlation window rule of
	 * prepareDataCepWindowTagCorrelationCloseOnUp() it creates the twelve rules of getWindowNoneRules(), which
	 * have no window at all (WINDOW_NONE) and only tag the events they match, one rule per operator. Every rule
	 * is named after the tag it adds, which in turn is named after its operator, so the rule set reads as:
	 *   - "service_equals": 'service' Equals "0";
	 *   - "service_not_equals": 'service' Does not equal "0";
	 *   - "service_contains": 'service' Contains "0";
	 *   - "service_not_contains": 'service' Does not contain "0";
	 *   - "service_more_equal": 'service' Is more than or equal "1";
	 *   - "service_less_equal": 'service' Is less than or equal "0";
	 *   - "service_exists": tag 'service_0' Exists;
	 *   - "service_not_exists": tag 'service_0' Does not exist;
	 *   - "event_name_equals": event name Equals "CEP trigger sensor1 down_1";
	 *   - "event_name_not_equals": event name Does not equal that name;
	 *   - "event_name_contains": event name Contains "down_1";
	 *   - "event_name_not_contains": event name Does not contain "down_1".
	 *
	 * The Exists pair tests a tag NAME rather than a value, so the prototypes additionally get the
	 * CEP_SERVICE_TAG tag whose name resolves to 'service_<id>' at event time; only the "down_0" event
	 * therefore carries a 'service_0' tag.
	 *
	 * The rules form six opposite pairs, so every problem event is tagged by exactly one rule of each pair:
	 * "down_0" by the Equals, the Contains, the Is less than or equal, the Exists, the name Does not equal and
	 * the name Does not contain rule; "down_1" by the Does not equal, the Does not contain, the Is more than or
	 * equal, the Does not exist, the name Equals and the name Contains rule; and "down_10" - an id that
	 * contains "0" without being equal to it, and whose name contains "down_1" without being equal to the
	 * "down_1" name - by the Does not equal, the Contains, the Is more than or equal, the Does not exist, the
	 * name Does not equal and the name Contains rule.
	 *
	 * None of the rules closes anything, which is the point of a windowless rule: the events keep flowing
	 * through untouched apart from the tag.
	 */
	public function prepareDataCepWindowNoneTagOperations() {
		// The extra tag carries the service id in its NAME, which is what the Exists / Does not exist pair
		// tests for; its value is irrelevant.
		$this->prepareCloseOnUpTriggerPrototypes([['tag' => self::CEP_SERVICE_TAG, 'value' => '']]);

		// Nothing except the trigger expression may close these problems - the scenario asserts they all stay
		// open - so drop both the global correlation rules and the window CEP rule a previous CloseOnUp
		// variant left behind; either of them would close the problems this scenario opens.
		$this->deleteCepCorrelations();
		$this->deleteCepRules();

		foreach ($this->getWindowNoneRules() as $tag => [$condition, $operand]) {
			$this->upsertCepRule($this->buildWindowNoneAddTagCepRuleParams(
				self::CEP_RULE_NAME_PREFIX.'window none '.$tag, $condition, $tag, $operand
			));
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the "close old down when new up" scenario with the correlation created under
	 * $baseline_evaltype and then updated in place to $target_evaltype, so the test exercises an evaltype
	 * transition on an existing rule rather than a freshly created one. The baseline rule is recreated from
	 * scratch first (so the starting evaltype is deterministic regardless of what a previous CloseOnUp
	 * variant left behind), then correlation.update switches it to the target evaltype.
	 */
	public function prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition($baseline_evaltype, $target_evaltype) {
		$this->prepareDataGlobalCorrelationCloseOnUp($baseline_evaltype, false, true);

		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', $target_evaltype)
		);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Create two independent webhook media types and two trigger actions that together add extra tags to
	 * every discovered CEP problem event using JavaScript. The first webhook adds a WEB_SERVICE_TAG tag (the
	 * trailing number of the item value, e.g. "0" for "down_0"); the second adds a WEB_SERVICE_TAG2 tag
	 * whose value is the same trailing number prefixed with 'second_'. This does not affect correlation
	 * (which still uses the 'service' trigger tag) — it lets the test verify that tags returned by media
	 * types are applied to the events they were generated for, including when two separate webhook actions
	 * tag the same event.
	 *
	 * Both media types have process_tags enabled; each script parses the item value passed as a parameter,
	 * extracts the trailing number and returns it in the {"tags": {...}} form the alerter applies to the
	 * event. The first script also copies the event's 'component' tag (passed via {EVENT.TAGS.component})
	 * into a WEB_COMPONENT_TAG tag, which the web-tag services (see createWebTagServices) match their
	 * problems on. Medias are attached to the Admin user for both media types, and two trigger actions
	 * firing on the discovered CEP triggers route their problem operations through the webhooks, so each
	 * PROBLEM event ("down_N" and, since the expression matches "up", "up_N") gets both a WEB_SERVICE_TAG
	 * and a WEB_SERVICE_TAG2 tag.
	 *
	 * Everything created here is removed in removeExtraTagWebhookAction() / clearData().
	 */
	private function createExtraTagWebhookAction(): void {
		$tag = self::WEB_SERVICE_TAG;
		$tag2 = self::WEB_SERVICE_TAG2;
		$component_tag = self::WEB_COMPONENT_TAG;
		$script_code = <<<HEREDOC
var params = JSON.parse(value),
	match = params.item_value.match(/([0-9]+)\$/),
	tags = {'$tag': match === null ? '' : match[1]};

tags['$component_tag'] = params.component;

return JSON.stringify({tags: tags});
HEREDOC;

		$script_code2 = <<<HEREDOC
var params = JSON.parse(value),
	match = params.item_value.match(/([0-9]+)\$/),
	tags = {'$tag2': match === null ? '' : 'second_' + match[1]};

return JSON.stringify({tags: tags});
HEREDOC;

		$response = $this->call('mediatype.create', [
			'name' => 'CEP extra tag webhook',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => $script_code,
			'process_tags' => ZBX_MEDIA_TYPE_TAGS_ENABLED,
			'status' => MEDIA_TYPE_STATUS_ACTIVE,
			'parameters' => [
				['name' => 'item_value', 'value' => '{ITEM.VALUE}'],
				['name' => 'component', 'value' => '{EVENT.TAGS.component}']
			]
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$tag_mediatypeid = $response['result']['mediatypeids'][0];

		$response = $this->call('mediatype.create', [
			'name' => 'CEP extra tag webhook 2',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => $script_code2,
			'process_tags' => ZBX_MEDIA_TYPE_TAGS_ENABLED,
			'status' => MEDIA_TYPE_STATUS_ACTIVE,
			'parameters' => [
				['name' => 'item_value', 'value' => '{ITEM.VALUE}']
			]
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$tag_mediatypeid2 = $response['result']['mediatypeids'][0];

		// Attach both tagging media types to the Admin user alongside the shared CEP webhook media, so the
		// actions' message operations actually generate alerts (and thus run the webhooks).
		$this->call('user.update', [
			'userid' => 1,
			'medias' => [
				['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep'],
				['mediatypeid' => self::$tag_mediatypeid, 'sendto' => 'cep'],
				['mediatypeid' => self::$tag_mediatypeid2, 'sendto' => 'cep']
			]
		]);

		// Trigger action firing on every discovered CEP trigger event (both prototypes, type=cep and
		// type=cep-dep are OR'd together), routing the problem operation through the tagging webhook.
		$response = $this->call('action.create', [
			'name' => 'CEP extra tag action',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 0,
			/*'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep'
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep-dep'
					]
				]
			],*/
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$tag_mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$tag_actionid = $response['result']['actionids'][0];

		// Second trigger action, identical except that it routes its problem operation through the second
		// tagging webhook, so every problem event is tagged by two independent webhook actions.
		$response = $this->call('action.create', [
			'name' => 'CEP extra tag action 2',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 0,
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$tag_mediatypeid2,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$tag_actionid2 = $response['result']['actionids'][0];

		// The service/trigger actions are already disabled once the services-specific tests finish (see
		// disableServicesActions), so only the tagging webhooks fire in this scenario.
	}

	/**
	 * Tear down the webhook media types, their user medias and the trigger actions created by
	 * createExtraTagWebhookAction(), restoring the Admin user to only the shared CEP webhook media so the
	 * tagging webhooks do not leak into subsequent tests.
	 */
	private function removeExtraTagWebhookAction(): void {
		if (!empty(self::$tag_actionid)) {
			$this->call('action.delete', [self::$tag_actionid]);
			self::$tag_actionid = null;
		}

		if (!empty(self::$tag_actionid2)) {
			$this->call('action.delete', [self::$tag_actionid2]);
			self::$tag_actionid2 = null;
		}

		if (!empty(self::$tag_mediatypeid) || !empty(self::$tag_mediatypeid2)) {
			$this->call('user.update', [
				'userid' => 1,
				'medias' => [
					['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep']
				]
			]);

			if (!empty(self::$tag_mediatypeid)) {
				$this->call('mediatype.delete', [self::$tag_mediatypeid]);
				self::$tag_mediatypeid = null;
			}

			if (!empty(self::$tag_mediatypeid2)) {
				$this->call('mediatype.delete', [self::$tag_mediatypeid2]);
				self::$tag_mediatypeid2 = null;
			}
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create one service per discovered component whose only problem tag is the webhook-applied
	 * WEB_COMPONENT_TAG (see createExtraTagWebhookAction). Unlike the per-trigger CEP services (which match
	 * the SERVICE_TAG trigger tag), no trigger tag matches these services: each can enter problem state
	 * only after the tagging webhook runs for an open problem event and the tags it returns are applied to
	 * that event. Removed in removeWebTagServices() / clearData().
	 */
	private function createWebTagServices(): void {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');

		$services = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$services[] = [
				'name' => 'CEP web tag service '.$base.$i,
				'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
				'sortorder' => 0,
				'problem_tags' => [
					[
						'tag' => self::WEB_COMPONENT_TAG,
						'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL,
						'value' => $base.$i
					]
				]
			];
		}

		$response = $this->call('service.create', $services);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']['serviceids'],
			'Not all web-tag services were created.');
		self::$web_tag_serviceids = $response['result']['serviceids'];

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete the services created by createWebTagServices() so they do not react to the events of later
	 * scenarios. Guarded, so it is safe in a finally block even if creation failed. The caller is expected
	 * to follow up with removeExtraTagWebhookAction(), which reloads the configuration cache.
	 */
	private function removeWebTagServices(): void {
		if (!empty(self::$web_tag_serviceids)) {
			$this->call('service.delete', self::$web_tag_serviceids);
			self::$web_tag_serviceids = [];
		}
	}

	/**
	 * Create the correlation rule described by $corr_params, or update it in place if one with the
	 * same name already exists (prepareData* methods are re-run by every dependent test). Returns
	 * the correlation id so the caller can track it for cleanup.
	 */
	private function upsertCorrelation(array $corr_params): string {
		$existing = $this->call('correlation.get',
			['filter' => ['name' => $corr_params['name']], 'output' => ['correlationid']]
		);
		if ($existing['result']) {
			$correlationid = $existing['result'][0]['correlationid'];
			$this->call('correlation.update', ['correlationid' => $correlationid] + $corr_params);
		}
		else {
			$response = $this->call('correlation.create', $corr_params);
			$this->assertArrayHasKey('correlationids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['correlationids']);
			$correlationid = $response['result']['correlationids'][0];
		}

		return $correlationid;
	}

	/**
	 * Create the CEP rule described by $rule_params, or update it in place if one with the same name already
	 * exists (prepareData* methods are re-run by every dependent test). Returns the CEP rule id so the caller
	 * can track it for cleanup.
	 */
	private function upsertCepRule(array $rule_params): string {
		$existing = $this->call('ceprule.get',
			['filter' => ['name' => $rule_params['name']], 'output' => ['cep_ruleid']]
		);
		if ($existing['result']) {
			$cep_ruleid = $existing['result'][0]['cep_ruleid'];
			$this->call('ceprule.update', ['cep_ruleid' => $cep_ruleid] + $rule_params);
		}
		else {
			$response = $this->call('ceprule.create', $rule_params);
			$this->assertArrayHasKey('cep_ruleids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['cep_ruleids']);
			$cep_ruleid = $response['result']['cep_ruleids'][0];
		}

		return $cep_ruleid;
	}

	/**
	 * Build a global event correlation rule that closes the problems of one parity. The new event must
	 * carry odd=$parity, and the rule matches old events with service="down" on the same component (tag
	 * pair), then closes both the old and the new problem.
	 *
	 * The condition set depends on $evaltype:
	 *   - CONDITION_EVAL_TYPE_AND_OR: service="down" (old) + odd=$parity (new) + component tag pair.
	 *     A 'type=cep-dep' new-event condition is intentionally omitted — combined with the 'odd'
	 *     new-event condition it would be OR'd (same type) and break parity selectivity. It is not
	 *     needed for correctness: only proto 2 (where "down" is re-sent) produces new events that find
	 *     a matching old problem, so service+parity+component already pinpoint the correlation.
	 *   - CONDITION_EVAL_TYPE_EXPRESSION: additionally AND-s a 'type=cep-dep' new-event condition,
	 *     exercising two same-type NEW_EVENT_TAG_VALUE conditions that only the expression evaltype can
	 *     AND together.
	 */
	private function buildParityCorrelationParams(string $name, string $parity, $evaltype): array {
		$service_down = [
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
			'tag' => 'service',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'down'
		];
		$new_type = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'type',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'cep-dep'
		];
		$new_odd = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'odd',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => $parity
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'component',
			'newtag' => 'component'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$service_down['formulaid'] = 'A';
			$new_type['formulaid'] = 'B';
			$new_odd['formulaid'] = 'C';
			$tag_pair['formulaid'] = 'D';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B and C and D',
				'conditions' => [$service_down, $new_type, $new_odd, $tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$service_down, $new_odd, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build a global event correlation rule whose only condition is an old-event odd=$parity match, with
	 * CLOSE_OLD + CLOSE_NEW operations. There is no component tag pair, so CLOSE_OLD is unrestricted: a
	 * single matching new event closes every open problem of that parity across all components at once
	 * (not just the same component's). Keeping the discriminator on the OLD event preserves parity
	 * selectivity — an odd rule never touches even problems and vice-versa.
	 */
	private function buildParityCloseAllCorrelationParams(string $name, string $parity, $evaltype): array {
		$old_odd = [
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
			'tag' => 'odd',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => $parity
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$old_odd['formulaid'] = 'A';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A',
				'conditions' => [$old_odd]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$old_odd]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build a global event correlation rule that closes an old "down" problem when a new "up" problem
	 * with the same sequence arrives. Unlike the parity/service rules that correlate a re-sent "down",
	 * here the closing event is itself a PROBLEM ("up_N", so the trigger expression must match "up" too):
	 *   - new event state="up"
	 *   - tag pair service=service (the trailing number, so "up_1" pairs with "down_1")
	 * CLOSE_OLD closes the paired "down" problem and CLOSE_NEW closes the "up" problem itself. No old-event
	 * state="down" condition is needed: correlation only matches open problems and every "up" closes itself
	 * via CLOSE_NEW, so the only open problem sharing a given service number is always its "down".
	 *
	 * CONDITION_EVAL_TYPE_OR is a special case: OR-ing the new state="up" condition with the tag pair would
	 * match every open problem (the state="up" condition alone is true for any "up" event), closing them all
	 * at once instead of the paired one. The OR variant therefore keeps only the service tag pair; with a
	 * single condition OR is equivalent to AND, so the 1:1 close-on-up pairing is preserved. The other
	 * evaltypes AND the two conditions (AND_OR because they are of distinct types), giving identical
	 * behaviour.
	 */
	private function buildCloseOnUpCorrelationParams(string $name, $evaltype): array {
		$new_up = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'state',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'up'
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'service',
			'newtag' => 'service'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$new_up['formulaid'] = 'A';
			$tag_pair['formulaid'] = 'B';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B',
				'conditions' => [$new_up, $tag_pair]
			];
		}
		elseif ($evaltype == CONDITION_EVAL_TYPE_OR) {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$new_up, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build the complex event processing (CEP) rule that drives the close-on-up scenario through a tag
	 * correlation time window instead of a global event correlation rule (buildCloseOnUpCorrelationParams()).
	 *
	 * The rule matches the problem events of these scenarios only - its single filter condition requires the
	 * 'state' tag, which only the close-on-up trigger prototypes add - and puts every matched event into a
	 * "Tag correlation" (WINDOW_TAG_MATCH) window keyed on the 'service' tag, so a "down_N" event and the
	 * "up_N" event pairing with it (even when the "up" is raised on another trigger) belong to the same
	 * window. The window is one hour long with no capacity limit, so nothing is evicted during the run.
	 *
	 * The operations are:
	 *   - Execute when "Event occurred": "Close window", restricted to the "up" events by its condition;
	 *   - Execute when "Window closed": "Close".
	 *
	 * The new event is already in the window when the close-window operation runs, so an event closes the
	 * window it just entered and "Window closed" then closes every event that window held. Restricting the
	 * close-window operation to the "up" events is therefore what reproduces the close-on-up behaviour of the
	 * global correlation rule: a "down_N" event only accumulates in the window of its 'service' id, and the
	 * "up_N" event closes that window, closing both the paired "down_N" problem (like CLOSE_OLD) and itself
	 * (like CLOSE_NEW). Without the condition every event would close the window it just entered, so every
	 * problem would be closed the moment it opened.
	 *
	 * The condition comes in two flavours, so both ways of writing it are covered:
	 *   - $tag_exists_condition = false: a tag value comparison on the 'state' tag (state Equals "up");
	 *   - $tag_exists_condition = true: a tag-exists condition on CEP_STATE_TAG_UP, the tag the prototypes
	 *     only produce for "up" events because its name is built from {ITEM.VALUE}.
	 */
	private function buildCloseOnUpCepRuleParams(string $name, bool $tag_exists_condition = false): array {
		$close_window_tag = $tag_exists_condition
			? ['tag' => self::CEP_STATE_TAG_UP, 'operator' => TAG_OPERATOR_EXISTS, 'value' => '']
			: ['tag' => 'state', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'up'];

		return [
			'name' => $name,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'type' => CCepRuleHelper::CONDITION_TAG,
						'operator' => CONDITION_OPERATOR_EXISTS,
						'tag' => 'state',
						'tag_value' => ''
					]
				]
			],
			'window_type' => CCepRuleHelper::WINDOW_TAG_MATCH,
			'window' => [
				'duration' => '1h',
				'capacity' => 0,
				'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
				'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
				'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
				'tags' => ['service']
			],
			'operations' => [
				[
					'sortorder' => 0,
					'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
					'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'tags' => [$close_window_tag]
				],
				[
					'sortorder' => 1,
					'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
					'type' => CCepRuleHelper::OP_CLOSE
				]
			]
		];
	}

	/**
	 * Build a complex event processing (CEP) rule without a window (WINDOW_NONE): the only thing it does is
	 * add the $add_tag:$add_tag_value tag to every event matching its filter, right when the event occurs
	 * (WHEN_EVENT_OCCURRED is the only execution point a windowless rule has). No window is opened and no
	 * problem is closed, so the matched events are left open and merely tagged.
	 *
	 * The filter picks the events of the close-on-up scenario with two conditions AND-ed together
	 * (CONDITION_EVAL_TYPE_AND, so both apply even when both are tag conditions - under
	 * CONDITION_EVAL_TYPE_AND_OR conditions of the same type would be OR-ed instead):
	 *   - 'type' Equals "cep": restricts the rule to the events of the primary discovered trigger prototype.
	 *     Without it the negative flavours ("Does not equal", "Does not contain", "Does not exist") would also
	 *     match every event that has no 'service' tag at all (a comparison against a missing tag is false, and
	 *     the negation makes it true), i.e. the events of every other host and trigger in the suite;
	 *   - $match_condition, the test that singles out the ids this rule tags. The scenario builds one rule per
	 *     operator, with the condition coming from buildWindowNoneServiceCondition() (testing the event tags)
	 *     or buildWindowNoneEventNameCondition() (testing the event name).
	 */
	private function buildWindowNoneAddTagCepRuleParams(string $name, array $match_condition, string $add_tag,
			string $add_tag_value): array {
		return [
			'name' => $name,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND,
				'conditions' => [
					[
						'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'tag' => 'type',
						'tag_value' => 'cep'
					],
					$match_condition
				]
			],
			'window_type' => CCepRuleHelper::WINDOW_NONE,
			// Every rule must be evaluated for every event, so none of them may stop the processing of the
			// rules after it.
			'stop' => CCepRuleHelper::EXECUTION_CONTINUE,
			'operations' => [
				[
					'sortorder' => 0,
					'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
					'type' => CCepRuleHelper::OP_ADD_TAG,
					'tag' => $add_tag,
					'tag_value' => $add_tag_value
				]
			]
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to single out the events of one 'service' id through
	 * the event tags. $service is always a plain id; how it is tested depends on the operator, exactly like in
	 * the CEP rule form:
	 *   - CONDITION_OPERATOR_EXISTS / CONDITION_OPERATOR_NOT_EXISTS test a tag NAME, so they get a
	 *     CONDITION_TAG condition on the per-id 'service_<id>' tag (CEP_SERVICE_TAG resolves the id into the
	 *     tag name at event time);
	 *   - every other operator compares the value of the plain 'service' tag, so it gets a
	 *     CONDITION_TAG_VALUE condition. CONDITION_TAG would not do: it matches on the tag name only (the
	 *     server evaluates it as tag exists / does not exist and never looks at tag_value), which is why the
	 *     form converts a "Tag" condition with a value operator to CONDITION_TAG_VALUE before handing it to
	 *     the API.
	 */
	private function buildWindowNoneServiceCondition(int $operator, string $service): array {
		if (in_array($operator, [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS])) {
			return [
				'type' => CCepRuleHelper::CONDITION_TAG,
				'operator' => $operator,
				'tag' => self::CEP_SERVICE_TAG_PREFIX.$service,
				'tag_value' => ''
			];
		}

		return [
			'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
			'operator' => $operator,
			'tag' => 'service',
			'tag_value' => $service
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to single out events by their NAME rather than by
	 * their tags. The event name of these prototypes is "CEP trigger <component> <item value>", so it carries
	 * the same id the tag conditions test - $event_name is the whole name for the Equals flavours and just the
	 * item value for the Contains ones.
	 */
	private function buildWindowNoneEventNameCondition(int $operator, string $event_name): array {
		return [
			'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
			'operator' => $operator,
			'event_name' => $event_name
		];
	}

	/**
	 * The rule set of the windowless scenario: one rule per operator, keyed by the tag it adds to the events
	 * it matched, holding that rule's filter condition and the operand it tests against. The operand doubles
	 * as the value of the added tag, so a tagged event states both which rule matched it and with which
	 * operand, and the rule is named after its tag (see prepareDataCepWindowNoneTagOperations()).
	 *
	 * This is the single definition of the rule set: prepareDataCepWindowNoneTagOperations() creates the rules
	 * from it and waitForCepWindowNoneTaggedEvents() takes both the expected tag values and the list of tags
	 * no non-matching rule may have added from the very same table.
	 *
	 * The operators come in six opposite pairs - four testing the 'service' id through the event tags, two
	 * testing it through the event name, which ends with the item value - so every event is matched by exactly
	 * one rule of every pair. The name conditions mirror the tag ones: Equals compares the whole event name of
	 * the "down_1" event, Contains only the "down_1" item value inside it.
	 */
	private function getWindowNoneRules(): array {
		$service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$service_next = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
		$event_name = self::CEP_RULE_WINDOW_NONE_EVENT_NAME;
		$value_next = self::CEP_RULE_WINDOW_NONE_VALUE_NEXT;

		return [
			self::CEP_TAG_SERVICE_EQUALS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_EQUALS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_CONTAINS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_LIKE, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_CONTAINS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_LIKE, $service), $service
			],
			self::CEP_TAG_SERVICE_MORE_EQUAL => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_MORE_EQUAL, $service_next),
				$service_next
			],
			self::CEP_TAG_SERVICE_LESS_EQUAL => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_LESS_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_EXISTS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EXISTS, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_EXISTS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_EXISTS, $service), $service
			],
			self::CEP_TAG_EVENT_NAME_EQUALS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_EQUAL, $event_name), $event_name
			],
			self::CEP_TAG_EVENT_NAME_NOT_EQUALS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_NOT_EQUAL, $event_name), $event_name
			],
			self::CEP_TAG_EVENT_NAME_CONTAINS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_LIKE, $value_next), $value_next
			],
			self::CEP_TAG_EVENT_NAME_NOT_CONTAINS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_NOT_LIKE, $value_next), $value_next
			]
		];
	}

	/**
	 * Build a global event correlation rule that only closes new problems (CLOSE_NEW), not old problems.
	 * Useful for testing correlation update behavior where you want to verify that changing the
	 * operations changes the event lifecycle behavior.
	 */
	private function buildCloseNewOnlyCorrelationParams(string $name, $evaltype): array {
		$new_up = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'state',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'up'
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'service',
			'newtag' => 'service'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$new_up['formulaid'] = 'A';
			$tag_pair['formulaid'] = 'B';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B',
				'conditions' => [$new_up, $tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$new_up, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Reconfigure both trigger prototypes for the parity-based global correlation scenario: the same
	 * find(regexp,"down") + multiple-event + global-correlation setup as prepareDataGlobalCorrelation,
	 * but every discovered trigger additionally carries an 'odd' tag whose value is the component
	 * parity ('1'/'0', from the {#PARITY} LLD macro). No correlation rule is created here; the run
	 * method opens problems on all triggers first, then adds the "even" and "odd" rules one at a time.
	 */
	public function prepareDataGlobalCorrelationParity() {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Both prototypes: find(regexp,"down") + multiple event generation + global correlation, plus
		// a 'service'={ITEM.VALUE} tag (so "down" sets service="down"), a stable 'component' tag for
		// the correlation tag pair and an 'odd' tag carrying the component parity.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}'],
				['tag' => 'odd', 'value' => self::PARITY_MACRO]
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}'],
				['tag' => 'odd', 'value' => self::PARITY_MACRO]
			]
		]);

		// Resend LLD discovery data (now including the parity macro) to re-instantiate the discovered
		// triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData(true)
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);

		// Verify the two primary discovered triggers reflect global correlation mode, multiple event
		// generation and that the parity 'odd' tag resolved (component sensor1 → odd index → '1');
		// poll until LLD has re-applied the new prototype config.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
				$odd = current(array_filter($trigger['tags'], fn($t) => $t['tag'] === 'odd'));
				if ($odd === false || $odd['value'] !== '1') {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_NONE, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to global correlation mode.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
			$odd = current(array_filter($trigger['tags'], fn($t) => $t['tag'] === 'odd'));
			$this->assertNotFalse($odd,
				'Discovered trigger '.$trigger['triggerid'].' is missing the parity "odd" tag.');
			$this->assertEquals('1', $odd['value'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected parity tag value.');
		}

		// Start from a clean correlation slate: remove any CEP correlation rules left over from earlier
		// scenarios so that no rule is active while the run method opens the initial problems. The
		// "even" and "odd" rules are then created one at a time during the run.
		$this->deleteCepCorrelations();

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Delete every global correlation rule created by these CEP scenarios (their names all start with
	 * "CEP global event correlation") and reset the tracked correlation ids.
	 */
	private function deleteCepCorrelations(): void {
		$response = $this->call('correlation.get', [
			'output' => ['correlationid'],
			'search' => ['name' => 'CEP global event correlation']
		]);
		$ids = array_column($response['result'], 'correlationid');
		if ($ids) {
			$this->call('correlation.delete', $ids);
		}
		self::$correlationid = null;
		self::$correlationid2 = null;
	}

	/**
	 * Delete every CEP rule (ceprule API) created by these scenarios - their names all start with
	 * CEP_RULE_NAME_PREFIX - and reset the tracked CEP rule id. A leftover rule would keep closing the
	 * problems of the scenarios that run afterwards, so this is also called from clearData() in case a test
	 * aborted before its own teardown ran.
	 */
	private function deleteCepRules(): void {
		$response = $this->call('ceprule.get', [
			'output' => ['cep_ruleid'],
			'search' => ['name' => self::CEP_RULE_NAME_PREFIX]
		]);
		$ids = array_column($response['result'], 'cep_ruleid');
		if ($ids) {
			$this->call('ceprule.delete', $ids);
		}
		self::$cep_ruleid = null;
	}

	/**
	 * Teardown every CEP window scenario must run, even when it failed: the CEP rule closes every problem
	 * carrying a 'state' tag, so it must not survive the test - the global correlation variants that run
	 * afterwards use the same trigger prototypes and would have their problems closed by this rule instead of
	 * by their correlation rule. The configuration cache is reloaded so the server drops the rule right away.
	 */
	private function cleanupCepRules(): void {
		$this->deleteCepRules();
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Send LLD data via the proxy dispatch helper and verify that the item and trigger
	 * prototypes are instantiated for the discovered component.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testPrepareTriggerCEP_LLDDiscovery() {
		// Reload configuration cache before sending discovery data.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send host LLD discovery data so the host prototype creates the discovered host
		// with the template linked.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					['{#HOST}' => self::HOST_DISC_VALUE]
				]])
			]
		]);

		// Wait for the discovered host to be created by the server.
		$response = $this->callUntilDataIsPresent('host.get', [
			'filter' => ['host' => self::HOST_DISC_VALUE],
			'output' => ['hostid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		$this->assertCount(1, $response['result'], 'Discovered host was not created by host prototype.');
		self::$disc_hostid = $response['result'][0]['hostid'];

		// Wait for the inherited LLD rule to be created on the discovered host.
		$this->callUntilDataIsPresent('discoveryrule.get', [
			'hostids' => [self::$disc_hostid],
			'filter' => ['key_' => self::LLD_RULE_KEY],
			'output' => ['itemid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Reload config so the server is aware of the discovered host's inherited LLD rule.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send item LLD discovery data to the discovered host's LLD rule (inherited from template).
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify all LLD_DISCOVERY_COUNT items from proto1 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'name', 'key_']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT triggers were created and store the primary one.
		$expected_description = 'CEP trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered triggers were created.');

		$primary_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_description
		));
		$this->assertNotFalse($primary_trigger, 'Primary discovered trigger not found.');
		self::$discovered_triggerid = $primary_trigger['triggerid'];
		self::$discovered_triggerids = array_column($response['result'], 'triggerid');

		$tags = $primary_trigger['tags'];
		$tags_json = json_encode($tags);
		$tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $tags);
		$this->assertCount(4, $tags_tv, 'Discovered trigger must have 4 tags, got: '.$tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep'], $tags_tv,
			'Tag type=cep not found in: '.$tags_json);
		$this->assertContains(['tag' => self::SERVICE_TAG, 'value' => self::COMPONENT_VALUE], $tags_tv,
			'Tag '.self::SERVICE_TAG.'='.self::COMPONENT_VALUE.' not found in: '.$tags_json);
		$this->assertContains(['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}'], $tags_tv,
			'Tag service=regsub not found in: '.$tags_json);

		// Verify all LLD_DISCOVERY_COUNT items from proto2 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY2.'['],
			'output' => ['itemid', 'name', 'key_']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all second discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT dependent triggers were created and store the primary one.
		$expected_dep_description = 'CEP dependent trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP dependent trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered dependent triggers were created.');

		$primary_dep_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_dep_description
		));
		$this->assertNotFalse($primary_dep_trigger, 'Primary discovered dependent trigger not found.');
		self::$discovered_dep_triggerid = $primary_dep_trigger['triggerid'];
		self::$discovered_dep_triggerids = array_column($response['result'], 'triggerid');

		$dep_tags = $primary_dep_trigger['tags'];
		$dep_tags_json = json_encode($dep_tags);
		$dep_tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $dep_tags);
		$this->assertCount(3, $dep_tags_tv, 'Discovered dependent trigger must have 3 tags, got: '.$dep_tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $dep_tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$dep_tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep-dep'], $dep_tags_tv,
			'Tag type=cep-dep not found in: '.$dep_tags_json);
		$this->assertContains(['tag' => self::SERVICE_TAG, 'value' => self::COMPONENT_VALUE], $dep_tags_tv,
			'Tag '.self::SERVICE_TAG.'='.self::COMPONENT_VALUE.' not found in: '.$dep_tags_json);

		// Discover the single log trigger up front (reloads the configuration cache before and after) so
		// the server is aware of all newly discovered items and the log event tests can drive it directly.
		$this->discoverLogTrigger();
	}

	/**
	 * Sanity check: send a numeric value of 0 to all discovered items and verify the values were
	 * written to the history cache (zabbix[vps,written] advanced), without asserting any trigger
	 * state or event changes.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_VpsWritten() {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);
		$this->assertVpsWrittenIncreasedBy($vps_written, count($keys));
	}

	/**
	 * Smoke test (part 1/2): a discovered trigger opens a problem on OK→PROBLEM.
	 * The problem is left open and closed by testTriggerCEP_CloseProblem.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenProblem() {
		$this->runOpenProblemTest(false);
	}

	/**
	 * Smoke test (part 1.5/2): re-send the problem value while the single-event triggers are already in
	 * PROBLEM. No new event is generated so the trigger state does not change, and CEP does not process
	 * the already-open problem events. Runs between open and close so the problem is still open.
	 *
	 * @depends testTriggerCEP_OpenProblem
	 */
	public function testTriggerCEP_OpenAlreadyOpenedProblem() {
		$this->runOpenAlreadyOpenedProblemTest(false);
	}

	/**
	 * Smoke test (part 2/2): the problem opened by testTriggerCEP_OpenProblem closes on PROBLEM→OK.
	 * Runs as a separate test so the open problem persists across the test boundary before recovery.
	 *
	 * @depends testTriggerCEP_OpenAlreadyOpenedProblem
	 */
	public function testTriggerCEP_CloseProblem() {
		$this->runCloseProblemTest(false);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenProblem but the server component is stopped and restarted first,
	 * to verify the problem opens and is cached correctly after a fresh restart. Depends on the non-restart
	 * close so it starts from the recovered state.
	 *
	 * @depends testTriggerCEP_CloseProblem
	 */
	public function testTriggerCEP_OpenProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenProblemTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenAlreadyOpenedProblem but the server component is stopped and
	 * restarted first, to verify a re-sent problem value generates no new event after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenProblemRestart
	 */
	public function testTriggerCEP_OpenAlreadyOpenedProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenAlreadyOpenedProblemTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_CloseProblem but the server component is stopped and restarted first,
	 * to verify recovery and cache draining after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenAlreadyOpenedProblemRestart
	 */
	public function testTriggerCEP_CloseProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runCloseProblemTest(true);
	}

	/**
	 * Open a problem on OK→PROBLEM (one PROBLEM event per trigger) and verify CEP processed and cached it.
	 * When $restart is true, the server is restarted first.
	 */
	private function runOpenProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// OK→PROBLEM: one PROBLEM event per trigger; trigger value goes TRUE.
		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 1);

		// CEP processed the opened problem events (one per trigger).
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));

		// CEP cached one event per opened problem (one per trigger).
		$this->assertCepStatEquals('tasks', 'cached_events', count($keys));
		$this->assertCepStatEquals('tasks', 'cached_objects', count($keys));
	}

	/**
	 * Re-send the problem value while the single-event triggers are already in PROBLEM and verify no new
	 * event is generated. When $restart is true, the server is restarted first.
	 */
	private function runOpenAlreadyOpenedProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// PROBLEM→PROBLEM: no new event (single-event triggers), trigger value/lastchange unchanged.
		// assertNoStateChangeForAll also verifies CEP did not process the already-open problem events.
		$this->captureEventBaseline($triggerids);
		$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 0);
	}

	/**
	 * Close the open problem on PROBLEM→OK (one RESOLVED event per trigger) and verify CEP processed it and
	 * drained its cache. When $restart is true, the server is restarted first.
	 */
	private function runCloseProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// PROBLEM→OK: one RESOLVED event per trigger. The baseline is per-test-instance, so it is
		// recaptured here (now past the open event) and the close adds exactly one more event.
		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, 1);
		$this->waitForNoOpenProblems($triggerids, 'close problem');

		// CEP processed the recovered events (one per trigger).
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));
		$this->assertCepStatEquals('tasks', 'cached_events', 0);
		$this->assertCepStatEquals('tasks', 'cached_objects', 0);
	}

	/**
	 * Create one service per discovered trigger (each mapped to its trigger via the stable SERVICE_TAG
	 * problem tag), a service action and a trigger action, all routed through the CEP webhook media type.
	 * Runs after the minimal open/close smoke tests so the same OK→PROBLEM→OK scenario can be repeated
	 * with the services and actions in place. The services and actions are removed in clearData().
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_AddServices() {
		$this->skipIfServicesTestsDisabled();
		$this->createServicesAndActions();
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoverySingleItem but also verifies the per-trigger service that
	 * createServicesAndActions() already created for the driven trigger (matched to it only by that trigger's
	 * own SERVICE_TAG tag). After the long rapid PROBLEM/recovery burst the service must have tracked every
	 * cycle by the trigger tag and ended OK with no open service problem, and a final explicit open then
	 * verifies the service manager matches the problem to the service purely by the trigger tag - it reaches
	 * the trigger's DISASTER priority with exactly one open service problem (no duplicate cached during the
	 * burst) - before following the close back to OK. Skipped entirely when the per-trigger services do not
	 * exist (service tests skipped), as there would be nothing to verify by trigger tag.
	 * (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoverySingleItemWithService)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoverySingleItemWithService() {
		if (empty(self::$serviceids)) {
			$this->markTestSkipped('No CEP services created (service tests skipped); nothing to match by trigger tag.');
		}

		$this->runOpenAndImmediateRecoverySingleItemTest(false, true);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoveryValueWaves but also verifies the per-trigger services that
	 * createServicesAndActions() already created for the driven triggers (each matched to its trigger only by
	 * that trigger's own SERVICE_TAG tag). The batch ends on a 1 wave with every trigger in PROBLEM, so every
	 * service must have followed the interleaved cross-item waves and reached the trigger's DISASTER priority
	 * with exactly one open service problem (no duplicate cached during the waves - a regression guard for the
	 * service manager matching the same event to a service more than once), and the closing 0 wave must then
	 * follow every service back to OK with no open service problem. Skipped entirely when the per-trigger
	 * services do not exist (service tests skipped), as there would be nothing to verify by trigger tag.
	 * (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWavesWithService)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWavesWithService() {
		if (empty(self::$serviceids)) {
			$this->markTestSkipped('No CEP services created (service tests skipped); nothing to match by trigger tag.');
		}

		$this->runOpenAndImmediateRecoveryValueWavesTest(false, true);
	}

	/**
	 * Repeat of testTriggerCEP_OpenProblem with the services and actions in place: the trigger opens a
	 * problem and every per-trigger service follows it to PROBLEM (disaster) with one open service problem.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenProblemWithServices() {
		$this->runOpenProblemWithServicesTest(false);
	}

	/**
	 * Repeat of testTriggerCEP_CloseProblem with the services and actions in place: the problem closes
	 * and every per-trigger service recovers to OK with no open service problems.
	 *
	 * @depends testTriggerCEP_OpenProblemWithServices
	 */
	public function testTriggerCEP_CloseProblemWithServices() {
		$this->runCloseProblemWithServicesTest(false);
	}

	/**
	 * Open a problem and send the recovery immediately afterwards, without waiting for the problem to be
	 * confirmed first, and verify CEP still generates both events per trigger: one PROBLEM event followed
	 * by one RESOLVED event. Guards against the open and close collapsing into a single event (or the
	 * problem being dropped) when they arrive back-to-back.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecovery() {
		$this->runOpenAndImmediateRecoveryTest(false);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenProblemWithServices but the server component is stopped and
	 * restarted first, to verify the problem opens and the per-trigger services follow it to PROBLEM
	 * after a fresh restart. Depends on the non-restart close so it starts from the recovered state.
	 *
	 * @depends testTriggerCEP_CloseProblemWithServices
	 */
	public function testTriggerCEP_OpenProblemWithServicesRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenProblemWithServicesTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_CloseProblemWithServices but the server component is stopped and
	 * restarted first, to verify recovery and service status after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenProblemWithServicesRestart
	 */
	public function testTriggerCEP_CloseProblemWithServicesRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runCloseProblemWithServicesTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenAndImmediateRecovery but the server component is stopped and
	 * restarted first, to verify CEP still emits one event per transition after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenAndImmediateRecovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenAndImmediateRecoveryTest(true);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the rapid burst also flips the item to an
	 * unsupported state mid-sequence in several combinations (problem→unsupported→recover,
	 * unsupported→problem→recover, problem→unsupported→problem→recover, and unsupported while already OK).
	 * The unsupported value sends the trigger to UNKNOWN without changing its value, so it must emit no
	 * trigger event; CEP must still emit exactly one event per real value transition without collapsing or
	 * dropping any when the transitions and the unsupported state arrive back-to-back.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryUnsupported() {
		$this->runOpenAndImmediateRecoveryUnsupportedTest(false);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the whole burst lands on a single discovered item
	 * (and its one trigger) instead of being spread across every discovered item, cycling PROBLEM → recover
	 * (1, 0) a large number of times. Verifies CEP emits exactly one event per transition on that single
	 * event stream under a long rapid burst, without collapsing or dropping any.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoverySingleItem() {
		$this->runOpenAndImmediateRecoverySingleItemTest(false);
	}


	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the single batch is grouped by value ("waves")
	 * instead of by key: every discovered item first gets 0 (the triggers are already OK, so this wave
	 * must emit no events), then every item gets 1 (every trigger opens) and finally every item gets 0
	 * again (every trigger recovers). Verifies CEP handles the cross-item interleaved ordering, emitting
	 * exactly one PROBLEM and one RESOLVED event per trigger and leaving no open problems.
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWaves)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWaves() {
		$this->runOpenAndImmediateRecoveryValueWavesTest(false);
	}


	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoveryValueWaves but with an even number of waves (1, 0, 1, 0),
	 * so the single batch itself ends on a recovery wave: every trigger opens twice and recovers twice
	 * within the batch and no separate closing wave is needed. Verifies CEP handles the cross-item
	 * interleaved ordering when the batch ends on a recovery, emitting exactly one PROBLEM and one
	 * RESOLVED event per trigger per cycle and leaving no open problems.
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWavesEndOk)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWavesEndOk() {
		$this->runOpenAndImmediateRecoveryValueWavesEndOkTest(false);
	}

	/**
	 * Open the problem and let every per-trigger service follow it to PROBLEM (disaster). When $restart is
	 * true, the server is restarted first.
	 */
	private function runOpenProblemWithServicesTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 1);

		// Each per-trigger service goes to PROBLEM (disaster) with one open service problem.
		$this->assertServicesStatus(TRIGGER_SEVERITY_DISASTER, count(self::$serviceids));

		// And each service holds exactly one open service problem — no duplicates (regression guard for the
		// service manager matching the same event to a service more than once).
		$this->assertOneServiceProblemPerService();
	}

	/**
	 * Close the problem and let every per-trigger service recover to OK with no open service problems.
	 * When $restart is true, the server is restarted first.
	 */
	private function runCloseProblemWithServicesTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, 1);
		$this->waitForNoOpenProblems($triggerids, 'close problem with services');

		// All services recover to OK with no open service problems.
		$this->assertServicesStatus(ZBX_SEVERITY_OK, 0);

		// This is the last services-specific test (the restart sibling is either the true last run or is
		// skipped), so disable the service/trigger actions: they must not fire on the events generated by the
		// later, non-services scenarios. The services and the actions are removed entirely in clearData().
		if ($restart || static::SKIP_RESTART_TESTS) {
			$this->disableServicesActions();
		}
	}

	/**
	 * Disable the service action created by createServicesAndActions() so it no longer fires on the events
	 * of later (non-services) scenarios. The trigger action is left enabled so it keeps firing on the
	 * discovered triggers in the later scenarios. Idempotent and guarded, so it is safe if the action was
	 * never created. Both actions are deleted in clearData().
	 */
	private function disableServicesActions(): void {
		if (!empty(self::$service_actionid)) {
			$this->call('action.update', [
				'actionid' => self::$service_actionid,
				'status' => ACTION_STATUS_DISABLED
			]);
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Open a problem and send the recovery immediately afterwards, verifying CEP emits one PROBLEM event
	 * followed by one RESOLVED event per trigger. When $restart is true, the server is restarted first.
	 */
	private function runOpenAndImmediateRecoveryTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		// Build a long alternating PROBLEM/recovery burst (1,0,1,0,...) for every discovered item and send
		// it in a single batch. Every value gets a strictly increasing (clock, ns) so CEP must process the
		// whole rapid burst in order and emit one event per transition without collapsing or dropping any.
		// The sequence ends on 0 so the triggers finish OK.
		$values = [];
		for ($i = 0; $i < 3; $i++) {
			$values[] = '1';
			$values[] = '0';
		}

		$data = [];
		foreach ($keys as $key) {
			foreach ($values as $value) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$this->dispatchSenderValues($data);

		$expected_events = count($values);

		// Each trigger must produce one event per transition: PROBLEM, RESOLVED, PROBLEM, RESOLVED, ...
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM, ... (the burst
		// ends on a recovery, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery');
	}

	/**
	 * Open/recover burst that also flips the item to an unsupported state mid-cycle (1, unsupported, 0),
	 * verifying CEP emits exactly one event per real value transition: the unsupported value changes only
	 * the item state (trigger goes UNKNOWN), not the trigger value, so it emits no event. When $restart is
	 * true, the server is restarted first.
	 */
	private function runOpenAndImmediateRecoveryUnsupportedTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		$unsupported = 'not_a_number';
		$values = [];
		for ($i = 0; $i < 3; $i++) {
			$values[] = $unsupported;
			$values[] = '0';
		}

		$data = [];
		foreach ($keys as $key) {
			foreach ($values as $value) {
				$entry = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
				// The server skips preprocessing for proxy-delivered values, so the unsupported transition
				// must be reported explicitly rather than relying on the non-numeric value failing.
				if ($value === $unsupported) {
					$entry['state'] = ITEM_STATE_NOTSUPPORTED;
				}
				$data[] = $entry;
			}
		}
		$this->dispatchSenderValues($data);

		$expected_values = [];
		$current = TRIGGER_VALUE_FALSE;
		foreach ($values as $value) {
			if ($value === $unsupported) {
				continue;
			}
			$new = ($value === '0') ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			if ($new !== $current) {
				$expected_values[] = $new;
				$current = $new;
			}
		}
		$this->assertEquals(TRIGGER_VALUE_FALSE, $current, 'burst must leave the triggers OK');

		$expected_events = count($expected_values);

		// Each trigger must produce one event per value transition.
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// Events are returned newest-first, so compare against the reversed expected sequence.
		$expected_newest_first = array_reverse($expected_values);
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$this->assertEquals($expected_newest_first[$pos], (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery unsupported');
	}

	private function getDiscoveredItemid(string $host, string $key): int {
		$this->ensureItemidsResolved([['host' => $host, 'key' => $key]]);
		return self::$itemid_cache[$host."\0".$key];
	}

	private function getTriggeridForKey(string $host, string $key): string {
		$itemid = $this->getDiscoveredItemid($host, $key);
		$response = $this->call('trigger.get', [
			'itemids' => [$itemid],
			'output' => ['triggerid']
		]);
		$this->assertCount(1, $response['result'],
			'Expected exactly one trigger for item on key '.$key.', got: '.json_encode($response['result']));
		return $response['result'][0]['triggerid'];
	}

	/**
	 * Same as runOpenAndImmediateRecoveryTest but the whole burst lands on a single discovered item (and
	 * its one trigger), cycling PROBLEM → recover (1, 0) a large number of times, to stress CEP with a long
	 * rapid back-to-back burst on one event stream. When $restart is true, the server is restarted first.
	 *
	 * When $with_service is true the per-trigger service that createServicesAndActions() already created for
	 * this trigger (matched to it only by the trigger's own SERVICE_TAG tag) is verified as well: after the
	 * burst the service must have tracked every cycle by the trigger tag and ended OK with no open service
	 * problem, and a final explicit open then verifies the service manager matches the problem to the service
	 * purely by that trigger tag - it reaches the trigger's DISASTER priority with exactly one open service
	 * problem (no duplicate cached during the burst) - before following the close back to OK. The service
	 * checks are skipped when the per-trigger services do not exist (service tests skipped).
	 */
	private function runOpenAndImmediateRecoverySingleItemTest(bool $restart, bool $with_service = false): void {
		$this->maybeRestartServer($restart);

		// Drive a single discovered item (and its one trigger) so the whole burst lands on one event
		// stream rather than being spread across every discovered item.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);

		// When requested, reuse the per-trigger service createServicesAndActions() already created for this
		// trigger (matched to it only by the trigger's own SERVICE_TAG tag). Null when those services do not
		// exist (service tests skipped), in which case the service checks below are skipped.
		$serviceid = $with_service ? $this->getServiceidForTrigger($triggerid) : null;

		// Baseline the service's own events (source SERVICE) before the burst, so the post-burst count is a
		// delta: the shared per-trigger service has accumulated events from earlier scenarios.
		$service_event_baseline = ($serviceid !== null) ? $this->captureServiceEventBaseline($serviceid) : 0;

		$this->captureEventBaseline([$triggerid]);

		// Build a long alternating PROBLEM/recovery burst (1,0,1,0,...) and send it in a single batch. Every
		// value gets a strictly increasing (clock, ns) so CEP must process the whole rapid burst in order
		// and emit one event per transition without collapsing or dropping any. The sequence ends on 0 so
		// the trigger finishes OK.
		$cycles = static::RECOVERY_CYCLES_COUNT;
		$values = [];
		for ($i = 0; $i < $cycles; $i++) {
			$values[] = '1';
			$values[] = '0';
		}

		$data = [];
		foreach ($values as $value) {
			$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
		}
		$vps_written = $this->getVpsWritten();
		$sent = $this->dispatchSenderValues($data);

		// Confirm the whole burst was ingested (written to the history cache) before asserting on events,
		// so a dropped or not-yet-processed value surfaces here rather than as a confusing event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($values));

		$expected_events = count($values);

		// The trigger must produce one event per transition: PROBLEM, RESOLVED, PROBLEM, RESOLVED, ... . Every
		// value flips the trigger, so each sent (clock, ns) must appear as exactly one event. If the count is
		// still off on the final wait iteration, the info callback diagnoses which sent offset/timestamp never
		// produced an event and appends it to the failure message.
		$this->waitForAllTriggerEventCounts([$triggerid], $expected_events,
			function () use ($triggerid, $sent) {
				return $this->diagnoseMissingBurstEvents($triggerid, $sent);
			}
		);

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM, ... (the burst
		// ends on a recovery, so the newest event is RESOLVED).
		$events = $this->getScenarioEventsByTrigger([$triggerid])[$triggerid];
		$info = 'trigger '.$triggerid.': '.count($events).' events';
		$this->assertCount($expected_events, $events, $info);
		foreach ($events as $pos => $event) {
			$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
		}

		$this->waitForNoOpenProblems([$triggerid], 'open and immediate recovery single item');

		if ($serviceid !== null) {
			// The burst ended on a recovery, so the trigger is OK and the service - matched only by the
			// trigger tag - must have followed every cycle and be OK too, with no open service problem left
			// over from the long rapid burst.
			$this->assertSingleServiceStatus($serviceid, ZBX_SEVERITY_OK, 0);

			// The service is matched to every trigger problem by the trigger tag, so the burst must have
			// driven exactly as many service events (one service PROBLEM per trigger PROBLEM, one service
			// RESOLVED per trigger RESOLVED) as the trigger did - the same expected_events. More means the
			// service manager cached a duplicated service problem; fewer means one was dropped or collapsed.
			$this->waitForServiceEventCount($serviceid, $service_event_baseline, $expected_events);

			// Now open one final problem and verify the service manager matches it to the service purely by
			// the trigger tag: the service reaches the trigger's DISASTER priority with exactly one open
			// service problem (a regression guard against a duplicated service problem being cached by the
			// service manager during the long burst).
			$this->dispatchSenderValues([['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1']]);
			$this->waitForOpenProblemCount([$triggerid], 1);
			$this->assertSingleServiceStatus($serviceid, TRIGGER_SEVERITY_DISASTER, 1);

			// Close it again: the service follows the recovery back to OK with no open service problem.
			$this->dispatchSenderValues([['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0']]);
			$this->assertSingleServiceStatus($serviceid, ZBX_SEVERITY_OK, 0);
			$this->waitForNoOpenProblems([$triggerid],
				'open and immediate recovery single item with service (final close)');
		}
	}

	/**
	 * Return the CEP service that createServicesAndActions() created for $triggerid (matched to it by the
	 * trigger's own SERVICE_TAG tag), or null when the per-trigger services do not exist (service tests
	 * skipped). The service is looked up by the trigger's SERVICE_TAG value, which is the same component
	 * value the service's problem tag matches on (see getServiceIdsByComponent).
	 */
	private function getServiceidForTrigger(string $triggerid): ?string {
		$service_by_component = $this->getServiceIdsByComponent();
		if (empty($service_by_component)) {
			return null;
		}

		$response = $this->call('trigger.get', [
			'triggerids' => [$triggerid],
			'output' => ['triggerid'],
			'selectTags' => 'extend'
		]);
		$this->assertCount(1, $response['result'], 'Expected exactly one trigger '.$triggerid.'.');

		$service_tag = current(array_filter($response['result'][0]['tags'],
			fn($t) => $t['tag'] === self::SERVICE_TAG
		));
		$this->assertNotFalse($service_tag,
			'Trigger '.$triggerid.' has no '.self::SERVICE_TAG.' tag.');

		$this->assertArrayHasKey($service_tag['value'], $service_by_component,
			'No CEP service matches trigger '.$triggerid.' by its '.self::SERVICE_TAG.' value '
				.$service_tag['value'].'.');

		return $service_by_component[$service_tag['value']];
	}

	/**
	 * Capture the highest eventid currently recorded for the service $serviceid's own events (source
	 * SERVICE), so a later count is a delta relative to this point. The per-trigger services are shared
	 * across the suite, so a service has accumulated events from earlier scenarios.
	 */
	private function captureServiceEventBaseline(string $serviceid): int {
		$response = $this->call('event.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		return empty($response['result']) ? 0 : (int) $response['result'][0]['eventid'];
	}

	/**
	 * Wait until exactly $expected service events (source SERVICE) have been recorded for $serviceid since
	 * $baseline_id. The service is matched to every trigger problem by the trigger tag, so a burst that
	 * flips the trigger $expected times must drive exactly $expected service events; more means the service
	 * manager cached a duplicated service problem, fewer means one was dropped or collapsed.
	 */
	private function waitForServiceEventCount(string $serviceid, int $baseline_id, int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'eventid_from' => $baseline_id + 1
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll until the single service $serviceid reaches $expected_status (a ZBX_SEVERITY_* value, or
	 * ZBX_SEVERITY_OK once recovered) and holds exactly $expected_open_problems open service problems. The
	 * per-service problem count is a regression guard against the service manager adding the same event to a
	 * service more than once. Mirrors assertServicesStatus() but scoped to one service.
	 */
	private function assertSingleServiceStatus(string $serviceid, int $expected_status,
			int $expected_open_problems): void {
		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => [$serviceid],
			'filter' => ['status' => $expected_status]
		], 1, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE
		], $expected_open_problems, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Same as runOpenAndImmediateRecoveryTest but the batch is grouped by value instead of by key:
	 * alternating waves of 1, 0, 1, each wave sent to every discovered item, all in one batch with
	 * strictly increasing (clock, ns). Each 1 wave opens one problem per trigger and the 0 wave
	 * recovers it, so the batch ends with every trigger in PROBLEM. Once the batch is fully processed,
	 * a separate closing 0 wave is sent to recover the open problems. So unlike the per-key bursts,
	 * each trigger's transitions are separated by values for every other item, and CEP must still emit
	 * exactly one event per transition and leave no open problems. When $restart is true, the server
	 * is restarted first.
	 *
	 * When $with_service is true the per-trigger services createServicesAndActions() already created (each
	 * matched to its trigger only by the trigger's own SERVICE_TAG tag) are verified as well: the batch ends
	 * on a 1 wave with every trigger in PROBLEM, so every service must have followed the interleaved waves and
	 * reached the trigger's DISASTER priority with exactly one open service problem (no duplicate cached during
	 * the waves), and after the closing 0 wave every service must be back to OK with no open service problem.
	 * One representative service is additionally checked to have recorded exactly one service event per trigger
	 * transition (no service event collapsed or duplicated during the interleaved waves).
	 */
	private function runOpenAndImmediateRecoveryValueWavesTest(bool $restart, bool $with_service = false): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		// When verifying services, pick one representative discovered trigger and baseline its service's own
		// events (source SERVICE) before the waves, so the post-wave count is a delta: the shared per-trigger
		// services have accumulated events from earlier scenarios.
		$serviceid = $with_service ? $this->getServiceidForTrigger($triggerids[0]) : null;
		$service_event_baseline = ($serviceid !== null) ? $this->captureServiceEventBaseline($serviceid) : 0;

		$data = [];
		foreach (['1', '0', '1'] as $value) {
			foreach ($keys as $key) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);

		// Confirm the whole batch was ingested (written to the history cache) before asserting on
		// events, so a dropped or not-yet-processed value surfaces here rather than as a confusing
		// event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// Every wave flips each trigger, so the batch produces exactly three events per trigger:
		// PROBLEM, RESOLVED, PROBLEM. Waiting for the exact count also ensures the whole batch is
		// processed before the closing 0 wave is sent.
		$this->waitForAllTriggerEventCounts($triggerids, 3);

		// The batch ends on a 1 wave, so every trigger must be left in PROBLEM.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_TRUE, 'must be PROBLEM after the batch');

		if ($with_service) {
			// Every trigger is in PROBLEM, so every per-trigger service - matched to its trigger only by the
			// trigger tag - must have followed the interleaved cross-item waves and reached the trigger's
			// DISASTER priority with exactly one open service problem. More means the service manager cached a
			// duplicated service problem during the waves; fewer means one was dropped or collapsed.
			$this->assertServicesStatus(TRIGGER_SEVERITY_DISASTER, count(self::$serviceids));
			$this->assertOneServiceProblemPerService();
		}

		// Send the closing 0 wave separately, after the batch has been fully processed, to recover
		// the problems left open by the batch's final 1 wave.
		$data = [];
		foreach ($keys as $key) {
			$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'];
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// The closing wave adds one RESOLVED event per trigger: PROBLEM, RESOLVED, PROBLEM, RESOLVED
		// in total. The wait requires an exact total, so a collapsed or extra event fails it too.
		$expected_events = 4;
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// The closing 0 wave must have recovered every trigger back to OK.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_FALSE, 'must be OK after the closing 0 wave');

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM (the batch
		// ends on a 0 wave, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery value waves');

		if ($with_service) {
			// The closing 0 wave recovered every trigger, so every per-trigger service must have followed the
			// recovery back to OK with no open service problem left over from the waves.
			$this->assertServicesStatus(ZBX_SEVERITY_OK, 0);

			// The representative service is matched to its one trigger by the trigger tag, so the four trigger
			// events (PROBLEM, RESOLVED, PROBLEM, RESOLVED) must have driven exactly four service events on it -
			// one service PROBLEM per trigger PROBLEM and one service RESOLVED per trigger RESOLVED. More means
			// the service manager cached a duplicated service problem during the interleaved cross-item waves;
			// fewer means one was dropped or collapsed.
			$this->waitForServiceEventCount($serviceid, $service_event_baseline, $expected_events);
		}
	}

	/**
	 * Same as runOpenAndImmediateRecoveryValueWavesTest but with an even number of waves (1, 0, 1, 0),
	 * all in one batch with strictly increasing (clock, ns). The batch itself ends on a 0 wave, so it
	 * leaves every trigger OK and no separate closing wave is needed. When $restart is true, the server
	 * is restarted first.
	 */
	private function runOpenAndImmediateRecoveryValueWavesEndOkTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		$data = [];
		foreach (['1', '0', '1', '0'] as $value) {
			foreach ($keys as $key) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);

		// Confirm the whole batch was ingested (written to the history cache) before asserting on
		// events, so a dropped or not-yet-processed value surfaces here rather than as a confusing
		// event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// Every wave flips each trigger, so the batch produces exactly four events per trigger:
		// PROBLEM, RESOLVED, PROBLEM, RESOLVED. The wait requires an exact total, so a collapsed or
		// extra event fails it too.
		$expected_events = 4;
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// The batch ends on a 0 wave, so every trigger must be left OK.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_FALSE, 'must be OK after the batch');

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM (the batch
		// ends on a 0 wave, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery value waves end OK');
	}

	/**
	 * Smoke test (part 1/2): a discovered item becomes unsupported and its trigger enters the UNKNOWN
	 * state. The internal "Report unknown triggers" and "Report not supported items" actions are active for
	 * this test (enabled by runOpenUnknownTest() when SCOPED_INTERNAL_ACTIONS is set, otherwise enabled for
	 * the whole suite in prepareData()), so the server opens an internal problem for every unsupported item
	 * and every unknown trigger. The trigger value stays OK (it was not in a problem) while the state
	 * becomes UNKNOWN; the UNKNOWN state and the open internal problems are left in place and cleared by
	 * testTriggerCEP_CloseUnknown.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenUnknown() {
		$this->runOpenUnknownTest();
	}

	/**
	 * Smoke test (part 2/2): the UNKNOWN state entered by testTriggerCEP_OpenUnknown clears when a
	 * numeric value is sent again. The triggers return to NORMAL/OK, the items become supported, and
	 * all internal problems (item-not-supported and trigger-unknown) are resolved. Runs as a separate
	 * test so the UNKNOWN state persists across the test boundary before recovery.
	 *
	 * @depends testTriggerCEP_OpenUnknown
	 */
	public function testTriggerCEP_CloseUnknown() {
		$this->runCloseUnknownTest();
	}

	/**
	 * Same scenario as testTriggerCEP_OpenUnknown but the server component is stopped and restarted
	 * before the test runs, to verify the UNKNOWN state and internal problems are produced correctly
	 * after a fresh restart.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenUnknownRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->runOpenUnknownTest();
	}

	/**
	 * Same scenario as testTriggerCEP_CloseUnknown but the server component is stopped and restarted
	 * before the test runs, to verify recovery and internal-problem resolution after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenUnknownRestart
	 */
	public function testTriggerCEP_CloseUnknownRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->runCloseUnknownTest();
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify CEP behaviour on the discovered trigger:
	 *
	 *   1. Send value 1        → trigger fires   (NORMAL / PROBLEM)
	 *   2. Send non-numeric    → item unsupported (UNKNOWN / PROBLEM)
	 *   3. Send value 0        → trigger recovers (NORMAL / OK)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 *
	 */
	public function testTriggerCEP_TriggerStateTransitions() {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Fire all triggers by sending a numeric value of 1 to all discovered items.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1'], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_TRUE);

		// Push a non-numeric value to flip all items into unsupported state.
		// CEP must keep all trigger values as PROBLEM while state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);

		// Recover all triggers by sending a numeric value of 0 to all discovered items.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);
	}

	/**
	 * Assess trigger event generation for all five state transitions:
	 *
	 *   1. Trigger A: OK→OK              – no new event, lastchange not updated
	 *   2. Trigger A: OK→PROBLEM         – PROBLEM event generated
	 *   3. Trigger A: item unsupported   – CEP keeps trigger PROBLEM, state becomes UNKNOWN
	 *   4. Trigger A: PROBLEM→PROBLEM    – item supported again; no new event, lastchange not updated
	 *   5. Trigger A: PROBLEM→OK         – RESOLVED event generated
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessment() {
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessment but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testTriggerCEP_EventAssessment
	 */
	public function testTriggerCEP_EventAssessmentRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify all meaningful parent→dependent state-transition combinations:
	 *
	 *   1. Parent OK→PROBLEM              – parent fires; PROBLEM event.
	 *   2. Dep condition met while parent PROBLEM – dep suppressed; no event, stays OK.
	 *   3. Parent PROBLEM→OK              – parent resolves; dep (condition still met) fires.
	 *   4. Dep PROBLEM→OK                 – dep recovers.
	 *
	 *   5. Parent OK→PROBLEM              – dep condition never becomes true; dep stays OK.
	 *   6. Parent PROBLEM→OK              – dep condition still false; dep produced no events.
	 *
	 *   7. Dep OK→PROBLEM                 – dep fires normally while parent is OK.
	 *   8. Parent OK→PROBLEM              – parent fires; dep already PROBLEM.
	 *   9. Dep PROBLEM→OK while parent PROBLEM – recovery suppressed; dep stays PROBLEM.
	 *  10. Parent PROBLEM→OK              – parent resolves; dep already OK.
	 *  Filter example: (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_DependentTrigger)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_DependentTrigger() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_DependentTrigger but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_DependentTriggerRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Assess trigger event generation for all five state transitions with "None" recovery mode:
	 *
	 *   1. Trigger A: OK→OK              – no new event, lastchange not updated
	 *   2. Trigger A: OK→PROBLEM         – PROBLEM event generated
	 *   3. Trigger A: item unsupported   – CEP keeps trigger PROBLEM, state becomes UNKNOWN
	 *   4. Trigger A: PROBLEM→PROBLEM    – item supported again; no new event, lastchange not updated
	 *   5. Trigger A: PROBLEM→OK         – trigger stays PROBLEM (None recovery), no event
	 *
	 * @depends testTriggerCEP_TriggerStateTransitions
	 */
	public function testTriggerCEP_EventAssessmentNone() {
		$this->prepareDataNoneOkEvent();
		$this->runEventAssessmentTest(false);
		$this->prepareDataRestoreRecovery();
		$this->assertRecoveryAfterRestore(false);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentNone but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testTriggerCEP_EventAssessmentNone
	 */
	public function testTriggerCEP_EventAssessmentNoneRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataNoneOkEvent();
		$this->runEventAssessmentTest(true);
		$this->prepareDataRestoreRecovery();
		$this->assertRecoveryAfterRestore(true);
	}

	/**
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpression() {
		$this->clearDiscoveredItemHistory();
		$this->prepareDataRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpression() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentMultipleEvent() {
		$this->prepareDataMultipleEventsRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_EventAssessmentMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEvent() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * prepareDataTagCorrelation() switches the prototypes to tag-correlation mode and resends LLD;
	 * the post-LLD defaults (numeric items, last()<>0 expression) are exactly what this scenario
	 * needs, so it only depends on the LLD step and can run in isolation together with the other
	 * tag-correlation variants and the service-correlation tests (see the regexp below).
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentTagCorrelation() {
		$this->prepareDataTagCorrelation();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_EventAssessmentTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataTagCorrelation();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelation() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * prepareDataServiceCorrelation() fully reconfigures the prototypes and resends LLD, so this
	 * test is self-contained and only needs the discovered host/triggers from the LLD step.
	 * Run in isolation as
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_.*TagCorrelation.*|testTriggerCEP_EventAssessmentServiceCorrelation.*)
	 * (the .* options also pull in the Restart, dependent-trigger and ManualClose variants that chain to these tests)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelation() {
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelation(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelation
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelation(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentServiceCorrelation but the remaining
	 * "down_1" problem is closed via manual close rather than an automatic recovery event.
	 *
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelation
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationManualClose() {
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelationManualClose(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentServiceCorrelationManualClose but the
	 * server component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelationManualClose
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationManualCloseRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelationManualClose(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify cross-trigger-prototype global event correlation: proto 1 and proto 2 both fire
	 * PROBLEM events with service="down"; sending "up" to proto 1 generates a RESOLVED event
	 * with service="up" which triggers the global correlation rule (old service="down",
	 * new service="up", CLOSE_OLD) to close proto 2's open problems without any explicit
	 * recovery sent to proto 2.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger() {
		/*$this->triggerCEP_Cleanup();
		$this->testTriggerCEP_LLDDiscovery();*/
		$this->prepareDataGlobalCorrelation();
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger but the
	 * server component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelation();
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same cross-trigger-prototype global event correlation scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_EXPRESSION with a custom formula ("A and B and C") instead of
	 * CONDITION_EVAL_TYPE_AND_OR, exercising the custom expression evaluation path.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerExpression() {
		$this->prepareDataGlobalCorrelation(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Verify parity-selective global event correlation (CONDITION_EVAL_TYPE_AND_OR). Every discovered
	 * trigger carries an 'odd' tag ('1' for odd components, '0' for even). First a problem is opened on
	 * every trigger; then an "even" correlation rule closes the even problems (the odd ones stay open),
	 * and finally an "odd" correlation rule closes the odd problems, so no open problem remains.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParity)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParity() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_AND_OR);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParity but the server component
	 * is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParity
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_AND_OR);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same parity-selective global event correlation scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationParity (open all, then close even, then odd), but
	 * both correlation rules use CONDITION_EVAL_TYPE_EXPRESSION with a custom formula
	 * ("A and B and C and D"). The expression variant additionally AND-s a 'type=cep-dep' new-event
	 * condition alongside the 'odd' new-event condition — two same-type conditions that only the
	 * expression evaltype can AND together.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_EXPRESSION);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationParityExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_EXPRESSION);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Same assessment as testTriggerCEP_EventAssessmentGlobalCorrelationParity, but the correlation rules
	 * have a single old-event odd=$parity condition (no component tag pair), so each rule closes every
	 * problem of its parity at once, and the order is flipped: odd problems are closed first, then even.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_AND_OR, true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAllRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_AND_OR, true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Verify "close old down when new up" global event correlation. Each discovered trigger opens two
	 * "down" problems, each with a globally unique 'service' id; then the matching "up" values — themselves
	 * PROBLEM events, since the trigger expression matches "up" too — close exactly the corresponding
	 * "down" problem (and themselves) via a rule keyed on old state="down" + new state="up" + a service
	 * tag pair. Unique ids make the closing strictly 1:1 rather than closing every problem at once. No
	 * open problem remains.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}


	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the whole flow lands on a single
	 * discovered item (and its one trigger) instead of every discovered item, exercising the correlation
	 * close-on-up path on one event stream.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up (see testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem) with
	 * the correlation rule created from scratch under CONDITION_EVAL_TYPE_AND. Both conditions (new
	 * state="up" + service tag pair) are AND'd, giving the same 1:1 close-on-up as AND_OR.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAnd$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAnd() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_AND, exercising an evaltype transition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_AND);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under
	 * CONDITION_EVAL_TYPE_AND_OR (the two distinct-type conditions are AND'd), the recreate-from-scratch
	 * counterpart of the default in-place testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOr$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOr() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND and then
	 * updated in place to CONDITION_EVAL_TYPE_AND_OR, exercising an evaltype transition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOrUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOrUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND,
			CONDITION_EVAL_TYPE_AND_OR);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under
	 * CONDITION_EVAL_TYPE_EXPRESSION (custom formula "A and B"), exercising the custom expression evaluation
	 * path on a single event stream.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpression() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_EXPRESSION (custom formula "A and B"), exercising a transition
	 * from a basic evaltype to a custom expression on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpressionUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpressionUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under CONDITION_EVAL_TYPE_OR.
	 * OR-ing the new state="up" condition with the tag pair would match every open problem at once, so the
	 * OR rule keeps only the service tag pair (see buildCloseOnUpCorrelationParams); with one condition OR
	 * is equivalent to AND, preserving the 1:1 close-on-up pairing.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOr$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOr() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_OR, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_OR (single service tag pair condition), exercising an evaltype
	 * transition that also drops a condition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOrUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOrUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_OR);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, false, false, false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Test correlation rule update behavior: verify that changing from CLOSE_OLD+CLOSE_NEW to CLOSE_NEW only
	 * leaves old problems open, and changing back to CLOSE_OLD+CLOSE_NEW restores the closing behavior.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpUpdateBehavior$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpUpdateBehavior() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpUpdateBehavior(false);
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp
	 * (correlation still pairs on the 'service' trigger tag), but two independent webhook media types with
	 * process_tags enabled additionally add a WEB_SERVICE_TAG and a WEB_SERVICE_TAG2 tag to every problem
	 * event from JavaScript, each driven by its own trigger action on the discovered CEP triggers. After the
	 * scenario the test asserts that every problem event carries both tags (WEB_SERVICE_TAG matching the
	 * trailing number of the event name, WEB_SERVICE_TAG2 the same number prefixed with 'second_'),
	 * verifying that tags returned by separate media types are all applied to the events they were
	 * generated for.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// The scenario opens two "down" problems per trigger (waves 1 and 2), which stay open long enough to
		// escalate and run the tagging webhook. The two "up" waves are PROBLEM events too, but each is closed
		// by correlation (CLOSE_NEW) on creation, so its escalation is cancelled and the webhook never fires —
		// only the down problems get tagged. Hence 2 tagged problem events per trigger (key).
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the event.get verification below to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, 2 * $m);
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, 2 * $m);
		}
		finally {
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same tag-application scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS, but the
	 * WEB_SERVICE_TAG assertion runs after every wave instead of only in the final state, verifying that the
	 * tags returned by the media type are applied to the down problem events as they open and are not
	 * disturbed by the "up" waves. Each down problem escalates and runs the tagging webhook, so the tag count
	 * grows to $m after wave 1 and 2 * $m after wave 2. The two "up" waves are PROBLEM events too, but each is
	 * closed by correlation (CLOSE_NEW) on creation, so its escalation is cancelled and it is never tagged —
	 * the count stays 2 * $m after waves 3 and 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			// Pass $check_tags = true so the run asserts the WEB_SERVICE_TAG tag count after every wave.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same tag-application scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS, but the
	 * webhook-applied tags drive services into problem state instead of only being asserted on the events.
	 * The webhook additionally returns a WEB_COMPONENT_TAG tag (the event's 'component' tag value), and one
	 * service per discovered component is created whose only problem tag matches that webhook-applied tag.
	 * Unlike the per-trigger CEP services (matched via the SERVICE_TAG trigger tag), no trigger tag matches
	 * these services, so each can go into problem state only after the escalation runs the tagging webhook
	 * and the tags it returns are applied to the open problem event. The run asserts the services start OK,
	 * turn DISASTER once the webhook tags the open problems (and stay DISASTER through waves 2 and 3), drop
	 * to WARNING once the still-open problems are manually downgraded after wave 3 and recover to OK once
	 * global correlation closes every problem in wave 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->createWebTagServices();

			// $check_tags sequences each wave on the webhook having tagged the events; $check_web_services
			// asserts the service state transitions driven by those webhook-applied tags.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeWebTagServices();
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp but the server component
	 * is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but once every problem is open (at the trigger's DISASTER priority, so each per-trigger service is at
	 * DISASTER too) the open problems are manually downgraded to WARNING via event.acknowledge. When services
	 * exist the test also verifies the service manager follows the manual severity change: every service drops
	 * from DISASTER to WARNING, then recovers to OK once the "up" values close the problems. When services are
	 * disabled the service assertions are skipped and the close-on-up flow runs as usual.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the discovered host only enters data-collection maintenance after the first wave of problems is
	 * already open: the maintenance is created mid-run, so the already-open problems must be suppressed
	 * retroactively, and every problem opened afterwards (while maintenance is active) suppressed at
	 * creation time too, while global correlation still closes them all, leaving nothing open.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed by correlation). Wait
			// until nothing on the discovered host is suppressed any more, so the next test starts with the
			// host fully out of maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the discovered host only enters data-collection maintenance after the first wave of problems is
	 * already open: the maintenance is created mid-run, so the already-open problems must be suppressed
	 * retroactively, and every problem opened afterwards (while maintenance is active) suppressed at
	 * creation time too, while global correlation still closes them all, leaving nothing open.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirstRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirstRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed by correlation). Wait
			// until nothing on the discovered host is suppressed any more, so the next test starts with the
			// host fully out of maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst,
	 * but verifies that problems and services are suppressed during maintenance and no longer suppressed after
	 * the maintenance is stopped. The stopped maintenances are then resumed one at a time out of creation
	 * order (middle, first, last) - suppression must return after the first resume and survive the
	 * overlapping ones - and finally stopped again, after which suppression must clear once more.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblems$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblems() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_SuppressUnsuppressProblems,
	 * but the server component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_SuppressUnsuppressProblems, but instead
	 * of host-wide maintenances that suppress every problem, one maintenance is created per discovered
	 * component, each scoped to that component through a 'component' problem-tag filter. Every open problem
	 * must then be suppressed by exactly the single maintenance whose tag matches it (and by no other),
	 * verifying tag-scoped maintenance suppression in CEP. Stopping the maintenances clears the
	 * suppression, resuming brings it back per matching tag, and global correlation still closes the
	 * problems normally.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsPerTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsPerTag() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			// maintenance_after_first=true, stop_maintenance_and_verify_suppression=true,
			// maintenance_by_tag=true
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "per problem tag" scenario as testTriggerCEP_SuppressUnsuppressProblemsPerTag, but the server
	 * component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsPerTagRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsPerTagRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			// Same as testTriggerCEP_SuppressUnsuppressProblemsPerTag but with restart=true.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_AND (every condition AND'd regardless of type) instead of
	 * CONDITION_EVAL_TYPE_AND_OR. The rule's two conditions (new state="up" + service tag pair) are of
	 * distinct types, so AND evaluates them identically to AND_OR while exercising the
	 * CONDITION_EVAL_TYPE_AND formula-generation path on the close-on-up scenario. The correlation is
	 * recreated from scratch (rather than updated in place) so its evaltype does not depend on whichever
	 * AND_OR CloseOnUp variant ran before it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpAnd$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpAnd() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * The complex event processing (CEP rule) counterpart of
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp: the exact same scenario - it runs the very same
	 * four waves through the very same runner and asserts the same problem counts after each of them - but no
	 * global event correlation rule is involved. The problems are closed by a single CEP rule with a "Tag
	 * correlation" time window keyed on the 'service' tag and the operations
	 *   - Execute when "Event occurred" -> "Close window" (on the "up" events only)
	 *   - Execute when "Window closed"  -> "Close"
	 * so a "down_N" problem stays open in the window of its 'service' id until the matching "up_N" event
	 * closes that window, closing both of them - the CEP equivalent of CLOSE_OLD + CLOSE_NEW. The "up" events
	 * are singled out by a tag value comparison (state Equals "up") on the close-window operation; the
	 * tag-exists flavour of the same condition is covered by
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists. See
	 * buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same CEP tag correlation close-on-up scenario as
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp - identical waves, identical runner and
	 * identical assertions - but the close-window operation singles out the "up" events with a tag-exists
	 * condition instead of a tag value comparison: both prototypes additionally carry the CEP_STATE_TAG tag,
	 * whose name is built from {ITEM.VALUE} and therefore resolves to 'state_up' only for the "up" events, and
	 * the operation requires that tag to exist. See buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The complex event processing (CEP rule) counterpart of
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem: the same "close old down when new
	 * up" flow landing on a single discovered item (and its one trigger) instead of every discovered item,
	 * but with no global event correlation rule involved - the problems are closed by the same single CEP
	 * rule with a "Tag correlation" time window keyed on the 'service' tag as in
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp, exercising the CEP window close path
	 * on one event stream. See buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSingleItem() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
			$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger: the same
	 * CEP tag correlation close-on-up scenario, but every "up_N" value is sent to the item whose trigger opened
	 * "down_N" instead of the next one, so the closing "up" PROBLEM event is raised on the same trigger. The
	 * window is keyed purely on the 'service' tag value, so the pairing must hold either way.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpFromSameTrigger$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpFromSameTrigger() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, false, false, false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS: the same CEP tag
	 * correlation close-on-up scenario (the window still pairs on the 'service' trigger tag), but two
	 * independent webhook media types with process_tags enabled additionally add a WEB_SERVICE_TAG and a
	 * WEB_SERVICE_TAG2 tag to every problem event from JavaScript, each driven by its own trigger action on
	 * the discovered CEP triggers. After the scenario the test asserts that every problem event carries both
	 * tags, verifying that tags returned by separate media types are all applied to the events they were
	 * generated for.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Every one of the four waves opens a problem event per trigger and all of them are tagged, so 4 tagged
		// problem events per trigger (key). Unlike the global correlation flavour - where CLOSE_NEW disables the
		// actions of the problem it closes, so the two "up" waves never escalate and only the "down" problems
		// end up tagged - a CEP rule closing an event leaves its actions enabled (only the correlation paths
		// set CEP_ACTION_DISABLED, see cep_worker.c), so the "up" problems escalate and get tagged as well.
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the event.get verification below to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, 4 * $m);
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, 4 * $m);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave: same
	 * tag-application scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS, but the
	 * WEB_SERVICE_TAG assertion runs after every wave instead of only in the final state, verifying that the
	 * tags returned by the media type are applied to the problem events as they open and are not disturbed by
	 * the later waves. A CEP rule closing an event leaves its actions enabled, so the "up" problems escalate
	 * and get tagged too and the count grows by $m per wave: $m, 2 * $m, 3 * $m, 4 * $m
	 * ($up_events_tagged = true).
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSAfterEachWave$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSAfterEachWave() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			// Pass $check_tags = true so the run asserts the WEB_SERVICE_TAG tag count after every wave, and
			// $up_events_tagged = true so those counts expect the "up" problems to be tagged as well.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, false, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices: same
	 * tag-application scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS, but the
	 * webhook-applied tags drive services into problem state instead of only being asserted on the events. One
	 * service per discovered component is created whose only problem tag matches the webhook-applied
	 * WEB_COMPONENT_TAG tag, so a service can go into problem state only after the escalation ran the tagging
	 * webhook. The run asserts the services start OK, turn DISASTER once the webhook tags the open problems
	 * (and stay DISASTER through waves 2 and 3), drop to WARNING once the still-open problems are manually
	 * downgraded after wave 3 and recover to OK once the CEP rule closes every problem in wave 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSServices$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSServices() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->createWebTagServices();

			// $check_tags sequences each wave on the webhook having tagged the events ($up_events_tagged = true:
			// the CEP-closed "up" problems are tagged too); $check_web_services asserts the service state
			// transitions driven by those webhook-applied tags.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, true, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeWebTagServices();
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpRestart: same scenario as
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp but the server component is stopped and
	 * restarted between each step, so the CEP windows must be restored from the database with their collected
	 * events and still close the paired problems afterwards.
	 *
	 * @depends testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity: same CEP tag
	 * correlation close-on-up scenario, but once every problem is open (at the trigger's DISASTER priority, so
	 * each per-trigger service is at DISASTER too) the open problems are manually downgraded to WARNING via
	 * event.acknowledge. When services exist the test also verifies the service manager follows the manual
	 * severity change: every service drops from DISASTER to WARNING, then recovers to OK once the "up" values
	 * close the problems through the CEP window.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSeverity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSeverity() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst: the
	 * discovered host only enters data-collection maintenance after the first wave of problems is already open,
	 * so the already-open problems must be suppressed retroactively and every problem opened afterwards (while
	 * maintenance is active) suppressed at creation time too, while the CEP rule still closes them all, leaving
	 * nothing open.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed). Wait until nothing
			// on the discovered host is suppressed any more, so the next test starts with the host fully out of
			// maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst but
	 * the server component is stopped and restarted between each step.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirstRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirstRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// See testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst: wait
			// until the timer has taken the host out of maintenance and cleared every suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_SuppressUnsuppressProblems: verifies that problems and services are
	 * suppressed during maintenance and no longer suppressed after the maintenance is stopped. The stopped
	 * maintenances are then resumed one at a time out of creation order (middle, first, last) - suppression must
	 * return after the first resume and survive the overlapping ones - and finally stopped again, after which
	 * suppression must clear once more. The problems are closed by the CEP rule rather than by global
	 * correlation.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblems$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblems() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_CepWindowTagSuppressUnsuppressProblems, but the server component is
	 * stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_SuppressUnsuppressProblemsPerTag: same scenario as
	 * testTriggerCEP_CepWindowTagSuppressUnsuppressProblems, but instead of host-wide maintenances that
	 * suppress every problem, one maintenance is created per discovered component, each scoped to that
	 * component through a 'component' problem-tag filter. Every open problem must then be suppressed by exactly
	 * the single maintenance whose tag matches it (and by no other). Stopping the maintenances clears the
	 * suppression, resuming brings it back per matching tag, and the CEP rule still closes the problems
	 * normally.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			// maintenance_after_first=true, stop_maintenance_and_verify_suppression=true,
			// maintenance_by_tag=true
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "per problem tag" scenario as testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag, but the
	 * server component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTagRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTagRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			// Same as testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag but with restart=true.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Complex event processing without a window (WINDOW_NONE): twelve windowless rules, forming six opposite
	 * pairs of conditions on the 'service' id of the event, tag the problem events of one discovered trigger
	 * the moment they occur. Four pairs work on the event tags (Equals "0" / Does not equal "0", Contains "0" /
	 * Does not contain "0", Is less than or equal "0" / Is more than or equal "1" and, on the per-id tag name,
	 * 'service_0' Exists / Does not exist) and two on the event name, which ends with the item value (Equals /
	 * Does not equal the whole "down_1" event name, Contains / Does not contain "down_1").
	 *
	 * Every rule tags the events it matched with a tag named after its operator ("service_equals",
	 * "event_name_contains", ...), and exactly one rule of every pair may match an event, so the tagging
	 * follows the ids: "down_0" gets service_equals + service_contains + service_less_equal + service_exists +
	 * event_name_not_equals + event_name_not_contains, "down_1" gets the opposite half of every pair, and
	 * "down_10" - the id that contains the others' values without being equal to them - gets
	 * service_not_equals + service_contains + service_more_equal + service_not_exists +
	 * event_name_not_equals + event_name_contains. No event may carry the tag of any other rule. None of the
	 * rules closes anything, so all three problems stay open until the trigger expression recovers them.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowNone$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowNone() {
		$this->prepareDataCepWindowNoneTagOperations();

		try {
			$this->runEventAssessmentTestCepWindowNone();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_EXPRESSION with a custom formula ("A and B and C") instead of
	 * CONDITION_EVAL_TYPE_AND_OR, exercising the custom expression evaluation path.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Discover a single log trigger from the dedicated log template (linked directly to the host) and
	 * verify that a burst of LOG_EVENT_COUNT log values, all matching the trigger pattern, produces
	 * exactly LOG_EVENT_COUNT problem events. The trigger prototype has multiple problem event
	 * generation enabled, so every matching value opens a new problem on the same trigger.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_LogMultipleEvents() {
		$this->openLogProblemBurst(false);
	}

	/**
	 * Recover the single log trigger: a single value that does not match the "problem" pattern turns
	 * the expression false, so the trigger goes back to OK and every problem opened by
	 * testTriggerCEP_LogMultipleEvents is resolved.
	 *
	 * @depends testTriggerCEP_LogMultipleEvents
	 */
	public function testTriggerCEP_LogRecovery() {
		$this->recoverLogTrigger(false);
	}

	/**
	 * Same as testTriggerCEP_LogMultipleEvents but the server is restarted before the burst is opened.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_LogMultipleEventsRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->openLogProblemBurst(true);
	}

	/**
	 * Same as testTriggerCEP_LogRecovery but the server is restarted before recovery. The restart forces
	 * the LOG_EVENT_COUNT problems opened by testTriggerCEP_LogMultipleEventsRestart to be reloaded into
	 * the event cache (exercising the batched initial event cache loading) before they are resolved.
	 *
	 * @depends testTriggerCEP_LogMultipleEventsRestart
	 */
	public function testTriggerCEP_LogRecoveryRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->recoverLogTrigger(true);
	}

	/**
	 * Push a burst of LOG_EVENT_COUNT log values to the discovered item in a single request and wait until
	 * every value has opened a problem event on the log trigger. Each value matches the "problem" pattern
	 * and carries a distinct timestamp so none collapse; with multiple problem event generation enabled
	 * every value opens a new problem on the discovered trigger. When $restart is true, the server is
	 * restarted before the burst is sent.
	 */
	private function openLogProblemBurst(bool $restart): void {
		$this->maybeRestartServer($restart);

		$triggerids = [self::$discovered_log_triggerid];
		$this->captureEventBaseline($triggerids);

		// The trigger fires on the dependent discovered log item. A real proxy resolves dependent items
		// itself and delivers their values directly, and the server does not propagate proxy-delivered
		// master values to dependents, so the burst is pushed straight to the dependent item here rather
		// than to the master.
		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';
		$values = [];
		for ($i = 0; $i < static::LOG_EVENT_COUNT; $i++) {
			$values[] = [
				'host' => self::HOST_NAME,
				'key' => $item_key,
				'value' => 'problem '.$i
			];
		}
		$this->dispatchSenderValues($values);

		// Every one of the LOG_EVENT_COUNT values must have generated a problem event on the trigger.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], static::LOG_EVENT_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Send a single non-matching log value so the trigger expression turns false, then wait until all open
	 * problems on the log trigger are resolved and the trigger is back to OK. When $restart is true, the
	 * server is restarted before the recovery value is sent.
	 */
	private function recoverLogTrigger(bool $restart): void {
		$this->maybeRestartServer($restart);

		$triggerids = [self::$discovered_log_triggerid];

		// Sent directly to the dependent discovered log item (see openLogProblemBurst): the server does not
		// propagate proxy-delivered master values to dependents. A burst of non-matching values is sent (as in
		// openLogProblemBurst) to stress parallel recovery, even though a single value is enough to turn the
		// trigger expression false and recover all open problems.
		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';
		$values = [];
		for ($i = 0; $i < static::LOG_EVENT_COUNT; $i++) {
			$values[] = [
				'host' => self::HOST_NAME,
				'key' => $item_key,
				'value' => 'recovered '.$i
			];
		}
		$this->dispatchSenderValues($values);

		$this->waitForNoOpenProblems($triggerids, 'log recovery');
	}

	/**
	 * Discover exactly one log trigger from the dedicated log template by sending LLD data with a single
	 * entry. Asserts the trigger has multiple problem event generation enabled and stores the discovered
	 * trigger id.
	 */
	private function discoverLogTrigger(): void {
		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::LOG_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					[self::LOG_LLD_MACRO => self::LOG_COMPONENT_VALUE]
				]])
			]
		]);

		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';

		// Wait for the discovered log item.
		$this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'filter' => ['key_' => $item_key],
			'output' => ['itemid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === 1;
		});

		// Wait for the single discovered log trigger.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => 'CEP log trigger for '],
			'output' => ['triggerid', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === 1;
		});
		$this->assertCount(1, $response['result'], 'Discovered log trigger was not created.');
		$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, (int) $response['result'][0]['type'],
			'Discovered log trigger must have multiple problem event generation enabled.');
		self::$discovered_log_triggerid = $response['result'][0]['triggerid'];

		// Reload config so the server is aware of the newly discovered item and trigger.
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Same "close old down when new up" setup as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the scenario only OPENS problems: both "down" waves are sent (two open problems per trigger) and
	 * the "up" values that would close them are deliberately never sent. The discovered triggers are then
	 * deleted via empty LLD (triggerCEP_Cleanup()) and the discovered host via testTriggerCEP_CleanupDiscoveredHost();
	 * deleting them must resolve every open problem and drop their events from the CEP cache, so no open
	 * problem remains afterwards.
	 *
	 * This is the terminal test that uses the discovered host, so it is declared last among the host-using
	 * tests: the cleanup chain (testTriggerCEP_Cleanup, testTriggerCEP_CleanupDiscoveredHost) runs after it and
	 * tolerates the already-removed host.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationOpenThenRemoveHost$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationOpenThenRemoveHost() {
		$this->prepareDataGlobalCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Only open problems: send both "down" waves (no "up" values), so two problems stay open per trigger.
		$this->openProblemsGlobalCorrelationCloseOnUp();

		// Delete the discovered triggers via empty LLD while their problems are still open: this must resolve
		// every open problem and drop their events from the CEP cache, exercising the trigger-deletion path
		// before the host itself is removed.
		$this->triggerCEP_Cleanup();

		// Removing the discovered host must delete any remaining resources and resolve every open problem.
		$this->testTriggerCEP_CleanupDiscoveredHost();

		// Deleting the host queues its triggers' problem/event records for removal; force both the general and
		// the trigger housekeeper so those records are actually deleted before verifying that nothing remains.
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'housekeeper_execute');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'forced execution of the housekeeper', true, 20, 3);
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'trigger_housekeeper_execute');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'forced execution of the trigger housekeeper',
				true, 20, 3);

		// The triggers were deleted (by empty LLD and then with the host), so only the problem count can be
		// checked here: waitForNoOpenProblems() additionally asserts the triggers are still present in OK
		// state, which no longer holds. No open problem may remain on the now-deleted triggers.
		$this->waitForOpenProblemCount($all, 0);

		// Removing the host must also drop the discovered items' problem events from the CEP cache: no other
		// problem is open in the system at this point, so cached_events must drain back to zero.
		$this->assertCepStatEquals('tasks', 'cached_events', 0);
		$this->assertCepStatEquals('tasks', 'cached_objects', 0);
	}

	/**
	 * Send empty LLD data to delete all resources that were discovered during the test run
	 * and verify the discovered triggers are actually removed.
	 *
	 * @depends testTriggerCEP_DependentTrigger
	 * @depends testTriggerCEP_EventAssessmentNone
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelationManualClose
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger
	 * @depends testTriggerCEP_LogRecovery
	 */
	public function testTriggerCEP_Cleanup() {
		self::triggerCEP_Cleanup();
	}

	/**
	 * Send empty host LLD data to remove the discovered host and verify it is actually deleted.
	 *
	 * @depends testTriggerCEP_Cleanup
	 */
	public function testTriggerCEP_CleanupDiscoveredHost() {
		// The open-then-remove-host scenario already removes the discovered host mid-suite, so this may run
		// with the host already gone. Nothing left to delete in that case.
		if (self::$disc_hostid === null) {
			return;
		}

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		]);

		$this->callUntilCountIsPresent('host.get', [
			'hostids' => [self::$disc_hostid]
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		self::$disc_hostid = null;
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete the template created during setup and verify it is gone.
	 *
	 * @depends testTriggerCEP_CleanupDiscoveredHost
	 */
	public function testTriggerCEP_CleanupTemplate() {
		$this->call('template.delete', [self::$templateid]);

		$response = $this->call('template.get', [
			'templateids' => [self::$templateid],
			'countOutput' => true
		]);
		$this->assertEquals(0, $response['result'], 'Template was not deleted.');
		self::$templateid = null;
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * @depends testTriggerCEP_CleanupTemplate
	 */
	public function testTriggerCEP_ClearData(): void {
		self::clearData();
	}

	/**
	 * Drive all discovered items into the unsupported state (triggers become UNKNOWN) and verify that
	 * an internal problem is opened for every unsupported item and every unknown trigger.
	 */
	private function runOpenUnknownTest(): void {
		// With SCOPED_INTERNAL_ACTIONS the internal actions were disabled in prepareData(); enable them here
		// so the server starts generating internal item-not-supported / trigger-unknown events just for the
		// *Unknown tests. They are disabled again by runCloseUnknownTest().
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->enableInternalActions();
		}

		// Record the highest internal-source eventid that already exists (the previous cycle, if any, was
		// fully drained by runCloseUnknownTest()) so this cycle's notifications can be awaited by restricting
		// the alert wait to the events generated after this baseline.
		$this->captureInternalEventBaseline();

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Push a non-numeric value to flip all items into unsupported state; CEP keeps the trigger
		// value unchanged (OK) while the state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);

		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_FALSE);

		// An internal problem must be opened for every unknown trigger.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], static::LLD_DISCOVERY_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// An internal problem must be opened for every unsupported item on the discovered host.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], static::LLD_DISCOVERY_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Restore all discovered items to the supported state (triggers return to NORMAL/OK) and verify that
	 * every internal problem opened by runOpenUnknownTest is resolved.
	 */
	private function runCloseUnknownTest(): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Send a numeric value of 0 to restore all items to supported state; the trigger returns to
		// the NORMAL state and stays OK.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);

		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);

		// Every internal trigger-unknown problem must be resolved once the triggers leave UNKNOWN.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Every internal item-not-supported problem must be resolved once the items become supported.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Every notification generated by this cycle must finish before the internal actions are disabled:
		// disabling an action cancels its still-running escalations, so a recovery notification that is
		// still queued when disableInternalActions() reloads the cache would be dropped by the escalator.
		$this->waitForInternalAlertsCompleted();

		// Disable the internal actions enabled by runOpenUnknownTest() so the rest of the suite runs without
		// the server generating internal events again.
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->disableInternalActions();
			$this->reloadConfigurationCacheAndWaitForLogLine();
		}
	}

	private function runEventAssessmentTest(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// Use the primary trigger for recovery/correlation mode detection.
		$triggers = $this->getTriggers($triggerids);
		$trigger = $triggers[self::$discovered_triggerid];
		$this->assertEquals(TRIGGER_VALUE_FALSE, $trigger['value'],
			'Trigger must start in OK state for assessment.');
		$none_recovery = ((int) $trigger['recovery_mode'] === ZBX_RECOVERY_MODE_NONE);
		$tag_correlation = ((int) $trigger['correlation_mode'] === ZBX_TRIGGER_CORRELATION_TAG);
		$mult_event = ((int) $trigger['type'] === TRIGGER_MULT_EVENT_ENABLED);

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. OK→OK: no new event, no lastchange update.
		$this->assertNoStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $expected_events);
		$this->maybeRestartServer($restart);

		// 2. OK→PROBLEM: PROBLEM event generated.
		$expected_events++;
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		$this->maybeRestartServer($restart);

		// 3. All items unsupported while PROBLEM: CEP keeps trigger values as PROBLEM, state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);
		$this->maybeRestartServer($restart);

		// 4. PROBLEM→PROBLEM: items supported again.
		//    With multiple event generation a new PROBLEM event is produced;
		//    without it no new event and no lastchange update.
		if ($mult_event) {
			$expected_events++;
			$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		}
		else {
			$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		}
		$this->maybeRestartServer($restart);

		// 5. PROBLEM→OK: with None recovery mode the triggers stay PROBLEM and no event is generated;
		//    otherwise a RESOLVED event is generated and triggers return to OK.
		if ($none_recovery) {
			$this->assertNoStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_TRUE, $expected_events);
		}
		else {
			$expected_events++;
			$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $expected_events);
		}
	}

	/**
	 * Run the service-correlation event-assessment scenario:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service tag = "0" → first PROBLEM event.
	 *   2. "down_1" → expression still true, service tag = "1" → new PROBLEM event alongside
	 *                 the already-open "down_0" problem; trigger value unchanged (stays TRUE).
	 *   3. "up_0"   → expression false, service tag = "0" → RESOLVED closes "down_0"; the
	 *                 "down_1" problem (service = "1") is still open → trigger stays TRUE.
	 *   4. "up_1"   → expression false, service tag = "1" → RESOLVED closes "down_1"; all
	 *                 problems resolved → trigger returns to OK.
	 */
	private function runEventAssessmentTestCorrelation(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);

		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. "up_1": expression false, service tag = "1" → RESOLVED event closes the last open problem.
		//    All problems resolved → trigger returns to OK.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'up_1', TRIGGER_VALUE_FALSE, $expected_events
		);
	}

	/**
	 * Same scenario as runEventAssessmentTestCorrelation but the "down_1" problem is closed
	 * via manual close instead of an automatic recovery:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service tag = "0" → first PROBLEM event.
	 *   2. "down_1" → expression still true, service tag = "1" → new PROBLEM event alongside
	 *                 the already-open "down_0" problem; trigger value unchanged (stays TRUE).
	 *   3. "up_0"   → expression false, service tag = "0" → RESOLVED closes "down_0"; the
	 *                 "down_1" problem (service = "1") is still open → trigger stays TRUE.
	 *   4. Manual close → closeTagCorrelationProblems closes the remaining "down_1" problem;
	 *                 all problems resolved → trigger returns to OK.
	 */
	private function runEventAssessmentTestCorrelationManualClose(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation manual-close assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. Manual close: exactly one problem per trigger remains open (the "down_1" / service="1"
		//    problem). closeTagCorrelationProblems verifies that manual close is rejected while
		//    manual_close=false, then enables it, closes all remaining problems and waits for all
		//    triggers to return to OK.
		$this->closeTagCorrelationProblems($triggerids);
	}

	/**
	 * Run the global event correlation scenario:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service="0" → first PROBLEM event; trigger TRUE.
	 *   2. "down_1" → expression still true, service="1" → second PROBLEM event (mult_event);
	 *                 trigger stays TRUE; no old event with service="1" yet, correlation silent.
	 *   3. "down_0" → expression still true, service="0" → third PROBLEM event (mult_event);
	 *                 global correlation matches oldtag/newtag 'service'="0" pair → closes old
	 *                 event #1 (RESOLVED generated); trigger stays TRUE (events #2 and #3 open).
	 *   4. "up"     → expression false → RESOLVED closes all remaining open problems at once
	 *                 (trigger-level correlation = NONE); trigger returns to OK.
	 */
	private function runEventAssessmentTestGlobalCorrelation(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. "up_1": expression false, service tag = "1" → RESOLVED event closes the last open problem.
		//    All problems resolved → trigger returns to OK.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'up_1', TRIGGER_VALUE_FALSE, $expected_events
		);
	}

	/**
	 * Run the "close old down when new up" global event correlation scenario. Unlike
	 * runEventAssessmentTestGlobalCorrelation (where "up" is a recovery that resolves the trigger), the
	 * trigger expression here matches "up" too, so the "up_<id>" values are PROBLEM events that drive
	 * the correlation.
	 *
	 * Every problem carries a globally unique 'service' id (the trailing number of the value), so the
	 * service tag pair correlates an "up" event to exactly one "down" problem (1:1). Broadcasting a
	 * reused id would instead let a single "up" close every problem sharing that id; unique ids make the
	 * closing strictly corresponding. Both prototypes participate; with $m = total triggers, key index
	 * $i opens problem id $i and, in a second wave, id $i+$m — so each trigger holds two problems:
	 *
	 *   1. "down_<i>"    → PROBLEM, state="down", service="<i>"; trigger goes TRUE.
	 *   2. "down_<i+m>"  → PROBLEM, state="down", service="<i+m>" (mult_event); trigger stays TRUE.
	 *                      Two problems are now open per trigger; the rule is silent (no "up" event yet).
	 *   3. "up_<i>"      → PROBLEM, state="up", service="<i>"; global correlation (old state="down" + new
	 *                      state="up" + service tag pair) closes exactly the paired "down_<i>" (CLOSE_OLD)
	 *                      and the "up_<i>" itself (CLOSE_NEW). Each trigger's "down_<i+m>" stays open, so
	 *                      triggers stay TRUE and exactly $m problems remain.
	 *   4. "up_<i+m>"    → closes each trigger's remaining "down_<i+m>" and itself. Nothing stays open.
	 */
	/**
	 * Open (but never close) the "close old down when new up" problems: send both "down" waves — a unique id
	 * per trigger, then a second unique id per trigger (mult_event) — so two problems stay open on every
	 * discovered trigger. The "up" values that would close them via global correlation are deliberately not
	 * sent. Returns the per-wave problem count $m (so 2 * $m problems are open on return).
	 */
	private function openProblemsGlobalCorrelationCloseOnUp(): int {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>".
		$values = fn(string $prefix, int $offset) => array_map(
			fn($key, $i) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.($offset + $i)],
			$keys, array_keys($keys)
		);

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for open-then-remove-host global correlation test.');
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		return $m;
	}

	/**
	 * Drive the "close old down when new up" scenario across four waves (down, down, up, up). When
	 * $check_tags is true, after each wave the test asserts how many problem events carry WEB_SERVICE_TAG
	 * and WEB_SERVICE_TAG2 (each applied by its own webhook action, see createExtraTagWebhookAction):
	 * every problem is tagged by the webhooks as it opens and the tags are never removed, and all problems
	 * open (both "down" waves) before any closes (the "up" waves), so the tagged count is the high-water
	 * mark of the open-problem count — $m after wave 1, then 2 * $m from wave 2 onwards (unchanged as the
	 * "up" waves close problems back down). Requires captureEventBaseline() and the tag webhook action to
	 * be set up by the caller.
	 *
	 * When $up_from_other_trigger is true, each "up_N" value is sent to the next discovered item instead
	 * of the one whose trigger opened "down_N", so the closing "up" PROBLEM event is raised on a different
	 * trigger and the correlation service tag pair must close the relevant "down" problem by its id across
	 * triggers rather than each trigger receiving its own "up".
	 *
	 * When $check_web_services is true, the run additionally asserts the per-component web-tag services
	 * (see createWebTagServices, to be created by the caller) follow the webhook-applied WEB_COMPONENT_TAG
	 * tag: OK before the first wave, DISASTER once the webhook has tagged the open problems (waves 1-3, the
	 * triggers have DISASTER priority), WARNING after wave 3 once the still-open problems are manually
	 * downgraded via event.acknowledge (the services must follow the severity down, not only up) and OK
	 * again once wave 4 closes everything.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUp(bool $restart,
			bool $maintenance_after_first = false, bool $check_tags = false,
			bool $stop_maintenance_and_verify_suppression = false, bool $up_from_other_trigger = true,
			bool $check_web_services = false, bool $maintenance_by_tag = false,
			bool $up_events_tagged = false): void {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// How many problem events are expected to carry the webhook-applied tags once $wave waves have been
		// sent. With $up_events_tagged every problem event is tagged by the escalation that runs the tagging
		// webhook, so the count is simply the number of problems opened so far ($wave * $m). Otherwise only the
		// two "down" waves are ever tagged: a global correlation CLOSE_NEW disables the actions of the problem
		// it closes (see the CEP_ACTION_DISABLED handling in cep_worker.c), so those "up" problems never
		// escalate - unlike the ones a CEP rule closes, whose actions stay enabled.
		$tagged_after_wave = fn(int $wave) => ($up_events_tagged ? $wave : min($wave, 2)) * $m;

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>". A
		// non-zero $shift sends the value carrying id N at the item $shift positions over, so the event
		// with service=N originates from a different trigger than the one that opened "down_N".
		$values = fn(string $prefix, int $offset, int $shift = 0) => array_map(
			fn($i) => [
				'host' => self::HOST_DISC_VALUE,
				'key' => $keys[($i + $shift) % $m],
				'value' => $prefix.'_'.($offset + $i)
			],
			array_keys($keys)
		);
		$up_shift = $up_from_other_trigger ? 1 : 0;

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for close-on-up global correlation test.');
		}

		// The web-tag services (matched only by the webhook-applied WEB_COMPONENT_TAG tag) must start OK:
		// no problem has been tagged for them yet.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(ZBX_SEVERITY_OK);
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// In $maintenance_by_tag mode the host is placed under one maintenance per discovered component,
		// each scoped to that component via a 'component' tag filter, so a problem is suppressed only by
		// the single maintenance whose tag matches it; the map component value => maintenanceid drives the
		// per-component suppression assertions below.
		$maintenance_by_component = [];

		// The host only enters maintenance after the first problems are already open: creating the
		// maintenance now must retroactively suppress those $m open problems (and every problem opened
		// later), while global correlation still closes them normally below.
		if ($maintenance_after_first) {
			if ($maintenance_by_tag) {
				$maintenance_by_component = $this->startDiscHostTagMaintenances();
				$this->waitForOpenProblemsSuppressedPerComponent($all, $m, $maintenance_by_component);
			}
			else {
				$this->startDiscHostMaintenances(static::MAINTENANCE_COUNT);
				$this->waitForOpenProblemsSuppressedByMaintenances($all, $m, self::$disc_maintenanceids);
			}
			$this->waitForServicesSuppressed();

			if (!$maintenance_by_tag) {
				// Start additional maintenances on the already-suppressed host: the extra overlapping
				// maintenances must not disturb the existing suppression, and every open problem must end
				// up suppressed by every active maintenance. (Tag-scoped maintenances are one-per-component,
				// so there is nothing to overlap.)
				$this->startDiscHostMaintenances(static::MAINTENANCE_COUNT_EXTRA);
				$this->waitForOpenProblemsSuppressedByMaintenances($all, $m, self::$disc_maintenanceids);
			}
		}

		// Wave 1 is fully open ($m problems), so $m problem events are tagged by both webhooks.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(1));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(1));
		}

		// Wave 1 covered every key, so the webhook has tagged an open problem of every component with
		// WEB_COMPONENT_TAG and every web-tag service goes to PROBLEM (DISASTER trigger priority) purely
		// via the webhook-applied tag - no trigger tag matches these services.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);
		}

		$this->maybeRestartServer($restart);

		if ($maintenance_after_first) {
			$this->waitForServicesSuppressed();
		}

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// The host is in maintenance by now, so this second wave (opened while maintenance is active) must
		// be suppressed at creation time as well: all 2 * $m open problems suppressed - each by every
		// active maintenance in host-wide mode, or by its single matching component maintenance in
		// $maintenance_by_tag mode.
		if ($maintenance_after_first) {
			if ($maintenance_by_tag) {
				$this->waitForOpenProblemsSuppressedPerComponent($all, 2 * $m, $maintenance_by_component);
			}
			else {
				$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, self::$disc_maintenanceids);
			}
			$this->waitForServicesSuppressed();

			// If requested, verify services are suppressed while problems are still open,
			// then stop maintenance and verify suppression is cleared.
			if ($stop_maintenance_and_verify_suppression && $maintenance_by_tag) {
				// Stop all tag maintenances at once: suppression of the still-open problems and services
				// must be cleared.
				$this->stopDiscHostMaintenances(self::$disc_maintenanceids);

				$this->maybeRestartServer($restart);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->waitForSuppressionCleared();

				// Each service is matched to one component via its SERVICE_TAG problem tag, so a service is
				// suppressed exactly while its component's maintenance is active; this map drives the
				// per-component service assertions in the resume/stop steps below.
				$service_by_component = $this->getServiceIdsByComponent();

				$per_component = intdiv(2 * $m, count($maintenance_by_component));

				// Process the per-component maintenances out of creation order in the same
				// [last, middle bulk, last-1] grouping as the host-wide branch: highest index (id) first,
				// then everything but the last two at once, then the gap. The middle group is skipped when
				// there are fewer than three components.
				$components = array_keys($maintenance_by_component);
				$last = count($components) - 1;
				$groups = [[$last]];
				if ($last >= 2) {
					$groups[] = range(0, $last - 2);
				}
				if ($last >= 1) {
					$groups[] = [$last - 1];
				}

				// Resume the maintenances group by group: after each group exactly the resumed components'
				// problems are suppressed (each only by its own maintenance) and exactly their services are
				// suppressed, while the not-yet-resumed components' problems and services stay in problem.
				$resumed_by_component = [];
				foreach ($groups as $indexes) {
					$ids = [];
					foreach ($indexes as $index) {
						$component = $components[$index];
						$ids[] = $maintenance_by_component[$component];
						$resumed_by_component[$component] = $maintenance_by_component[$component];
					}
					$this->resumeDiscHostMaintenances($ids);

					$suppressed = $per_component * count($resumed_by_component);
					$this->waitForOpenProblemsSuppressedPerComponent($all, $suppressed, $resumed_by_component);
					$this->waitForServicesSuppressedForComponents($service_by_component,
						array_keys($resumed_by_component));
				}

				// Every component maintenance is active again, so every problem is suppressed and every
				// service is suppressed once more.
				$this->waitForServicesSuppressed();

				$this->maybeRestartServer($restart);

				// Now stop the maintenances again group by group, but split the bulk group so its last
				// (highest-id) component comes out of maintenance on its own first, then the remaining bulk
				// components: this exercises the suppression-data merge with the bulk's highest id removed
				// ahead of the lower ones. Stopping a group must unsuppress exactly its components' problems
				// while the others stay suppressed by their own still-active maintenance, so the suppressed
				// count shrinks by that group's worth per step and only stopping the final group clears the
				// suppression entirely. Each stopped group's services return to problem at the same step
				// while the still-maintained ones stay suppressed.
				$stop_groups = [[$last]];
				if ($last >= 2) {
					// Take the last of the bulk out first on its own, then the rest of the bulk.
					$stop_groups[] = [$last - 2];
					if ($last >= 3) {
						$stop_groups[] = range(0, $last - 3);
					}
				}
				if ($last >= 1) {
					$stop_groups[] = [$last - 1];
				}

				$remaining_by_component = $resumed_by_component;
				foreach ($stop_groups as $indexes) {
					$ids = [];
					foreach ($indexes as $index) {
						$component = $components[$index];
						$ids[] = $maintenance_by_component[$component];
						unset($remaining_by_component[$component]);
					}
					$this->stopDiscHostMaintenances($ids);
					$this->reloadConfigurationCacheAndWaitForLogLine();

					if (!empty($remaining_by_component)) {
						$suppressed = $per_component * count($remaining_by_component);
						$this->waitForOpenProblemsSuppressedPerComponent($all, $suppressed,
							$remaining_by_component);
						$this->waitForServicesSuppressedForComponents($service_by_component,
							array_keys($remaining_by_component));
					}
					else {
						// Last group stopped: nothing stays suppressed and every service returns to problem
						// (waitForSuppressionCleared() also asserts services are no longer suppressed).
						$this->waitForSuppressionCleared();
					}
				}
			}
			elseif ($stop_maintenance_and_verify_suppression) {
				// Stop all maintenances at once: suppression of the still-open problems and services
				// must be cleared.
				$this->stopDiscHostMaintenances(self::$disc_maintenanceids);

				$this->maybeRestartServer($restart);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->waitForSuppressionCleared();

				// Resume the stopped maintenances out of creation order, highest maintenanceid
				// first: the suppression data in the DB then holds only the highest id, and every
				// later resume adds maintenances with lower ids, which sort before the existing
				// entries (both sides are compared sorted by maintenanceid, not by start time).
				// The second step resumes all remaining lower-id maintenances but one at once, so a
				// single timer pass sees far more new cache-side maintenances than there are
				// DB-side suppression rows. After every step each problem must be suppressed by
				// exactly the maintenances resumed so far - the stopped ones must not linger in
				// the suppression data.
				$last = count(self::$disc_maintenanceids) - 1;
				$resumed = [];
				foreach ([[$last], range(0, $last - 2), [$last - 1]] as $indexes) {
					$ids = array_map(fn($index) => self::$disc_maintenanceids[$index], $indexes);
					$resumed = array_merge($resumed, $ids);
					$this->resumeDiscHostMaintenances($ids);
					$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, $resumed);
					$this->waitForServicesSuppressed();
				}

				// Stop the resumed maintenances one by one in the same order: while at least one of
				// them is still active every problem must stay suppressed - by exactly the remaining
				// maintenances - and only stopping the last one may clear the suppression.
				//foreach ($resumed as $i => $maintenanceid) {
				//	$this->stopDiscHostMaintenances([$maintenanceid]);
				//
				//	$this->reloadConfigurationCacheAndWaitForLogLine();
				//
				//	$remaining = array_slice($resumed, $i + 1);
				//	if (!empty($remaining)) {
				//		$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, $remaining);
				//		$this->waitForServicesSuppressed();
				//	}
				//}

				// Stop all resumed maintenances in bulk.
				$this->stopDiscHostMaintenances($resumed);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->maybeRestartServer($restart);

				$this->waitForSuppressionCleared();
			}
		}

		// Both "down" waves are now open (2 * $m problems), so 2 * $m problem events are tagged by both
		// webhooks.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(2));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(2));
		}

		// Wave 2 keeps every component with open webhook-tagged problems, so the services stay DISASTER.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);
		}

		$this->maybeRestartServer($restart);

		// 3. "up" for the first id set: each is a PROBLEM that closes only its corresponding "down"
		//    (CLOSE_OLD) and itself (CLOSE_NEW) — matched by the service id even when the "up" was
		//    raised on another trigger ($up_shift). Each trigger's second problem stays open, so
		//    triggers stay TRUE and exactly $m problems remain.
		$this->dispatchSenderValues($values('up', 0, $up_shift));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// Wave 3 closed $m problems and the down problems keep their tags, so the tagged count only grows by
		// this wave's own "up" problem events - by $m when they are tagged too, by nothing when correlation
		// closed them with their actions disabled.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(3));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(3));
		}

		// Wave 2's webhook-tagged problems are still open on every component, so the services stay DISASTER.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);

			// Manually downgrade the still-open problems to WARNING: the service manager must recompute
			// the web-tag service status from the new lower severity, so every service drops
			// DISASTER -> WARNING without any problem closing.
			$this->updateOpenProblemsSeverity($all, TRIGGER_SEVERITY_WARNING);
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_WARNING);
		}

		$this->maybeRestartServer($restart);

		// 4. "up" for the second id set closes each trigger's remaining problem; nothing stays open.
		$this->dispatchSenderValues($values('up', $m, $up_shift));
		$this->waitForNoOpenProblems($all);

		// Wave 4 closed the rest; nothing stays open, but the tagged count still reflects every problem event
		// that was ever tagged: every "down" problem, plus the "up" problems when they escalate too.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(4));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(4));
		}

		// No webhook-tagged problem stays open, so every web-tag service recovers to OK.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(ZBX_SEVERITY_OK);
		}
	}

	/**
	 * Same "close old down when new up" scenario as runEventAssessmentTestGlobalCorrelationCloseOnUp, but
	 * the whole flow lands on a single discovered item (and its one trigger) rather than being spread
	 * across every discovered item. The one trigger opens two "down" problems (each with a unique 'service'
	 * id), then the matching "up" values — themselves PROBLEM events — close each corresponding "down"
	 * (and themselves) strictly 1:1, leaving no open problem. When $restart is true, the server is
	 * restarted between steps.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(bool $restart): void {
		// Drive a single discovered item (and its one trigger) so the whole scenario lands on one event
		// stream rather than being spread across every discovered item.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for single-item close-on-up global correlation test.');
		}

		// Send one value with a unique id: value "<prefix>_<id>".
		$send = fn(string $prefix, int $id) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.$id]
		]);

		// 1. Open the first problem on the trigger (unique id); trigger goes TRUE.
		$send('down', 0);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 2. Open a second problem on the trigger (a different unique id, mult_event); still TRUE.
		$send('down', 1);
		$this->waitForOpenProblemCount($all, 2);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 3. "up" for the first id: a PROBLEM that closes only its corresponding "down" (CLOSE_OLD) and
		//    itself (CLOSE_NEW). The second problem stays open, so the trigger stays TRUE and one remains.
		$send('up', 0);

		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 4. "up" for the second id closes the trigger's remaining problem; nothing stays open.
		$send('up', 1);
		$this->waitForNoOpenProblems($all);
	}

	/**
	 * Drive the windowless (WINDOW_NONE) CEP scenario on a single discovered item, so the whole flow lands on
	 * one event stream. Three problems are opened on its one trigger, each with its own 'service' id, and each
	 * id is matched by exactly one rule of every pair, so it ends up carrying the tags naming those rules:
	 *   - "down_0":  service "0"  -> service_equals, service_contains, service_less_equal, service_exists,
	 *                                event_name_not_equals, event_name_not_contains;
	 *   - "down_1":  service "1"  -> service_not_equals, service_not_contains, service_more_equal,
	 *                                service_not_exists, event_name_equals, event_name_contains;
	 *   - "down_10": service "10" -> service_not_equals, service_contains, service_more_equal,
	 *                                service_not_exists, event_name_not_equals, event_name_contains.
	 *
	 * "down_10" is what makes the Contains pairs more than slower Equals pairs: its id contains "0" without
	 * being equal to it and its event name contains the "down_1" item value without being equal to the
	 * "down_1" event name, so both Equals/Contains pairs must disagree on it (and, being numerically above the
	 * "1" threshold, it also keeps the numeric comparison from degrading into a string one). No rule closes
	 * anything, so every problem stays open, and the tags of all events so far are re-checked after each
	 * value, so a rule tagging an event it must not match is caught on the value that produced it. A value
	 * matching neither "down" nor "up" finally turns the trigger expression false and closes all three
	 * problems at once.
	 */
	private function runEventAssessmentTestCepWindowNone(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the windowless CEP test.');
		}

		// Only the events generated from here on are inspected for the CEP tags.
		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// The 'service' id of every problem the scenario opens, in the order they are sent, and the rules that
		// must have tagged its event (every rule tags with its own name): one rule of every opposite pair
		// matches an id, so each event must end up with exactly six of the twelve rule tags.
		$expected_tags = [
			// The only id carrying a 'service_0' tag, so the only one the Exists rule may tag. Its event name
			// is neither equal to nor contains the "down_1" one, so both negative name rules match it.
			self::CEP_RULE_WINDOW_NONE_SERVICE => [
				self::CEP_TAG_SERVICE_EQUALS,
				self::CEP_TAG_SERVICE_CONTAINS,
				self::CEP_TAG_SERVICE_LESS_EQUAL,
				self::CEP_TAG_SERVICE_EXISTS,
				self::CEP_TAG_EVENT_NAME_NOT_EQUALS,
				self::CEP_TAG_EVENT_NAME_NOT_CONTAINS
			],
			// The id whose full event name the name Equals rule was built from, so it is the only one matched
			// by both positive name rules.
			self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT => [
				self::CEP_TAG_SERVICE_NOT_EQUALS,
				self::CEP_TAG_SERVICE_NOT_CONTAINS,
				self::CEP_TAG_SERVICE_MORE_EQUAL,
				self::CEP_TAG_SERVICE_NOT_EXISTS,
				self::CEP_TAG_EVENT_NAME_EQUALS,
				self::CEP_TAG_EVENT_NAME_CONTAINS
			],
			// Contains "0" without being equal to it, so the Contains rule matches it but the Equals rule does
			// not - the case that tells the two string pairs apart. Its own tag is 'service_10', so the Exists
			// rule (which looks for 'service_0') must not match it either, and its event name ends with
			// "down_10", which contains the "down_1" value without being equal to the "down_1" event name -
			// the same split, on the name side.
			'10' => [
				self::CEP_TAG_SERVICE_NOT_EQUALS,
				self::CEP_TAG_SERVICE_CONTAINS,
				self::CEP_TAG_SERVICE_MORE_EQUAL,
				self::CEP_TAG_SERVICE_NOT_EXISTS,
				self::CEP_TAG_EVENT_NAME_NOT_EQUALS,
				self::CEP_TAG_EVENT_NAME_CONTAINS
			]
		];

		// Each value opens one more problem (multiple event generation) that no rule ever closes, so the open
		// problem count only grows. The events of the ids sent so far are all re-verified after every value.
		$expected_so_far = [];
		$problem_count = 0;

		foreach ($expected_tags as $service => $tags) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$problem_count);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			$expected_so_far[$service] = $tags;
			$this->waitForCepWindowNoneTaggedEvents($triggerid, $expected_so_far);
		}

		// A value matching neither "down" nor "up" turns the trigger expression false, which recovers the
		// trigger and closes every problem this scenario left open.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the windowless CEP scenario recovery value');
	}

	/**
	 * Wait until the problem events generated on $triggerid since the scenario baseline are exactly the ones
	 * described by $expected_by_service - a 'service' tag value => list of tags map naming, for every event,
	 * the rules that must have tagged it (each rule tags with its own name, see getWindowNoneRules()). The tag
	 * value expected with each of them is the operand of that rule, taken from the same rule table, and every
	 * other rule tag - the tag of a rule whose condition the event does not satisfy - fails the check, so
	 * "tagged by service_equals" always means "tagged by service_equals only".
	 *
	 * The tags are applied asynchronously after the event is created, hence the polling; the callback returns
	 * a description of the first event that does not match, which callUntilDataIsPresent() surfaces in the
	 * failure message.
	 */
	private function waitForCepWindowNoneTaggedEvents(int $triggerid, array $expected_by_service): void {
		// tag => the value the rule adds it with, for every rule of the scenario.
		$rule_values = array_map(fn($rule) => $rule[1], $this->getWindowNoneRules());

		$this->callUntilDataIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['eventid', 'name'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($expected_by_service, $rule_values) {
				if (count($response['result']) !== count($expected_by_service)) {
					return 'expected '.count($expected_by_service).' problem event(s), got '
						.count($response['result']);
				}

				foreach ($response['result'] as $event) {
					$tags = array_column($event['tags'], 'value', 'tag');
					$info = 'event '.$event['eventid'].' ('.$event['name'].') tags '.json_encode($event['tags']);

					if (!array_key_exists('service', $tags)) {
						return $info.': no "service" tag';
					}

					$service = $tags['service'];

					if (!array_key_exists($service, $expected_by_service)) {
						return $info.': unexpected event with service "'.$service.'"';
					}

					$expected_tags = $expected_by_service[$service];

					foreach ($expected_tags as $expected_tag) {
						if (!array_key_exists($expected_tag, $tags)) {
							return $info.': missing "'.$expected_tag.'" tag';
						}

						if ($tags[$expected_tag] !== $rule_values[$expected_tag]) {
							return $info.': "'.$expected_tag.'" tag value "'.$tags[$expected_tag]
								.'", expected "'.$rule_values[$expected_tag].'"';
						}
					}

					// Only the rules whose conditions the event satisfies may have tagged it.
					foreach (array_keys($rule_values) as $rule_tag) {
						if (!in_array($rule_tag, $expected_tags) && array_key_exists($rule_tag, $tags)) {
							return $info.': unexpected "'.$rule_tag.'" tag added by a non-matching rule';
						}
					}
				}

				return true;
			}
		);
	}

	/**
	 * Test correlation update behavior: start with CLOSE_OLD+CLOSE_NEW, then update to CLOSE_NEW only,
	 * verify old problems stay open, then update back to CLOSE_OLD+CLOSE_NEW and verify closing works.
	 * This validates that correlation rule changes take effect on subsequent events.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpUpdateBehavior(bool $restart): void {
		// Drive a single discovered item (and its one trigger) so the whole scenario lands on one event stream.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for correlation update test.');
		}

		// Send one value with a unique id: value "<prefix>_<id>".
		$send = fn(string $prefix, int $id) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.$id]
		]);

		// 1. Open the first problem on the trigger (unique id); trigger goes TRUE.
		$send('down', 0);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 2. Open a second problem on the trigger (a different unique id, mult_event); still TRUE.
		$send('down', 1);
		$this->waitForOpenProblemCount($all, 2);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 3. Update correlation to only close new problems (not old). This will be applied to the next event.
		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseNewOnlyCorrelationParams('CEP global event correlation up', CONDITION_EVAL_TYPE_AND_OR)
		);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// 4. "up" for the first id: with updated correlation (CLOSE_NEW only), this closes only itself,
		//    not the corresponding "down_0" problem. So we still have 2 problems open (down_0 and down_1).
		$send('up', 0);
		$this->waitForOpenProblemCount($all, 2, 'After up_0 with CLOSE_NEW only, down_0 should remain open');
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 5. Update correlation back to close both old and new problems.
		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', CONDITION_EVAL_TYPE_AND_OR)
		);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// 6. "up" for the second id: with restored correlation (CLOSE_OLD+CLOSE_NEW), this closes down_1
		//    and itself, but down_0 was already created before the correlation was restored, so it needs
		//    to be closed manually or by another mechanism. We check that down_1 is closed.
		$send('up', 1);
		$this->waitForOpenProblemCount($all, 1, 'After up_1 with CLOSE_OLD+CLOSE_NEW, only down_0 remains open');
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 7. Send "down_0" recovery to close the last remaining problem.
		// For now, we'll send a different value to trigger recovery, or manually acknowledge the problem.
		// Since the trigger is based on find(regexp,"down|up"), we need to send a value that doesn't match.
		// Let's send a recovery for down_0 by changing the item value to something that doesn't match the pattern.
		$this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0']
		]);
		$this->waitForNoOpenProblems($all, 'After final recovery, all problems should be closed');
	}

	/**
	 * Same close-on-up flow as runEventAssessmentTestGlobalCorrelationCloseOnUp, but once all problems are
	 * open it downgrades every open problem's severity to WARNING via event.acknowledge and asserts the
	 * per-trigger services follow the manual severity change from DISASTER to WARNING, then closes the
	 * problems with "up" and asserts the services recover to OK.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(bool $restart): void {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>".
		$values = fn(string $prefix, int $offset) => array_map(
			fn($key, $i) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.($offset + $i)],
			$keys, array_keys($keys)
		);

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for close-on-up severity global correlation test.');
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// The trigger prototypes have DISASTER priority, so with problems open every per-trigger service
		// (matched via the SERVICE_TAG problem tag) is at DISASTER.
		$this->waitForServicesStatus(TRIGGER_SEVERITY_DISASTER);

		$this->maybeRestartServer($restart);

		// 3. Manually downgrade every open problem to WARNING. The service manager must recompute each
		//    service's status from the new problem severity, so all services drop DISASTER -> WARNING.
		$this->updateOpenProblemsSeverity($all, TRIGGER_SEVERITY_WARNING);
		$this->waitForServicesStatus(TRIGGER_SEVERITY_WARNING);

		$this->maybeRestartServer($restart);

		// 4. "up" for the first id set closes each corresponding "down" (and itself); each trigger's second
		//    problem stays open, so the services stay in problem state (still WARNING).
		$this->dispatchSenderValues($values('up', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForServicesStatus(TRIGGER_SEVERITY_WARNING);

		$this->maybeRestartServer($restart);

		// 5. "up" for the second id set closes each trigger's remaining problem; every service recovers to OK.
		$this->dispatchSenderValues($values('up', $m));
		$this->waitForNoOpenProblems($all);
		$this->waitForServicesStatus(ZBX_SEVERITY_OK);
	}

	/**
	 * Run the cross-trigger-prototype global event correlation scenario:
	 *
	 *   1. "down" → proto 1 items → find(regexp,"down") = true, service="down"
	 *                 → PROBLEM event on proto 1 triggers; proto 1 triggers go TRUE.
	 *   2. "down" → proto 2 items → find(regexp,"down") = true, service="down"
	 *                 → PROBLEM event on proto 2 triggers;
	 *                 global correlation matches new type "cep-dep" and old service="down"
	 *                 closes matched (old and new) problems
	 */
	private function runEventAssessmentTestGlobalCorrelationCrossTrigger(bool $restart): void {
		$keys1 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$keys2 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$triggerids1 = self::$discovered_triggerids;
		$triggerids2 = self::$discovered_dep_triggerids;

		// All triggers must start in OK state.
		$triggers1 = $this->getTriggers($triggerids1);
		$triggers2 = $this->getTriggers($triggerids2);
		foreach ($triggers1 as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Proto 1 triggers must start in OK state for cross-trigger global correlation test.');
		}
		foreach ($triggers2 as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Proto 2 triggers must start in OK state for cross-trigger global correlation test.');
		}

		$this->captureEventBaseline(array_merge($triggerids1, $triggerids2));
		$expected_events1 = 0;

		// 1. "down" → proto 1: PROBLEM, service="down"; proto 1 triggers go TRUE.
		$expected_events1++;
		$this->assertStateChangeForAll(
			$triggerids1, $keys1, 'down', TRIGGER_VALUE_TRUE, $expected_events1
		);
		$this->maybeRestartServer($restart);

		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'], $keys2)
		);

		$this->waitForNoOpenProblems(array_merge($triggerids1, $triggerids2));
	}

	/**
	 * Run the parity-based global event correlation scenario: open a problem on every discovered trigger,
	 * then close them in two parity-selective waves (even first, then odd — the order does not matter).
	 * The one differing knob is $close_all: false uses the per-component rules (old service="down" + new
	 * odd=parity + component tag pair, closing each component 1:1); true uses the single old-event
	 * odd=parity condition with no tag pair, so each event's unrestricted CLOSE_OLD closes the whole
	 * parity at once. Everything else — opening all problems, the parity count checks between waves, the
	 * final "nothing open" — is identical, which is the point of sharing one method.
	 */
	private function runEventAssessmentTestGlobalCorrelationParity(bool $restart,
			$evaltype = CONDITION_EVAL_TYPE_AND_OR, bool $close_all = false): void {
		$keys1 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$keys2 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$triggerids1 = self::$discovered_triggerids;
		$triggerids2 = self::$discovered_dep_triggerids;
		$all = array_merge($triggerids1, $triggerids2);

		// Per prototype: this many components carry odd="1" (odd index) and odd="0" (even index). With
		// problems open on both prototypes, twice each count is open before the parity waves run.
		$open_count = [
			'1' => 2 * intdiv(static::LLD_DISCOVERY_COUNT + 1, 2),
			'0' => 2 * intdiv(static::LLD_DISCOVERY_COUNT, 2)
		];
		$keys2_by_parity = [
			'1' => $this->buildDiscoveredKeysByParity(self::ITEM_PROTO_KEY2, '1'),
			'0' => $this->buildDiscoveredKeysByParity(self::ITEM_PROTO_KEY2, '0')
		];

		if ($close_all) {
			$keys2_by_parity['1'] = array_slice($keys2_by_parity['1'], 0, 256);
			$keys2_by_parity['0'] = array_slice($keys2_by_parity['0'], 0, 1);
		}
		$rule_name = ['1' => 'CEP global event correlation odd', '0' => 'CEP global event correlation even'];
		$build = fn(string $parity) => $close_all
			? $this->buildParityCloseAllCorrelationParams($rule_name[$parity], $parity, $evaltype)
			: $this->buildParityCorrelationParams($rule_name[$parity], $parity, $evaltype);

		// The two parities are closed in separate waves; the order between them is irrelevant.
		$first_parity = '0';
		$second_parity = '1';

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for parity global correlation test.');
		}

		$this->captureEventBaseline($all);

		// 1. Open a problem on every discovered trigger (both prototypes). No rule is active yet, so all
		//    problems stay open.
		$this->assertStateChangeForAll($triggerids1, $keys1, 'down', TRIGGER_VALUE_TRUE, 1);
		$this->maybeRestartServer($restart);
		$this->assertStateChangeForAll($triggerids2, $keys2, 'down', TRIGGER_VALUE_TRUE, 1);
		$this->maybeRestartServer($restart);

		$this->waitForOpenProblemCountByTag($all, 'odd', '1', $open_count['1']);
		$this->waitForOpenProblemCountByTag($all, 'odd', '0', $open_count['0']);

		// 2. First wave: add the $first_parity rule and re-send "down" to that parity's proto 2 keys.
		//    Those problems close; the other parity stays open.
		self::$correlationid = $this->upsertCorrelation($build($first_parity));
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'],
				$keys2_by_parity[$first_parity])
		);
		$this->waitForOpenProblemCountByTag($all, 'odd', $first_parity, 0);
		$this->waitForOpenProblemCountByTag($all, 'odd', $second_parity, $open_count[$second_parity]);
		$this->maybeRestartServer($restart);

		// 3. Second wave: add the $second_parity rule and re-send "down" to its proto 2 keys. Nothing
		//    remains open.
		self::$correlationid2 = $this->upsertCorrelation($build($second_parity));
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'],
				$keys2_by_parity[$second_parity])
		);

		$this->waitForNoOpenProblems($all);
	}

	/**
	 * Poll problem.get until the number of open problems on $triggerids carrying the exact tag
	 * $tag=$value equals $expected.
	 */
	private function waitForOpenProblemCountByTag(array $triggerids, string $tag, string $value, int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'tags' => [['tag' => $tag, 'value' => $value, 'operator' => TAG_OPERATOR_EQUAL]]
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll problem.get until the total number of open problems on $triggerids equals $expected.
	 */
	private function waitForOpenProblemCount(array $triggerids, int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], $expected, 120, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed. The 'suppressed'
	 * filter returns only suppressed problems, so a matching count means every open problem is suppressed
	 * (as expected while the host is under maintenance).
	 */
	private function waitForOpenProblemsSuppressed(array $triggerids, int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true
		], $expected, 120, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed and every one of
	 * them is suppressed by exactly the given maintenances: each problem's suppression data must list
	 * every maintenanceid from $maintenanceids and nothing else, so rows of stopped maintenances must
	 * be gone and rows of every active maintenance must be present.
	 */
	private function waitForOpenProblemsSuppressedByMaintenances(array $triggerids, int $expected,
			array $maintenanceids): void {
		$expected_ids = array_values($maintenanceids);
		sort($expected_ids);

		$this->callUntilDataIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true,
			'selectSuppressionData' => ['maintenanceid']
		], 120, self::WAIT_ITERATION_DELAY, function (array $response) use ($expected, $expected_ids) {
			if (count($response['result']) != $expected) {
				return 'expected '.$expected.' suppressed problems, got '.count($response['result']);
			}

			foreach ($response['result'] as $problem) {
				$ids = array_column($problem['suppression_data'], 'maintenanceid');
				sort($ids);

				if ($ids !== $expected_ids) {
					return 'problem '.$problem['eventid'].' is suppressed by maintenances ['.
							implode(', ', $ids).'], expected ['.implode(', ', $expected_ids).']';
				}
			}

			return true;
		});
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed and each one is
	 * suppressed by exactly the single tag-scoped maintenance matching its 'component' tag: a problem
	 * carrying component=X must be suppressed by $maintenance_by_component[X] and by nothing else. This
	 * proves the tag filter on a maintenance suppresses only the problems whose tag it matches, unlike a
	 * host-wide maintenance which suppresses every problem.
	 */
	private function waitForOpenProblemsSuppressedPerComponent(array $triggerids, int $expected,
			array $maintenance_by_component): void {
		$this->callUntilDataIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true,
			'selectTags' => ['tag', 'value'],
			'selectSuppressionData' => ['maintenanceid']
		], 120, self::WAIT_ITERATION_DELAY, function (array $response) use ($expected, $maintenance_by_component) {
			if (count($response['result']) != $expected) {
				return 'expected '.$expected.' suppressed problems, got '.count($response['result']);
			}

			foreach ($response['result'] as $problem) {
				$component_tag = current(array_filter($problem['tags'],
					fn($t) => $t['tag'] === 'component'
				));

				if ($component_tag === false) {
					return 'problem '.$problem['eventid'].' has no component tag';
				}

				$component = $component_tag['value'];

				if (!isset($maintenance_by_component[$component])) {
					return 'problem '.$problem['eventid'].' has unexpected component "'.$component.'"';
				}

				$ids = array_column($problem['suppression_data'], 'maintenanceid');
				$expected_ids = [$maintenance_by_component[$component]];

				sort($ids);
				sort($expected_ids);

				if ($ids !== $expected_ids) {
					return 'problem '.$problem['eventid'].' (component "'.$component.'") is suppressed by ['.
							implode(', ', $ids).'], expected ['.implode(', ', $expected_ids).']';
				}
			}

			return true;
		});
	}

	/**
	 * Wait until no suppressed trigger events remain on the discovered host and its services are no
	 * longer suppressed - the state expected once every maintenance is out of its active window.
	 */
	private function waitForSuppressionCleared(): void {
		$this->callUntilCountIsPresent('event.get', [
			'hostids' => [self::$disc_hostid],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => true
		], 0, 120, self::WAIT_ITERATION_DELAY);

		$this->waitForServicesNoLongerSuppressed();
	}

	private function runDependentTriggerTest(bool $restart): void {
		$parent_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$dep_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$parent_ids = self::$discovered_triggerids;
		$dep_ids = self::$discovered_dep_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers(array_merge($parent_ids, $dep_ids));
		foreach ($triggers as $id => $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state.');
		}

		$this->captureEventBaseline(array_merge($parent_ids, $dep_ids));
		$parent_event_count = 0;
		$dep_event_count = 0;

		// 1. Parent OK→PROBLEM.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$this->maybeRestartServer($restart);

		// 2. Dependents suppressed while parents are PROBLEM.
		$this->assertNoStateChangeForAll($dep_ids, $dep_keys, '2', TRIGGER_VALUE_FALSE, $dep_event_count);
		$this->maybeRestartServer($restart);

		// 3. Parent PROBLEM→OK: dependents fire.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);

		$this->assertStateChangeForAll($dep_ids, $dep_keys, '1', TRIGGER_VALUE_TRUE, $dep_event_count + 1, false);
		$this->maybeRestartServer($restart);

		// 4. Dependent PROBLEM→OK: dependents recover.
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_FALSE, $dep_event_count + 2);
		$this->maybeRestartServer($restart);

		$parent_event_count += 2;
		$dep_event_count += 2;
		$dep_triggers = $this->getTriggers($dep_ids);
		$dep_lastchanges = array_map(fn($tid) => $dep_triggers[$tid]['lastchange'], $dep_ids);

		// 5. Parent OK→PROBLEM; dep condition was never true → deps must stay OK.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$dep_triggers = $this->getTriggers($dep_ids);
		foreach ($dep_ids as $dep_id) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $dep_triggers[$dep_id]['value'],
				'Dependent must stay OK while parent is PROBLEM and dep condition is not met.');
		}
		$this->maybeRestartServer($restart);

		// 6. Parent PROBLEM→OK; dep condition still false → deps stay OK throughout.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);
		$this->maybeRestartServer($restart);

		$parent_event_count += 2;

		// 7. Deps fire normally while parents are OK.
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '1', TRIGGER_VALUE_TRUE, $dep_event_count + 1);
		$this->maybeRestartServer($restart);

		// 8. Parents fire; deps are already PROBLEM.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$this->maybeRestartServer($restart);

		// 9. Dep recovery suppressed while parents are PROBLEM; deps stay PROBLEM.
		$this->assertNoStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_TRUE, $dep_event_count + 1);
		$this->maybeRestartServer($restart);

		// 10. Parents recover; deps recover as well.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_FALSE, $dep_event_count + 2);

		/*$this->runIntermingledDependentTriggerBatch($restart, $parent_event_count + 2);*/
	}

	private function runIntermingledDependentTriggerBatch(bool $restart, int $parent_event_count): void {
		$parent_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$dep_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$parent_ids = self::$discovered_triggerids;

		// 1. Intermingled batch: parent PROBLEM + dep PROBLEM values arrive in the same
		//    sender packet (parent_key, dep_key, parent_key, dep_key, ...); only the parent
		//    triggers are asserted.
		$intermingled = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '1'];
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '1'];
		}
		$this->dispatchSenderValues($intermingled);

		$this->waitForParentsValue($parent_ids, TRIGGER_VALUE_TRUE);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 1);

		$this->maybeRestartServer($restart);

		// 2. Intermingled recovery: parent OK + dep OK values in one packet
		//    (parent_key, dep_key, parent_key, dep_key, ...); only parents are asserted.
		$intermingled_recovery = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '0'];
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '0'];
		}
		$this->dispatchSenderValues($intermingled_recovery);

		$this->waitForParentsValue($parent_ids, TRIGGER_VALUE_FALSE);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 2);

		// When the parent recovered in the intermingled batch above, dependency suppression lifted while a
		// dependent's last value could still be '1' (processed before its own '0' in the same packet), so a
		// dependent problem may have opened. Send a final dep '0' to deterministically clear any such
		// leftover before asserting no open problems.
		$dep_recovery = [];
		foreach ($dep_keys as $dkey) {
			$dep_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dkey, 'value' => '0'];
		}
		$this->dispatchSenderValues($dep_recovery);

		// After recovery no problems must remain open on either the parent or the dependent triggers.
		$this->waitForNoOpenProblems(array_merge($parent_ids, self::$discovered_dep_triggerids),
			'intermingled batch recovery', false);
	}

	/**
	 * Wait until every parent trigger reached $expected_value in NORMAL state. The callback returns a
	 * descriptive string on mismatch (surfaced in the callUntilDataIsPresent failure message) rather
	 * than a bare false.
	 */
	private function waitForParentsValue(array $parent_ids, int $expected_value): void {
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $parent_ids,
			'output' => ['triggerid', 'value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($parent_ids, $expected_value) {
				$by_id = array_column($response['result'], null, 'triggerid');
				foreach ($parent_ids as $tid) {
					if (!isset($by_id[$tid])) {
						return 'trigger '.$tid.' missing from response';
					}
					$t = $by_id[$tid];
					if ((int) $t['value'] !== $expected_value) {
						return 'trigger '.$tid.' value '.$t['value'].', expected '.$expected_value;
					}
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) {
						return 'trigger '.$tid.' state '.$t['state'].', expected NORMAL';
					}
				}
				return true;
			}
		);
	}

	/**
	 * Verify that after restoring expression-based recovery the discovered trigger
	 * (which is stuck PROBLEM from the None-mode run) can now recover via assertStateChange.
	 */
	private function assertRecoveryAfterRestore(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must still be PROBLEM from the None-mode run.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$this->assertEquals(TRIGGER_VALUE_TRUE, $triggers[$triggerid]['value'],
				'trigger #'.$idx.' must still be PROBLEM before recovery-after-restore check.');
		}
		$this->captureEventBaseline($triggerids);
		$event_count = 0;

		$this->maybeRestartServer($restart);

		// Send recovery value – now that recovery mode is expression, a RESOLVED event must fire.
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $event_count + 1);

		$this->waitForNoOpenProblems($triggerids, 'After recovery-after-restore');
	}

	/**
	 * Collect open problem event IDs for all given triggers, verify that manual close is
	 * rejected while the flag is off, then enable manual_close on the trigger prototypes,
	 * resend LLD discovery data so the change propagates to the discovered triggers, reload
	 * the configuration cache, close all problems, and wait for every trigger to return to OK.
	 */
	private function closeTagCorrelationProblems(array $triggerids): void {
		// Collect the event ID of the open problem for every trigger.
		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);
		$this->assertCount(count($triggerids), $response['result'], 'Expected exactly one open problem per trigger: '.json_encode($response));
		$problem_eventids = array_column($response['result'], 'eventid');

		// Manual close must be rejected because the manual_close flag is off.
		$ack_response = CAPIHelper::call('event.acknowledge', [
			'eventids' => [$problem_eventids[0]],
			'action' => ZBX_PROBLEM_UPDATE_CLOSE,
			'message' => 'Manual close for tag-correlation mode test'
		]);
		$this->assertArrayHasKey('error', $ack_response,
			'Expected manual close to be rejected, but it succeeded: '.json_encode($ack_response));

		// Enable manual close on the trigger prototypes so the setting propagates to all
		// discovered triggers after LLD re-discovery.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
		]);
		$this->callUntilDataIsPresent('triggerprototype.get', [
			'triggerids' => [self::$trigger_prototypeid],
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});
		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
		]);
		$this->callUntilDataIsPresent('triggerprototype.get', [
			'triggerids' => [self::$dep_trigger_prototypeid],
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});

		// Resend LLD discovery data so the server re-instantiates discovered triggers
		// with the updated prototype configuration.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Wait for the discovered triggers to reflect the updated manual_close setting.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
			if (count($response['result']) !== count($triggerids)) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['manual_close'] !== ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED) {
					return false;
				}
			}
			return true;
		});

		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->call('event.acknowledge', [
			'eventids' => $problem_eventids,
			'action' => ZBX_PROBLEM_UPDATE_CLOSE,
			'message' => 'Manual close for tag-correlation mode test'
		]);

		$this->waitForNoOpenProblems($triggerids);
	}

	public function triggerCEP_Cleanup() {
		// Send empty log LLD data to remove the discovered log item and trigger.
		if (!empty(self::$discovered_log_triggerid)) {
			$this->dispatchSenderValues([
				[
					'host' => self::HOST_NAME,
					'key' => self::LOG_LLD_RULE_KEY,
					'value' => json_encode(['data' => []])
				]
			]);

			$this->callUntilCountIsPresent('trigger.get', [
				'triggerids' => [self::$discovered_log_triggerid]
			], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

			self::$discovered_log_triggerid = null;
			$this->reloadConfigurationCacheAndWaitForLogLine();
		}

		$triggerids = array_filter([self::$discovered_triggerid, self::$discovered_dep_triggerid]);

		if (!$triggerids) {
			return;
		}

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		]);

		$this->callUntilCountIsPresent('trigger.get', [
			'triggerids' => $triggerids
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		self::$discovered_triggerid = null;
		self::$discovered_dep_triggerid = null;
		self::$discovered_triggerids = [];
		self::$discovered_dep_triggerids = [];
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete all history and trend data for items on the discovered host.
	 * Must be called before test phases that use history-sensitive functions (e.g. change()).
	 */
	private function clearDiscoveredItemHistory(): void {
		$response = $this->call('item.get', [
			'hostids' => [self::$disc_hostid],
			'output' => ['itemid']
		]);

		if (empty($response['result'])) {
			return;
		}

		CDataHelper::removeItemData(array_column($response['result'], 'itemid'));
	}

	private function maybeRestartServer(bool $restart): void {
		if (!$restart) {
			return;
		}
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
	}

	/**
	 * Put the discovered host into data-collection maintenance and wait for the server to start it, so any
	 * problem opened afterwards is suppressed. Returns the maintenance id for stopDiscHostMaintenance().
	 */
	private function startDiscHostMaintenance(string $name): string {
		$maintenanceid = $this->upsertDiscHostMaintenance($name);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return $maintenanceid;
	}

	/**
	 * Create a data-collection maintenance for the discovered host with an active period covering now, or,
	 * if a maintenance with the given name already exists (left over from a previous run), update it back
	 * into an active window. Returns the maintenance id.
	 */
	private function upsertDiscHostMaintenance(string $name): string {
		$maintenanceids = $this->upsertDiscHostMaintenances([$name]);

		return $maintenanceids[0];
	}

	/**
	 * Bulk variant of upsertDiscHostMaintenance(): one maintenance.get to find leftovers by name, then a
	 * single maintenance.update for the existing ones and a single maintenance.create for the rest.
	 * Returns the maintenance ids in the same order as the given names.
	 *
	 * $extra_by_name optionally maps a name to extra maintenance fields (e.g. a tag filter) merged on top
	 * of the host-wide defaults, so a caller can scope individual maintenances without duplicating the
	 * leftover-safe upsert logic.
	 */
	private function upsertDiscHostMaintenances(array $names, array $extra_by_name = []): array {
		if (empty($names)) {
			return [];
		}

		$now = time();
		$defaults = [
			'hosts' => ['hostid' => self::$disc_hostid],
			'active_since' => $now - 60,
			'active_till' => $now + 3600,
			'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 3600,
				'start_date' => $now - 60
			]
		];

		$response = $this->call('maintenance.get', [
			'output' => ['maintenanceid', 'name'],
			'filter' => ['name' => $names]
		]);

		$ids_by_name = [];
		foreach ($response['result'] as $maintenance) {
			$ids_by_name[$maintenance['name']] = $maintenance['maintenanceid'];
		}

		$updates = [];
		$creates = [];
		foreach ($names as $name) {
			$fields = isset($extra_by_name[$name]) ? array_merge($defaults, $extra_by_name[$name]) : $defaults;

			if (isset($ids_by_name[$name])) {
				$updates[] = array_merge(['maintenanceid' => $ids_by_name[$name]], $fields);
			}
			else {
				$creates[] = array_merge(['name' => $name], $fields);
			}
		}

		if (!empty($updates)) {
			$this->call('maintenance.update', $updates);
		}

		if (!empty($creates)) {
			$response = $this->call('maintenance.create', $creates);
			$this->assertArrayHasKey('maintenanceids', $response['result']);
			$this->assertCount(count($creates), $response['result']['maintenanceids']);

			foreach ($creates as $index => $maintenance) {
				$ids_by_name[$maintenance['name']] = $response['result']['maintenanceids'][$index];
			}
		}

		$maintenanceids = [];
		foreach ($names as $name) {
			$maintenanceids[] = $ids_by_name[$name];
		}

		return $maintenanceids;
	}

	/**
	 * End a maintenance created by startDiscHostMaintenance() without deleting it: push its active period
	 * far into the future so it is no longer active now, then reload the configuration cache so the host
	 * leaves maintenance before the rest of the suite runs.
	 */
	private function stopDiscHostMaintenance(string $maintenanceid): void {
		$this->stopDiscHostMaintenances([$maintenanceid]);
	}

	/**
	 * Bulk variant of stopDiscHostMaintenance(): push the active period of all given maintenances out of
	 * the current window with a single maintenance.update call.
	 */
	private function stopDiscHostMaintenances(array $maintenanceids): void {
		if (empty($maintenanceids)) {
			return;
		}

		// Ten years ahead - a start that will never come within the test run, so the maintenances stay
		// defined but idle and the host is taken out of maintenance.
		$future = time() + 10 * 365 * 24 * 3600;

		$maintenances = [];
		foreach ($maintenanceids as $maintenanceid) {
			$maintenances[] = [
				'maintenanceid' => $maintenanceid,
				'active_since' => $future,
				'active_till' => $future + 3600,
				'timeperiods' => [
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => 3600,
					'start_date' => $future
				]
			];
		}

		$this->call('maintenance.update', $maintenances);
	}

	/**
	 * Bring maintenances ended by stopDiscHostMaintenances() back into an active window covering now
	 * with a single maintenance.update call, then reload the configuration cache so the host re-enters
	 * maintenance.
	 */
	private function resumeDiscHostMaintenances(array $maintenanceids): void {
		if (empty($maintenanceids)) {
			return;
		}

		$now = time();

		$maintenances = [];
		foreach ($maintenanceids as $maintenanceid) {
			$maintenances[] = [
				'maintenanceid' => $maintenanceid,
				'active_since' => $now - 60,
				'active_till' => $now + 3600,
				'timeperiods' => [
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => 3600,
					'start_date' => $now - 60
				]
			];
		}

		$this->call('maintenance.update', $maintenances);

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create multiple maintenances for the discovered host and reload configuration cache once after all are created.
	 */
	private function startDiscHostMaintenances(int $count): void {
		$start = count(self::$disc_maintenanceids) + 1;

		$names = [];
		for ($i = $start; $i < $start + $count; $i++) {
			$names[] = 'CEP close-on-up maintenance'.$i;
		}

		self::$disc_maintenanceids = array_merge(self::$disc_maintenanceids, $this->upsertDiscHostMaintenances($names));

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create one maintenance per discovered component, each scoped to that component via a 'component'
	 * problem-tag filter (host + tag), so a maintenance suppresses only the problems carrying its own
	 * component tag rather than every problem on the host. Appends the created ids to
	 * self::$disc_maintenanceids and reloads the configuration cache once. Returns a map of
	 * component value => maintenanceid so callers can assert which maintenance must suppress each problem.
	 */
	private function startDiscHostTagMaintenances(): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');

		$names = [];
		$extra_by_name = [];
		$component_by_name = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$component = $base.$i;
			$name = 'CEP per-tag maintenance '.$component;

			$names[] = $name;
			$component_by_name[$name] = $component;
			$extra_by_name[$name] = [
				'tags' => [
					['tag' => 'component', 'operator' => MAINTENANCE_TAG_OPERATOR_EQUAL, 'value' => $component]
				]
			];
		}

		$ids = $this->upsertDiscHostMaintenances($names, $extra_by_name);
		self::$disc_maintenanceids = array_merge(self::$disc_maintenanceids, $ids);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		$maintenance_by_component = [];
		foreach ($names as $index => $name) {
			$maintenance_by_component[$component_by_name[$name]] = $ids[$index];
		}

		return $maintenance_by_component;
	}

	/**
	 * Skip the calling *Restart test when SKIP_RESTART_TESTS is enabled. The non-restart sibling
	 * leaves the system in the same asserted state, so dependents can rely on it instead.
	 */
	private function skipIfRestartTestsDisabled(): void {
		if (static::SKIP_RESTART_TESTS) {
			$this->markTestSkipped('Restart test variants disabled via SKIP_RESTART_TESTS.');
		}
	}

	/**
	 * Skip the calling service-specific test when SKIP_SERVICES_TESTS is enabled. Skipping the root
	 * testTriggerCEP_AddServices cascades to its dependents via @depends, so the suite runs without the
	 * per-trigger services and their actions.
	 */
	private function skipIfServicesTestsDisabled(): void {
		$skip_services_tests = static::SKIP_SERVICES_TESTS;

		if ($skip_services_tests === null) {
			$skip_services_tests = (time() % 2 === 0);
		}

		if ($skip_services_tests) {
			$this->markTestSkipped('Service test variants disabled via SKIP_SERVICES_TESTS.');
		}
	}

	private function buildItemLLDData(bool $with_parity = false): string {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$data = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$entry = [self::LLD_MACRO => $base.$i];
			if ($with_parity) {
				// Odd component index → '1', even → '0'. Consumed by the trigger prototype 'odd'
				// tag so each discovered problem carries its parity for parity-based correlation.
				$entry[self::PARITY_MACRO] = ($i % 2 === 1) ? '1' : '0';
			}
			$data[] = $entry;
		}
		return json_encode(['data' => $data]);
	}

	private function buildDiscoveredKeys(string $proto_key): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$keys = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$keys[] = $proto_key.'['.$base.$i.']';
		}
		return $keys;
	}

	/**
	 * Like buildDiscoveredKeys() but only the keys of components matching the given parity
	 * ('1' = odd index, '0' = even index), matching the {#PARITY} macro emitted by buildItemLLDData().
	 */
	private function buildDiscoveredKeysByParity(string $proto_key, string $parity): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$keys = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			if ((($i % 2 === 1) ? '1' : '0') === $parity) {
				$keys[] = $proto_key.'['.$base.$i.']';
			}
		}
		return $keys;
	}

	/**
	 * Drop-in replacement for sendSenderValues() that delivers the values to the server as if they came
	 * from the active proxy (PROXY_NAME) instead of as direct sender/trapper data. Each value is given as
	 * ['host' => ..., 'key' => ..., 'value' => ...] (plus optional 'clock', 'ns' and 'state'); the host/key
	 * pair is translated to the item id the proxy data protocol requires and the batch is handed to
	 * dispatchValues(). The $component argument is accepted for call-site compatibility with
	 * sendSenderValues() but ignored — values always target the server through the proxy.
	 *
	 * Because the server skips preprocessing for proxy-delivered values, an item can only be reported as
	 * unsupported by setting 'state' => ITEM_STATE_NOTSUPPORTED explicitly (the value is then taken as the
	 * error text); a non-numeric value alone would be dropped rather than turning the item unsupported.
	 */
	protected function dispatchSenderValues($values, $component = null, $delayOverride = 0): array {
		$this->ensureItemidsResolved($values);

		$data = [];
		foreach (array_values($values) as $value) {
			// Fall back to a strictly increasing (clock, ns) so any value without an explicit timestamp is
			// still globally unique and ordered, even across batches.
			$cn = (!isset($value['clock']) || !isset($value['ns'])) ? $this->currentClockNs() : null;
			$entry = [
				'itemid' => self::$itemid_cache[$value['host']."\0".$value['key']],
				'value' => $value['value'],
				'clock' => isset($value['clock']) ? $value['clock'] : $cn['clock'],
				'ns' => isset($value['ns']) ? $value['ns'] : $cn['ns']
			];
			if (isset($value['state'])) {
				$entry['state'] = $value['state'];
			}
			$data[] = $entry;
		}

		$this->dispatchValues($data, $delayOverride);

		// Return the enriched entries (with resolved itemid and the assigned clock/ns) so callers can keep a
		// reference of exactly what was sent and, on an event mismatch, pinpoint which (clock, ns) is missing.
		return $data;
	}

	/**
	 * Deliver item id based history values to the server impersonating the active proxy. Subclasses that
	 * run a real proxy daemon can override this to route the values through the proxy instead.
	 */
	protected function dispatchValues(array $values, $delayOverride = 0): void {
		$this->sendAgentDataValues($values, self::HOST_NAME, self::COMPONENT_SERVER, $delayOverride,
			self::PROXY_NAME);
	}

	/**
	 * Populate self::$itemid_cache for every host/key pair in $values that is not cached yet, then return.
	 * Idempotent and cheap to call on every dispatch: when all pairs are already cached it makes no API
	 * call at all. Discovered items and the master/log items are resolved via item.get; LLD rules (which
	 * item.get does not return) fall back to discoveryrule.get. Missing pairs are gathered first and
	 * resolved in bulk per host, so a batch of thousands of discovered keys costs a couple of API calls on
	 * first use and none afterwards.
	 */
	private function ensureItemidsResolved(array $values): void {
		$need = [];
		foreach ($values as $value) {
			$ck = $value['host']."\0".$value['key'];
			if (!isset(self::$itemid_cache[$ck])) {
				$need[$value['host']][$value['key']] = true;
			}
		}

		foreach ($need as $host => $keymap) {
			$hostid = $this->hostidByName($host);

			$response = $this->call('item.get', [
				'hostids' => [$hostid],
				'filter' => ['key_' => array_keys($keymap)],
				'output' => ['itemid', 'key_'],
				'webitems' => true
			]);
			foreach ($response['result'] as $item) {
				self::$itemid_cache[$host."\0".$item['key_']] = (int) $item['itemid'];
				unset($keymap[$item['key_']]);
			}

			if ($keymap) {
				$response = $this->call('discoveryrule.get', [
					'hostids' => [$hostid],
					'filter' => ['key_' => array_keys($keymap)],
					'output' => ['itemid', 'key_']
				]);
				foreach ($response['result'] as $rule) {
					self::$itemid_cache[$host."\0".$rule['key_']] = (int) $rule['itemid'];
					unset($keymap[$rule['key_']]);
				}
			}

			$this->assertEmpty($keymap,
				'Could not resolve item id(s) on host "'.$host.'" for proxy dispatch: '
					.implode(', ', array_keys($keymap)));
		}
	}

	private function hostidByName(string $host): int {
		if (!isset(self::$hostid_cache[$host])) {
			$response = $this->call('host.get', [
				'filter' => ['host' => $host],
				'output' => ['hostid']
			]);
			$this->assertCount(1, $response['result'], 'Host "'.$host.'" not found for proxy dispatch.');
			self::$hostid_cache[$host] = (int) $response['result'][0]['hostid'];
		}

		return self::$hostid_cache[$host];
	}

	/**
	 * Enable or disable a built-in action by name (used for the internal "Report unknown triggers"
	 * and "Report not supported items" actions).
	 */
	private function setInternalActionStatus(string $name, int $status): void {
		$response = $this->call('action.get', [
			'output' => ['actionid'],
			'filter' => ['name' => $name]
		]);
		$this->assertNotEmpty($response['result'], 'Action "'.$name.'" not found.');
		$this->call('action.update', [
			'actionid' => $response['result'][0]['actionid'],
			'status' => $status
		]);
	}

	/**
	 * Re-enable the built-in internal "Report not supported items" and "Report unknown triggers" actions
	 * and reload the configuration cache so the server starts generating internal item-not-supported /
	 * trigger-unknown events. Used by the *Unknown tests when SCOPED_INTERNAL_ACTIONS disables the built-in
	 * internal actions in prepareData().
	 */
	private function enableInternalActions(): void {
		$this->setInternalActionStatus('Report not supported items', ACTION_STATUS_ENABLED);
		$this->setInternalActionStatus('Report unknown triggers', ACTION_STATUS_ENABLED);

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Disable every internal-source action so the server generates no internal item-not-supported /
	 * trigger-unknown events. Called from prepareData() (to clear the built-in actions for the whole
	 * suite) and after the *Unknown tests to disable the actions they enabled via enableInternalActions().
	 */
	private function disableInternalActions(): void {
		$response = $this->call('action.get', [
			'output' => ['actionid'],
			'filter' => ['eventsource' => EVENT_SOURCE_INTERNAL]
		]);
		if (!empty($response['result'])) {
			$this->call('action.update', array_map(
				fn($actionid) => ['actionid' => $actionid, 'status' => ACTION_STATUS_DISABLED],
				array_column($response['result'], 'actionid')
			));
		}
	}

	/**
	 * Capture the highest internal-source eventid currently recorded and store it as the *Unknown cycle
	 * baseline. waitForInternalAlertsCompleted() then restricts its alert wait to the events generated after
	 * this point (eventid greater than the baseline), so the wait counts only this cycle's notifications
	 * regardless of how many internal-source alerts already accumulated in the database.
	 */
	private function captureInternalEventBaseline(): int {
		$response = $this->call('event.get', [
			'source' => EVENT_SOURCE_INTERNAL,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		self::$internal_event_baseline_id = empty($response['result'])
			? 0 : (int) $response['result'][0]['eventid'];

		return self::$internal_event_baseline_id;
	}

	/**
	 * Wait until every notification generated by the current *Unknown cycle has been delivered, so that the
	 * caller can safely disable the internal actions without the escalator dropping a queued alert.
	 *
	 * runOpenUnknownTest() opens one internal problem per unsupported item and per unknown trigger
	 * (LLD_DISCOVERY_COUNT each) and runCloseUnknownTest() recovers all of them, so the internal actions add
	 * 2 * LLD_DISCOVERY_COUNT notifications for this cycle. Rather than trusting a total-count delta against
	 * a pre-cycle baseline, the alerts are anchored to the events generated after captureInternalEventBaseline():
	 * every internal event (problem and recovery) already exists by now (runCloseUnknownTest() waited for all
	 * problems to resolve), so their eventids are resolved here and the alert wait is restricted to them.
	 * alert.get has no eventid range filter, hence the explicit eventid list. We first wait for all of this
	 * cycle's alerts to be created (they lag the problem.get resolution the caller already checked, since the
	 * escalator produces them only after processing the events), then wait for none to be left queued.
	 */
	private function waitForInternalAlertsCompleted(): void {
		// Resolve the events generated by this cycle, i.e. those with an eventid past the baseline captured
		// in runOpenUnknownTest(). Their alerts are the only ones this wait may count.
		$response = $this->call('event.get', [
			'source' => EVENT_SOURCE_INTERNAL,
			'eventid_from' => self::$internal_event_baseline_id + 1,
			'output' => ['eventid']
		]);
		$eventids = array_column($response['result'], 'eventid');

		$expected_alerts = 2 * static::LLD_DISCOVERY_COUNT;

		// All notifications of this cycle must have been created ...
		$this->callUntilCountIsPresent('alert.get', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'eventids' => $eventids
		], $expected_alerts, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// ... and none may still be queued for delivery (NEW or NOT_SENT).
		$this->callUntilCountIsPresent('alert.get', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'eventids' => $eventids,
			'filter' => ['status' => [ALERT_STATUS_NEW, ALERT_STATUS_NOT_SENT]]
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	private function validateTriggerParams($expected_state, $expected_value) {
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => self::$discovered_triggerids,
			'output' => ['triggerid', 'value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_state, $expected_value) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ($trigger['state'] != $expected_state || $trigger['value'] != $expected_value) {
					return false;
				}
			}
			return true;
		});

		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals($expected_state, $trigger['state'], 'Unexpected trigger state.');
			$this->assertEquals($expected_value, $trigger['value'], 'Unexpected trigger value.');
		}
	}

	/**
	 * Capture the highest eventid currently recorded for the given triggers. The returned value
	 * is stored as the scenario baseline so that subsequent event.get queries only retrieve events
	 * generated after this point (see $event_baseline_id and waitForAllTriggerEventCounts), keeping
	 * the queries bounded regardless of how much event history has accumulated. Per-trigger event
	 * counts are then expressed as deltas relative to this baseline (so they start at 0).
	 */
	private function captureEventBaseline(array $triggerids): int {
		$response = $this->call('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		$this->event_baseline_id = empty($response['result']) ? 0 : (int) $response['result'][0]['eventid'];

		return $this->event_baseline_id;
	}

	private function waitForTriggerEventCount(int $triggerid, int $expected_count): array {
		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'output' => ['eventid', 'name', 'value', 'clock']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_count) {
			return count($response['result']) === $expected_count;
		});
		return $response['result'];
	}

	/**
	 * Wait until exactly $expected_count events per trigger have been generated since the scenario
	 * baseline. All triggers are fed identical values, so their per-trigger counts move together;
	 * waiting on the total lets the server aggregate (countOutput) instead of fetching and counting
	 * every event row on each poll iteration. callUntilCountIsPresent requires exact equality, so a
	 * missing or extra event on any trigger keeps the total off-target and fails the wait.
	 */
	private function waitForAllTriggerEventCounts(array $triggerids, int $expected_count,
			?callable $info_callback = null): void {
		// eventid_from is inclusive, so +1 excludes the baseline event itself. A target of 0
		// (no state change expected) is handled too: the count returns 0 immediately, and any
		// spurious event keeps it off-target and fails the wait. $info_callback (if given) is invoked
		// only on the final failed iteration to append a diagnostic to the failure message.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], count($triggerids) * $expected_count,
			self::STATE_CHANGE_WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, null, $info_callback
		);
	}

	/**
	 * Fetch the events generated since the scenario baseline, grouped by trigger (newest first).
	 * Only needed where the event values themselves are asserted; most callers just wait on the
	 * count via waitForAllTriggerEventCounts().
	 */
	private function getScenarioEventsByTrigger(array $triggerids): array {
		$response = $this->call('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'output' => ['value', 'objectid']
		]);

		$events_by_trigger = array_fill_keys($triggerids, []);
		foreach ($response['result'] as $event) {
			$events_by_trigger[(int) $event['objectid']][] = $event;
		}
		return $events_by_trigger;
	}

	/**
	 * Build a human-readable diagnostic for a burst that produced the wrong number of events. Each sent
	 * value in $sent (as returned by dispatchSenderValues(), carrying the assigned clock/ns) is expected to
	 * flip the trigger and thus produce exactly one event with the same (clock, ns). This fetches the
	 * events generated since the baseline and reports, per sent offset, which (clock, ns) never produced an
	 * event and which events were emitted with no matching sent value, so a dropped or duplicated value is
	 * pinned to its exact offset and timestamp instead of surfacing only as a count mismatch.
	 */
	private function diagnoseMissingBurstEvents(int $triggerid, array $sent): string {
		$response = $this->call('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => 'ASC',
			'output' => ['eventid', 'value', 'clock', 'ns']
		]);

		// Index the emitted events by their (clock, ns) so each sent value can be looked up directly.
		$events_by_key = [];
		foreach ($response['result'] as $event) {
			$events_by_key[$event['clock']."\0".$event['ns']][] = $event;
		}

		$lines = ['event diagnostic for trigger '.$triggerid.': sent '.count($sent).' value(s), got '
			.count($response['result']).' event(s)'];

		foreach ($sent as $offset => $entry) {
			$key = $entry['clock']."\0".$entry['ns'];
			if (isset($events_by_key[$key]) && $events_by_key[$key] !== []) {
				array_shift($events_by_key[$key]);
			}
			else {
				$lines[] = 'MISSING event for offset '.$offset.' value='.$entry['value']
					.' clock='.$entry['clock'].' ns='.$entry['ns'];
			}
		}

		// Any events left over matched no sent value (e.g. a duplicate emitted for one value).
		foreach ($events_by_key as $key => $leftover) {
			foreach ($leftover as $event) {
				$lines[] = 'UNEXPECTED event eventid='.$event['eventid'].' value='.$event['value']
					.' clock='.$event['clock'].' ns='.$event['ns'].' (no matching sent value)';
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Wait until exactly $expected PROBLEM events since the scenario baseline carry the $tag tag on
	 * $triggerids. Used to verify that tags returned by a webhook media type are applied to the events they
	 * were generated for. Uses a server-side count (countOutput + a tag-exists filter) instead of fetching
	 * every event and its tags, so the query cost stays flat regardless of how many events were generated.
	 */
	private function waitForProblemEventsTagged(array $triggerids, string $tag, int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'tags' => [['tag' => $tag, 'operator' => TAG_OPERATOR_EXISTS]]
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	private function getTriggers(array $triggerids): array {
		$response = $this->call('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['triggerid', 'value', 'lastchange', 'state', 'recovery_mode', 'type', 'correlation_mode']
		]);
		return array_column($response['result'], null, 'triggerid');
	}

	/**
	 * Assert that every trigger currently has the expected value. Instead of failing on the first
	 * mismatch, all triggers are checked and the failure message reports how many were correct, how
	 * many were wrong and the full data of every wrong one.
	 */
	private function assertAllTriggerValues(array $triggerids, int $expected_value, string $info): void {
		$triggers = $this->getTriggers($triggerids);
		$wrong = [];
		foreach ($triggerids as $idx => $triggerid) {
			if (!isset($triggers[$triggerid]) || (int) $triggers[$triggerid]['value'] !== $expected_value) {
				$wrong[] = 'trigger #'.$idx.': '
					.(isset($triggers[$triggerid]) ? json_encode($triggers[$triggerid]) : 'missing from trigger.get');
			}
		}
		$this->assertCount(0, $wrong, $info.': expected value '.$expected_value.' on all '.count($triggerids)
			.' triggers, '.(count($triggerids) - count($wrong)).' correct, '.count($wrong).' wrong: '
			.implode('; ', $wrong));
	}

	/**
	 * Assert that sending $item_value produces a new event but trigger stays PROBLEM.
	 * Used for partial tag-correlation recoveries where one tagged problem closes while
	 * another remains open, so the trigger value stays TRUE and lastchange is not updated.
	 */
	private function assertPartialRecoveryForAll(array $triggerids, array $keys, string $item_value,
			int $expected_event_count): void {
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);
		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers[$triggerid];
			$info = 'trigger #'.$idx.' after '.$item_value.': '.json_encode($trigger);
			$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'], $info);
			$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], $info);
			$this->assertEquals(TRIGGER_VALUE_FALSE, $events_by_trigger[$triggerid][0]['value'], $info);
		}
	}

	private function assertStateChangeForAll(array $triggerids, array $keys, string $item_value,
			int $expected_trigger_value, int $expected_event_count, bool $check_lastchange = true): void {
		$now = time();
		$prev_triggers = $this->getTriggers($triggerids);
		$prev_lastchanges = array_map(fn($tid) => $prev_triggers[$tid]['lastchange'], $triggerids);
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);

		$trigger_params = [
			'triggerids' => $triggerids,
			'output' => ['triggerid', 'value', 'lastchange', 'state', 'recovery_mode', 'type', 'correlation_mode']
		];
		$this->callUntilDataIsPresent('trigger.get', $trigger_params,
			self::STATE_CHANGE_WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($triggerids, $expected_trigger_value, $prev_lastchanges, $now, $check_lastchange) {
				$by_id = array_column($response['result'], null, 'triggerid');
				$missing = 0;
				$wrong_value = 0;
				$wrong_state = 0;
				$wrong_lastchange = 0;
				$failing_triggerid = null;
				foreach ($triggerids as $idx => $triggerid) {
					if (!isset($by_id[$triggerid])) {
						$missing++;
						if ($failing_triggerid === null) {
							$failing_triggerid = $triggerid;
						}
						continue;
					}
					$t = $by_id[$triggerid];
					$wrong = false;
					if ((int) $t['value'] !== $expected_trigger_value) {
						$wrong_value++;
						$wrong = true;
					}
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) {
						$wrong_state++;
						$wrong = true;
					}
					if ($check_lastchange && $now > $prev_lastchanges[$idx]
							&& (int) $t['lastchange'] <= $prev_lastchanges[$idx]) {
						$wrong_lastchange++;
						$wrong = true;
					}
					if ($wrong && $failing_triggerid === null) {
						$failing_triggerid = $triggerid;
					}
				}
				if ($missing > 0 || $wrong_value > 0 || $wrong_state > 0 || $wrong_lastchange > 0) {
					$last_event_name = '<none>';
					$events = $this->call('event.get', [
						'objectids' => [$failing_triggerid],
						'source' => EVENT_SOURCE_TRIGGERS,
						'object' => EVENT_OBJECT_TRIGGER,
						'output' => ['name'],
						'sortfield' => ['clock', 'eventid'],
						'sortorder' => ZBX_SORT_DOWN,
						'limit' => 1
					]);
					if (!empty($events['result'])) {
						$last_event_name = $events['result'][0]['name'];
					}
					return 'of '.count($triggerids).' triggers: '.$missing.' missing, '.$wrong_value.
							' wrong value (expected '.$expected_trigger_value.'), '.$wrong_state.
							' wrong state (expected NORMAL), '.$wrong_lastchange.' lastchange not updated now:'.$now.
							'; last event of failing trigger '.$failing_triggerid.': "'.$last_event_name.'"';
				}
				return true;
			}
		);

		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
	}

	private function assertNoStateChangeForAll(array $triggerids, array $keys, string $item_value,
			int $expected_trigger_value, int $expected_event_count): void {
		$current_triggers = $this->getTriggers($triggerids);

		$vps_written = $this->getVpsWritten();
		//$cep_processed = $this->getCepStat('events', 'assessed');
		$expected_lastchanges = array_map(fn($tid) => $current_triggers[$tid]['lastchange'], $triggerids);
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);

		$this->assertVpsWrittenIncreasedBy($vps_written, count($keys));
		//$this->assertCepStatIncreasedBy('events', 'assessed', $cep_processed, count($keys));

		// The count is enforced by the wait itself (exact total match); no per-trigger fetch needed.
		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
		$triggers_by_id = $this->getTriggers($triggerids);

		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers_by_id[$triggerid];
			$info = 'trigger #'.$idx.' '.json_encode($trigger);
			$this->assertEquals($expected_trigger_value, $trigger['value'], $info);
			$this->assertEquals($expected_lastchanges[$idx], $trigger['lastchange'], $info);
		}
	}

	private function waitForNoOpenProblems(array $triggerids, string $message = '', bool $wait_cep_drained = true): void {
		// Wait for all problems to have a recovery event.
		// Wait until no unresolved problems remain. Using countOutput avoids fetching/decoding any
		// problem rows: the server returns just a count, and we poll until it reaches zero. (Default
		// problem.get without 'recent' returns only open problems, so count 0 means all recovered.)
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Wait for all triggers to return to OK.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
			$expected = count($triggerids);

			if (count($response['result']) !== $expected) {
				return 'expected '.$expected.' triggers, got '.count($response['result']);
			}

			// A trigger is only OK when its value is FALSE and its state is NORMAL: a trigger left in
			// UNKNOWN (e.g. after an unsupported item) is not yet recovered even with value FALSE.
			$ok = 0;
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['value'] === TRIGGER_VALUE_FALSE
						&& (int) $trigger['state'] === TRIGGER_STATE_NORMAL) {
					$ok++;
				}
			}

			if ($ok !== $expected) {
				return 'expected '.$expected.' triggers in OK state, got '.$ok;
			}

			return true;
		});

		$this->assertTriggersValueAndState($triggerids, TRIGGER_VALUE_FALSE, 'trigger after recovery');

		// With no open problems left, CEP should have drained its cached events back to zero.
		// Skip when other problems may still be open elsewhere in the system (cached_events is global).
		if ($wait_cep_drained) {
			$this->assertCepStatEquals('tasks', 'cached_events', 0);
			$this->assertCepStatEquals('tasks', 'cached_objects', 0);
		}
	}

	private function assertTriggersValueAndState(array $triggerids, int $expected_value, string $label): void {
		$triggers = $this->getTriggers($triggerids);

		// Count how many triggers are off so the failure message reports the scale of the mismatch, not
		// just the first offending trigger.
		$wrong_value = 0;
		$wrong_state = 0;
		foreach ($triggerids as $triggerid) {
			if ((int) $triggers[$triggerid]['value'] !== $expected_value) {
				$wrong_value++;
			}
			if ((int) $triggers[$triggerid]['state'] !== TRIGGER_STATE_NORMAL) {
				$wrong_state++;
			}
		}

		$total = count($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers[$triggerid];
			$info = $label.' #'.$idx.' ('.$wrong_value.'/'.$total.' wrong value, '.$wrong_state.'/'.$total
					.' wrong state): '.json_encode($trigger);
			$this->assertEquals($expected_value, $trigger['value'], $info);
			$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], $info);
		}
	}

	/**
	 * Poll the per-trigger CEP services until every one reports the expected status, then assert the
	 * status and the number of open service problems. Each service has a single problem tag
	 * (SERVICE_TAG=<component>) matching its trigger's events, so the service status and its service
	 * problem events follow the trigger's problem state through the service manager:
	 * TRIGGER_SEVERITY_DISASTER with one open problem per service while the trigger problem is open,
	 * and ZBX_SEVERITY_OK with no open problems once it recovers.
	 *
	 * @param int    $expected_status        ZBX_SEVERITY_* expected for every service
	 * @param int    $expected_open_problems open service problems expected across all services
	 */
	private function assertServicesStatus(int $expected_status, int $expected_open_problems): void {
		$serviceids = self::$serviceids;

		// Service status reflects the matched trigger problem severity (or OK after recovery). The poll
		// fails if all services do not reach $expected_status in time.
		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => $expected_status]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// One open service problem per service while in problem state; none after recovery. The poll
		// fails if the open service problem count does not reach $expected_open_problems in time.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $serviceids,
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE
		], $expected_open_problems, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Assert that every per-trigger service has exactly one open service problem, i.e. the service manager
	 * created a single service problem per matched service and did not add the same event to a service more
	 * than once. This is a regression guard for duplicated service problems: the aggregate count checked by
	 * assertServicesStatus() can be satisfied by an uneven distribution (one service with two problems and
	 * another with none), so the per-service breakdown is verified explicitly here.
	 */
	private function assertOneServiceProblemPerService(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$response = $this->call('problem.get', [
			'objectids' => $serviceids,
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'output' => ['eventid', 'objectid']
		]);

		$counts = [];
		foreach ($response['result'] as $problem) {
			$objectid = $problem['objectid'];
			$counts[$objectid] = isset($counts[$objectid]) ? $counts[$objectid] + 1 : 1;
		}

		foreach ($serviceids as $serviceid) {
			$count = isset($counts[$serviceid]) ? $counts[$serviceid] : 0;
			$this->assertSame(1, $count, 'Service '.$serviceid.' must have exactly one open service problem, '.
				'got '.$count.'. Open service problems: '.json_encode($response['result']));
		}
	}

	/**
	 * Poll the per-trigger CEP services until every one reports $expected_status (a ZBX_SEVERITY_* value,
	 * or ZBX_SEVERITY_OK once recovered). Unlike assertServicesStatus() this only checks the status, so it
	 * can be used after a manual problem-severity change where the open service problem count is irrelevant.
	 * A no-op when no services exist (service tests disabled), so the caller runs as usual either way.
	 */
	private function waitForServicesStatus(int $expected_status): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => $expected_status]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll the web-tag services (created by createWebTagServices) until every one reports $expected_status.
	 * Unlike the per-trigger CEP services these are matched to problems only via the webhook-applied
	 * WEB_COMPONENT_TAG tag, so reaching a problem status here proves the tags returned by the media type
	 * were applied to the open problem events and picked up by the service manager.
	 */
	private function waitForWebTagServicesStatus(int $expected_status): void {
		$serviceids = self::$web_tag_serviceids;

		$this->assertNotEmpty($serviceids, 'Web-tag services must be created before waiting for their status.');

		try {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $serviceids,
				'filter' => ['status' => $expected_status]
			], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		} catch (Exception $e) {
			$response = $this->call('service.get', [
				'serviceids' => $serviceids,
				'output' => ['serviceid', 'status']
			]);
			$wrong = array_values(array_filter($response['result'],
				fn($service) => (int) $service['status'] !== $expected_status
			));
			throw new Exception('Expected all '.count($serviceids).' web-tag services to have status '
				.$expected_status.', but '.count($wrong).' differ, first (max 5): '
				.json_encode(array_slice($wrong, 0, 5)).'. '.$e->getMessage());
		}
	}

	private function waitForServicesSuppressed(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		try {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $serviceids,
				'filter' => ['status' => -1]
			], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		} catch (Exception $e) {
			$response = $this->call('service.get', [
				'serviceids' => $serviceids,
				'output' => ['serviceid', 'status']
			]);
			if (!empty($response['result'])) {
				$status = $response['result'][0]['status'];
				throw new Exception('Expected all services to have status -1 (OK), but got status '.$status.' for service '.$response['result'][0]['serviceid'].'. '.$e->getMessage());
			}
			throw $e;
		}
	}

	private function waitForServicesNoLongerSuppressed(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => [
				TRIGGER_SEVERITY_NOT_CLASSIFIED,
				TRIGGER_SEVERITY_INFORMATION,
				TRIGGER_SEVERITY_WARNING,
				TRIGGER_SEVERITY_AVERAGE,
				TRIGGER_SEVERITY_HIGH,
				TRIGGER_SEVERITY_DISASTER
			]]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Build a map of component value => serviceid for the CEP services, read from each service's
	 * SERVICE_TAG problem-tag value (the tag by which a service is matched to its component's trigger).
	 * Returns an empty array when no services exist (service tests skipped).
	 */
	private function getServiceIdsByComponent(): array {
		if (empty(self::$serviceids)) {
			return [];
		}

		$response = $this->call('service.get', [
			'serviceids' => self::$serviceids,
			'output' => ['serviceid'],
			'selectProblemTags' => ['tag', 'value']
		]);

		$service_by_component = [];
		foreach ($response['result'] as $service) {
			$service_tag = current(array_filter($service['problem_tags'],
				fn($t) => $t['tag'] === self::SERVICE_TAG
			));

			if ($service_tag !== false) {
				$service_by_component[$service_tag['value']] = $service['serviceid'];
			}
		}

		return $service_by_component;
	}

	/**
	 * Wait until exactly the services of $suppressed_components are suppressed (status -1, i.e. OK because
	 * all their problems are suppressed) while every other CEP service shows a problem severity (its
	 * problems are no longer suppressed). $service_by_component maps a component value to its serviceid.
	 * No-op when there are no services (service tests skipped).
	 */
	private function waitForServicesSuppressedForComponents(array $service_by_component,
			array $suppressed_components): void {
		if (empty($service_by_component)) {
			return;
		}

		$suppressed_ids = [];
		$unsuppressed_ids = [];
		foreach ($service_by_component as $component => $serviceid) {
			if (in_array($component, $suppressed_components, true)) {
				$suppressed_ids[] = $serviceid;
			}
			else {
				$unsuppressed_ids[] = $serviceid;
			}
		}

		if (!empty($suppressed_ids)) {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $suppressed_ids,
				'filter' => ['status' => -1]
			], count($suppressed_ids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}

		if (!empty($unsuppressed_ids)) {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $unsuppressed_ids,
				'filter' => ['status' => [
					TRIGGER_SEVERITY_NOT_CLASSIFIED,
					TRIGGER_SEVERITY_INFORMATION,
					TRIGGER_SEVERITY_WARNING,
					TRIGGER_SEVERITY_AVERAGE,
					TRIGGER_SEVERITY_HIGH,
					TRIGGER_SEVERITY_DISASTER
				]]
			], count($unsuppressed_ids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Collect every open problem on $triggerids and manually change its severity to $severity via
	 * event.acknowledge (ZBX_PROBLEM_UPDATE_SEVERITY), so the service manager recomputes the status of the
	 * services matched to those problems.
	 */
	private function updateOpenProblemsSeverity(array $triggerids, int $severity): void {
		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);
		$eventids = array_column($response['result'], 'eventid');
		$this->assertNotEmpty($eventids, 'Expected open problems to update severity for, found none.');

		$this->call('event.acknowledge', [
			'eventids' => $eventids,
			'action' => ZBX_PROBLEM_UPDATE_SEVERITY,
			'severity' => $severity
		]);
	}

	private function currentClockNs(): array {
		static $ns = 0;

		// Use a monotonically increasing ns starting from 0, so no two returned values ever collide.
		return ['clock' => time(), 'ns' => $ns++];
	}

	private function getApiSessionId(): string {
		if (self::$sessionid === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
			self::$sessionid = CAPIHelper::getSessionId();
		}
		else {
			CAPIHelper::setSessionId(self::$sessionid);
		}

		return self::$sessionid;
	}

	private function testItemOnServer(string $hostid, string $sid, array $item,
			array $options = ['single' => false, 'state' => 0]): array|false {
		$response = $this->call('host.get', [
			'hostids' => [$hostid],
			'output' => ['maintenance_status', 'maintenance_type']
		]);
		$this->assertCount(1, $response['result']);
		$host = $response['result'][0];

		$data = [
			'options' => $options,
			'item' => $item,
			'host' => [
				'hostid' => $hostid,
				'maintenance_status' => $host['maintenance_status'],
				'maintenance_type' => $host['maintenance_type'],
			]
		];

		return $this->getClient(self::COMPONENT_SERVER)->testItem($data, $sid);
	}

	private function getVpsWritten(): int {
		$result = $this->testItemOnServer((string) self::$hostid, $this->getApiSessionId(),
			['value_type' => '3', 'type' => '5', 'key' => 'zabbix[vps,written]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertIsNumeric($result['item']['result']);

		return (int) $result['item']['result'];
	}

	private function assertVpsWrittenIncreasedBy(int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;

		$this->callTestItemUntilCallback(
			['value_type' => '3', 'type' => '5', 'key' => 'zabbix[vps,written]'],
			function ($result) use ($expected) {
				return $result !== false && isset($result['item']['result'])
						&& is_numeric($result['item']['result']) && (int) $result['item']['result'] >= $expected;
			}
		);
	}

	/**
	 * Query the server's internal zabbix["cep"] item and return the decoded CEP statistics:
	 *   ['events' => ['assessed' => N, 'processed' => N, 'discarded' => N],
	 *    'tasks'  => ['remote' => N, 'internal' => N, 'completed' => N]]
	 */
	private function getCepStats(): array {
		$result = $this->testItemOnServer((string) self::$hostid, $this->getApiSessionId(),
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);

		$stats = json_decode($result['item']['result'], true);
		$this->assertIsArray($stats, 'zabbix["cep"] did not return valid JSON: '.json_encode($result['item']));

		return $stats;
	}

	/**
	 * Read a single numeric CEP statistic, e.g. getCepStat('events', 'processed').
	 */
	private function getCepStat(string $group, string $name): int {
		$stats = $this->getCepStats();
		$this->assertArrayHasKey($group, $stats, 'zabbix["cep"] stats missing group: '.json_encode($stats));
		$this->assertArrayHasKey($name, $stats[$group], 'zabbix["cep"] stats missing '.$group.'.'.$name);
		$this->assertIsNumeric($stats[$group][$name]);

		return (int) $stats[$group][$name];
	}

	/**
	 * Extract a single numeric CEP statistic from a testItem() response, or null if it is not available.
	 */
	private function cepStatFromResult($result, string $group, string $name): ?int {
		if ($result === false || !isset($result['item']['result'])) {
			return null;
		}

		$stats = json_decode($result['item']['result'], true);

		return (isset($stats[$group][$name]) && is_numeric($stats[$group][$name]))
				? (int) $stats[$group][$name] : null;
	}

	/**
	 * Poll the zabbix["cep"] statistics until the given counter reaches $baseline + $min_increase, then
	 * assert it. Mirrors assertVpsWrittenIncreasedBy.
	 */
	private function assertCepStatIncreasedBy(string $group, string $name, int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;

		$this->callTestItemUntilCallback(
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]'],
			function ($result) use ($group, $name, $expected) {
				$value = $this->cepStatFromResult($result, $group, $name);

				return $value !== null && $value >= $expected;
			}
		);
	}

	/**
	 * Poll the zabbix["cep"] statistics until the given counter equals $expected, then assert it.
	 */
	private function assertCepStatEquals(string $group, string $name, int $expected): void {
		$this->callTestItemUntilCallback(
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]'],
			function ($result) use ($group, $name, $expected) {
				return $this->cepStatFromResult($result, $group, $name) === $expected;
			},
			['single' => false, 'state' => 0],
			null,
			// Surface up to 10 still-open problems to help diagnose why the cache did not drain.
			function () use ($group, $name, $expected) {
				$response = $this->call('problem.get', [
					'object' => EVENT_OBJECT_TRIGGER,
					'source' => EVENT_SOURCE_TRIGGERS,
					'output' => ['eventid', 'objectid', 'name', 'clock'],
					'limit' => 10
				]);

				// If no trigger-source problem is open, fall back to any open problem (e.g. internal-source) so
				// the diagnostics are not empty when the cache is held open by a non-trigger problem.
				if (empty($response['result'])) {
					$response = $this->call('problem.get', [
						'output' => ['eventid', 'source', 'object', 'objectid', 'name', 'clock'],
						'limit' => 10
					]);
				}

				return ' CEP '.$group.'.'.$name.' did not reach '.$expected.
						'. Open problems (max 10): '.json_encode($response['result']);
			}
		);
	}

	public static function clearData(): void {
		if (!empty(self::$service_actionid)) {
			CDataHelper::call('action.delete', [self::$service_actionid]);
			self::$service_actionid = null;
		}

		if (!empty(self::$trigger_actionid)) {
			CDataHelper::call('action.delete', [self::$trigger_actionid]);
			self::$trigger_actionid = null;
		}

		// Remove the extra-tag webhook actions (created by createExtraTagWebhookAction) in case a test
		// aborted before its own teardown ran.
		if (!empty(self::$tag_actionid)) {
			CDataHelper::call('action.delete', [self::$tag_actionid]);
			self::$tag_actionid = null;
		}

		if (!empty(self::$tag_actionid2)) {
			CDataHelper::call('action.delete', [self::$tag_actionid2]);
			self::$tag_actionid2 = null;
		}

		// Detach the media from the Admin user before deleting the media types they reference.
		if (!empty(self::$mediatypeid) || !empty(self::$tag_mediatypeid) || !empty(self::$tag_mediatypeid2)) {
			CDataHelper::call('user.update', ['userid' => 1, 'medias' => []]);

			if (!empty(self::$tag_mediatypeid)) {
				CDataHelper::call('mediatype.delete', [self::$tag_mediatypeid]);
				self::$tag_mediatypeid = null;
			}

			if (!empty(self::$tag_mediatypeid2)) {
				CDataHelper::call('mediatype.delete', [self::$tag_mediatypeid2]);
				self::$tag_mediatypeid2 = null;
			}

			if (!empty(self::$mediatypeid)) {
				CDataHelper::call('mediatype.delete', [self::$mediatypeid]);
				self::$mediatypeid = null;
			}
		}

		if (!empty(self::$serviceids)) {
			CDataHelper::call('service.delete', self::$serviceids);
			self::$serviceids = [];
		}

		// Remove the web-tag services (created by createWebTagServices) in case a test aborted before its
		// own teardown ran.
		if (!empty(self::$web_tag_serviceids)) {
			CDataHelper::call('service.delete', self::$web_tag_serviceids);
			self::$web_tag_serviceids = [];
		}

		if (!empty(self::$correlationid)) {
			CDataHelper::call('correlation.delete', [self::$correlationid]);
			self::$correlationid = null;
		}

		if (!empty(self::$correlationid2)) {
			CDataHelper::call('correlation.delete', [self::$correlationid2]);
			self::$correlationid2 = null;
		}

		// Remove the CEP rules (created by prepareDataCepWindowTagCorrelationCloseOnUp) in case a test aborted
		// before its own teardown ran; a rule left behind would keep closing problems of later suites.
		$cep_rules = CDataHelper::call('ceprule.get', [
			'output' => ['cep_ruleid'],
			'search' => ['name' => self::CEP_RULE_NAME_PREFIX]
		]);
		if (!empty($cep_rules)) {
			CDataHelper::call('ceprule.delete', array_column($cep_rules, 'cep_ruleid'));
		}
		self::$cep_ruleid = null;

		// stopDiscHostMaintenance() only pushes the maintenance out of its active window; delete it for real
		// here (before its host) so it does not leak into later suites.
		if (!empty(self::$disc_maintenanceids)) {
			CDataHelper::call('maintenance.delete', self::$disc_maintenanceids);
			self::$disc_maintenanceids = [];
		}

		if (!empty(self::$disc_hostid)) {
			CDataHelper::call('host.delete', [self::$disc_hostid]);
			self::$disc_hostid = null;
		}

		if (!empty(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
			self::$hostid = null;
		}

		// Deleted after the hosts it monitors (self::$hostid and the discovered host) have been removed,
		// since a proxy with assigned hosts cannot be deleted.
		if (!empty(self::$proxyid)) {
			CDataHelper::call('proxy.delete', [self::$proxyid]);
			self::$proxyid = null;
		}

		self::$hostid_cache = [];
		self::$itemid_cache = [];

		// Deleted after the host it is linked to (self::$hostid) has been removed.
		if (!empty(self::$log_templateid)) {
			CDataHelper::call('template.delete', [self::$log_templateid]);
			self::$log_templateid = null;
		}

		if (!empty(self::$templateid)) {
			CDataHelper::call('template.delete', [self::$templateid]);
			self::$templateid = null;
		}

		if (self::SCOPED_INTERNAL_ACTIONS) {
			// Disable any internal-source actions the *Unknown tests enabled (in case one aborted before
			// restoring them), returning to the all-internal-actions-disabled state prepareData() set up.
			// The built-in actions must not be deleted here: enableInternalActions() re-enables them by name,
			// so deleting them would make the next *Unknown run fail to find them.
			$result = CDataHelper::call('action.get', [
				'output' => ['actionid'],
				'filter' => ['eventsource' => EVENT_SOURCE_INTERNAL]
			]);
			if (!empty($result)) {
				CDataHelper::call('action.update', array_map(
					fn($actionid) => ['actionid' => $actionid, 'status' => ACTION_STATUS_DISABLED],
					array_column($result, 'actionid')
				));
			}
		}
		else {
			// Disable the internal actions again in case a test enabled them and aborted before restoring.
			foreach (['Report unknown triggers', 'Report not supported items'] as $action_name) {
				$result = CDataHelper::call('action.get', [
					'output' => ['actionid'],
					'filter' => ['name' => $action_name]
				]);
				if (!empty($result)) {
					CDataHelper::call('action.update', [
						'actionid' => $result[0]['actionid'],
						'status' => ACTION_STATUS_DISABLED
					]);
				}
			}
		}

		// Re-enable audit log disabled in prepareData().
		CDataHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}
}
