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
	class CLink extends CTag
	{
/* public */
		function CLink($item="www.zabbix.com",$url="http://www.zabbix.com",$class=NULL)
		{
			parent::CTag("a","yes");

			$this->tag_start= "";
			$this->tag_end = "";
			$this->tag_body_start = "";
			$this->tag_body_end = "";

			$this->SetClass($class);
			$this->AddItem($item);
			$this->SetUrl($url);
		}
		function SetUrl($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetUrl [$value]");
			}
			
			$this->AddOption("href",$value);
		}
	}
?>
