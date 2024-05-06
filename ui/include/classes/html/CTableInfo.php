<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CTableInfo extends CTable {

	protected $message = null;
	protected $page_navigation;

	public function __construct() {
		parent::__construct();

		$this->addClass(ZBX_STYLE_LIST_TABLE);
	}

	public function setNoDataMessage($message, $description = null, $icon = null) {
		if ($description === null && $icon === null) {
			$container = (new CDiv($message))->addClass(ZBX_STYLE_NO_DATA_MESSAGE);
		}
		else {
			$container = new CDiv([
				(new CDiv($message))
					->addClass(ZBX_STYLE_NO_DATA_MESSAGE)
					->addClass($icon),
				$description !== null ? (new CDiv($description))->addClass(ZBX_STYLE_NO_DATA_DESCRIPTION) : null
			]);

			if ($icon !== null) {
				$this->addClass(ZBX_STYLE_NO_DATA);
				$container->addClass(ZBX_STYLE_NO_DATA_FOUND);
			}
		}

		$this->message = new CCol($container);

		return $this;
	}

	public function setPageNavigation($page_navigation) {
		$this->page_navigation = $page_navigation;

		return $this;
	}

	public function toString($destroy = true) {
		$tableid = $this->getId();

		if (!$tableid) {
			$tableid = uniqid('t', true);
			$tableid = str_replace('.', '', $tableid);
			$this->setId($tableid);
		}

		if ($this->rownum == 0 && $this->message === null) {
			$this->setNoDataMessage(_('No data found'), null, ZBX_ICON_SEARCH_LARGE);
		}

		return parent::toString($destroy);
	}

	protected function endToString() {
		$ret = '';

		if ($this->rownum == 0) {
			$ret .= $this->prepareRow($this->message, ZBX_STYLE_NOTHING_TO_SHOW)->toString();
		}

		$ret .= parent::endToString();

		if ($this->page_navigation && $this->getNumRows() > 0) {
			$ret .= $this->page_navigation;
		}

		return $ret;
	}
}
