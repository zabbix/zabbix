<?php
/* 
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
include_once('include/triggers.inc.php');

class CTriggersInfo extends CTable{

 public $style;
 public $show_header;
 public $nodeid;
	
	public function __construct($style = STYLE_HORISONTAL){
		$this->style = null;

		parent::__construct(NULL,'triggers_info');
		$this->setOrientation($style);
		$this->show_header = true;
		$this->nodeid = get_current_nodeid();
		$this->groupid = 0;
	}

	public function setOrientation($value){
		if($value != STYLE_HORISONTAL && $value != STYLE_VERTICAL)
			return $this->error('Incorrect value for SetOrientation ['.$value.']');

		$this->style = $value;
	}

	public function setNodeid($nodeid){
		$this->nodeid = (int)$nodeid;
	}
	
	public function set_host_group($groupid){
		$this->groupid = $groupid;
	}
	
	public function hideHeader(){
		$this->show_header = false;
	}

	public function bodyToString(){
		$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array(), PERM_RES_IDS_ARRAY, get_current_nodeid(true));
		
		foreach($available_triggers as $id => $triggerid){
			if(trigger_dependent($triggerid))	unset($available_triggers[$id]);
		}
	
		$this->cleanItems();

		$ok = $uncn = $info = $warn = $avg = $high = $dis = 0;
		
		$sql_from = '';
		$sql_where = '';
		if($this->groupid > 0){
			$sql_from = ', hosts_groups hg ';
			$sql_where = ' AND hg.groupid='.$this->groupid.
							' AND h.hostid=hg.hostid ';
		}
		
		$db_priority = DBselect('SELECT t.priority,t.value,count(DISTINCT t.triggerid) as cnt '.
						' FROM triggers t,hosts h,items i,functions f '.$sql_from.
						' WHERE t.status='.TRIGGER_STATUS_ENABLED.
							' AND f.itemid=i.itemid '.
							' AND h.hostid=i.hostid '.
//								' AND '.DBin_node('h.hostid').
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND t.triggerid=f.triggerid '.
							' AND i.status='.ITEM_STATUS_ACTIVE.
							' AND '.DBcondition('t.triggerid',$available_triggers).
							$sql_where.
						' GROUP BY t.priority,t.value');
		while($row=DBfetch($db_priority)){
			
			switch($row["value"]){
				case TRIGGER_VALUE_TRUE:
					switch($row["priority"]){
						case TRIGGER_SEVERITY_INFORMATION:	$info	+= $row["cnt"];	break;
						case TRIGGER_SEVERITY_WARNING:		$warn	+= $row["cnt"];	break;
						case TRIGGER_SEVERITY_AVERAGE:		$avg	+= $row["cnt"];	break;
						case TRIGGER_SEVERITY_HIGH:			$high	+= $row["cnt"];	break;
						case TRIGGER_SEVERITY_DISASTER:		$dis	+= $row["cnt"];	break;
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

		if($this->show_header){
			$header = new CCol(S_TRIGGERS_INFO,"header");
			if($this->style == STYLE_HORISONTAL)
				$header->SetColspan(7);
			$this->addRow($header);
		}

		$trok	= new CCol($ok.SPACE.S_OK,					get_severity_style('ok',false));
		$uncn	= new CCol($uncn.SPACE.S_NOT_CLASSIFIED,	get_severity_style(TRIGGER_SEVERITY_NOT_CLASSIFIED,$uncn));
		$info	= new CCol($info.SPACE.S_INFORMATION,		get_severity_style(TRIGGER_SEVERITY_INFORMATION,$info));
		$warn	= new CCol($warn.SPACE.S_WARNING,			get_severity_style(TRIGGER_SEVERITY_WARNING,$warn));
		$avg	= new CCol($avg.SPACE.S_AVERAGE,			get_severity_style(TRIGGER_SEVERITY_AVERAGE,$avg));
		$high	= new CCol($high.SPACE.S_HIGH,				get_severity_style(TRIGGER_SEVERITY_HIGH,$high));
		$dis	= new CCol($dis.SPACE.S_DISASTER,			get_severity_style(TRIGGER_SEVERITY_DISASTER,$dis));
		

		if(STYLE_HORISONTAL == $this->style){
			$this->addRow(array($trok, $uncn, $info, $warn, $avg, $high, $dis));
		}
		else{			
			$this->addRow($trok);
			$this->addRow($uncn);
			$this->addRow($info);
			$this->addRow($warn);
			$this->addRow($avg);
			$this->addRow($high);
			$this->addRow($dis);
		}
		return parent::BodyToString();
	}
}
?>