<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CColorValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'empty' => true,
				'messageType' => 'Not a string',
				'messageEmpty' => 'Empty color',
				'messageRegex' => 'Incorrect color'
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(array(), '000000'),
			array(array(), 'AAAAAA'),
			array(array(), 'F3F3F3'),
			array(array(), 'FFFFFF'),
			array(array(), 'ffffff'),
			array(array('empty' => true), ''),
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageEmpty' => 'Empty color'),
				'',
				'Empty color'
			),
			array(
				array('messageRegex' => 'Incorrect color "%1$s"'),
				'GGGGGG',
				'Incorrect color "GGGGGG"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%1$s"'),
				'0000000',
				'Incorrect color "0000000"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%1$s"'),
				'FFF',
				'Incorrect color "FFF"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%1$s"'),
				'fff',
				'Incorrect color "fff"'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array('messageEmpty' => 'Empty color for "%1$s"'),
				'',
				'Empty color for "object"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%2$s" for "%1$s"'),
				'@#$$%^',
				'Incorrect color "@#$$%^" for "object"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%2$s" for "%1$s"'),
				'0000000',
				'Incorrect color "0000000" for "object"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%2$s" for "%1$s"'),
				'FFF',
				'Incorrect color "FFF" for "object"'
			),
			array(
				array('messageRegex' => 'Incorrect color "%2$s" for "%1$s"'),
				'fff',
				'Incorrect color "fff" for "object"'
			),
		);
	}

	protected function createValidator(array $params = array()) {
		return new CColorValidator($params);
	}
}
