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
class CButtonCancel extends CButton{
	public function __construct($vars=NULL,$action=NULL){
		parent::__construct('cancel',S_CANCEL);
		$this->attributes['type'] = 'button';
		$this->setVars($vars);
		if(!is_null($action))
			$this->setAttribute('onclick', $action);
	}
	public function setVars($value=NULL){
		global $page;

		$url = '?cancel=1';
		if(!is_null($value)) $url.= $value;

		$uri = new Curl($url);
		$url = $uri->getUrl();

		return $this->setAttribute('onclick', "javascript: return redirect('".$url."');");
	}
}
?>
