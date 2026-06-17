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

/**
 * Test suite to check if trigger CEP (Correlation Event Processing) works properly
 * when item state toggles between normal and unsupported
 *
 * @required-components server
 * @suite-components-reuse true
 * @onAfter clearData
 * @hosts test
 */
class testTriggerCEP extends CIntegrationTest {

	const HOST_NAME = 'test';
	const TEMPLATE_NAME = 'template_trigger_cep';
	const LLD_RULE_KEY = 'lld.cep.trapper';
	const HOST_LLD_RULE_KEY = 'host.lld.trapper';
	const HOST_DISC_VALUE = 'discovered_host1';
	const LLD_MACRO = '{#COMPONENT}';
	const ITEM_PROTO_KEY = 'cep.trap';
	const ITEM_PROTO_KEY2 = 'cep.trap2';
	const COMPONENT_VALUE = 'sensor1';
	const LLD_DISCOVERY_COUNT = 4000;

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
	const LOG_EVENT_COUNT = 10000;
	const WAIT_ITERATIONS = 60;
	const WAIT_ITERATION_DELAY = 1;

	// change iterations to fail faster when debugging
	const STATE_CHANGE_WAIT_ITERATIONS = 30;

	// When true, the *Restart test variants are skipped entirely. Set during development to avoid the
	// slow server stop/start cycles; the non-restart tests still run (their @depends point at non-restart
	// siblings, so they do not cascade-skip).
	const SKIP_RESTART_TESTS = false;


	private static $hostid;
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
	private static $sessionid = null;

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
				'LogSlowQueries' => 10000
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
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
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
			'dependencies' => [
				['triggerid' => self::$trigger_prototypeid]
			],
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep']
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

		// Enable the internal event actions for the whole suite so the server generates internal
		// item-not-supported and trigger-unknown events (verified by the *Unknown tests). They are
		// disabled again in clearData(). The configuration cache is reloaded by the first test.
		$this->setInternalActionStatus('Report unknown triggers', ACTION_STATUS_ENABLED);
		$this->setInternalActionStatus('Report not supported items', ACTION_STATUS_ENABLED);

		return true;
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Reload configuration cache so the server is aware of the changed prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Verify the discovered triggers reflect the updated recovery mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Verify the discovered triggers reflect the updated mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'type', 'recovery_mode']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Verify the discovered triggers reflect the updated correlation mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'type']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
				['tag' => 'type', 'value' => 'cep-dep']
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== self::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect the updated expression and correlation config.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'manual_close', 'type', 'expression']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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
	public function prepareDataGlobalCorrelation() {
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
				['tag' => 'service', 'value' => '{ITEM.VALUE}']
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== self::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect global correlation mode and multiple event generation.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
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

		// Create a global event correlation rule: close old events whose 'service' tag value
		// is 'down' when a new event arrives with 'service' tag value 'up'.
		// This exercises the global-correlation path independently of trigger-level correlation.
		$corr_params = [
			'name' => 'CEP global event correlation',
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
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
				]
			],
			'operations' => [
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_OLD
				],
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_NEW
				]
			]
		];

		$existing = $this->call('correlation.get', ['filter' => ['name' => $corr_params['name']], 'output' => ['correlationid']]);
		if ($existing['result']) {
			self::$correlationid = $existing['result'][0]['correlationid'];
			$this->call('correlation.update', ['correlationid' => self::$correlationid] + $corr_params);
		}
		else {
			$response = $this->call('correlation.create', $corr_params);
			$this->assertArrayHasKey('correlationids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['correlationids']);
			self::$correlationid = $response['result']['correlationids'][0];
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Send LLD data via sendSenderValues and verify that the item and trigger
	 * prototypes are instantiated for the discovered component.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testPrepareTriggerCEP_LLDDiscovery() {
		// Reload configuration cache before sending discovery data.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send host LLD discovery data so the host prototype creates the discovered host
		// with the template linked.
		$this->sendSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					['{#HOST}' => self::HOST_DISC_VALUE]
				]])
			]
		], null, 0);

		// Wait for the discovered host to be created by the server.
		$response = $this->callUntilDataIsPresent('host.get', [
			'filter' => ['host' => self::HOST_DISC_VALUE],
			'output' => ['hostid']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		$this->assertCount(1, $response['result'], 'Discovered host was not created by host prototype.');
		self::$disc_hostid = $response['result'][0]['hostid'];

		// Wait for the inherited LLD rule to be created on the discovered host.
		$this->callUntilDataIsPresent('discoveryrule.get', [
			'hostids' => [self::$disc_hostid],
			'filter' => ['key_' => self::LLD_RULE_KEY],
			'output' => ['itemid']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Reload config so the server is aware of the discovered host's inherited LLD rule.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send item LLD discovery data to the discovered host's LLD rule (inherited from template).
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Verify all LLD_DISCOVERY_COUNT items from proto1 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'name', 'key_']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT triggers were created and store the primary one.
		$expected_description = 'CEP trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered triggers were created.');

		$primary_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_description
		));
		$this->assertNotFalse($primary_trigger, 'Primary discovered trigger not found.');
		self::$discovered_triggerid = $primary_trigger['triggerid'];
		self::$discovered_triggerids = array_column($response['result'], 'triggerid');

		$tags = $primary_trigger['tags'];
		$tags_json = json_encode($tags);
		$tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $tags);
		$this->assertCount(3, $tags_tv, 'Discovered trigger must have 3 tags, got: '.$tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep'], $tags_tv,
			'Tag type=cep not found in: '.$tags_json);
		$this->assertContains(['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}'], $tags_tv,
			'Tag service=regsub not found in: '.$tags_json);

		// Verify all LLD_DISCOVERY_COUNT items from proto2 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY2.'['],
			'output' => ['itemid', 'name', 'key_']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result'], 'Not all second discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT dependent triggers were created and store the primary one.
		$expected_dep_description = 'CEP dependent trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP dependent trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered dependent triggers were created.');

		$primary_dep_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_dep_description
		));
		$this->assertNotFalse($primary_dep_trigger, 'Primary discovered dependent trigger not found.');
		self::$discovered_dep_triggerid = $primary_dep_trigger['triggerid'];
		self::$discovered_dep_triggerids = array_column($response['result'], 'triggerid');

		$dep_tags = $primary_dep_trigger['tags'];
		$dep_tags_json = json_encode($dep_tags);
		$dep_tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $dep_tags);
		$this->assertCount(2, $dep_tags_tv, 'Discovered dependent trigger must have 2 tags, got: '.$dep_tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $dep_tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$dep_tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep-dep'], $dep_tags_tv,
			'Tag type=cep-dep not found in: '.$dep_tags_json);

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
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys),
			null, 0
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
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// OK→PROBLEM: one PROBLEM event per trigger; trigger value goes TRUE.
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 1);
	}

	/**
	 * Smoke test (part 2/2): the problem opened by testTriggerCEP_OpenProblem closes on PROBLEM→OK.
	 * Runs as a separate test so the open problem persists across the test boundary before recovery.
	 *
	 * @depends testTriggerCEP_OpenProblem
	 */
	public function testTriggerCEP_CloseProblem() {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// PROBLEM→OK: one RESOLVED event per trigger. The baseline is per-test-instance, so it is
		// recaptured here (now past the open event) and the close adds exactly one more event.
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, 1);
		$this->waitForNoOpenProblems($triggerids, 'close problem');
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		// Push the PROBLEM value (1) and then the recovery value (0) back-to-back. The recovery carries a
		// later clock so the problem is evaluated first, but the test does not wait between the two sends.
		$now = time();
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1',
					'clock' => $now, 'ns' => $this->currentNs()], $keys),
			null, 0
		);
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0',
					'clock' => $now + 1, 'ns' => $this->currentNs()], $keys),
			null, 0
		);

		// Each trigger must produce exactly two events: one PROBLEM and one RESOLVED.
		$this->waitForAllTriggerEventCounts($triggerids, 2);

		// Newest event is the recovery (OK); the one before it is the problem (PROBLEM).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.json_encode($events);
			$this->assertCount(2, $events, $info);
			$this->assertEquals(TRIGGER_VALUE_FALSE, (int) $events[0]['value'], $info);
			$this->assertEquals(TRIGGER_VALUE_TRUE, (int) $events[1]['value'], $info);
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery');
		$this->assertNoOpenProblems($triggerids);
	}

	/**
	 * Smoke test (part 1/2): a discovered item becomes unsupported and its trigger enters the UNKNOWN
	 * state. The internal "Report unknown triggers" and "Report not supported items" actions are enabled
	 * for the whole suite in prepareData(), so the server opens an internal problem for every unsupported
	 * item and every unknown trigger. The trigger value stays OK (it was not in a problem) while the state
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
	 * @depends testTriggerCEP_CloseUnknown
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
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1'], $keys),
			null, 0
		);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_TRUE);

		// Push a non-numeric value to flip all items into unsupported state.
		// CEP must keep all trigger values as PROBLEM while state becomes UNKNOWN.
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number'], $keys),
			null, 0
		);
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);

		// Recover all triggers by sending a numeric value of 0 to all discovered items.
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys),
			null, 0
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
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
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
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
	 * @depends testTriggerCEP_EventAssessmentNone
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpression() {
		$this->clearDiscoveredItemHistory();
		$this->prepareDataRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpression() {
		$this->runDependentTriggerTest(false);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentMultipleEvent() {
		$this->prepareDataMultipleEventsRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_EventAssessmentMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEvent() {
		$this->runDependentTriggerTest(false);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_EventAssessmentTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataTagCorrelation();
		$this->runEventAssessmentTest(true);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelation() {
		$this->runDependentTriggerTest(false);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelation
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelation(true);
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$this->assertNoOpenProblems(self::$discovered_triggerids);
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
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
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
		$this->assertNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

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

		// Values are pushed to the master item; the dependent discovered item receives a copy of each.
		$item_key = self::LOG_MASTER_ITEM_KEY;
		$base_clock = time();
		$values = [];
		for ($i = 0; $i < self::LOG_EVENT_COUNT; $i++) {
			$values[] = [
				'host' => self::HOST_NAME,
				'key' => $item_key,
				'value' => 'problem '.$i,
				'clock' => $base_clock,
				'ns' => $i + 1
			];
		}
		$this->sendSenderValues($values, null, 0);

		// Every one of the LOG_EVENT_COUNT values must have generated a problem event on the trigger.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], self::LOG_EVENT_COUNT, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Send a single non-matching log value so the trigger expression turns false, then wait until all open
	 * problems on the log trigger are resolved and the trigger is back to OK. When $restart is true, the
	 * server is restarted before the recovery value is sent.
	 */
	private function recoverLogTrigger(bool $restart): void {
		$this->maybeRestartServer($restart);

		$triggerids = [self::$discovered_log_triggerid];

		$this->sendSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::LOG_MASTER_ITEM_KEY,
				'value' => 'recovered',
				'clock' => time(),
				'ns' => $this->currentNs()
			]
		], null, 0);

		$this->waitForNoOpenProblems($triggerids, 'log recovery');
		$this->assertNoOpenProblems($triggerids);
	}

	/**
	 * Discover exactly one log trigger from the dedicated log template by sending LLD data with a single
	 * entry. Asserts the trigger has multiple problem event generation enabled and stores the discovered
	 * trigger id.
	 */
	private function discoverLogTrigger(): void {
		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->sendSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::LOG_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					[self::LOG_LLD_MACRO => self::LOG_COMPONENT_VALUE]
				]])
			]
		], null, 0);

		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';

		// Wait for the discovered log item.
		$this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'filter' => ['key_' => $item_key],
			'output' => ['itemid']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === 1;
		});

		// Wait for the single discovered log trigger.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => 'CEP log trigger for '],
			'output' => ['triggerid', 'type']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
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
		$this->sendSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		], null, 0);

		for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
			$response = $this->call('host.get', [
				'hostids' => [self::$disc_hostid],
				'countOutput' => true
			]);
			if ($response['result'] == 0) {
				self::$disc_hostid = null;
				$this->reloadConfigurationCacheAndWaitForLogLine();
				return;
			}
			sleep(self::WAIT_ITERATION_DELAY);
		}

		$this->fail('Discovered host was not deleted after sending empty host LLD data.');
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
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Push a non-numeric value to flip all items into unsupported state; CEP keeps the trigger
		// value unchanged (OK) while the state becomes UNKNOWN.
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number'], $keys),
			null, 0
		);

		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_FALSE);

		// An internal problem must be opened for every unknown trigger.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], self::LLD_DISCOVERY_COUNT, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// An internal problem must be opened for every unsupported item on the discovered host.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], self::LLD_DISCOVERY_COUNT, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Restore all discovered items to the supported state (triggers return to NORMAL/OK) and verify that
	 * every internal problem opened by runOpenUnknownTest is resolved.
	 */
	private function runCloseUnknownTest(): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Send a numeric value of 0 to restore all items to supported state; the trigger returns to
		// the NORMAL state and stays OK.
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys),
			null, 0
		);

		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);

		// Every internal trigger-unknown problem must be resolved once the triggers leave UNKNOWN.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Every internal item-not-supported problem must be resolved once the items become supported.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
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
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number'], $keys),
			null, 0
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

		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'], $keys2),
			null, 0
		);

		$this->waitForNoOpenProblems(array_merge($triggerids1, $triggerids2));
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

		$this->runIntermingledDependentTriggerBatch($restart, $parent_event_count + 2);
	}

	private function runIntermingledDependentTriggerBatch(bool $restart, int $parent_event_count): void {
		$parent_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$dep_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$parent_ids = self::$discovered_triggerids;
		$prev_parent_triggers = $this->getTriggers($parent_ids);
		$prev_parent_lastchanges = array_map(fn($tid) => $prev_parent_triggers[$tid]['lastchange'], $parent_ids);

		// 1. Intermingled batch: parent PROBLEM + dep PROBLEM values arrive in the same
		//    sender packet (parent_key, dep_key, parent_key, dep_key, ...); only the parent
		//    triggers are asserted.
		$intermingled = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '1'];
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '1'];
		}
		$this->sendSenderValues($intermingled, null, 1);

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $parent_ids,
			'output' => ['triggerid', 'value', 'lastchange', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($parent_ids, $prev_parent_lastchanges) {
				$by_id = array_column($response['result'], null, 'triggerid');
				foreach ($parent_ids as $idx => $tid) {
					if (!isset($by_id[$tid])) return false;
					$t = $by_id[$tid];
					if ((int) $t['value'] !== TRIGGER_VALUE_TRUE) return false;
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) return false;
					if ((int) $t['lastchange'] <= $prev_parent_lastchanges[$idx]) return false;
				}
				return true;
			}
		);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 1);

		$this->maybeRestartServer($restart);

		// 2. Intermingled recovery: parent OK + dep OK values in one packet
		//    (parent_key, dep_key, parent_key, dep_key, ...); only parents are asserted.
		$prev_parent_triggers = $this->getTriggers($parent_ids);
		$prev_parent_lastchanges = array_map(fn($tid) => $prev_parent_triggers[$tid]['lastchange'], $parent_ids);
		$intermingled_recovery = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '0'];
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '0'];
		}
		$this->sendSenderValues($intermingled_recovery, null, 1);

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $parent_ids,
			'output' => ['triggerid', 'value', 'lastchange', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($parent_ids, $prev_parent_lastchanges) {
				$by_id = array_column($response['result'], null, 'triggerid');
				foreach ($parent_ids as $idx => $tid) {
					if (!isset($by_id[$tid])) return false;
					$t = $by_id[$tid];
					if ((int) $t['value'] !== TRIGGER_VALUE_FALSE) return false;
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) return false;
					if ((int) $t['lastchange'] <= $prev_parent_lastchanges[$idx]) return false;
				}
				return true;
			}
		);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 2);

		// When the parent recovered in the intermingled batch above, dependency suppression lifted while a
		// dependent's last value could still be '1' (processed before its own '0' in the same packet), so a
		// dependent problem may have opened. Send a final dep '0' to deterministically clear any such
		// leftover before asserting no open problems.
		$dep_recovery = [];
		foreach ($dep_keys as $dkey) {
			$dep_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dkey, 'value' => '0'];
		}
		$this->sendSenderValues($dep_recovery, null, 1);

		// After recovery no problems must remain open on either the parent or the dependent triggers.
		$this->waitForNoOpenProblems(array_merge($parent_ids, self::$discovered_dep_triggerids),
			'intermingled batch recovery');
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

		$this->assertNoOpenProblems($triggerids, 'After recovery-after-restore');
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
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});
		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
		]);
		$this->callUntilDataIsPresent('triggerprototype.get', [
			'triggerids' => [self::$dep_trigger_prototypeid],
			'output' => ['manual_close']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});

		// Resend LLD discovery data so the server re-instantiates discovered triggers
		// with the updated prototype configuration.
		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		], null, 0);

		// Wait for the discovered triggers to reflect the updated manual_close setting.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['manual_close']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
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
			$this->sendSenderValues([
				[
					'host' => self::HOST_NAME,
					'key' => self::LOG_LLD_RULE_KEY,
					'value' => json_encode(['data' => []])
				]
			], null, 0);

			for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
				$response = $this->call('trigger.get',
					['triggerids' => [self::$discovered_log_triggerid], 'countOutput' => true]
				);
				if ($response['result'] == 0) {
					self::$discovered_log_triggerid = null;
					$this->reloadConfigurationCacheAndWaitForLogLine();
					break;
				}
				sleep(self::WAIT_ITERATION_DELAY);
			}

			$this->assertNull(self::$discovered_log_triggerid,
				'Discovered log trigger was not deleted after sending empty log LLD data.');
		}

		$triggerids = array_filter([self::$discovered_triggerid, self::$discovered_dep_triggerid]);

		if (!$triggerids) {
			return;
		}

		$this->sendSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		], null, 0);

		for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
			$response = $this->call('trigger.get', ['triggerids' => $triggerids, 'countOutput' => true]);
			if ($response['result'] == 0) {
				self::$discovered_triggerid = null;
				self::$discovered_dep_triggerid = null;
				self::$discovered_triggerids = [];
				self::$discovered_dep_triggerids = [];
				$this->reloadConfigurationCacheAndWaitForLogLine();
				return;
			}
			sleep(self::WAIT_ITERATION_DELAY);
		}

		$this->fail('Discovered triggers were not deleted after sending empty LLD data:'. json_encode($response));
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
	 * Skip the calling *Restart test when SKIP_RESTART_TESTS is enabled. The non-restart sibling
	 * leaves the system in the same asserted state, so dependents can rely on it instead.
	 */
	private function skipIfRestartTestsDisabled(): void {
		if (self::SKIP_RESTART_TESTS) {
			$this->markTestSkipped('Restart test variants disabled via SKIP_RESTART_TESTS.');
		}
	}

	private function buildItemLLDData(): string {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$data = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$data[] = [self::LLD_MACRO => $base.$i];
		}
		return json_encode(['data' => $data]);
	}

	private function buildDiscoveredKeys(string $proto_key): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$keys = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$keys[] = $proto_key.'['.$base.$i.']';
		}
		return $keys;
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

	private function validateTriggerParams($expected_state, $expected_value) {
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => self::$discovered_triggerids,
			'output' => ['triggerid', 'value', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_state, $expected_value) {
			if (count($response['result']) !== self::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ($trigger['state'] != $expected_state || $trigger['value'] != $expected_value) {
					return false;
				}
			}
			return true;
		});

		$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result']);
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
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_count) {
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
	private function waitForAllTriggerEventCounts(array $triggerids, int $expected_count): void {
		// eventid_from is inclusive, so +1 excludes the baseline event itself. A target of 0
		// (no state change expected) is handled too: the count returns 0 immediately, and any
		// spurious event keeps it off-target and fails the wait.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], count($triggerids) * $expected_count,
			self::STATE_CHANGE_WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY
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

	private function getTriggers(array $triggerids): array {
		$response = $this->call('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['triggerid', 'value', 'lastchange', 'state', 'recovery_mode', 'type', 'correlation_mode']
		]);
		return array_column($response['result'], null, 'triggerid');
	}

	/**
	 * Assert that sending $item_value produces a new event but trigger stays PROBLEM.
	 * Used for partial tag-correlation recoveries where one tagged problem closes while
	 * another remains open, so the trigger value stays TRUE and lastchange is not updated.
	 */
	private function assertPartialRecoveryForAll(array $triggerids, array $keys, string $item_value,
			int $expected_event_count): void {
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys),
			null, 1
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
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value,
					'clock' => $now , 'ns' => $this->currentNs()], $keys),
			null, 0
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
		$expected_lastchanges = array_map(fn($tid) => $current_triggers[$tid]['lastchange'], $triggerids);
		$this->sendSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value,
					'clock' => time(), 'ns' => $this->currentNs()], $keys),
			null, 0
		);

		$this->assertVpsWrittenIncreasedBy($vps_written, count($keys));

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

	private function waitForNoOpenProblems(array $triggerids, string $message = ''): void {
		// Wait for all problems to have a recovery event.
		// Wait until no unresolved problems remain. Using countOutput avoids fetching/decoding any
		// problem rows: the server returns just a count, and we poll until it reaches zero. (Default
		// problem.get without 'recent' returns only open problems, so count 0 means all recovered.)
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], 0, self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Wait for all triggers to return to OK.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['value', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
			$expected = count($triggerids);

			if (count($response['result']) !== $expected) {
				return 'expected '.$expected.' triggers, got '.count($response['result']);
			}

			$ok = 0;
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['value'] === TRIGGER_VALUE_FALSE) {
					$ok++;
				}
			}

			if ($ok !== $expected) {
				return 'expected '.$expected.' triggers in OK state, got '.$ok;
			}

			return true;
		});

		$this->assertTriggersValueAndState($triggerids, TRIGGER_VALUE_FALSE, 'trigger after recovery');
	}

	private function assertTriggersValueAndState(array $triggerids, int $expected_value, string $label): void {
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers[$triggerid];
			$info = $label.' #'.$idx.': '.json_encode($trigger);
			$this->assertEquals($expected_value, $trigger['value'], $info);
			$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], $info);
		}
	}

	private function assertNoOpenProblems(array $triggerids, string $message = ''): void {
		$prefix = $message !== '' ? $message.': ' : '';

		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);
		$this->assertEmpty($response['result'],
			$prefix.'Expected no open problems: '.json_encode($response));

		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $triggerid) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $triggers[$triggerid]['value'],
				$prefix.'Expected trigger '.$triggerid.' to have value OK (0).');
		}
	}

	private function currentNs(): int {
		return (int)(fmod(microtime(true), 1) * 1e9);
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
			'output' => ['maintenance_status', 'maintenance_type', 'proxyid']
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
				'proxyid' => (int) $host['proxyid']
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

		// Poll every 100 ms; keep the same overall timeout as the 1 s-based waits by scaling the
		// iteration count up by 10x (WAIT_ITERATIONS * WAIT_ITERATION_DELAY seconds total).
		$iterations = self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY * 10;
		for ($i = 0; $i < $iterations; $i++) {
			if ($this->getVpsWritten() >= $expected) {
				break;
			}
			usleep(100000);
		}
		$this->assertGreaterThanOrEqual($expected, $this->getVpsWritten());
	}

	public static function clearData(): void {
		if (!empty(self::$correlationid)) {
			CDataHelper::call('correlation.delete', [self::$correlationid]);
			self::$correlationid = null;
		}

		if (!empty(self::$disc_hostid)) {
			CDataHelper::call('host.delete', [self::$disc_hostid]);
			self::$disc_hostid = null;
		}

		if (!empty(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
			self::$hostid = null;
		}

		// Deleted after the host it is linked to (self::$hostid) has been removed.
		if (!empty(self::$log_templateid)) {
			CDataHelper::call('template.delete', [self::$log_templateid]);
			self::$log_templateid = null;
		}

		if (!empty(self::$templateid)) {
			CDataHelper::call('template.delete', [self::$templateid]);
			self::$templateid = null;
		}

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

		// Re-enable audit log disabled in prepareData().
		CDataHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}
}
