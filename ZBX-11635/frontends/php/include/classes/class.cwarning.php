<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CWarning extends CTable {

	protected $header;
	protected $message;
	protected $alignment;
	protected $paddings;
	protected $buttons;

	public function __construct($header, $message = null) {
		parent::__construct(null, 'warningTable');
		$this->setAlign('center');
		$this->header = $header;
		$this->message = $message;
		$this->alignment = null;
		$this->paddings = null;
		$this->buttons = array();
	}

	public function setAlignment($alignment) {
		$this->alignment = $alignment;
	}

	public function setPaddings($padding) {
		$this->paddings = $padding;
	}

	public function setButtons($buttons = array()) {
		$this->buttons = is_array($buttons) ? $buttons : array($buttons);
	}

	public function show($destroy = true) {
		$this->setHeader($this->header, 'header');

		$cssClass = 'content';
		if (!empty($this->alignment)) {
			$cssClass .= ' '.$this->alignment;
		}

		if (!empty($this->paddings)) {
			$this->addRow($this->paddings);
			$this->addRow(new CSpan($this->message), $cssClass);
			$this->addRow($this->paddings);
		}
		else {
			$this->addRow(new CSpan($this->message), $cssClass);
		}

		$this->setFooter(new CDiv($this->buttons, 'buttons'), 'footer');

		parent::show($destroy);
	}
}
