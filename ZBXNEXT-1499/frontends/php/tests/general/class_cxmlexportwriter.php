<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/classes/export/writers/CExportWriter.php';
require_once dirname(__FILE__).'/../../include/classes/export/writers/CXmlExportWriter.php';

class class_cxmlexportwriter extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return array(
			array(
				array(
					'root' => array(
						'string' => 'string',
						'null' => null,
						'empty' => '',
						'array' => array(
							'string' => 'string'
						)
					)
				),
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
				"<root>\n".
				"    <string>string</string>\n".
				"    <null/>\n".
				"    <empty/>\n".
				"    <array>\n".
				"        <string>string</string>\n".
				"    </array>\n".
				"</root>\n"
			)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_writeXml($array, $expectedResult) {
		$writer = new CXmlExportWriter();
		$xml = $writer->write($array);

		$this->assertEquals($xml, $expectedResult);
	}
}
