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

	const EVALTYPE_AND_OR = 0;
	const EVALTYPE_OR = 2;

	const OP_LIKE = 0;
	const OP_EQUAL = 1;
	const OP_NOT_LIKE = 2;
	const OP_NOT_EQUAL = 3;
	const OP_EXISTS = 4;
	const OP_NOT_EXISTS = 5;

	public static function filterHostsByTagsData() {
		return [
			'test-equals-with-single-tag' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Windows']
					]
				],
				'expected' => [
					'Host OS - Windows'
				]
			],
			'test-equals-with-two-tags' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Windows'],
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Linux']
					]
				],
				'expected' => [
					'Host OS - Windows', 'Host OS - Linux'
				]
			],
			'test-not-equals-with-single-tag' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => self::OP_NOT_EQUAL, 'value' => 'Chrome']
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
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => self::OP_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => self::OP_NOT_EQUAL, 'value' => 'Firefox']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host OS', 'Host OS - Android', 'Host OS - Linux',
					'Host OS - Mac', 'Host OS - Windows', 'Host without tags', 'Host with very general tags only'
				]
			],
			'test-exists-with-single-tag' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => self::OP_EXISTS]
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host Browser - Chrome', 'Host Browser - Firefox'
				]
			],
			'test-contains-with-single-tag' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => self::OP_LIKE, 'value' => '']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE', 'Host Browser - Chrome', 'Host Browser - Firefox'
				]
			],
			'test-contains-or-equals' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_LIKE, 'value' => 'Win'],
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Linux']
					]
				],
				'expected' => [
					'Host OS - Linux', 'Host OS - Windows'
				]
			],
			'test-not-exists-with-exception' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_NOT_EXISTS],
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Android']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - Chrome', 'Host Browser - Firefox', 'Host Browser - IE',
					'Host OS - Android', 'Host without tags', 'Host with very general tags only'
				]
			],
			'test-two-not-exists-with-exception' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => self::OP_NOT_EXISTS],
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Android']
					]
				],
				'expected' => [
					'Host OS - Android', 'Host without tags', 'Host with very general tags only'
				]
			],
			'test-not-exists-with-two-exceptions' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'Browser', 'operator' => self::OP_EXISTS],
						['tag' => 'Browser', 'operator' => self::OP_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => self::OP_NOT_EQUAL, 'value' => 'Firefox']
					]
				],
				'expected' => [
					'Host Browser', 'Host Browser - IE'
				]
			],
			'tets-tag-inheritance-off' => [
				'filter' => [
					'evaltype' => self::EVALTYPE_AND_OR,
					'tags' => [
						['tag' => 'OS', 'operator' => self::OP_EQUAL, 'value' => 'Win7']
					],
					'inheritedTags' => false
				],
				'expected' => []
			]
		];
	}

	/**
	 * @dataProvider filterHostsByTags
	 */
	public function filterHostsByTags($filter, $expected) {
		$request = [
			'output' => ['host'],
			'groupids' => '50027'
		] + $filter;

		$result = $this->call('host.get', $request);
		$result = array_column($result, 'host');

		sort($result);
		sort($expected);

		$this->assertTrue(array_values($result) === array_values($expected));
	}
}
