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


class ClockWidgets {

	/**
	 * Create data for autotests which use ClockWidget.
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'DEV-2236 hostgroup',
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

		CDataHelper::call('host.create', [
			'host' => 'DEV-2236 host',
			'groups' => [
				[
					'groupid' => $hostgrpid['DEV-2236 hostgroup']
				]
			],
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '192.168.3.217',
				'dns' => '',
				'port' => '10050'
			]
		]);
		$hostid = CDataHelper::getIds('host');

		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.$hostid['DEV-2236 host']);

		CDataHelper::call('item.create', [
			[
				'hostid' => $hostid['DEV-2236 host'],
				'name' => 'DEV-2236 item',
				'key_' => 'system.localtime[local]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => $interfaceid,
				'delay' => '5s',
			]
		]);
		$itemid = CDataHelper::getIds('name');

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'DEV-2236',
				'widgets' => [
					[
						'type' => 'clock',
						'name' => 'Local',
						'x' => 0,
						'y' => 0,
						'width' => 5,
						'height' => 5,
						'fields' => [
							[
								'type' => 0,
								'name' => 'rf_rate',
								'value' => -1
							]
						]
					],
					[
						'type' => 'clock',
						'name' => 'Server',
						'x' => 5,
						'y' => 0,
						'width' => 5,
						'height' => 5,
						'fields' => [
							[
								'type' => 0,
								'name' => 'rf_rate',
								'value' => -1
							]
						]
					],
					[
						'type' => 'clock',
						'name' => 'Host',
						'x' => 10,
						'y' => 0,
						'width' => 5,
						'height' => 5,
						'fields' => [
							[
								'type' => 4,
								'name' => 'itemid',
								'value' => $itemid['DEV-2236 item']
							],
							[
								'type' => 0,
								'name' => 'time_type',
								'value' => 2
							]
						]
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 7,
						'permission' => 3
					]
				]
			]
		]);
		$dashboardids = CDataHelper::getIds('name');

		return [
			'dashboardids' => $dashboardids
		];
	}
}
