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
class CImg extends CTag{

	public function __construct($src,$name=NULL,$width=NULL,$height=NULL,$class=NULL){
		parent::__construct('img','no');

		$this->tag_start= '';
		$this->tag_end = '';
		$this->tag_body_start = '';
		$this->tag_body_end = '';

		if(is_null($name))
			$name='image';

		$this->setAttribute('border',0);
		$this->setAttribute('alt',$name);
		$this->setName($name);
		$this->setAltText($name);
		$this->setSrc($src);
		$this->setWidth($width);
		$this->setHeight($height);
		$this->setClass($class);
	}

	public function setSrc($value){
		if(!is_string($value)){
			return $this->error('Incorrect value for SetSrc ['.$value.']');
		}
	return $this->setAttribute('src',$value);
	}

	public function setAltText($value=NULL){
		if(!is_string($value)){
			return $this->error('Incorrect value for SetText ['.$value.']');
		}
	return $this->setAttribute('alt',$value);
	}

	public function setMap($value=NULL){
		if(is_null($value))
			$this->deleteOption('usemap');

		if(!is_string($value)){
			return $this->error('Incorrect value for SetMap ['.$value.']');
		}

		$value = '#'.ltrim($value,'#');
	return $this->setAttribute('usemap',$value);
	}

	public function SetWidth($value=NULL){
		if(is_null($value))
			return $this->removeAttribute('width');
		else if(is_numeric($value)||is_int($value))
			return $this->setAttribute('width',$value);
		else
			return $this->error('Incorrect value for SetWidth ['.$value.']');
	}

	public function setHeight($value=NULL){
		if(is_null($value))
			return $this->removeAttribute('height');
		else if(is_numeric($value)||is_int($value))
			return $this->setAttribute('height',$value);
		else
			return $this->error('Incorrect value for SetHeight ['.$value.']');
	}
}
?>
