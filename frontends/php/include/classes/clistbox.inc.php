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
	class CListBox extends CComboBox
	{
/* public */
		function CListBox($name='combobox',$value=NULL,$size=5,$action=NULL)
		{
			parent::CComboBox($name,NULL,$action);
			$this->AddOption("multiple","multiple");
			$this->SetSize($size);
			$this->SetValue($value);
		}
		function SetSize($value)
		{
			if(is_null($value))
				return $this->DelOption("size");
			if(!is_numeric($value))
				return $this->error("Incorrect value for SetSize [$value]");
			$this->AddOption("size",$value);
		}
		function SetSelectedByValue(&$item)
		{
			if(!is_null($this->value))
			{
				if(is_array($this->value))
				{
					$selected = 'no';
					if(in_array($item->GetValue(),$this->value))	$selected = 'yes';
					return $item->SetSelected($selected);
				}
				else
				{
					return parent::SetSelectedByValue($item);
				}
			}
			return false;
		}
	}
?>
