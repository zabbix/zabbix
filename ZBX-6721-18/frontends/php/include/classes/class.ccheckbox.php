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
class CCheckBox extends CTag{
/* public */
	public function __construct($name='checkbox',$checked='no',$action=null,$value='yes'){
		parent::__construct('input','no');
		$this->tag_body_start = '';

		$this->setAttribute('class','checkbox');
		$this->setAttribute('type','checkbox');
		$this->setAttribute('value',$value);
		$this->setAttribute('name',$name);
		$this->setAttribute('id',$name);

		$this->setAction($action);
		$this->setChecked($checked);
	}

	public function setEnabled($value='yes'){
		if($value === 'yes' || $value == true || $value == 1){
			return $this->removeAttribute('disabled');
		}
		$this->attributes['disabled'] = 'disabled';

	return true;
	}

	public function setChecked($value='yes'){
		if((is_numeric($value) && ($value!=0)) || (is_string($value) && ($value=='yes' || $value=='checked' || $value=='on') || $value=='1'))
			return $this->attributes['checked'] = 'checked';

		$this->removeAttribute('checked');
	}

	public function setAction($value='submit()', $event='onclick'){
		$this->addAction('onclick', $value);
	}
}
?>
