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
	class CHostsInfo extends CTable
	{
		//var $style;
		function CHostsInfo($style = STYLE_HORISONTAL)
		{
			$this->style = null;

			parent::CTable(NULL,"hosts_info");
			$this->SetOrientation($style);
		}

		function SetOrientation($value)
		{
			if($value != STYLE_HORISONTAL && $value != STYLE_VERTICAL)
				return $this->error("Incorrect value for SetOrientation [$value]");

			$this->style = $value;
		}

		function BodyToString()
		{
			$this->CleanItems();

			$db_host_cnt = DBselect("select count(*) as cnt from hosts where available=".HOST_AVAILABLE_TRUE);
			$host_cnt = DBfetch($db_host_cnt);
			$avail = $host_cnt["cnt"];

			$db_host_cnt = DBselect("select count(*) as cnt from hosts where available=".HOST_AVAILABLE_FALSE);
			$host_cnt = DBfetch($db_host_cnt);
			$notav = $host_cnt["cnt"];

			$db_host_cnt = DBselect("select count(*) as cnt from hosts where available=".HOST_AVAILABLE_UNKNOWN);
			$host_cnt = DBfetch($db_host_cnt);
			$uncn = $host_cnt["cnt"];

			$header = new CCol(S_HOSTS_INFO,"header");
			if($this->style == STYLE_HORISONTAL)
				$header->SetColspan(3);

			$this->AddRow($header);

			$avail	= new CCol($avail."  ".S_AVAILABLE,	"avail");
			$notav	= new CCol($notav."  ".S_NOT_AVAILABLE,	"notav");
			$uncn	= new CCol($uncn."  ".S_UNKNOWN,		"uncn");

			if($this->style == STYLE_HORISONTAL)
			{
				$this->AddRow(array($avail, $notav, $uncn));
			}
			else
			{			
				$this->AddRow($avail);
				$this->AddRow($notav);
				$this->AddRow($uncn);
			}
			return parent::BodyToString();
		}
	}
?>
