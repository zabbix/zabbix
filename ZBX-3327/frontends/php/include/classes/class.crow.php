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
class CRow extends CTag{
/* public */
	public function __construct($item=NULL,$class=NULL){
		parent::__construct('tr','yes');

		$this->addItem($item);
		$this->setClass($class);
	}

	public function setAlign($value){
		return $this->attributes['align'] = $value;
	}

	public function addItem($item){
		if(is_object($item) && zbx_strtolower(get_class($item))=='ccol') {
			parent::addItem($item);
		}
		elseif(is_array($item)){
			foreach($item as $el){
				if(is_object($el) && zbx_strtolower(get_class($el))=='ccol') {
					parent::addItem($el);
				}
				else if(!is_null($el)){
					parent::addItem(new CCol($el));
				}
			}
		}
		elseif(!is_null($item)){
			parent::addItem(new CCol($item));
		}
	}

	public function setWidth($value){
		if(is_string($value))$this->setAttribute('width',$value);
	}
}
?>
