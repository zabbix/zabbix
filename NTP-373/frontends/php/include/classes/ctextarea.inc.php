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
	class CTextArea extends CTag
	{
/* public */
		function CTextArea($name='textarea',$value="",$cols=77,$rows=7,$readonly='no')
		{
			parent::CTag("textarea","yes");
			$this->options['class'] = 'biginput';
			/* $this->options['wrap'] = 'soft'; */
			$this->options['name'] = $name;
			$this->options['rows'] = $rows;
			$this->options['cols'] = $cols;
			$this->SetReadonly($readonly);

			$this->AddItem($value);
		}
		function SetReadonly($value='yes')
		{
			if($value=='yes')
				return $this->options['readonly'] = 'readonly';

			$this->DelOption("readonly");
		}
		function SetValue($value="")
		{
			return $this->AddItem($value);
		}
		function SetRows($value)
		{
			return $this->options['rows'] = $value;
			
		}
		function SetCols($value)
		{
			return $this->options['cols'] = $value;
			
		}
	}
?>
