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


class CDiv extends CTag {

	public function __construct($items = null, $class = null, $id = null) {
		parent::__construct('div', 'yes');
		$this->attr('class', $class);
		if (!empty($id)) {
			$this->attr('id', zbx_formatDomId($id));
		}
		$this->addItem($items);

		$this->tag_body_start = '';
		$this->tag_start = '';
		$this->tag_end = '';
		$this->tag_body_start = '';
		$this->tag_body_end = '';

		return $this;
	}

	public function useJQueryStyle() {
		$this->setAttribute('class', $this->getAttribute('class').' jqueryinputset');
		if (!defined('ZBX_JQUERY_INPUTSET')) {
			define('ZBX_JQUERY_INPUTSET', true);
			zbx_add_post_js('setTimeout(function() { jQuery("div.jqueryinputset").buttonset(); }, 10);');
		}
		return $this;
	}
}
