<?php declare(strict_types = 0);
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


require_once __DIR__.'/../include/CTest.php';
require_once __DIR__.'/../../include/classes/core/CRegistryFactory.php';
require_once __DIR__.'/../../include/classes/api/API.php';
require_once __DIR__.'/../../include/classes/api/CApiServiceFactory.php';
require_once __DIR__.'/../../include/classes/api/helpers/CApiTagHelper.php';
require_once __DIR__.'/../../include/classes/api/services/CItemGeneral.php';
require_once __DIR__.'/../../include/classes/api/services/CItem.php';

class CApiTagHelperTest extends CTest {

	public static function getNativeTagsData(): array {
		$sql_args = [2 => ['e'], 'event_tag', 'eventid'];

		return [
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND UPPER(event_tag.value)'.
						' LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value4'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value5'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],	// duplicate
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag1\''.
						' AND ('.
							'UPPER(event_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(event_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR event_tag.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag2\''.
						' AND ('.
							'UPPER(event_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							' OR event_tag.value=\'Value4\''.
						')'.
				')'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag3\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value4'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value5'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(event_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(event_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR event_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(event_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR event_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag3\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'Value\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
						' AND event_tag.value=\'Chrome\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
						' AND event_tag.value IN (\'Chrome\',\'Firefox\')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
							' AND event_tag.value=\'Android\''.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
							' AND event_tag.value=\'Android\''.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Browser\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'OS\''.
						' AND UPPER(event_tag.value)'.
						' LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'tag1\''.
						' AND UPPER(event_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'OS\''.
						' AND UPPER(event_tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'val'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'('.
					'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
							' AND ('.
								'UPPER(event_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR event_tag.value=\'val\''.
							')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'val'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'value'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
							' AND ('.
								'UPPER(event_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR event_tag.value=\'val\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
					')'.
				')'
			]
		];
	}

	public static function getInheritedTagsData(): Generator {
		$params_host_tags = [2 => ['h'], 'host_tag', 'hostid'];
		$params_host_tags_inherited = [2 => ['h'], 'host_tag', 'hostid', true];
		$params_httptest_tags = [2 => ['ht'], 'httptest_tag', 'httptestid'];
		$params_httptest_tags_inherited = [2 => ['ht', 'hti'], 'httptest_tag', 'httptestid', true];
		$params_item_tags = [2 => ['i'], 'item_tag', 'itemid'];
		$params_item_tags_inherited = [2 => ['i'], 'item_tag', 'itemid', true];
		$params_trigger_tags = [2 => ['t'], 'trigger_tag', 'triggerid'];
		$params_trigger_tags_inherited = [2 => ['t', 'f'], 'trigger_tag', 'triggerid', true];

		$select_host_tags_where =
			'SELECT NULL'.
			' FROM host_tag'.
			' WHERE h.hostid=host_tag.hostid';

		$select_host_inherited_tags_where =
			'SELECT NULL'.
			' FROM host_template_cache htc'.
			' JOIN host_tag ON htc.link_hostid=host_tag.hostid'.
			' WHERE h.hostid=htc.hostid';

		$select_httptest_tags_where =
			'SELECT NULL'.
			' FROM httptest_tag'.
			' WHERE ht.httptestid=httptest_tag.httptestid';

		$select_httptest_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE hti.itemid=itc.itemid';

		$select_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag'.
			' WHERE i.itemid=item_tag.itemid';

		$select_item_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE i.itemid=itc.itemid';

		$select_trigger_tags_where =
			'SELECT NULL'.
			' FROM trigger_tag'.
			' WHERE t.triggerid=trigger_tag.triggerid';

		$select_trigger_inherited_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag'.
			' WHERE f.itemid=item_tag.itemid';

		$select_trigger_inherited_item_tags_where_not =
			'SELECT NULL'.
			' FROM functions'.
			' JOIN item_tag ON functions.itemid=item_tag.itemid'.
			' WHERE t.triggerid=functions.triggerid';

		$select_trigger_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE f.itemid=itc.itemid';

		$select_trigger_inherited_host_tags_where_not =
			'SELECT NULL'.
			' FROM functions'.
			' JOIN item_template_cache itc ON functions.itemid=itc.itemid'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE t.triggerid=functions.triggerid';

		yield 'Get host native tags only (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_host_tags,
			'EXISTS ('.
				$select_host_tags_where.
					' AND host_tag.tag=\'Tag\''.
					' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
			')'
		];

		yield 'Get host native tags and inherited tags (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_host_tags_inherited,
			'EXISTS ('.
				$select_host_inherited_tags_where.
					' AND host_tag.tag=\'Tag\''.
					' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
			')'
		];

		yield 'Get httptest native tags only (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_httptest_tags,
			'EXISTS ('.
				$select_httptest_tags_where.
					' AND httptest_tag.tag=\'Tag\''.
					' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
			')'
		];

		yield 'Get httptest native tags and inherited tags (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_httptest_tags_inherited,
			'('.
				'EXISTS ('.
				$select_httptest_tags_where.
					' AND httptest_tag.tag=\'Tag\''.
					' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_httptest_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
			')'
		];

		yield 'Get httptest native tags and inherited tags (AND/OR|1 tag (2 equal)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_httptest_tags_inherited,
			'('.
				'EXISTS ('.
				$select_httptest_tags_where.
					' AND httptest_tag.tag=\'Tag\''.
					' AND httptest_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
					$select_httptest_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value\',\'Value2\')'.
				')'.
			')'
		];

		yield 'Get item native tags only (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_item_tags,
			'EXISTS ('.
				$select_item_tags_where.
					' AND item_tag.tag=\'Tag\''.
					' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
			')'
		];

		yield 'Get item native tags and inherited tags (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_item_tags_inherited,
			'('.
				'EXISTS ('.
				$select_item_tags_where.
					' AND item_tag.tag=\'Tag\''.
					' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
			')'
		];

		yield 'Get trigger native tags only (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_trigger_tags,
			'EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag\''.
					' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
			')'
		];

		yield 'Get trigger native tags and inherited tags (AND/OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_trigger_tags_inherited,
			'('.
				'EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag\''.
					' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
			')'
		];

		yield 'Get trigger native tags and inherited tags (OR|1 tag (1 like)).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR
			] + $params_trigger_tags_inherited,
			'('.
				'EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag\''.
					' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
			')'
		];

		yield 'Get trigger native tags and inherited tags (OR|2 tag ((2 equal), (2 equal))).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR
			] + $params_trigger_tags_inherited,
			'('.
				'EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag\''.
					' AND trigger_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag2\''.
					' AND trigger_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_item_tags_where.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' OR '.
				'EXISTS ('.
					$select_trigger_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value\',\'Value2\')'.
				')'.
			')'
		];

		yield 'Get trigger native tags and inherited tags (AND/OR|2 tag ((2 not equal), (2 not equal))).' => [
			[
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR
			] + $params_trigger_tags_inherited,
			'('.
				'NOT EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag\''.
					' AND trigger_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' AND '.
				'NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' AND '.
				'NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value\',\'Value2\')'.
				')'.
			')'.
			' AND '.
			'('.
				'NOT EXISTS ('.
				$select_trigger_tags_where.
					' AND trigger_tag.tag=\'Tag2\''.
					' AND trigger_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' AND '.
				'NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value IN (\'Value\',\'Value2\')'.
				')'.
				' AND '.
				'NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value\',\'Value2\')'.
				')'.
			')'
		];
	}

	/**
	 * @dataProvider getNativeTagsData
	 * @dataProvider getInheritedTagsData
	 */
	public function test(array $params, string $expected): void {
		$this->assertSame($expected, call_user_func_array(['CApiTagHelper', 'getTagCondition'], $params));
	}
}
