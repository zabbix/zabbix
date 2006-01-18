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
	class CCheckBox extends CTag
	{
/* public */
		function CCheckBox($name='checkbox',$value='no',$caption=NULL,$action=NULL)
		{
			parent::CTag("input","no");
			$this->tag_body_start = "";
			$this->AddOption('type','checkbox');
			$this->SetName($name);
			$this->SetCaption($caption);
			$this->SetChecked($value);
			$this->SetAction($action);
		}
		function SetName($value='checkbox')
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
				return 0;
			elseif(is_string($value))	
				return $this->AddItem(nbsp($value));
			return $this->error("Incorrect value for SetCaption [$value]");
		}
		function SetChecked($value="yes")
		{
			if($value=="yes" || $value=="checked" || $value=="on" || $value==1)
				return $this->AddOption("checked","checked");
			elseif($value=="no" || $value=="unchecked" || $value=="off" || $value==NULL || $value==0)
				return $this->DelOption("checked");
			return $this->error("Incorrect value for SetChacked [$value]");
		}
		function SetAction($value='submit()', $event='onClick')
		{
			if(is_null($value))
				return 1;
			if(!is_string($value))
				return $this->error("Incorrect value for SetAction [$value]");
			if(!is_string($event))
				return $this->error("Incorrect event for SetAction [$event]");
			return $this->AddOption($event,$value);
		}
	}
?>
