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
class CRadioButton extends CDiv{
	protected $count;
	protected $name;
	protected $value;

	public function __construct($name='radio', $value='yes'){
		parent::__construct();

		$this->count = 0;
	}


	public function addItem($name, $value){
		$this->count++;

		$radio = CInput('radio', $this->name, $value);
		$radio->setAttribute('id', $this->name.$this->count);

		if(strcmp($value,$this->value) == 0){
			$radio->setAttribute('checked', 'checked');
		}

		$label = CLabel($name, $this->name.$this->count);

		parrent::addItem(array($radio, $label));
	}
}
?>
