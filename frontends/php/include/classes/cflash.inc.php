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
	class CFlashEmbed extends CTag
	{
		function CFlashEmbed($src=NULL, $width = NULL, $height = NULL)
		{
			parent::CTag("embed");
			$this->AddOption("allowScriptAccess","sameDomain");
			$this->AddOption("type","application/x-shockwave-flash");
			$this->AddOption("pluginspage","http://www.macromedia.com/go/getflashplayer");
			$this->AddOption("align","middle");
			$this->AddOption("quality","high");
			
			$this->SetWidth($width);
			$this->SetHeight($height);
			$this->SetSrc($src);
		}
		function SetWidth($value)
		{
			if(is_null($value))
				return $this->DelOption("width");
			if(!is_numeric($value))
				return $this->error("Incorrect value for SetWidth [$value]");

			$this->AddOption("width",$value);
		}
		function SetHeight($value)
		{
			if(is_null($value))
				return $this->DelOption("height");
			if(!is_numeric($value))
				return $this->error("Incorrect value for SetHeight [$value]");

			$this->AddOption("height",$value);
		}
		function SetSrc($value)
		{
			if(is_null($value))
				return $this->DelOption("src");
			if(!is_string($value))
				return $this->error("Incorrect value for SetSrc[$value]");

			$this->AddOption("src",$value);
		}
	}

	class CParam extends CTag
	{
		function CParam($name,$value)
		{
			parent::CTag("param","no");

			$this->SetName($name);
			$this->SetValue($value);
		}
		function SetName($value)
		{
			$this->AddOption("name",$value);
		}
		function SetValue($value)
		{
			$this->AddOption("value",$value);
		}
	}

	class CFlash extends CTag
	{
		var $timetype;
		function CFlash($src=NULL, $width = NULL, $height = NULL)
		{
			parent::CTag("object",'yes');
			$this->AddOption("classid","clsid:d27cdb6e-ae6d-11cf-96b8-444553540000");
			$this->AddOption("codebase","http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0");
			$this->AddOption("align","middle");

			$this->AddItem(new CParam("allowScriptAccess","sameDomain"));
			$this->AddItem(new CParam("quality","high"));
			$this->items["src"] = new CParam("movie",$src);

			$this->items["embeded"] = new CFlashEmbed();

			$this->SetWidth($width);
			$this->SetHeight($height);
			$this->SetSrc($src);
		}
		function SetWidth($value)
		{
			if(is_null($value))
				return $this->DelOption("width");
			if(!is_numeric($value))
				return $this->error("Incorrect value for SetWidth [$value]");

			$this->AddOption("width",$value);
			$this->items["embeded"]->SetWidth($value);
		}
		function SetHeight($value)
		{
			if(is_null($value))
				return $this->DelOption("height");
			if(!is_numeric($value))
				return $this->error("Incorrect value for SetHeight [$value]");

			$this->AddOption("height",$value);
			$this->items["embeded"]->SetHeight($value);
		}
		function SetSrc($value)
		{
			if(is_null($value))
				return $this->DelOption("src");
			if(!is_string($value))
				return $this->error("Incorrect value for SetSrc[$value]");

			$this->items["src"]->SetValue($value);
			$this->items["embeded"]->SetSrc($value);
		}
	}
?>
