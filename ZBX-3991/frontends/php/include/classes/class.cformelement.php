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
?>
<?php

class CFormElement {

	private $header = null;
	private $footer = null;
	private $body = null;

	public function __construct($header = null, $body = null, $footer = null) {
		$this->header = $header;
		$this->body = $body;
		$this->footer = $footer;
	}

	public function setHeader($header) {
		$this->header = $header;
	}

	public function setBody($body) {
		$this->body = $body;
	}

	public function setFooter($footer) {
		$this->footer = $footer;
	}

	public function toString() {
		$content = array();
		if (!is_null($this->header)) {
			$header_div = new CDiv($this->header, 'formElement_header');
			$content[] = $header_div;
		}
		if (!is_null($this->body)) {
			$body_div = new CDiv($this->body, 'formElement_body');
			$content[] = $body_div;
		}
		if (!is_null($this->footer)) {
			$footer_div = new CDiv($this->footer, 'formElement_footer');
			$content[] = $footer_div;
		}
		$main_div = new CDiv($content, 'formElement');
		return unpack_object($main_div);
	}
}
?>
