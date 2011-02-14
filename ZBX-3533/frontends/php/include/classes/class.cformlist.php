<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
class CFormList extends CDiv{
	protected $formList = null;
	protected $editable = true;
	protected $formInputs = array('ctextbox','cnumericbox','ctextarea','ccombobox','ccheckbox','cpassbox','cipbox');

	public function __construct($id, $class=null, $editable=true){
		$this->editable = $editable;
		$this->formList = new CList(null, 'formlist');

		parent::__construct();
		$this->setAttribute('id', $id);
		$this->setAttribute('class', $class);
	}

	public function addRow($term, $description=null){
		$label = $term;
		if(is_object($description)){
			$inputClass = zbx_strtolower(get_class($description));
			if(in_array($inputClass, $this->formInputs)){
				$label = new CLabel($term, $description->getAttribute('id'));
			}
		}

		if(!is_null($description))
			$this->formList->addItem(array(new CDiv($label, 'dt'), new CDiv($description, 'dd')));
		else
			$this->formList->addItem(array(new CDiv(SPACE, 'dt'), new CDiv($label, 'dd')));
	}

	public function toString($destroy=true){
		$this->addItem($this->formList);
		return parent::toString($destroy);
	}
}
?>
