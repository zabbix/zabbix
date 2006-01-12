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
	class CButton extends CTag
	{
/* public */
		function CButton($name="button", $caption="", $action=NULL, $accesskey=NULL)
		{
			parent::CTag("input","no");
			$this->tag_body_start = "";
			$this->AddOption("type","submit");
			$this->SetClass("button");
			$this->SetName($name);
			$this->SetCaption($caption);
			$this->SetAction($action);
			$this->SetAccessKey($accesskey);
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
		function SetTitle($value='button title')
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetTitle [$value]");
			}
			return $this->AddOption("title",$value);
		}
		function SetAccessKey($value='B')
		{
			if(is_null($value))
				return 0;
			elseif(!is_string($value))
			{
				return $this->error("Incorrect value for SetAccessKey [$value]");
			}

			if($this->GetOption('title')==NULL)
				$this->SetTitle($this->GetOption('value')." [Alt+$value]");

			return $this->AddOption("accessKey",$value);
		}
		function SetName($value='button')
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetName [$value]");
			}
			return $this->AddOption("name",$value);
		}
		function SetCaption($value="")
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetCaption [$value]");
			}
			return $this->AddOption("value",$value);
		}
	}
?>
