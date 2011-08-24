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
class CList extends CTag{
/* public */
	public function __construct($value=NULL,$class=NULL){
		parent::__construct('ul','yes');
		$this->tag_end = '';
		$this->addItem($value);
		$this->setClass($class);
	}

	public function prepareItem($value=NULL,$class=null){
		if(!is_null($value)){
			$value = new CListItem($value,$class);
		}
		return $value;
	}

	public function addItem($value,$class=null){
		if(is_array($value)){
			foreach($value as $el)
				parent::addItem($this->prepareItem($el,$class));
		}
		else{
			parent::addItem($this->prepareItem($value,$class));
		}
	}
}
?>
