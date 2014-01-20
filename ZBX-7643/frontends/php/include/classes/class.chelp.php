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
class CHelp extends CIcon{
	public function __construct($url='index.php',$side=null){
		if(is_null($side)) $side = 'right';
		if($side == 'right'){
			$pside = 'left';
		}
		else{
			$side = 'left';
			$pside = 'right';
		}

		parent::__construct("http://www.zabbix.com/documentation",'iconhelp');//'http://www.zabbix.com/manual/v1.1/'.$url);
		parent::onClick('window.open("http://www.zabbix.com/documentation/");');
		$this->attributes['style'] = 'float:'.$side.'; text-decoration: none;';
	}
}
?>
