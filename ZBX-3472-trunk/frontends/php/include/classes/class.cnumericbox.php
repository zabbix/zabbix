<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
class CNumericBox extends CInput{
	public function __construct($name='number',$value='0',$size=20,$readonly='no',$allowempty=false){
		parent::__construct('text', $name, $value);
		$this->setReadonly($readonly);
		$this->setAttribute('size', $size);
		$this->setAttribute('maxlength', $size);

		$this->setAttribute('style', 'text-align: right;');

		$this->addAction('onchange',
				($allowempty ? ' if(this.value.length==0 || this.value==null) this.value = \'\'; else ' : '').
				' if(isNaN(parseInt(this.value,10))) this.value = 0; '.
				' else this.value = parseInt(this.value,10);'
			);
	}
}
?>
