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

class CFlash extends CTag{
 public $srcParam;
 public $embededFlash;

	public function __construct($src=NULL, $width = NULL, $height = NULL){
		parent::__construct('object','yes');
		$this->attributes['classid'] = 'clsid:d27cdb6e-ae6d-11cf-96b8-444553540000';
		$this->attributes['codebase'] = 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0';
		$this->attributes['align'] = 'middle';

		$this->addItem(new CParam('allowScriptAccess','sameDomain'));
		$this->addItem(new CParam('quality','high'));

		$this->srcParam = new CParam('movie',$src);
		$this->embededFlash = new CFlashEmbed();

		$this->setWidth($width);
		$this->setHeight($height);
		$this->setSrc($src);
	}

	public function setWidth($value){
		$this->attributes['width'] = $value;
		$this->embededFlash->attributes['width'] = $value;
	}

	public function setHeight($value){
		$this->attributes['height'] = $value;
		$this->embededFlash->attributes['height'] = $value;
	}

	public function setSrc($value){
		$this->srcParam->attributes['value'] = $value;
		$this->embededFlash->attributes['src'] = $value;
	}

	public function bodyToString(){
		$ret = parent::bodyToString();
		$ret .= $this->srcParam->toString();
		$ret .= $this->embededFlash->toString();
	return $ret;
	}
}
?>
