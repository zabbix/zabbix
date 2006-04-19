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
		function CTextBox($name='textbox',$value="",$size=20,$caption=NULL,$readonly="no")
		{
			parent::CTag("input","no");
			$this->SetClass("biginput");
			$this->SetName($name);
			$this->SetSize($size);
			$this->SetCaption($caption);
			$this->SetValue($value);
			$this->SetReadonly($readonly);
		}
		function Show()
		{
			if(isset($this->caption))
				echo $this->caption." ";
			parent::Show();
		}
		function SetReadonly($value='yes')
		{
			if(is_string($value))
			{
				if($value=='no')
					return $this->DelOption("readonly");
				elseif($value=='yes')
					return $this->AddOption("readonly",'readonly');
			}
			return $this->error("Incorrect value for SetReadonly [$value]");
		}
		function SetName($value='textbox')
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetName [$value]");
			}
			return $this->AddOption("name",$value);
		}
		function SetCaption($value=NULL)
		{
			if(is_null($value))
				unset($this->caption);
			elseif(is_string($value))	
				$this->caption = $value;
			else
			{
				return $this->error("Incorrect value for SetCaption [$value]");
			}
			return 0;
		}
		function SetValue($value=NULL)
		{
			if(is_null($value))	$value="";

			if(is_numeric($value))	$value = strval($value);

			if(is_string($value))
			{
				return $this->AddOption("value",$value);
			}
			return $this->error("Incorrect value for SetValue [$value]");
		}
		function SetSize($value)
		{
			if(!is_numeric($value))
			{
				return $this->error("Incorrect value for SetSize [$value]");
			}
			return $this->AddOption("size",strval($value));
			
		}
	}
?>
