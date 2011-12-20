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
class CComboBox extends CTag{
	public $value;

	public function __construct($name='combobox', $value=NULL, $action=NULL){
		parent::__construct('select', 'yes');
		$this->tag_end = '';

		$this->attributes['id'] = $name;
		$this->attributes['name'] = $name;

		$this->attributes['class'] = 'select';
		$this->attributes['size'] = 1;

		$this->value = $value;
		$this->setAction($action);
	}

	public function setAction($value='submit()', $event='onchange'){
		$this->setAttribute($event,$value);
	}

	public function setValue($value=NULL){
		$this->value = $value;
	}

	public function addItem($value, $caption='', $selected=NULL, $enabled='yes'){
//			if($enabled=='no') return;	/* disable item method 1 */
		if(is_object($value) && (zbx_strtolower(get_class($value)) == 'ccomboitem')){
			parent::addItem($value);
		}
		else{
			if(zbx_strlen($caption) > 44){
				$this->setAttribute('class', 'select selectShorten');
			}

			if(is_null($selected)){
				$selected = 'no';
				if(is_array($this->value)) {
					if(str_in_array($value,$this->value))
						$selected = 'yes';
				}
				else if(strcmp($value,$this->value) == 0){
					$selected = 'yes';
				}
			}

			parent::addItem(new CComboItem($value, $caption, $selected, $enabled));
		}
	}

	public function addItems($items){
		foreach($items as $value => $caption){
			$selected = (int) ($value == $this->value);
			parent::addItem(new CComboItem($value, $caption, $selected));
		}
	}
	
	public function addItemsInGroup($label, $items){
		$group = new COptGroup($label);
		foreach($items as $value => $caption){
			$selected = (int) ($value == $this->value);
			$group->addItem(new CComboItem($value, $caption, $selected));
		}
		parent::addItem($group);
	}
}

class COptGroup extends CTag{
	public function __construct($label){
		parent::__construct('optgroup', 'yes');
		
		$this->setAttribute('label', $label);	
	}
}

?>
