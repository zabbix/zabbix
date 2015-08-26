<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class CRegexValidatorTest extends CValidatorTest
{
	public function validParamProvider()
	{
		return array(array(
			array(
				'messageInvalid' => 'Invalid regular expression'
			)
		));
	}

	public function validValuesProvider()
	{
		return array(
			array(array(), 'foobar'),
			array(array(), '/foobar'),
			array(array(), 'foobar/'),
			array(array(), 'foobar/i'),
			array(array(), '/'),
			array(array(), ' '),
			array(array(), '\\\\'),
			array(array(), '[A-Z]+[0-9]{123}foo.*(bar|buz)[^A-K]{4}'),
			array(array(), 'asd\('),
			array(array(), '^Timestamp \[[0-9]{4}-[A-Za-z]{3}-[0-9]{1,2}\]: ERROR.*$'),
			array(array(), '/[a-z]+'),
			array(array(), '[a-z]+\ \[/'),
			array(array(), '[a-f0-9]{32}/iu'),
			array(array(), '[a-f0-9]{32}/i'),
			array(array(), '/foo bar// me!/'),
			array(array(), 1),
			array(array(), 1.2)
		);
	}

	public function invalidValuesProvider()
	{
		return array(
			array(
				array('messageInvalid' => 'Not a string'),
				array(),
				'Not a string'
			),
			array(
				array('messageInvalid' => 'Not a string'),
				null,
				'Not a string'
			),
			array(
				array('messageInvalid' => 'Not a string'),
				true,
				'Not a string'
			),
			array(
				array('messageRegex' => 'Incorrect regular expression "%1$s": "%2$s"'),
				'[[',
				'Incorrect regular expression "[[": "Compilation failed: missing terminating ] for character class at offset 2"'
			),
			array(
				array('messageRegex' => 'Incorrect regular expression "%1$s": "%2$s".'),
				'asd(',
				'Incorrect regular expression "asd(": "Compilation failed: missing ) at offset 4".'
			)
		);
	}

	public function invalidValuesWithObjectsProvider()
	{
		return array(
			array(
				array('messageRegex' => 'Incorrect regular expression "%2$s" for object "%1$s": "%3$s"'),
				'test[',
				'Incorrect regular expression "test[" for object "object": "Compilation failed: missing terminating ] for character class at offset 5"'
			)
		);
	}

	protected function createValidator(array $params = array())
	{
		return new CRegexValidator($params);
	}
}
