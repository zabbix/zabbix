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
class CInput extends CTag{
	protected $jQuery;
	public function __construct($type='text',$name='textbox',$value='',$readonly='no'){
		parent::__construct('input','no');

		$this->jQuery = false;

		$this->setType($type);
		
		$this->setAttribute('id', $name);
		$this->setAttribute('name', $name);

		$this->setAttribute('value',$value);

		$this->setReadonly($readonly);
	}

	public function setType($type){
		$this->setAttribute('type', $type);

		if(!$this->jQuery)
			$this->setAttribute('class', 'input '.$type);
	}

	public function setReadonly($value='yes'){
		if(
			(is_string($value) && ($value=='yes' || $value=='checked' || $value=='on') || $value=='1') ||
			(is_int($value) && $value<>0) || ($value === true)
		){
			$this->setAttributes('readonly', 'readonly');
			return $this;
		}

		$this->removeAttribute('readonly');
		return $this;
	}

	public function setEnabled($value='yes'){
		if(
			(is_string($value) && ($value=='yes' || $value=='checked' || $value=='on') || $value=='1') ||
			(is_int($value) && $value<>0) || ($value === true)
		){
			$this->removeAttribute('disabled');
			return $this;
		}

		$this->setAttribute('disabled','disabled');

	return $this;
	}


	public function useJQuery($state){
		$this->jQuery = (bool) $state;
	}
}
?>
