<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CXmlImportReaderTest extends PHPUnit_Framework_TestCase {

	public function dataProvider() {
		return [
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'</zabbix_export>',
				[
					'zabbix_export' => [
						'version' => '1.0',
						'date' => '09.01.10',
						'time' => '14.23'
					]
				]
			],
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'<hosts>'."\n".
				'    <host host="Zabbix server">'."\n".
				'        <status>0</status>'."\n".
				'    </host>'."\n".
				'    <host host="Linux server">'."\n".
				'        <status>0</status>'."\n".
				'    </host>'."\n".
				'</hosts>'."\n".
				'<screens/>'."\n".
				'<images/>'."\n".
				'</zabbix_export>',
				[
					'zabbix_export' => [
						'version' => '1.0',
						'date' => '09.01.10',
						'time' => '14.23',
						'hosts' => [
							'host' => [
								'host' => 'Zabbix server',
								'status' => '0'
							],
							'host1' => [
								'host' => 'Linux server',
								'status' => '0'
							]
						],
						'screens' => '',
						'images' => ''
					]
				]
			],
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'<hosts></hosts>'."\n".
				'<screens></screens>text'."\n".
				'<images></images>'."\n".
				'</zabbix_export>',
				'Invalid XML tag "/zabbix_export": unexpected text "text".'
			],
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'<hosts>'."\n".
				'    <host host="Zabbix server">'."\n".
				'        abc'."\n".
				'    </host>'."\n".
				'</hosts>'."\n".
				'</zabbix_export>',
				'Invalid XML tag "/zabbix_export/hosts/host": unexpected text "abc".'
			],
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'<hosts>'."\n".
				'    <host>'."\n".
				'        abc'."\n".
				'        <status>0</status>'."\n".
				'    </host>'."\n".
				'</hosts>'."\n".
				'</zabbix_export>',
				'Invalid XML tag "/zabbix_export/hosts/host": unexpected text "abc".'
			],
			[
				'<'.'?xml version="1.0"?'.'>'."\n".
				'<zabbix_export version="1.0" date="09.01.10" time="14.23">'."\n".
				'<hosts>'."\n".
				'    <host>'."\n".
				'        <status>0</status>p'."\n".
				'        <item type="3" key="icmpping" value_type="3">'."\n".
				'        </item>'."\n".
				'    </host>'."\n".
				'</hosts>'."\n".
				'</zabbix_export>',
				'Invalid XML tag "/zabbix_export/hosts/host": unexpected text "p".'
			],
			[
				'',
				'Cannot read XML: XML is empty.'
			],
			[
				'abc',
				'Cannot read XML: (4) Start tag expected, \'<\' not found [Line: 1 | Column: 1].'
			],
			[
				'<a></b>',
				'Cannot read XML: (76) Opening and ending tag mismatch: a line 1 and b [Line: 1 | Column: 8].'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $xml
	 * @param mixed  $expected
	 */
	public function testReadXML($xml, $expected) {
		$reader = new CXmlImportReader();

		try {
			$data = $reader->read($xml);
			$this->assertEquals(is_array($expected), is_array($data));
			$this->assertEquals($expected, $data);
		} catch (Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}
	}

}
