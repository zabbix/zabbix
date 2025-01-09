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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Test checks the objects created by LLD and then no more discovered or/and disabled.
 *
 * @backup hstgrp
 *
 * @onBefore prepareLLDData
 */
class testLowLevelDiscoveryDisabledObjects extends CWebTest {

	const HINT_HOST = 'Host for LLD hint';

	protected static $hint_hostid;

	public static function prepareLLDData() {
		// Create hostgroup for hosts with LLD.
		$hostgroupid = CDataHelper::call('hostgroup.create', [['name' => 'Group for LLD hint']])['groupids'][0];

		// Create hosts with low level discovery.
		$hosts = CDataHelper::createHosts([
			[
				'host' => self::HINT_HOST,
				'groups' => [['groupid' => $hostgroupid]],
				'discoveryrules' => [
					[
						'name' => '1 Delete - never, disable - never',
						'key_' => 'rule1',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_NEVER,
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_NEVER
					],
					[
						'name' => '2 Delete - never, disable - immediately',
						'key_' => 'rule2',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_NEVER,
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_IMMEDIATELY
					],
					[
						'name' => '3 Delete - never, disable - after 2w',
						'key_' => 'rule3',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_NEVER,
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_AFTER,
						'enabled_lifetime' => '2w'
					],
					[
						'name' => '4 Delete - after 52w, disable - never',
						'key_' => 'rule4',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_AFTER,
						'lifetime' => '52w',
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_NEVER
					],
					[
						'name' => '5 Delete - after 7d, disable - immediately',
						'key_' => 'rule5',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_AFTER,
						'lifetime' => '7d',
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_IMMEDIATELY
					],
					[
						'name' => '6 Delete - after 7d, disable - after 20h',
						'key_' => 'rule6',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_AFTER,
						'lifetime' => '7d',
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_AFTER,
						'enabled_lifetime' => '20h'
					],
					[
						'name' => '7 Delete - Immediately',
						'key_' => 'rule7',
						'type' => ITEM_TYPE_TRAPPER,
						'lifetime_type' => ZBX_LLD_DELETE_IMMEDIATELY
					]
				]
			]
		]);
		self::$hint_hostid = $hosts['hostids'][self::HINT_HOST];

		// Statuses of discovered object in DB.
		$time = time();
		$statuses = [
			// 1 Delete - never, disable - never.
			[
				'status' => 0,
				'lastcheck' => $time,
				'ts_delete' => 0,
				'disable_source' => 0,
				'ts_disable' => 0
			],
			// 2 Delete - never, disable - immediately.
			[
				'status' => 1,
				'lastcheck' => $time,
				'ts_delete' => 0,
				'disable_source' => 1,
				'ts_disable' => 1
			],
			// 3 Delete - never, disable - after 2w.
			[
				'status' => 0,
				'lastcheck' => $time,
				'ts_delete' => 0,
				'disable_source' => 1,
				'ts_disable' => $time + 1209600
			],
			// 4 Delete - after 52w, disable - never.
			[
				'status' => 0,
				'lastcheck' => $time,
				'ts_delete' => $time + 31536000,
				'disable_source' => 1,
				'ts_disable' => 0
			],
			// 5 Delete - after 7d, disable - immediately.
			[
				'status' => 1,
				'lastcheck' => $time,
				'ts_delete' => $time + 604800,
				'disable_source' => 1,
				'ts_disable' => 1
			],
			// 6 Delete - after 7d, disable - after 20h.
			[
				'status' => 0,
				'lastcheck' => $time,
				'ts_delete' => $time + 604800,
				'disable_source' => 1,
				'ts_disable' => $time + 72000
			],
			// 7 Delete - immediately.
			[
				'status' => 0,
				'lastcheck' => $time,
				'ts_delete' => $time,
				'disable_source' => 0,
				'ts_disable' => 0
			]
		];

		// Create item prototypes for hint check.
		$item_prototypes_data = [];
		$i = 1;
		foreach ($hosts['discoveryruleids'] as $lldid) {
			$item_prototypes_data[] = [
				'hostid' => self::$hint_hostid,
				'ruleid' => $lldid,
				'name' => '{#KEY} Prototype '.$i,
				'key_' => 'trap'.$i.'[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			];
			$i++;
		}
		$item_protototypes = CDataHelper::call('itemprototype.create', $item_prototypes_data);

		$discovered_items = [];
		$r = 1;
		foreach ($item_protototypes['itemids'] as $item_protototypeid) {
			$discovered_items[] = array_merge($statuses[$r - 1], [
				'item_name' => 'KEY1 Prototype '.$r,
				'key_' => 'trap'.$r.'[KEY1]',
				'itemid' => $r + 10090000,
				'item_prototypeid' => $item_protototypeid,
				'graph_itemid' => $r + 20090000,
				'itemdiscoveryid' => $r + 30090000
			]);
			$r++;
		}

		// Emulate item discovery in DB.
		foreach ($discovered_items as $discovered_item) {
			DBexecute('INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, query_fields,'.
					' params, posts, headers, status) VALUES ('.zbx_dbstr($discovered_item['itemid']).', 2, '.
					zbx_dbstr(self::$hint_hostid).', '.zbx_dbstr($discovered_item['item_name']).', \'\', '.
					zbx_dbstr($discovered_item['key_']).', NULL, 4, \'\', \'\', \'\', \'\', '.zbx_dbstr($discovered_item['status']).')'
			);
			DBexecute('INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, lastcheck, ts_delete, disable_source,'.
					' ts_disable, status) VALUES ('.zbx_dbstr($discovered_item['itemdiscoveryid']).', '.
					zbx_dbstr($discovered_item['itemid']).', '.zbx_dbstr($discovered_item['item_prototypeid']).', '.
					zbx_dbstr($discovered_item['lastcheck']).', '.zbx_dbstr($discovered_item['ts_delete']).', '.
					zbx_dbstr($discovered_item['disable_source']).', '.zbx_dbstr($discovered_item['ts_disable']).', 1);'
			);
		}

		// Create trigger prototypes for hint check.
		$lld_count = count($hosts['discoveryruleids']);
		$trigger_protototypes_data = [];
		for ($j = 1; $j <= $lld_count; $j++) {
			$trigger_protototypes_data[] = [
				'description' => 'Trigger prototype '.$j.' {#KEY}',
				'expression' => 'last(/'.self::HINT_HOST.'/trap'.$j.'[{#KEY}])=0'
			];
		}
		CDataHelper::call('triggerprototype.create', $trigger_protototypes_data);
		$trigger_protototypeids = CDataHelper::getIds('description');

		$discovered_triggers = [];
		$y = 0;
		foreach ($discovered_items as $discovered_item) {
			$discovered_triggers[] = array_merge($statuses[$y], [
				'description' => 'Trigger prototype '.($y + 1).' KEY1',
				'functionid' => $y + 2090000,
				'triggerid' => $y + 3090000,
				'itemid' => $discovered_item['itemid'],
				'parent_prototypeid' => $trigger_protototypeids['Trigger prototype '.($y + 1).' {#KEY}']
			]);
			$y++;
		}

		// Emulate triggers discovery in DB.
		foreach ($discovered_triggers as $discovered_trigger) {
			DBexecute('INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state, flags)'.
					' VALUES ('.zbx_dbstr($discovered_trigger['triggerid']).', '.zbx_dbstr($discovered_trigger['description']).
					', '.zbx_dbstr('{'.$discovered_trigger['functionid'].'}=0').', '.$discovered_trigger['status'].', 0, 0, \'\', 0, 4)'
			);

			DBexecute('INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES ('.
					zbx_dbstr($discovered_trigger['functionid']).', '.zbx_dbstr($discovered_trigger['itemid']).', '.
					zbx_dbstr($discovered_trigger['triggerid']).', \'last\', \'$\')'
			);

			DBexecute('INSERT INTO trigger_discovery (triggerid, parent_triggerid, lastcheck, ts_delete, disable_source, ts_disable, status) VALUES ('.
					zbx_dbstr($discovered_trigger['triggerid']).', '.zbx_dbstr($discovered_trigger['parent_prototypeid']).', '.
					zbx_dbstr($discovered_trigger['lastcheck']).', '.zbx_dbstr($discovered_trigger['ts_delete']).', '.
					zbx_dbstr($discovered_trigger['disable_source']).', '.zbx_dbstr($discovered_trigger['ts_disable']).', 1)'
			);
		}

		// Create graph prototypes for hint check.
		$graph_prototypes_data = [];
		$k = 1;
		foreach ($item_protototypes['itemids'] as $itemid) {
			$graph_prototypes_data[] = [
				'name' => 'Graph prototype '.$k.' {#KEY}',
				'width' => 600,
				'height' => 300,
				'gitems' => [
					[
						'itemid' => $itemid,
						'color' => '5C6BC0'
					]
				]
			];
			$k++;
		}
		$graph_protototypes = CDataHelper::call('graphprototype.create', $graph_prototypes_data);

		$discovered_graphs = [];
		$p = 0;
		foreach ($graph_protototypes['graphids'] as $graph_protototypeid) {
			$discovered_graphs[] = array_merge($statuses[$p], [
				'name' => 'Graph prototype '.($p + 1).' KEY1',
				'graphid' => $p + 2090000,
				'graph_prototypeid' => $graph_protototypeid,
				'graph_itemid' => $p + 3090000,
				'itemid' => $discovered_items[$p]['itemid']
			]);
			$p++;
		}

		// Emulate graph discovery in DB.
		foreach ($discovered_graphs as $discovered_graph) {
			DBexecute('INSERT INTO graphs (graphid, width, height, name, flags) VALUES ('.zbx_dbstr($discovered_graph['graphid']).
					', 600, 300, '.zbx_dbstr($discovered_graph['name']).', 4)'
			);
			DBexecute('INSERT INTO graphs_items (gitemid, graphid, itemid, color) VALUES ('.
					zbx_dbstr($discovered_graph['graph_itemid']).', '.zbx_dbstr($discovered_graph['graphid']).', '.
					zbx_dbstr($discovered_graph['itemid']).', '.zbx_dbstr('5C6BC0').')'
			);
			DBexecute('INSERT INTO graph_discovery (graphid, parent_graphid, lastcheck, ts_delete, status)'.
					' VALUES ('.zbx_dbstr($discovered_graph['graphid']).', '.zbx_dbstr($discovered_graph['graph_prototypeid']).', '.
					zbx_dbstr($discovered_graph['lastcheck']).', '.zbx_dbstr($discovered_graph['ts_delete']).', 1)'
			);
		}

		// Create host prototypes for hint check.
		$host_prototype_data = [];
		$l = 1;
		foreach ($hosts['discoveryruleids'] as $lldid) {
			$host_prototype_data[] = [
				'host' => 'Host prototype '.$l.' {#KEY}',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => $hostgroupid]]
			];
			$l++;
		}
		$host_prototypes = CDataHelper::call('hostprototype.create', $host_prototype_data);

		$discovered_hosts = [];
		$m = 1;
		foreach ($host_prototypes['hostids'] as $host_prototypeid) {
			$discovered_hosts[] = array_merge($statuses[$m - 1], [
				'discovered_host_name' => 'Host prototype '.$m.' KEY1',
				'hostid' => $m + 7090000,
				'host_prototypeid' => $host_prototypeid,
				'host_groupid' => $m + 9090000
			]);
			$m++;
		}

		// Emulate host discovery in DB.
		foreach ($discovered_hosts as $discovered_host) {
			DBexecute('INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES ('.
					zbx_dbstr($discovered_host['hostid']).', '.zbx_dbstr($discovered_host['discovered_host_name']).
					', '.zbx_dbstr($discovered_host['discovered_host_name']).', '.$discovered_host['status'].', 4, \'\')'
			);
			DBexecute('INSERT INTO host_discovery (hostid, parent_hostid, lastcheck, ts_delete, disable_source, ts_disable, status)'.
					' VALUES ('.zbx_dbstr($discovered_host['hostid']).', '.zbx_dbstr($discovered_host['host_prototypeid']).', '.
					zbx_dbstr($discovered_host['lastcheck']).', '.zbx_dbstr($discovered_host['ts_delete']).', '.
					zbx_dbstr($discovered_host['disable_source']).', '.zbx_dbstr($discovered_host['ts_disable']).', 1)'
			);
			DBexecute('INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES ('.zbx_dbstr($discovered_host['host_groupid']).
					', '.zbx_dbstr($discovered_host['hostid']).', 4)'
			);
		}
	}

	public static function getTestPagesData() {
		return [
			// #0.
			[
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list&filter_set=1&filter_host=KEY1'
				]
			],
			// #1.
			[
				[
					'object' => 'item',
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #2.
			[
				[
					'object' => 'trigger',
					'url' => 'zabbix.php?action=trigger.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #3.
			[
				[
					'object' => 'graph',
					'url' => 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			]
		];
	}

	/**
	 * @dataProvider getTestPagesData
	 */
	public function testLowLevelDiscoveryDisabledObjects_TestPages($data) {
		$url = ($data['object'] === 'host') ? $data['url'] : $data['url'].self::$hint_hostid;
		$this->page->login()->open($url)->waitUntilReady();
		$table = $this->query('xpath://form/table')->asTable()->waitUntilVisible()->one();

		$lld_objects = [
			'1 Delete - never, disable - never' => [
				'common_hint' => '/^The '.$data['object'].' is not discovered anymore and will not be disabled,'.
						' will not be deleted\.$/',
				'graph_hint' => '/^The graph is not discovered anymore and will not be deleted\.$/'
			],
			'2 Delete - never, disable - immediately' => [
				'common_hint' => '/^The '.$data['object'].' is not discovered anymore and has been disabled,'.
						' will not be deleted\.$/',
				'graph_hint' => '/^The graph is not discovered anymore and will not be deleted\.$/',
				'disabled' => true
			],
			'3 Delete - never, disable - after 2w' => [
				'common_hint' => "/^The ".$data['object']." is not discovered anymore and will be disabled in".
						" \d{1,2}d( \d{1,2}h( \d{1,2}m)?)?, will not be deleted\.$/",
				'graph_hint' => '/^The graph is not discovered anymore and will not be deleted\.$/'
			],
			'4 Delete - after 52w, disable - never' => [
				'common_hint' => '/^The '.$data['object'].' is not discovered anymore and will not be disabled,'.
						' will be deleted in 1y\.$/',
				'graph_hint' => '/^The graph is not discovered anymore and will be deleted in 1y\.$/'
			],
			'5 Delete - after 7d, disable - immediately' => [
				'common_hint' => "/^The ".$data['object']." is not discovered anymore and has been disabled,".
						" will be deleted in \d{1,2}d( \d{1,2}h( \d{1,2}m)?)?\.$/",
				'graph_hint' => "/^The graph is not discovered anymore and will be deleted in \d{1,2}d( \d{1,2}h( \d{1,2}m)?)?\.$/",
				'disabled' => true
			],
			'6 Delete - after 7d, disable - after 20h' => [
				'common_hint' => "/^The ".$data['object']." is not discovered anymore and will be disabled in".
						" \d{1,2}h( \d{1,2}m( \d{1,2}s)?)?, will be deleted in \d{1,2}d( \d{1,2}h( \d{1,2}m)?)?\.$/",
				'graph_hint' => "/^The graph is not discovered anymore and will be deleted in \d{1,2}d( \d{1,2}h( \d{1,2}m)?)?\.$/"
			],
			'7 Delete - Immediately' => [
				'common_hint' => '/^The '.$data['object'].' is not discovered anymore and will'.
						' (be deleted the next time discovery rule is processed|not be disabled, will be deleted in 0)\.$/',
				'graph_hint' => '/^The graph is not discovered anymore and will'.
						' (be deleted the next time discovery rule is processed|not be disabled, will be deleted in 0)\.$/'
			]
		];

		foreach ($lld_objects as $lld => $hint) {
			$row = $table->findRow('Name', $lld, true);
			$overlay = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog();

			// Check object status, there is no Status column on Graph page.
			if ($data['object'] !== 'graph') {
				$status_column = $row->getColumn('Status');
				$status = (CTestArrayHelper::get($hint, 'disabled', false)) ? 'Disabled' :'Enabled';
				$this->assertEquals($status, $status_column->getText());
				$alert_button = $status_column->query('xpath:.//button[contains(@class, "zi-alert-with-content")]');

				// Check alert button and alert text if status is disabled.
				if ($status === 'Enabled') {
					$this->assertFalse($alert_button->exists());
				}
				else {
					$alert_button->one()->waitUntilClickable()->click();
					$alert_overlay = $overlay->all()->last()->waitUntilPresent();
					$this->assertEquals('Disabled automatically by an LLD rule.', $alert_overlay->getText());
					$alert_overlay->close();
				}
			}

			// Click button in Info column.
			$row->getColumn('Info')->query('xpath:.//button[contains(@class, "zi-i-warning")]')
					->one()->waitUntilCLickable()->click();
			$hint_overlay = $overlay->all()->last()->waitUntilReady();

			// Hints are different for graphs.
			$hint_text = ($data['object'] === 'graph') ? $hint['graph_hint'] : $hint['common_hint'];

			// Assert hint text for every object depending on LLD configuration.
			$this->assertEquals(1, preg_match($hint_text, $hint_overlay->getText()), 'Hint text "'.
					$hint_overlay->getText().'" does not match with expected "'.$hint_text.'"');
			$hint_overlay->close();
		}
	}
}
