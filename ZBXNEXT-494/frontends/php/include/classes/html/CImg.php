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


class CImg extends CTag {

	public $preloader;

	public function __construct($src, $name = null, $width = null, $height = null) {
		if (is_null($name)) {
			$name = 'image';
		}

		parent::__construct('img');
		$this->setAttribute('border', 0);
		$this->setName($name);
		$this->setAltText($name);
		$this->setSrc($src);
		$this->setWidth($width);
		$this->setHeight($height);
	}

	public function setSrc($value) {
		$this->setAttribute('src', $value);
		return $this;
	}

	public function setAltText($value = null) {
		$this->setAttribute('alt', $value);
		return $this;
	}

	public function setMap($value = null) {
		if (is_null($value)) {
			$this->deleteOption('usemap');
		}
		else {
			$value = '#'.ltrim($value, '#');
			$this->setAttribute('usemap', $value);
		}
		return $this;
	}

	public function setWidth($value = null) {
		if (is_null($value)) {
			$this->removeAttribute('width');
		}
		else {
			$this->setAttribute('width', $value);
		}
		return $this;
	}

	public function setHeight($value = null) {
		if (is_null($value)) {
			$this->removeAttribute('height');
		}
		else {
			$this->setAttribute('height', $value);
		}
		return $this;
	}
}
