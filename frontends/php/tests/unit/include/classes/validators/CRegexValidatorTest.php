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

class CRegexValidatorTest extends CValidatorTest
{
	public function validParamProvider()
	{
		return array(array(array()));
	}

	public function validValuesProvider()
	{
		return array(
			array(array(), 'foobar'),
			array(array(), '\/foobar'),
			array(array(), 'foobar\/'),
			array(array(), 'foobar\/i'),
			array(array(), '\/'),
			array(array(), ' '),
			array(array(), '\\\\'),
			array(array(), '[A-Z]+[0-9]{123}foo.*(bar|buz)[^A-K]{4}'),
			array(array(), 'asd\('),
		);
	}

	public function invalidValuesProvider()
	{
		return array(
			array(array(), '[[', $this->pcreMessage('Compilation failed: missing terminating ] for character class at offset 2')),
			array(array(), 'asd(', $this->pcreMessage('Compilation failed: missing ) at offset 4')),
			array(array(), '/[a-z]+', $this->delimiterMessage()),
			array(array(), '[a-z]+\ \[/', $this->delimiterMessage()),
			array(array(), '[a-f0-9]{32}/iu', $this->delimiterMessage()),
			array(array(), '[a-f0-9]{32}/i', $this->delimiterMessage()),
		);
	}

	public function invalidValuesWithObjectsProvider()
	{
		return array();
	}

	protected function createValidator(array $params = array())
	{
		return new CRegexValidator($params);
	}

	protected function delimiterMessage()
	{
		return 'Regular expression should not contain delimiters of modifiers (you should escape "/" with "\/").';
	}

	protected function pcreMessage($message)
	{
		return sprintf('Incorrect regular expression: "%s".', $message);
	}
}
