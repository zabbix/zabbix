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
class CArea extends CTag{
	public function __construct($coords,$href,$alt,$shape){
		parent::__construct('area','no');
		$this->setCoords($coords);
		$this->setShape($shape);
		$this->setHref($href);
		$this->setAlt($alt);
	}

	public function setCoords($value){
		if(!is_array($value))
			return $this->error('Incorrect value for setCoords ['.$value.']');
		if(count($value)<3)
			return $this->error('Incorrect values count for setCoords ['.count($value).']');

		$str_val = '';
		foreach($value as $val){
			if(!is_numeric($val))
				return $this->error('Incorrect value for setCoords ['.$val.']');

			$str_val .= $val.',';
		}
		$this->setAttribute('coords',trim($str_val,','));
	}

	public function setShape($value){
		if(!is_string($value))
			return $this->error('Incorrect value for setShape ['.$value.']');

		$this->setAttribute('shape',$value);
	}

	public function setHref($value){

		if(!is_string($value)) return $this->error('Incorrect value for setHref ['.$value.']');
		$this->setAttribute('href',$value);
	}

	public function setAlt($value){
		if(!is_string($value))
			return $this->error('Incorrect value for setAlt ['.$value.']');

		$this->setAttribute('alt',$value);
	}
}
?>
