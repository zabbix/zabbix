<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testTagFiltering extends CAPITest {

	public static function host_get_data() {
		return [
			'test-equals-with-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Windows']
					]
				],
				'expected' => [
					'Host OS - Windows'
				]
			],
			'test-equals-with-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Windows'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Linux']
					]
				],
				'expected' => [
					'Host OS - Windows', 'Host OS - Linux'
				]
			],
			'test-not-equals-with-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Firefox', 'Host Browser - IE', 'Host OS', 'Host OS - Android',
					'Host OS - Linux', 'Host OS - Mac', 'Host OS - Windows', 'Host without tags',
					'Host with very general tags only'
				]
			],
			'test-not-equals-with-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host OS', 'Host OS - Android', 'Host OS - Linux',
					'Host OS - Mac', 'Host OS - Windows', 'Host without tags', 'Host with very general tags only'
				]
			],
			'test-exists-with-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host Browser - Chrome', 'Host Browser - Firefox'
				]
			],
			'test-contains-with-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host Browser - Chrome', 'Host Browser - Firefox'
				]
			],
			'test-contains-or-equals' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Win'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Linux']
					]
				],
				'expected' => [
					'Host OS - Linux', 'Host OS - Windows'
				]
			],
			'test-not-exists-with-exception' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE',
					'Host OS - Android', 'Host without tags', 'Host with very general tags only'
				]
			],
			'test-two-not-exists-with-exception' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE', 'Host OS',
					'Host OS - Android', 'Host OS - Linux', 'Host OS - Mac', 'Host OS - Windows', 'Host without tags',
					'Host with very general tags only'
				]
			],
			'test-not-exists-with-two-exceptions' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE'
				]
			],
			'test-not-equals-with-empty-value' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE',
					'Host OS - Android', 'Host OS - Linux', 'Host OS - Mac', 'Host OS - Windows', 'Host without tags',
					'Host with very general tags only'
				]
			],
			'tets-tag-inheritance-on' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7']
					],
					'inheritedTags' => true
				],
				'expected' => ['Host OS - Windows']
			],
			'tets-tag-inheritance-off' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7']
					],
					'inheritedTags' => false
				],
				'expected' => []
			]
		];
	}

	/**
	 * @dataProvider host_get_data
	 */
	public function testHost_Get($filter, $expected) {
		$request = [
			'output' => ['host'],
			'groupids' => '50027'
		] + $filter;

		['result' => $result] = $this->call('host.get', $request);

		$result = array_column($result, 'host');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}
}
