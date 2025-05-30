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

	public static function provider(): array {
		// Similar when filtering any entities (hosts, items, events, etc.) by own tags.
		$params_own_tags = [2 => false, 'event_tag', 'e', 'eventid'];
		$select_own_tags_where =
			'SELECT NULL'.
			' FROM event_tag tag'.
			' WHERE e.eventid=tag.eventid';

		// Custom when filtering entities by own and inherited tags.
		$params_host_tags = [2 => true, 'host_tag', 'h', 'hostid'];
		$params_httptest_tags = [2 => true, 'httptest_tag', 'ht', 'httptestid'];
		$params_item_tags = [2 => true, 'item_tag', 'i', 'itemid'];
		$params_trigger_tags = [2 => true, 'trigger_tag', 't', 'triggerid'];

		$select_host_tags_where =
			'SELECT NULL'.
			' FROM host_template_cache htc'.
			' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
			' WHERE h.hostid=htc.hostid';

		$select_httptest_tags_where =
			'SELECT NULL'.
			' FROM httptest_tag tag'.
			' WHERE ht.httptestid=tag.httptestid';
		$select_httptest_host_tags_where =
			'SELECT NULL'.
			' FROM httptest_template_cache htc'.
			' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
			' WHERE ht.httptestid=htc.httptestid';

		$select_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag tag'.
			' WHERE i.itemid=tag.itemid';
		$select_item_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
			' WHERE i.itemid=itc.itemid';

		$select_trigger_tags_where =
			'SELECT NULL'.
			' FROM trigger_tag tag'.
			' WHERE t.triggerid=tag.triggerid';
		$select_trigger_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag tag'.
			' WHERE f.itemid=tag.itemid';
		$select_trigger_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
			' WHERE f.itemid=itc.itemid';

		return [
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
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
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag1\''.
						' AND ('.
							'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR tag.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag2\''.
						' AND ('.
							'tag.value=\'Value4\''.
							' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag3\''.
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
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag1\''.
						' AND ('.
							'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR tag.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag2\''.
						' AND ('.
							'tag.value=\'Value4\''.
							' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag3\''.
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
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
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
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
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
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
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
				] + $params_own_tags,
				'('.
					'EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
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
				] + $params_host_tags,
				'('.
					'EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'Tag2\''.
							' AND ('.
								'tag.value=\'Value4\''.
								' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'Tag3\''.
					')'.
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
				] + $params_httptest_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
					')'.
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
				] + $params_item_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
					')'.
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
				] + $params_trigger_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'Tag1\''.
								' AND ('.
									'UPPER(tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
									' OR UPPER(tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
									' OR tag.value=\'Value3\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'Tag2\''.
								' AND ('.
									'tag.value=\'Value4\''.
									' OR UPPER(tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'Tag3\''.
						')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'Value\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
						' AND tag.value=\'Value\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
							' AND tag.value=\'Value\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Tag\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'NOT EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Browser\''.
						' AND tag.value=\'Chrome\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'NOT EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Browser\''.
						' AND tag.value=\'Chrome\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'NOT EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
					' AND NOT EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'NOT EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
					' AND NOT EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'NOT EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND tag.value=\'Chrome\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'NOT EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Browser\''.
						' AND ('.
							'tag.value=\'Chrome\''.
							' OR tag.value=\'Firefox\''.
						')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'NOT EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Browser\''.
						' AND ('.
							'tag.value=\'Chrome\''.
							' OR tag.value=\'Firefox\''.
						')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'NOT EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
					' AND NOT EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'NOT EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
					' AND NOT EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'NOT EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Browser\''.
							' AND ('.
								'tag.value=\'Chrome\''.
								' OR tag.value=\'Firefox\''.
							')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
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
				] + $params_own_tags,
				'EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'Browser\''.
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
				] + $params_host_tags,
				'EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'Browser\''.
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
				] + $params_httptest_tags,
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
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
				] + $params_item_tags,
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
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
				] + $params_trigger_tags,
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'Browser\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $params_own_tags,
				'('.
					'NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'OS\''.
							' AND tag.value=\'Android\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $params_host_tags,
				'('.
					'NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND tag.value=\'Android\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $params_httptest_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $params_item_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $params_trigger_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
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
				] + $params_own_tags,
				'('.
					'NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'OS\''.
							' AND tag.value=\'Android\''.
					')'.
					' OR NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'Browser\''.
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
				] + $params_host_tags,
				'('.
					'NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND tag.value=\'Android\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'Browser\''.
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
				] + $params_httptest_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
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
				] + $params_item_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
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
				] + $params_trigger_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'OS\''.
						')'.
					')'.
					' OR ('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'OS\''.
								' AND tag.value=\'Android\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'Browser\''.
						')'.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'NOT EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'OS\''.
						' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'NOT EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'OS\''.
						' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'NOT EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'NOT EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'NOT EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_own_tags,
				'NOT EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'tag1\''.
						' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_own_tags_where.
						' AND tag.tag=\'OS\''.
						' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_host_tags,
				'NOT EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'tag1\''.
						' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_host_tags_where.
						' AND tag.tag=\'OS\''.
						' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_httptest_tags,
				'('.
					'NOT EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'NOT EXISTS ('.
						$select_httptest_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_httptest_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_item_tags,
				'('.
					'NOT EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'NOT EXISTS ('.
						$select_item_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_item_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $params_trigger_tags,
				'('.
					'NOT EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'NOT EXISTS ('.
						$select_trigger_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_item_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
					' AND NOT EXISTS ('.
						$select_trigger_host_tags_where.
							' AND tag.tag=\'OS\''.
							' AND UPPER(tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
					')'.
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
				] + $params_own_tags,
				'('.
					'NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND ('.
								'tag.value=\'val\''.
								' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'tag1\''.
					')'.
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
				] + $params_host_tags,
				'('.
					'NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND ('.
								'tag.value=\'val\''.
								' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'tag1\''.
					')'.
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
				] + $params_httptest_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
					')'.
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
				] + $params_item_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
					')'.
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
				] + $params_trigger_tags,
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'tag1\''.
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
				] + $params_own_tags,
				'('.
					'EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND ('.
								'tag.value=\'val\''.
								' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_own_tags_where.
							' AND tag.tag=\'tag1\''.
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
				] + $params_host_tags,
				'('.
					'EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'tag1\''.
							' AND ('.
								'tag.value=\'val\''.
								' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_host_tags_where.
							' AND tag.tag=\'tag1\''.
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
				] + $params_httptest_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_host_tags_where.
								' AND tag.tag=\'tag1\''.
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
				] + $params_item_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_host_tags_where.
								' AND tag.tag=\'tag1\''.
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
				] + $params_trigger_tags,
				'('.
					'('.
						'EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
						' OR EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'tag1\''.
								' AND ('.
									'tag.value=\'val\''.
									' OR UPPER(tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_item_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_host_tags_where.
								' AND tag.tag=\'tag1\''.
						')'.
					')'.
				')'
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, string $expected): void {
		$this->assertSame($expected, call_user_func_array(['CApiTagHelper', 'addWhereCondition'], $params));
	}
}
