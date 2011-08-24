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
class CIFrame extends CTag{
	public function __construct($src=NULL,$width='100%',$height='100%',$scrolling='no',$id='iframe'){
		parent::__construct('iframe','yes');

		$this->tag_start= '';
		$this->tag_end = '';
		$this->tag_body_start = '';
		$this->tag_body_end = '';

		$this->setSrc($src);
		$this->setWidth($width);
		$this->setHeight($height);
		$this->setScrolling($scrolling);
		$this->setAttribute('id',$id);
	}

	public function setSrc($value=NULL){
		if(is_null($value)){
			return $this->removeAttribute('src');
		}
		else if(!is_string($value)){
			return $this->error('Incorrect value for setSrc ['.$value.']');
		}
	return $this->setAttribute('src',$value);
	}

	public function setWidth($value){
		if(is_null($value)){
			return $this->removeAttribute('width');
		}
		else if(!is_string($value)){
			return $this->error('Incorrect value for setWidth ['.$value.']');
		}

		$this->setAttribute('width',$value);
	}

	public function setHeight($value){
		if(is_null($value)){
			return $this->removeAttribute('height');
		}
		else if(!is_string($value)){
			return $this->error('Incorrect value for setHeight ['.$value.']');
		}

		$this->setAttribute('height',$value);
	}

	public function setScrolling($value){
		if(is_null($value)) $value = 'no';

		if(($value!=='no') && ($value!=='yes') && ($value!=='auto')){
			return $this->error('Incorrect value for setScrolling ['.$value.']');
		}

		$this->setAttribute('scrolling',$value);
	}
}
?>
