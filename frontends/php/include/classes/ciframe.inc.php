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
	class CIFrame extends CTag
	{
/* public */
		function CIFrame($src=NULL,$width="100%",$height="100%",$scrolling="no",$id='iframe')
		{
			parent::CTag("iframe","yes");

			$this->tag_start= "";
			$this->tag_end = "";
			$this->tag_body_start = "";
			$this->tag_body_end = "";

			$this->SetSrc($src);
			$this->SetWidth($width);
			$this->SetHeight($height);
			$this->SetScrolling($scrolling);
			$this->AddOption('id',$id);
		}
		function SetSrc($value=NULL)
		{
			if(is_null($value))
			{
				return $this->DelOption("src");
			}
			elseif(!is_string($value))
			{
				return $this->error("Incorrect value for SetSrc [$value]");
			}
			return $this->AddOption("src",$value);
		}
		function SetWidth($value)
		{
			if(is_null($value))
			{
				return $this->DelOption("width");
			}
			elseif(!is_string($value))
			{
				return $this->error("Incorrect value for SetWidth [$value]");
			}
			
			$this->AddOption("width",$value);
		}
		function SetHeight($value)
		{
			if(is_null($value))
			{
				return $this->DelOption("height");
			}
			elseif(!is_string($value))
			{
				return $this->error("Incorrect value for SetHeight [$value]");
			}
			
			$this->AddOption("height",$value);
		}
		function SetScrolling($value)
		{
			if(is_null($value)) $value = 'no';
	
			if($value=='no' && $value=='yes' && $value=='auto')
			{
				return $this->error("Incorrect value for SetScrolling [$value]");
			}
			
			$this->AddOption("scrolling",$value);
		}
	}
?>
