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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testTagFiltering extends CAPITest {

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'Group of hosts with wide usage of tags/Templates']
			],
			'templates' => [
				[
					'host' => 'Workstation',
					'groups' => [
						['groupid' => ':template_group:Group of hosts with wide usage of tags/Templates']
					],
					'tags' => [
						['tag' => 'office', 'value' => 'Riga']
					]
				],
				[
					'host' => 'Template Browser - FF',
					'groups' => [
						['groupid' => ':template_group:Group of hosts with wide usage of tags/Templates']
					],
					'tags' => [
						['tag' => 'Browser', 'value' => 'FF'],
						['tag' => 'Webbrowser', 'value' => 'Mozilla']
					]
				],
				[
					'host' => 'Template OS - Ubuntu Bionic Beaver',
					'groups' => [
						['groupid' => ':template_group:Group of hosts with wide usage of tags/Templates']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Ubuntu Bionic Beaver']
					]
				]
			],
			'host_groups' => [
				['name' => 'Group of hosts with wide usage of tags/Hosts']
			],
			'hosts' => [
				[
					'host' => 'Host without tags',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					]
				],
				[
					'host' => 'Host with very general tags only',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'Other', 'value' => '']
					]
				],
				[
					'host' => 'Host Browser',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'Browser', 'value' => '']
					]
				],
				[
					'host' => 'Host Browser - Chrome',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'Browser', 'value' => 'Chrome']
					]
				],
				[
					'host' => 'Host Browser - IE',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'Browser', 'value' => 'IE']
					]
				],
				[
					'host' => 'Host OS',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'OS', 'value' => '']
					]
				],
				[
					'host' => 'Host OS - Android',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Android']
					]
				],
				[
					'host' => 'Host OS - Mac',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Mac']
					]
				]
			]
		]);

		CTestDataHelper::createObjects([
			'templates' => [
				[
					'host' => 'Template OS - Windows',
					'groups' => [
						['groupid' => ':template_group:Group of hosts with wide usage of tags/Templates']
					],
					'templates' => [
						['templateid' => ':template:Workstation']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Win7']
					]
				]
			]
		]);

		CTestDataHelper::createObjects([
			'hosts' => [
				[
					'host' => 'Host Browser - Firefox',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'templates' => [
						['templateid' => ':template:Template Browser - FF']
					],
					'tags' => [
						['tag' => 'Browser', 'value' => 'Firefox']
					]
				],
				[
					'host' => 'Host OS - Linux',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'templates' => [
						['templateid' => ':template:Template OS - Ubuntu Bionic Beaver']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Linux']
					]
				],
				[
					'host' => 'Host OS - Windows',
					'groups' => [
						['groupid' => ':host_group:Group of hosts with wide usage of tags/Hosts']
					],
					'templates' => [
						['templateid' => ':template:Template OS - Windows']
					],
					'tags' => [
						['tag' => 'OS', 'value' => 'Windows']
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function host_get_data() {
		return [
			// evaltype: AND/OR
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
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE'
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

			// evaltype: OR
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
			]
		];
	}

	/**
	 * @dataProvider host_get_data
	 */
	public function testHost_Get($filter, $expected) {
		$request = [
			'output' => ['host'],
			'groupids' => CTestDataHelper::getConvertedValueReferences([
				':template_group:Group of hosts with wide usage of tags/Templates',
				':host_group:Group of hosts with wide usage of tags/Hosts'
			])
		] + $filter;

		['result' => $result] = $this->call('host.get', $request);

		$result = array_column($result, 'host');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}

	public static function host_tag_inheritance_get_data() {
		return [
			// evaltype: AND/OR
			'tests-exist-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'Host OS', 'Host OS - Android', 'Host OS - Linux', 'Host OS - Mac', 'Host OS - Windows'
				]
			],
			'tests-exist-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => []
			],
			'tests-equal-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Ubuntu Bionic Beaver']
					]
				],
				'expected' => [
					'Host OS - Linux'
				]
			],
			'tests-equal-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'FF']
					]
				],
				'expected' => []
			],
			'tests-contain-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'win7']
					]
				],
				'expected' => [
					'Host OS - Windows'
				]
			],
			'tests-not-exist-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE',
					'Host without tags', 'Host with very general tags only'
				]
			],
			'tests-not-exist-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'Host without tags', 'Host with very general tags only'
				]
			],
			'tests-not-equal-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Mac']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE', 'Host OS',
					'Host OS - Android', 'Host OS - Linux', 'Host OS - Windows', 'Host without tags',
					'Host with very general tags only'
				]
			],
			'tests-not-contain-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE', 'Host OS',
					'Host OS - Android', 'Host OS - Linux', 'Host OS - Mac', 'Host without tags',
					'Host with very general tags only'
				]
			],
			'tests-containing-template-tag-with-excluding-different-hosts-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'Webbrowser', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Mozilla'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					]
				],
				'expected' => []
			],
			'tests-inheritance-from-2nd-level-template' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'office', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Riga']
					]
				],
				'expected' => [
					'Host OS - Windows'
				]
			],

			// evaltype: OR
			'tests-equal-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Ubuntu Bionic Beaver']
					]
				],
				'expected' => [
					'Host OS - Linux', 'Host OS - Windows'
				]
			],
			'tests-contain-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Win7'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'F']
					]
				],
				'expected' => [
					'Host Browser - Firefox', 'Host OS - Windows'
				]
			]
		];
	}

	/**
	 * @dataProvider host_tag_inheritance_get_data
	 */
	public function testHostTagInheritance_Get($filter, $expected) {
		$request = [
			'output' => ['host'],
			'groupids' => CTestDataHelper::getConvertedValueReferences([
				':template_group:Group of hosts with wide usage of tags/Templates',
				':host_group:Group of hosts with wide usage of tags/Hosts'
			]),
			'inheritedTags' => true
		] + $filter;

		['result' => $result] = $this->call('host.get', $request);

		$result = array_column($result, 'host');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}

	public static function template_get_data() {
		return [
			// evaltype: AND/OR
			'templates-exists-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'Template OS - Ubuntu Bionic Beaver', 'Template OS - Windows'
				]
			],
			'templates-equals-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7']
					]
				],
				'expected' => [
					'Template OS - Windows'
				]
			],
			'templates-equals-nonexistent-value-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win777']
					]
				],
				'expected' => []
			],
			'templates-contains-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Win']
					]
				],
				'expected' => [
					'Template OS - Windows'
				]
			],
			'templates-case-insensitive-contains-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'WIN']
					]
				],
				'expected' => [
					'Template OS - Windows'
				]
			],
			'templates-not-exists-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'Template Browser - FF', 'Workstation'
				]
			],
			'templates-not-queal-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Win7']
					]
				],
				'expected' => [
					'Template Browser - FF', 'Template OS - Ubuntu Bionic Beaver', 'Workstation'
				]
			],
			'templates-exists-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => []
			],
			'templates-equals-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					]
				],
				'expected' => [
					'Template OS - Windows'
				]
			],
			'templates-not-exists-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'Workstation'
				]
			],

			// evaltype: OR
			'templates-exists-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'Template Browser - FF', 'Template OS - Ubuntu Bionic Beaver', 'Template OS - Windows'
				]
			],
			'templates-equals-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Win7'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'FF']
					]
				],
				'expected' => [
					'Template Browser - FF', 'Template OS - Windows'
				]
			],
			'templates-equals-or-exists' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'FF']
					]
				],
				'expected' => [
					'Template Browser - FF', 'Template OS - Ubuntu Bionic Beaver', 'Template OS - Windows'
				]
			],
			'templates-not-equal-one-of-two-nonexistent-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'FF'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Win7']
					]
				],
				'expected' => [
					'Template Browser - FF', 'Template OS - Ubuntu Bionic Beaver', 'Template OS - Windows',
					'Workstation'
				]
			]
		];
	}

	/**
	 * @dataProvider template_get_data
	 */
	public function testTemplate_Get($filter, $expected) {
		$request = [
			'output' => ['name'],
			'groupids' => CTestDataHelper::getConvertedValueReferences([
				':template_group:Group of hosts with wide usage of tags/Templates',
				':host_group:Group of hosts with wide usage of tags/Hosts'
			])
		] + $filter;

		['result' => $result] = $this->call('template.get', $request);

		$result = array_column($result, 'name');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}
}
