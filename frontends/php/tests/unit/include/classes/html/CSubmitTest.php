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


class CSubmitTest extends CTagTest {

	public function constructProvider() {
		return array(
			array(
				array(),
				'<input class="input button shadow ui-corner-all" type="submit" id="submit" name="submit" value="" />'
			),
			array(
				array('my-button'),
				'<input class="input button shadow ui-corner-all" type="submit" id="my-button" name="my-button" value="" />'
			),
			array(
				array('button[value]'),
				'<input class="input button shadow ui-corner-all" type="submit" id="button_value" name="button[value]" value="" />'
			),
			array(
				array('button', 'caption'),
				'<input class="input button shadow ui-corner-all" type="submit" id="button" name="button" value="caption" />'
			),
			array(
				array('button', 'caption', 'callback()'),
				'<input class="input button shadow ui-corner-all" type="submit" id="button" name="button" value="caption" onclick="callback()" />'
			),
			array(
				array('button', 'caption', null, 'my-class'),
				'<input class="input my-class" type="submit" id="button" name="button" value="caption" />'
			),
		);
	}
	/**
	 * @param $name
	 * @param $caption
	 * @param $action
	 * @param $class
	 * @return CSubmit
	 */
	protected function createTag($name = 'submit', $caption = '', $action = null, $class = null) {
		return new CSubmit($name, $caption, $action, $class);
	}
}
