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
		/*
		var $style;
		var $show_header;
		var $nodeid;*/
		
		function CTriggersInfo($style = STYLE_HORISONTAL)
		{
			global $ZBX_CURNODEID;

			$this->style = null;

			parent::CTable(NULL,"triggers_info");
			$this->SetOrientation($style);
			$this->show_header = true;
			$this->nodeid = $ZBX_CURNODEID;
		}

		function SetOrientation($value)
		{
			if($value != STYLE_HORISONTAL && $value != STYLE_VERTICAL)
				return $this->error("Incorrect value for SetOrientation [$value]");

			$this->style = $value;
		}

		function SetNodeid($nodeid)
		{
			$this->nodeid = (int)$nodeid;
		}
		
		function HideHeader()
		{
			$this->show_header = false;
		}

		function BodyToString()
		{
			global $USER_DETAILS;

			$this->CleanItems();

			$ok = $uncn = $info = $warn = $avg = $high = $dis = 0;

			$db_priority = DBselect("select t.priority,t.value,count(*) as cnt from triggers t,hosts h,items i,functions f".
				" where t.status=".TRIGGER_STATUS_ENABLED." and f.itemid=i.itemid ".
				" and h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and t.triggerid=f.triggerid ".
				" and i.status=".ITEM_STATUS_ACTIVE.
				' and h.hostid in ('.get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,
					null, null, $this->nodeid).') '.
				" group by priority");
			while($row=DBfetch($db_priority))
			{
				switch($row["value"])
				{
					case TRIGGER_VALUE_TRUE:
						switch($row["priority"])
						{
							case 1: $info	+= $row["cnt"];	break;
							case 2: $warn	+= $row["cnt"];	break;
							case 3: $avg	+= $row["cnt"];	break;
							case 4: $high	+= $row["cnt"];	break;
							case 5: $dis	+= $row["cnt"];	break;
							default:
								$uncn	+= $row["cnt"];	break;
						}
						break;
					case TRIGGER_VALUE_FALSE:
						$ok	+= $row["cnt"];	break;
					default:
						$uncn	+= $row["cnt"];	break;
				}
			}

			if($this->show_header)
			{
				$header = new CCol(S_TRIGGERS_INFO,"header");
				if($this->style == STYLE_HORISONTAL)
					$header->SetColspan(7);
				$this->AddRow($header);
			}

			$trok	= new CCol($ok.SPACE.S_OK,		"normal");
			$uncn	= new CCol($uncn.SPACE.S_NOT_CLASSIFIED,"uncnown");
			$info	= new CCol($info.SPACE.S_INFORMATION,	"information");
			$warn	= new CCol($warn.SPACE.S_WARNING,	"warning");
			$avg	= new CCol($avg.SPACE.S_AVERAGE,	"average");
			$high	= new CCol($high.SPACE.S_HIGH,		"high");
			$dis	= new CCol($dis.SPACE.S_DISASTER,	"disaster");
			

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
