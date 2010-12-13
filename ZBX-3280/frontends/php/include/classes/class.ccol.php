<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
class CCol extends CTag{
	public function __construct($item=NULL,$class=NULL){
		parent::__construct('td','yes');

		$this->addItem($item);
		$this->setClass($class);
	}

	public function setAlign($value){
		return $this->attributes['align'] = $value;
	}

	public function setRowSpan($value){
		return $this->attributes['rowspan'] = strval($value);
	}

	public function setColSpan($value){
		return $this->attributes['colspan'] =strval($value);
	}

	public function setWidth($value){
		if(is_string($value))$this->setAttribute('width',$value);
	}
}
?>
