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


class CSubmitButtonTest extends CTagTest {

	public function constructProvider() {
		return array(
			array(
				array('caption'),
				'<button class="button button-plain shadow ui-corner-all" type="submit">caption</button>'
			),
			array(
				array('caption', 'button'),
				'<button class="button button-plain shadow ui-corner-all" type="submit" name="button">caption</button>'
			),
			array(
				array('caption', 'button[value]'),
				'<button class="button button-plain shadow ui-corner-all" type="submit" name="button[value]">caption</button>'
			),
			array(
				array('caption', 'button', 'value'),
				'<button class="button button-plain shadow ui-corner-all" type="submit" name="button" value="value">caption</button>'
			),
			array(
				array('caption', 'button', 'value', 'my-class'),
				'<button class="button my-class" type="submit" name="button" value="value">caption</button>'
			),
			// caption encoding
			array(
				array('</button>'),
				'<button class="button button-plain shadow ui-corner-all" type="submit">&lt;/button&gt;</button>'
			),
			// parameter encoding
			array(
				array('caption', 'button', 'button"&"'),
				'<button class="button button-plain shadow ui-corner-all" type="submit" name="button" value="button&quot;&amp;&quot;">caption</button>'
			),
		);
	}
	/**
	 * @param $name
	 * @param $value
	 * @param $caption
	 * @param $class
	 *
	 * @return CSubmitButton
	 */
	protected function createTag($name = null, $value = null, $caption = null, $class = 'button-plain shadow ui-corner-all') {
		return new CSubmitButton($name, $value, $caption, $class);
	}
}
