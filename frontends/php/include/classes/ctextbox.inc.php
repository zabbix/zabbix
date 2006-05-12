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

?>
