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


class CIFrame extends CTag {

	public function __construct($src = null, $width = '100%', $height = '100%', $scrolling = 'no', $id = 'iframe') {
		parent::__construct('iframe', true);

		$this->setSrc($src);
		$this->setWidth($width);
		$this->setHeight($height);
		$this->setScrolling($scrolling);
		$this->setId($id);
	}

	public function setSrc($value = null) {
		if (is_null($value)) {
			$this->removeAttribute('src');
		}
		else {
			$this->setAttribute('src', $value);
		}
		return $this;
	}

	public function setWidth($value) {
		if (is_null($value)) {
			$this->removeAttribute('width');
		}
		else {
			$this->setAttribute('width', $value);
		}
		return $this;
	}

	public function setHeight($value) {
		if (is_null($value)) {
			$this->removeAttribute('height');
		}
		else {
			$this->setAttribute('height', $value);
		}
		return $this;
	}

	public function setScrolling($value) {
		if (is_null($value)) {
			$value = 'no';
		}

		$this->setAttribute('scrolling', $value);
		return $this;
	}
}
