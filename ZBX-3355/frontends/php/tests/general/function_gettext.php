<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * Testing gettext related functions
 */

require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../../include/gettextwrapper.inc.php');

class function_gettext extends PHPUnit_Framework_TestCase
{
	/**
	 * Data provider from for _s() function
	 */
	public static function provider_s()
	{
		return array(
			array('host "%s" has been deleted', array('Host 1'), 'host "Host 1" has been deleted'),
			array('id=%d', array(8), 'id=8'),
			array('roses are %1$s, violets are %2$s, all by %3$s are belong to %4$s', array('red','blue','base','you'), 'roses are red, violets are blue, all by base are belong to you'),
			array('reverse argument order: %2$s, %1$s', array('first','second'), 'reverse argument order: second, first'),
		);
	}

	/**
	 * Data provider from for _n() function
	 */
	public static function provider_n()
	{
		return array(
			array('deleted %d host', 'deleted %d hosts', array(1), 'deleted 1 host'),
			array('deleted %d host', 'deleted %d hosts', array(8), 'deleted 8 hosts'),
			array('deleted %d host', 'deleted %d hosts', array(100500), 'deleted 100500 hosts'),

			array('deleted %d host from template "%s"', 'deleted %d hosts from template "%s"', array(1, 'my_template'), 'deleted 1 host from template "my_template"'),
			array('deleted %d host from template "%s"', 'deleted %d hosts from template "%s"', array(90, 'my_template'), 'deleted 90 hosts from template "my_template"'),

			array('on table %2$s, there is %1$d cake', 'on table %2$s, there are %1$d cakes', array(1, 'table_name'), 'on table table_name, there is 1 cake'),
			array('on table %2$s, there is %1$d cake', 'on table %2$s, there are %1$d cakes', array(2, 'table_name'), 'on table table_name, there are 2 cakes'),

			array('%3$s, %4$s, %2$s, %1$d thing', '%3$s, %4$s, %2$s, %1$d things', array(1, 'foo', 'bar', 'bla'), 'bar, bla, foo, 1 thing'),
			array('%3$s, %4$s, %2$s, %1$d thing', '%3$s, %4$s, %2$s, %1$d things', array(56, 'foo', 'bar', 'bla'), 'bar, bla, foo, 56 things'),
		);
	}

	/**
	* Test _s() function
	* @dataProvider provider_s
	*/
	public function test_gettext_s($string, $parameters, $expected_result)
	{
		array_unshift($parameters, $string);
		$output = call_user_func_array("_s", $parameters);
		$this->assertEquals($output, $expected_result);
	}

	/**
	* Test _n() function
	* @dataProvider provider_n
	*/
	public function test_gettext_n($string1, $string2, $parameters, $expected_result)
	{
		array_unshift($parameters, $string2);
		array_unshift($parameters, $string1);
		$output = call_user_func_array("_n", $parameters);
		$this->assertEquals($output, $expected_result);
	}

}
?>
