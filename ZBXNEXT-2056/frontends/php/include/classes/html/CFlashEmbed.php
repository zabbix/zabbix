<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFlashEmbed extends CTag {

	public function __construct($src = null, $width = null, $height = null) {
		parent::__construct('embed');
		$this->attributes['allowScriptAccess'] = 'sameDomain';
		$this->attributes['type'] = 'application/x-shockwave-flash';
		$this->attributes['pluginspage'] = 'http://www.macromedia.com/go/getflashplayer';
		$this->attributes['align'] = 'middle';
		$this->attributes['quality'] = 'high';
		$this->attributes['wmode'] = 'opaque';
		$this->attributes['width'] = $width;
		$this->attributes['height'] = $height;
		$this->attributes['src'] = $src;
	}

	public function setWidth($value) {
		$this->attributes['width']  = $value;
		return $this;
	}

	public function setHeight($value) {
		$this->attributes['height'] = $value;
		return $this;
	}

	public function setSrc($value) {
		$this->attributes['src'] = $value;
		return $this;
	}
}
