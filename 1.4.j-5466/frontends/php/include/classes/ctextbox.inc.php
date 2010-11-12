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
		//var $caption;
/* public */
		function CTextBox($name='textbox',$value="",$size=20,$readonly="no")
		{
			$this->caption = null;
			parent::CTag('input','no');
			$this->tag_body_start = '';
			$this->options['class'] = 'biginput';
			$this->AddOption('name', $name);
			$this->AddOption('size', $size);
			$this->AddOption('value',$value);
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
		function CNumericBox($name='number',$value='0',$size=20,$readonly="no",$allowempty=false)
		{
			parent::CTextBox($name,$value,$size,$readonly);
			$this->AddOption('MaxLength', $size);
			$this->AddOption('Style', 'text-align: right;');
			$this->AddAction('OnKeyPress',
				' var c = (window.event) ? event.keyCode : event.which;'.
				' if(event.ctrlKey || c <= 31 || (c >= 48 && c <= 57)) return true; else return false; ');
			$this->AddAction('OnChange',
					($allowempty ? ' if(this.value.length==0 || this.value==null) this.value = \'\'; else ' : '').
					' if(isNaN(parseInt(this.value))) this.value = 0; '.
					' else this.value = parseInt(this.value);'
				);
		}
	}

	class CIpBox
	{
		//var $ip_parts = array();
		
		function CIPBox($name='ip',$value)
		{
			$this->ip_parts = array();

			if(!is_array($value)) $value = explode('.', $value);
			if(!isset($value[0])) $value[0] = 0;
			if(!isset($value[1])) $value[1] = 0;
			if(!isset($value[2])) $value[2] = 0;
			if(!isset($value[3])) $value[3] = 0;
			
			for($i = 0; $i < 4; $i++)
			{
				$this->ip_parts[$i] = new CNumericBox($name.'['.$i.']', $value[$i], 3);
				if($i != 3)
				{
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

		function ToString($destroy=true)
		{
			$res = implode('.',$this->ip_parts);
			
			if($destroy)
			{
### TODO Problem under PHP 5.0  "Fatal error: Cannot re-assign $this in ..."
#				$this = null;
			}

			return $res;
		}

		function Show($destroy=true)
		{
			echo $this->ToString($destroy);
		}
	}

?>
