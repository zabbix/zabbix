<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
class CComboItem extends CTag{
	public function __construct($value, $caption=NULL, $selected=NULL, $enabled=NULL){
		parent::__construct('option', 'yes');
		$this->tag_body_start = '';
		$this->attributes['value'] = $value;
		$this->setAttribute('title', $caption);

		$this->addItem($caption);

		$this->setSelected($selected);
		$this->setEnabled($enabled);

	}

	public function setValue($value){
		return $this->attributes['value'] = $value;
	}

	public function getValue(){
		return $this->getAttribute('value');
	}

	public function setCaption($value=NULL){
		$this->addItem(nbsp($value));
	}

	public function addItem($value) {
		$value = $this->sanitize($value);

		parent::addItem($value);
	}

	public function setSelected($value='yes'){
		if((is_string($value) && ($value == 'yes' || $value == 'selected' || $value=='on')) || (is_int($value) && $value<>0)){
			return $this->attributes['selected'] = 'selected';
		}

		$this->removeAttribute('selected');
	}
}
?>
