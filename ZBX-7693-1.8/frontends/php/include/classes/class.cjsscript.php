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
class CJSscript extends CObject{
	public function __construct($item=NULL){
		$this->items = array();
		$this->addItem($item);
	}

	public function addItem($value){
		if(is_array($value)){
			foreach($value as $item){
				array_push($this->items,unpack_object($item));
			}
		}
		else if(!is_null($value)){
			array_push($this->items,unpack_object($value));
		}
	}
}
?>
