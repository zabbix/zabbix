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
	class CImg extends CTag
	{
/* public */
		function CImg($src,$alt_text=NULL,$width=NULL,$height=NULL,$class=NULL)
		{
			parent::CTag("img","no");

			$this->tag_start= "";
			$this->tag_end = "";
			$this->tag_body_start = "";
			$this->tag_body_end = "";

			$this->AddOption('border',0);
			$this->SetAltText($alt_text);
			$this->SetSrc($src);
			$this->SetWidth($width);
			$this->SetHeight($height);
			$this->SetClass($class);
		}
		function SetSrc($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetSrc [$value]");
			}
			return $this->AddOption("src",$value);
		}
		function SetAltText($value=NULL)
		{
			if(is_null($value))
				$value="image";

			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetText [$value]");
			}
			return $this->AddOption("alt",$value);
		}
		function SetMap($value=NULL)
		{
			if(is_null($value))
				$this->DeleteOption("usemup");

			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetMap [$value]");
			}
			
			$value = '#'.ltrim($value,'#');
			return $this->AddOption("usemap",$value);
		}
		function SetWidth($value=NULL){
			if(is_null($value))
				return $this->DelOption("width");
			elseif(is_numeric($value)||is_int($value))
				return $this->AddOption("width",$value);
			else
				return $this->error("Incorrect value for SetWidth [$value]");
		}
		function SetHeight($value=NULL){
			if(is_null($value))
				return $this->DelOption("height");
			elseif(is_numeric($value)||is_int($value))
				return $this->AddOption("height",$value);
			else
				return $this->error("Incorrect value for SetHeight [$value]");
		}
	}
?>
