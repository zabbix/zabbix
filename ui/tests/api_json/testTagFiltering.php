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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testTagFiltering extends CAPITest {

	const HOST_GROUPS = [50027, 50028];

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
			'groupids' => self::HOST_GROUPS
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
			'groupids' => self::HOST_GROUPS,
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
			'groupids' => self::HOST_GROUPS
		] + $filter;

		['result' => $result] = $this->call('template.get', $request);

		$result = array_column($result, 'name');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}

	/**
	 * Test cases for event.get and problem.get API methods.
	 */
	public static function event_get_data() {
		return [
			// evaltype: AND/OR
			'events-exists-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'trigger1', 'trigger2', 'trigger3', 'trigger4'
				]
			],
			'events-exists-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'trigger1', 'trigger2'
				]
			],
			'events-equals-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'value6']
					]
				],
				'expected' => [
					'trigger2'
				]
			],
			'events-equals-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'value1'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'value5']
					]
				],
				'expected' => [
					'trigger1', 'trigger2'
				]
			],
			'events-equals-two-tags-one-empty' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'value6'],
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					]
				],
				'expected' => [
					'trigger1', 'trigger2'
				]
			],
			'events-contains-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'value']
					]
				],
				'expected' => [
					'trigger1'
				]
			],
			'events-contains-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Win']
					]
				],
				'expected' => [
					'trigger1', 'trigger2', 'trigger3'
				]
			],
			'events-not-exist-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'trigger3', 'trigger4'
				]
			],
			'events-not-equal-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
					]
				],
				'expected' => [
					'trigger2', 'trigger3', 'trigger4'
				]
			],
			'events-not-equal-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => ''],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'value7']
					]
				],
				'expected' => [
					'trigger2', 'trigger4'
				]
			],
			'events-not-contain-single-tag' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_AND_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value']
					]
				],
				'expected' => [
					'trigger4'
				]
			],

			// evaltype: OR
			'events-exist-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'tag3', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'trigger1', 'trigger2'
				]
			],
			'events-tag-exists-or-another-empty' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'tag3', 'operator' => TAG_OPERATOR_EXISTS]
					]
				],
				'expected' => [
					'trigger1', 'trigger2'
				]
			],
			'events-contains-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => '5'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => '7']
					]
				],
				'expected' => [
					'trigger2', 'trigger3'
				]
			],
			'events-not-exist-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'tag3', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					]
				],
				'expected' => [
					'trigger2', 'trigger3', 'trigger4'
				]
			],
			'events-not-equal-one-of-two-tag-values' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'value1'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'value7']
					]
				],
				'expected' => [
					'trigger2', 'trigger4'
				]
			],
			'events-not-contain-one-of-two-tags' => [
				'filter' => [
					'evaltype' => TAG_EVAL_TYPE_OR,
					'tags' => [
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					]
				],
				'expected' => [
					'trigger4'
				]
			]
		];
	}

	/**
	 * @dataProvider event_get_data
	 */
	public function testEvent_Get($filter, $expected) {
		$request = [
			'output' => ['name'],
			'groupids' => self::HOST_GROUPS
		] + $filter;

		['result' => $result] = $this->call('event.get', $request);

		$result = array_column($result, 'name');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}

	/**
	 * @dataProvider event_get_data
	 */
	public function testProblem_Get($filter, $expected) {
		$request = [
			'output' => ['name'],
			'groupids' => self::HOST_GROUPS
		] + $filter;

		['result' => $result] = $this->call('problem.get', $request);

		$result = array_column($result, 'name');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}
}
