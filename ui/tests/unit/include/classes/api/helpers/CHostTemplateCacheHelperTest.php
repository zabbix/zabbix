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


namespace helpers;

use CHostTemplateCacheHelper;
use PHPUnit\Framework\TestCase;

class CHostTemplateCacheHelperTest extends TestCase {

	public function dataProviderCreateLinks(): array {
		return [
			[
				[],
				[],
				[],
				[]
			],
			// Templates to link: T1 to T2.
			[
				['t2' => ['t1']],
				[],
				[],
				['t2' => ['t1']]
			],
			// Templates to link: T1 and T2 to T3.
			[
				['t3' => ['t1', 't2']],
				[],
				[],
				['t3' => ['t2', 't1']]
			],
			// Templates to link: T1 to T2; T3 to T4.
			[
				['t2' => ['t1'], 't4' => ['t3']],
				[],
				[],
				['t2' => ['t1'], 't4' => ['t3']]
			],
			// Templates to link: T1 to T2; T2 to T3.
			[
				['t2' => ['t1'], 't3' => ['t2']],
				[],
				[],
				['t2' => ['t1'], 't3' => ['t2', 't1']]
			],
			// Templates to link: T2 to T3. Templates linked: T1 to T2.
			[
				['t3' => ['t2']],
				['t2' => ['t1']],
				[],
				['t3' => ['t2', 't1']]
			],
			// Templates to link: T3 to T4. Templates linked: T1 and T2 to T3.
			[
				['t4' => ['t3']],
				['t3' => ['t1', 't2']],
				[],
				['t4' => ['t3', 't2', 't1']]
			],
			// Templates to link: T1 to T2. Templates linked: T2 to T3.
			[
				['t2' => ['t1']],
				[],
				['t2' => ['t3']],
				['t2' => ['t1'], 't3' => ['t1']]
			],
			// Templates to link: T1 to T2. Templates linked: T2 to T3 and T4.
			[
				['t2' => ['t1']],
				[],
				['t2' => ['t3', 't4']],
				['t2' => ['t1'], 't3' => ['t1'], 't4' => ['t1']]
			],
			// Templates to link: T2 to T3. Templates linked: T1 to T2; T3 to T4.
			[
				['t3' => ['t2']],
				['t2' => ['t1']],
				['t3' => ['t4']],
				['t3' => ['t2', 't1'], 't4' => ['t2', 't1']]
			],
			// Templates to link: T3 to T4. Templates linked: T1 and T2 to T3; T5 and T6 to T4.
			[
				['t4' => ['t3']],
				['t3' => ['t1', 't2']],
				['t4' => ['t5', 't6']],
				['t4' => ['t3', 't2', 't1'], 't5' => ['t3', 't2', 't1'], 't6' => ['t3', 't2', 't1']]
			],
			// Templates to link: T1 and T4 to T2; T2 to T3; T2 to T5.
			[
				[
					't2' => ['t1', 't4'],
					't3' => ['t2'],
					't5' => ['t2']
				],
				[],
				[],
				[
					't2' => ['t4', 't1'],
					't3' => ['t2', 't4', 't1'],
					't5' => ['t2', 't4', 't1']
				]
			],
			// Templates to link: T2 and T7 to T3; T3 to T4; T3 to T8. Templates linked: T1 to T2; T4 to T5; T6 to T7; T8 to T9.
			[
				[
					't3' => ['t2', 't7'],
					't4' => ['t3'],
					't8' => ['t3']
				],
				[
					't2' => ['t1'],
					't7' => ['t6']
				],
				[
					't4' => ['t5'],
					't8' => ['t9']
				],
				[
					't3' => ['t7', 't6', 't2', 't1'],
					't4' => ['t3', 't7', 't6', 't2', 't1'],
					't8' => ['t3', 't7', 't6', 't2', 't1'],
					't5' => ['t3', 't7', 't6', 't2', 't1'],
					't9' => ['t3', 't7', 't6', 't2', 't1']
				]
			],
			// Link multiple templates to other templates to form a single continuous chain.
			[
				[
					't3' => ['t11'],
					't4' => ['t3'],
					't5' => ['t4'],
					't6' => ['t5'],
					't9' => ['t8'],
					't13' => ['t12'],
					't14' => ['t4']
				],
				[
					't3' => ['t2', 't1'],
					't5' => ['t13'],
					't8' => ['t7', 't6']
				],
				[
					't4' => ['t14'],
					't5' => ['h3'],
					't6' => ['t7', 't8', 'h4'],
					't9' => ['t10', 'h1', 'h5'],
					't13' => ['t5', 'h3'],
					't14' => ['h2']
				],
				[
					't3' => ['t11'],
					't4' => ['t3', 't1', 't2', 't11'],
					't5' => ['t12', 't4', 't3', 't1', 't2', 't11'],
					't6' => ['t5', 't13', 't12', 't4', 't3', 't1', 't2', 't11'],
					't9' => ['t8', 't6', 't5', 't13', 't12', 't4', 't3', 't1', 't2', 't11', 't7'],
					't13' => ['t12'],
					't14' => ['t4', 't3', 't1', 't2', 't11'],
					't8' => ['t5', 't13', 't12', 't4', 't3', 't1', 't2', 't11'],
					'h3' => ['t12', 't4', 't3', 't1', 't2', 't11'],
					't7' => ['t5', 't13', 't12', 't4', 't3', 't1', 't2', 't11'],
					'h4' => ['t5', 't13', 't12', 't4', 't3', 't1', 't2', 't11'],
					't10' => ['t8', 't6', 't5', 't13', 't12', 't4', 't3', 't1', 't2', 't11', 't7'],
					'h1' => ['t8', 't6', 't5', 't13', 't12', 't4', 't3', 't1', 't2', 't11', 't7'],
					'h5' => ['t8', 't6', 't5', 't13', 't12', 't4', 't3', 't1', 't2', 't11', 't7'],
					'h2' => ['t4', 't3', 't1', 't2', 't11']
				]
			]
		];
	}

	public function dataProviderDeleteLinks(): array {
		return [
			[
				[],
				[],
				[],
				[]
			],
			// Templates to unlink: T1 from T2.
			[
				['t2' => ['t1']],
				[],
				[],
				['t2' => ['t1']]
			],
			// Templates to unlink: T1 and T2 from T3.
			[
				['t3' => ['t1', 't2']],
				[],
				[],
				['t3' => ['t1', 't2']]
			],
			// Templates to unlink: T1 from T2; T3 from T4.
			[
				['t2' => ['t1'], 't4' => ['t3']],
				[],
				[],
				['t2' => ['t1'], 't4' => ['t3']]
			],
			// Templates to unlink: T1 from T2; T2 from T3.
			[
				['t2' => ['t1'], 't3' => ['t2']],
				[],
				['t2' => ['t3']],
				['t2' => ['t1'], 't3' => ['t2', 't1']]
			],
			// Templates to unlink: T2 from T3. Templates remain linked: T1 to T2.
			[
				['t3' => ['t2']],
				['t2' => ['t1']],
				[],
				['t3' => ['t2', 't1']]
			],
			// Templates to unlink: T3 from T4. Templates remain linked: T1 and T2 to T3.
			[
				['t4' => ['t3']],
				['t3' => ['t1', 't2']],
				[],
				['t4' => ['t3', 't1', 't2']]
			],
			// Templates to unlink: T1 from T2. Templates remain linked: T2 to T3.
			[
				['t2' => ['t1']],
				[],
				['t2' => ['t3']],
				['t2' => ['t1'], 't3' => ['t1']]
			],
			// Templates to unlink: T1 from T2. Templates remain linked: T2 to T3 and T4.
			[
				['t2' => ['t1']],
				[],
				['t2' => ['t3', 't4']],
				['t2' => ['t1'], 't3' => ['t1'], 't4' => ['t1']]
			],
			// Templates to unlink: T2 from T3. Templates remain linked: T1 to T2; T3 to T4.
			[
				['t3' => ['t2']],
				['t2' => ['t1']],
				['t3' => ['t4']],
				['t3' => ['t2', 't1'], 't4' => ['t2', 't1']]
			],
			// Templates to unlink: T3 from T4. Templates remain linked: T1 and T2 to T3; T5 and T6 to T4.
			[
				['t4' => ['t3']],
				['t3' => ['t1', 't2']],
				['t4' => ['t5', 't6']],
				['t4' => ['t3', 't1', 't2'], 't5' => ['t3', 't1', 't2'], 't6' => ['t3', 't1', 't2']]
			],
			// Templates to unlink: T1 and T4 from T2; T2 from T3; T2 from T5.
			[
				[
					't2' => ['t1', 't4'],
					't3' => ['t2'],
					't5' => ['t2']
				],
				[
					't2' => ['t4', 't1'],
					't3' => ['t2', 't4', 't1'],
					't5' => ['t2', 't4', 't1']
				],
				[
					't2' => ['t3', 't5']
				],
				[
					't2' => ['t1', 't4'],
					't3' => ['t2', 't4', 't1'],
					't5' => ['t2', 't4', 't1']
				]
			],
			// Templates to unlink: T2 and T7 from T3; T3 from T4; T3 from T8. Templates remain linked: T1 to T2; T4 to T5; T6 to T7; T8 to T9.
			[
				[
					't3' => ['t2', 't7'],
					't4' => ['t3'],
					't8' => ['t3']
				],
				[
					't3' => ['t2', 't1', 't7', 't6'],
					't4' => ['t3', 't2', 't1', 't7', 't6'],
					't8' => ['t3', 't2', 't1', 't7', 't6']
				],
				[
					't3' => ['t4', 't5', 't8', 't9'],
					't4' => ['t5'],
					't8' => ['t9']
				],
				[
					't3' => ['t2', 't7'],
					't4' => ['t3', 't2', 't1', 't7', 't6'],
					't8' => ['t3', 't2', 't1', 't7', 't6'],
					't5' => ['t2', 't7', 't3', 't1', 't6'],
					't9' => ['t2', 't7', 't3', 't1', 't6']
				]
			],
			// Unlink multiple templates from other templates to break the existing single continuous chain.
			[
				[
					't3' => ['t11'],
					't4' => ['t3'],
					't5' => ['t4'],
					't6' => ['t5'],
					't9' => ['t8'],
					't13' => ['t12'],
					't14' => ['t4']
				],
				[
					't3' => ['t2', 't1', 't11'],
					't4' => ['t3', 't2', 't1', 't11'],
					't5' => ['t4', 't3', 't2', 't1', 't11', 't12', 't13'],
					't6' => ['t5', 't4', 't3', 't2', 't1', 't11', 't12', 't13'],
					't9' => ['t8', 't7', 't6', 't5', 't4', 't3', 't2', 't1', 't11', 't12', 't13'],
					't13' => ['t12'],
					't14' => ['t4', 't3', 't2', 't1', 't11']
				],
				[
					't3' => ['t4', 't5', 't6', 't7', 't8', 't9', 't10', 'h1', 't14', 'h2', 'h3', 'h4', 'h5'],
					't4' => ['t5', 't6', 't7', 't8', 't9', 't10', 'h1', 't14', 'h2', 'h3', 'h4', 'h5'],
					't5' => ['t6', 't7', 't8', 't9', 't10', 'h1', 'h3', 'h4', 'h5'],
					't6' => ['t7', 't8', 't9', 't10', 'h1', 'h4', 'h5'],
					't9' => ['t10', 'h1', 'h5'],
					't13' => ['t5', 't6', 't7', 't8', 't9', 't10', 'h1', 'h3', 'h4', 'h5'],
					't14' => ['h2']
				],
				[
					't3' => ['t11'],
					't4' => ['t3', 't2', 't1', 't11'],
					't5' => ['t4', 't3', 't2', 't1', 't11', 't12'],
					't6' => ['t5', 't4', 't3', 't2', 't1', 't11', 't12', 't13'],
					't9' => ['t8', 't11', 't3', 't2', 't1', 't4', 't5', 't12', 't13'],
					't13' => ['t12'],
					't14' => ['t4', 't3', 't2', 't1', 't11'],
					't7' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13'],
					't8' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13'],
					't10' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13', 't8'],
					'h1' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13', 't8'],
					'h2' => ['t11', 't3', 't2', 't1', 't4'],
					'h3' => ['t11', 't3', 't2', 't1', 't4', 't12'],
					'h4' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13'],
					'h5' => ['t11', 't3', 't2', 't1', 't4', 't5', 't12', 't13', 't8']
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderCreateLinks
	 */
	public function testGetLinksToCreate(array $links, array $ancestors, array $descendants, array $expected_links): void {
		$links = CHostTemplateCacheHelper::getLinksToCreate($links, $ancestors, $descendants);

		$this->assertSame($expected_links, $links);
	}

	/**
	 * @dataProvider dataProviderDeleteLinks
	 */
	public function testGetLinksToDelete(array $links, array $ancestors, array $descendants, array $expected_links): void {
		$links = CHostTemplateCacheHelper::getLinksToDelete($links, $ancestors, $descendants);

		$this->assertSame($expected_links, $links);
	}
}
