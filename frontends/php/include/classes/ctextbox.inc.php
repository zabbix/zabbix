<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	class CTextBox extends CTag
	{
/* private */
		var $caption;
/* public */
		function CTextBox($name='textbox',$value="",$size=20,$readonly="no")
		{
			parent::CTag('input','no');
			$this->tag_body_start = '';
			$this->options['class'] = 'biginput';
			$this->options['name'] = $name;
			$this->options['size'] = $size;
			$this->options['value'] = $value;
			$this->SetReadonly($readonly);
		}
		function SetReadonly($value='yes')
		{
			if($value=='yes')
				return $this->options['readonly'] = 'readonly';

			$this->DelOption('readonly');
		}
		function SetValue($value=NULL)
		{
			$this->options['value'] = $value;
		}
		function SetSize($value)
		{
			$this->options['size'] = $value;
			
		}
	}

	class CPassBox extends CTextBox
	{
/* public */
		function CPassBox($name='password',$value='',$size=20)
		{
			parent::CTextBox($name,$value,$size);
			$this->options['type'] = 'password';
		}
	}

	class CNumericBox extends CTextBox
	{
		function CNumericBox($name='password',$value='',$size=20)
		{
			parent::CTextBox($name,$value,$size);
			$this->options['OnKeyPress'] = 
				" var c= (event.which) ? event.which : event.keyCode; ".
				" if(c <= 31 || (c >= 48 && c <= 57)) return true; else return false; ";
		}
	}
/* TEST
	class CIpBox
	{
		var $ip_parts = array();
		
		function CIPBox($name='ip',$value)
		{
			if(!is_array($value)) $value = explode('.', $value);
			if(!isset($value[0])) $value[0] = 0;
			if(!isset($value[1])) $value[1] = 0;
			if(!isset($value[2])) $value[2] = 0;
			if(!isset($value[3])) $value[3] = 0;
			
			for($i = 0; $i < 4; $i++)
			{
				$this->ip_parts[$i] = new CNumericBox($name.'['.$i.']', $value[$i], 3);
				if($i < 3) $this->ip_parts[$i]->tag_end = '';
				$this->ip_parts[$i] = unpack_object($this->ip_parts[$i]);
			}
		}

		function ToString($destroy=true)
		{
			$res = implode('.',$this->ip_parts);
			
			if($destroy) $this = null;

			return $res;
		}

		function Show($destroy=true)
		{
			echo $this->ToString($destroy);
		}
	}
TEST */

?>
