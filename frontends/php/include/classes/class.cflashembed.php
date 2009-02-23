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
class CFlashEmbed extends CTag{
	public function __construct($src=NULL, $width = NULL, $height = NULL){
		parent::__construct('embed');
		$this->options['allowScriptAccess'] = 'sameDomain';
		$this->options['type'] = 'application/x-shockwave-flash';
		$this->options['pluginspage']  = 'http://www.macromedia.com/go/getflashplayer';
		$this->options['align'] = 'middle';
		$this->options['quality'] = 'high';
		
		$this->options['width'] = $width;
		$this->options['height'] = $height;
		$this->options['src'] = $src;
	}
	
	public function setWidth($value){
		$this->options['width']  = $value;
	}
	
	public function setHeight($value){
		$this->options['height'] = $value;
	}
	
	public function setSrc($value){
		$this->options['src'] = $value;
	}
}
?>