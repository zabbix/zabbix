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
class CColorCell extends CDiv{
	public function __construct($name, $value, $action=null){
		parent::__construct(SPACE.SPACE.SPACE, null);
		$this->setName($name);
		$this->addOption('id', $name);
		$this->addOption('title', '#'.$value);
		$this->addOption('class', 'pointer');
		$this->addOption('style', 'display: inline; width: 10px; height: 10px; text-decoration: none; outline: 1px solid black; background-color: #'.$value);
		
		$this->setAction($action);
	}
	
	public function setAction($action=null){
		if(!isset($action)) return false;
		
		return $this->addAction('onclick', 'javascript:'.$action);
	}
}
?>