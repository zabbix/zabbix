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
class CVar{
 public $var_container;
 public $var_name;

	public function __construct($name,$value=null){
		$this->var_container = array();
		$this->var_name = $name;

		$this->setValue($value);
	}

	public function setValue($value){
		$this->var_container = array();

		if(is_null($value)) return;

		$this->parseValue($this->var_name, $value);
	}

	public function parseValue($name, $value){
		if(is_array($value)){
			foreach($value as $itemid => $item){
				if( is_null($item) ) continue;
				$this->parseValue($name.'['.$itemid.']', $item);
			}
			return;
		}

		array_push($this->var_container, new CVarTag($name, $value));
	}

	public function toString(){
		$res = '';

		foreach($this->var_container as $item){
			$res .= $item->toString();
		}
	return $res;
	}
}
?>
