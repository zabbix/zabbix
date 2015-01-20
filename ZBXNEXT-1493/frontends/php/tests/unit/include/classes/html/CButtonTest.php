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


class CButtonTest extends CTagTest {

	public function constructProvider() {
		return array(
			array(
				array(),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button" name="button"></button>'
			),
			array(
				array('my-button'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="my-button" name="my-button"></button>'
			),
			array(
				array('button[value]'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button_value" name="button[value]"></button>'
			),
			array(
				array('button', 'caption'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button" name="button">caption</button>'
			),
			array(
				array('button', 'caption', 'callback()'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button" name="button" onclick="callback()">caption</button>'
			),
			array(
				array('button', 'caption', null, 'my-class'),
				'<button class="button my-class" type="button" id="button" name="button">caption</button>'
			),
			// value encoding
			array(
				array('button', '</button>'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button" name="button">&lt;/button&gt;</button>'
			),
			// parameter encoding
			array(
				array('button"&"'),
				'<button class="button button-plain shadow ui-corner-all" type="button" id="button&quot;&amp;&quot;" name="button&quot;&amp;&quot;"></button>'
			),
		);
	}

	public function testSetEnabled() {
		$button = $this->createTag();
		$button->setEnabled(false);
		$this->assertEquals(
			'<button class="button button-plain shadow ui-corner-all" type="button" id="button" name="button" disabled="disabled"></button>',
			(string) $button
		);
	}

	public function testMain() {
		$button = $this->createTag();
		$button->main();
		$this->assertEquals(
			'<button class="button main button-plain shadow ui-corner-all" type="button" id="button" name="button"></button>',
			(string) $button
		);
	}

	public function testSetButtonStyle() {
		$button = $this->createTag();
		$button->setButtonClass('my-button');
		$this->assertEquals(
			'<button class="button my-button" type="button" id="button" name="button"></button>',
			(string) $button
		);

		// test class reset
		$button = $this->createTag('button', '', null, 'my-button');
		$button->setButtonClass(null);
		$this->assertEquals(
			'<button class="button" type="button" id="button" name="button"></button>',
			(string) $button
		);
	}

	/**
	 * @param $name
	 * @param $caption
	 * @param $action
	 * @param $class
	 * @return CButton
	 */
	protected function createTag($name = 'button', $caption = '', $action = null, $class = 'button-plain shadow ui-corner-all') {
		return new CButton($name, $caption, $action, $class);
	}
}
