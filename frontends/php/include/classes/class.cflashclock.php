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
class CFlashClock extends CFlash{

 public $timetype;
 public $src;

	public function __construct($width = 200, $height = 200, $timetype = TIME_TYPE_LOCAL, $url = NULL){
		$this->timetype = null;

		if(!is_numeric($width) || $width < 24) $width = 200;
		if(!is_numeric($height) || $height< 24) $height = 200;

		$this->src = 'images/flash/zbxclock.swf?analog=1&smooth=1';
		if(!is_null($url))	$this->src .= '&url='.urlencode($url);

		parent::__construct($this->src,$width,$height);
		$this->setTimeType($timetype);
	}

	public function setTimeType($value){
		if($value != TIME_TYPE_LOCAL && $value != TIME_TYPE_SERVER)
			return $this->error('Incorrect value vor SetTimeType ['.$value.']');

		$this->timetype = $value;
	}

	public function bodyToString(){
		if($this->timetype == TIME_TYPE_SERVER)
			$this->setSrc($this->src.'&timestamp='.(time() + date('Z')));

	return parent::bodyToString();
	}
}
?>
