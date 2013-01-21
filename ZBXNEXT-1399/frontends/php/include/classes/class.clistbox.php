<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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


class CListBox extends CComboBox {

	public function __construct($name = 'listbox', $value = null, $size = 5, $action = null) {
		parent::__construct($name, null, $action);
		$this->attr('multiple', 'multiple');
		$this->attr('size', $size);
		$this->setValue($value);
	}

	public function setSize($value) {
		$this->attr('size', $value);
	}

	/**
	 * Apply chosen jQuery plugin to listbox.
	 *
	 * @param int    $options['width']
	 * @param string $options['objectName']
	 */
	public function makeDynamic(array $options = array()) {
		$name = zbx_formatDomId($this->getName());
		$this->setAttribute('class', 'chzn-select-'.$name);

		// width
		if (empty($options['width'])) {
			$options['width'] = ZBX_TEXTAREA_STANDARD_WIDTH;
		}
		$this->setAttribute('style', 'width:'.$options['width'].'px;');

		zbx_add_post_js('jQuery(".chzn-select-'.$name.'").zbxChosen({objectName: "'.$options['objectName'].'"});');
	}
}
