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
class CIpBox{
 public $ip_parts;

	public function __construct($name='ip',$value){
		$this->ip_parts = array();

		if(!is_array($value)) $value = explode('.', $value);
		if(!isset($value[0])) $value[0] = 0;
		if(!isset($value[1])) $value[1] = 0;
		if(!isset($value[2])) $value[2] = 0;
		if(!isset($value[3])) $value[3] = 0;

		for($i = 0; $i < 4; $i++){
			$this->ip_parts[$i] = new CNumericBox($name.'['.$i.']', $value[$i], 3);
			if($i != 3){
				$this->ip_parts[$i]->tag_end = '';
				$this->ip_parts[$i]->AddAction('OnKeyDown',
					' this.maxlength = this.getAttribute("maxlength"); '.
					' this.oldlength = this.value.length; ');
				$this->ip_parts[$i]->AddAction('OnKeyUp',
					' if(this.oldlength != this.value.length && this.value.length == this.maxlength) {'.
					' var el = this.form.elements["'.$name.'['.($i+1).']'.'"];'.
					' if(el) { el.focus(); el.select(); }}');
			}
			$this->ip_parts[$i] = unpack_object($this->ip_parts[$i]);
		}
	}

	public function toString($destroy=true){
		$res = implode('.',$this->ip_parts);

		if($destroy){
			$this = array();
		}

	return $res;
	}

	public function show($destroy=true){
		echo $this->toString($destroy);
	}
}

?>
