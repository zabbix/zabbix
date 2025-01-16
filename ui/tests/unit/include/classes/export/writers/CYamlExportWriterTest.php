<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class CYamlExportWriterTest extends TestCase {

	public function dataProvider() {
		return [
			'Property "collection" has correct position' => [
				[
					'yaml' => [
						'string' => implode("\n", ['line1', 'line2', '-']),
						'collection' => [
							['name' => 'item1'],
							['name' => 'item2']
						]
					]
				],
				implode("\n", [
					'yaml:',
					'  string: |',
					'    line1',
					'    line2',
					'    -',
					'  collection:',
					'    - name: item1',
					'    - name: item2',
					''
				])
			],
			'Property "collection.description" has correct position' => [
				[
					'yaml' => [
						'string' => implode("\n", ['line1', 'line2', '-']),
						'collection' => [
							['description' => "\n-\n -"],
							['description' => 'item2'],
							['description' => 'item3']
						]
					]
				],
				implode("\n", [
					'yaml:',
					'  string: |',
					'    line1',
					'    line2',
					'    -',
					'  collection:',
					'    - description: |',
					'        ',
					'        -',
					'         -',
					'    - description: item2',
					'    - description: item3',
					''
				])
			],
			'Compact nested mapping is not applied for "collection.description" property content' => [
				[
					'yaml' => [
						'collection' => [
							['description' => "-\nitem1\n"],
							['description' => 'item2']
						]
					]
				],
				implode("\n", [
					'yaml:',
					'  collection:',
					'    - description: |',
					'        -',
					'        item1',
					'        ',
					'    - description: item2',
					''
				])
			],
			'CR with LF dump multiline string as quoted single line string' => [
				[
					'yaml' => [
						'string' => implode("\r\n", ['line1', 'line2', '-']),
						'collection' => [
							['name' => 'item1'],
							['name' => 'item2']
						]
					]
				],
				implode("\n", [
					'yaml:',
					'  string: "line1\r\nline2\r\n-"',
					'  collection:',
					'    - name: item1',
					'    - name: item2',
					''
				])
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param array $input
	 * @param mixed $expected
	 */
	public function test_writeXml(array $input, $expected) {
		$writer = new CYamlExportWriter();
		$actual = $writer->write($input);

		$this->assertEquals($expected, $actual);
	}
}
