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

class TagFilter {

	/**
	 * Create data for tag filtering tests.
	 *
	 * @return array
	 */
	public static function load() {
		// Create templates.
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template for tags filtering',
				'groups' => [
					'groupid' => 1
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'simple'
					],
					[
						'tag' => 'tag',
						'value' => 'TEMPLATE'
					],
					[
						'tag' => 'test',
						'value' => 'test_tag'
					]
				]
			],
			[
				'host' => 'Template for tags filtering - clone',
				'groups' => [
					'groupid' => 1
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'tag',
						'value' => 'template'
					]
				]
			],
			[
				'host' => 'Template for tags filtering - update',
				'groups' => [
					'groupid' => 1
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag',
						'value' => 'template'
					]
				]
			]
		]);

		// Create hosts.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for tags filtering',
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10051'
					]
				],
				'groups' => [
					'groupid' => 4
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'simple'
					],
					[
						'tag' => 'tag',
						'value' => 'HOST'
					],
					[
						'tag' => 'test',
						'value' => 'test_tag'
					]
				],
				'templates' => [
					'templateid' => $templates['templateids']['Template for tags filtering']
				]
			],
			[
				'host' => 'Host for tags filtering - update',
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10051'
					]
				],
				'groups' => [
					'groupid' => 4
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag',
						'value' => 'host'
					]
				]
			],
			[
				'host' => 'Host for tags filtering - clone',
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10051'
					]
				],
				'groups' => [
					'groupid' => 4
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'tag',
						'value' => 'host'
					]
				]
			]
		]);

		$result = array_merge_recursive($hosts, $templates);

		return $result;
	}
}
