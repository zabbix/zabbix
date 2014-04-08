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
class CEditableComboBox extends CComboBox{
	public function __construct($name='editablecombobox',$value=NULL,$size=0,$action=NULL){
		inseret_javascript_for_editable_combobox();

		parent::__construct($name,$value,$action);
		parent::addAction('onfocus','CEditableComboBoxInit(this);');
		parent::addAction('onchange','CEditableComboBoxOnChange(this,'.$size.');');
	}

	public function addItem($value, $caption='', $selected=NULL, $enabled='yes'){
		if(is_null($selected)){
			if(is_array($this->value)) {
				if(str_in_array($value,$this->value))
					$this->value_exist = 1;
			}
			else if(strcmp($value,$this->value) == 0){
				$this->value_exist = 1;
			}
		}

		parent::addItem($value,$caption,$selected,$enabled);
	}

	public function toString($destroy=true){
		if(!isset($this->value_exist) && !empty($this->value)){
			$this->addItem($this->value, $this->value, 'yes');
		}
		return parent::toString($destroy);
	}
}
?>
