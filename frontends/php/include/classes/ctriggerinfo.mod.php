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
	class CTriggersInfo extends CTable
	{
		var $style;
		function CTriggersInfo($style = STYLE_HORISONTAL)
		{
			parent::CTable(NULL,"triggers_info");
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

			$uncn = $info = $warn = $avg = $high = $dis = 0;

			$db_priority = DBselect("select t.priority,count(*) as cnt from triggers t,hosts h,items i,functions f".
				" where t.value=1 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and t.triggerid=f.triggerid and i.status=0 group by priority");

			while($row=DBfetch($db_priority))
			{
				switch($row["priority"])
				{
					case 0: $uncn	=$row["cnt"];	break;
					case 1: $info	=$row["cnt"];	break;
					case 2: $warn	=$row["cnt"];	break;
					case 3: $avg	=$row["cnt"];	break;
					case 4: $high	=$row["cnt"];	break;
					case 5: $dis	=$row["cnt"];	break;
				}
			}

			$db_ok_cnt = DBselect("select count(*) as cnt from triggers t,hosts h,items i,functions f".
				" where t.value=0 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and t.triggerid=f.triggerid and i.status=0");

			$ok_cnt = DBfetch($db_ok_cnt);

			$header = new CCol(S_TRIGGERS_INFO,"header");
			if($this->style == STYLE_HORISONTAL)
				$header->SetColspan(7);
			$this->AddRow($header);

			$trok	= new CCol($ok_cnt["cnt"]."  ".S_OK,	"trok");
			$uncn	= new CCol($uncn."  ".S_NOT_CLASSIFIED,	"uncn");
			$info	= new CCol($info."  ".S_INFORMATION,	"info");
			$warn	= new CCol($warn."  ".S_WARNING,		"warn");
			$avg	= new CCol($avg."  ".S_AVERAGE,		"avg");
			$high	= new CCol($high."  ".S_HIGH,		"high");
			$dis	= new CCol($dis."  ".S_DISASTER,		"dis");
			

			if($this->style == STYLE_HORISONTAL)
			{
				$this->AddRow(array($trok, $uncn, $info, $warn, $avg, $high, $dis));
			}
			else
			{			
				$this->AddRow($trok);
				$this->AddRow($uncn);
				$this->AddRow($info);
				$this->AddRow($warn);
				$this->AddRow($avg);
				$this->AddRow($high);
				$this->AddRow($dis);
			}
			return parent::BodyToString();
		}
	}
?>
