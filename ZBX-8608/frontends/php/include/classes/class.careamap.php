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
class CAreaMap extends CTag{
/* public */
	public function __construct($name=''){
		parent::__construct('map','yes');
		$this->setName($name);
	}

	public function addRectArea($x1,$y1,$x2,$y2,$href,$alt){
		return $this->addArea(array($x1,$y1,$x2,$y2),$href,$alt,'rect');
	}

	public function addArea($coords,$href,$alt,$shape){
		return $this->addItem(new CArea($coords,$href,$alt,$shape));
	}

	public function addItem($value){
		if(is_object($value) && zbx_strtolower(get_class($value)) != 'carea')
			return $this->error('Incorrect value for addItem ['.$value.']');

		return parent::addItem($value);
	}
}
?>
