<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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


class CInput extends CTag {

	protected $jQuery = false;

	public function __construct($type, $name, $value, $htmlAttrs = array()) {
		parent::__construct('input', 'no');
		$this->setType($type);
		$this->attr('name', $name);
		$this->attr('value', $value);

		// if id is not passed, it will be the same as element name
		if (!isset($htmlAttrs['id'])) {
			$htmlAttrs['id'] = zbx_formatDomId($name);
		}

		$htmlAttrs['class'] = isset($htmlAttrs['class']) ? 'input '.$htmlAttrs['class'] : 'input';

		$this->attrs($htmlAttrs);
	}

	public function setReadOnly($value = true) {
		if ($value) {
			$this->attr('readonly', true);
		}
		else {
			$this->removeAttr('readonly');
		}
	}

	public function setAutoFocus($value = true) {
		if ($value) {
			$this->attr('autofocus', true);
		}
		else {
			$this->removeAttr('autofocus');
		}
	}

	public function useJQueryStyle($class = '') {
		$this->jQuery = true;
		$this->attr('class', 'jqueryinput '.$this->getAttr('class').' '.$class);
		if (!defined('ZBX_JQUERY_INPUT')) {
			define('ZBX_JQUERY_INPUT', true);
			zbx_add_post_js('jQuery("input.jqueryinput").button();');
		}
		return $this;
	}

	protected function setType($type) {
		$this->attr('type', $type);
		return $this;
	}
}
