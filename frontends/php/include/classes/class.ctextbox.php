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
class CTextBox extends CTag{
 private $caption;
	public function __construct($name='textbox',$value='',$size=20,$readonly='no'){
		$this->caption = null;
		parent::__construct('input','no');
		$this->tag_body_start = '';
		$this->options['class'] = 'biginput';
		$this->addOption('name', $name);
		$this->addOption('id', $name);
		$this->addOption('size', $size);
		$this->addOption('value',$value);
		$this->setReadonly($readonly);
	}
	
	public function setReadonly($value='yes'){
		if((is_string($value) && ($value=='yes' || $value=='checked' || $value=='on') || $value=='1') || (is_int($value) && $value<>0))
			return $this->options['readonly'] = 'readonly';

		$this->delOption('readonly');
	}
	
	public function setValue($value=NULL){
		$this->options['value'] = $value;
	}
	
	public function setSize($value){
		$this->options['size'] = $value;
		
	}
}
?>