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


class CArea extends CTag {

	public function __construct($coords, $href, $alt, $shape) {
		parent::__construct('area');
		$this->setCoords($coords);
		$this->setShape($shape);
		$this->setHref($href);
		$this->setAlt($alt);
	}

	public function setCoords($value) {
		if (!is_array($value)) {
			return $this->error('Incorrect value for setCoords "'.$value.'".');
		}
		if (count($value) < 3) {
			return $this->error('Incorrect values count for setCoords "'.count($value).'".');
		}

		$str_val = '';
		foreach ($value as $val) {
			if (!is_numeric($val)) {
				return $this->error('Incorrect value for setCoords "'.$val.'".');
			}
			$str_val .= $val.',';
		}
		$this->setAttribute('coords', trim($str_val, ','));
		return $this;
	}

	public function setShape($value) {
		$this->setAttribute('shape', $value);
		return $this;
	}

	public function setHref($value) {
		$this->setAttribute('href', $value);
		return $this;
	}

	public function setAlt($value) {
		$this->setAttribute('alt', $value);
		return $this;
	}
}
