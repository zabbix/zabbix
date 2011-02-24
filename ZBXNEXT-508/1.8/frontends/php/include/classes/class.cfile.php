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
class CFile extends CTag{
	public function __construct($name='file',$value=''){
		parent::__construct('input','no');
		$this->setClass('biginput');
		$this->setAttribute('type','file');
		$this->setName($name);
		$this->setFile($value);
	}

	public function setFile($value=''){
		if(!is_string($value)){
			return $this->error('Incorrect value for SetFile ['.$value.']');
		}
	return $this->setAttribute('value',$value);
	}
}
?>
