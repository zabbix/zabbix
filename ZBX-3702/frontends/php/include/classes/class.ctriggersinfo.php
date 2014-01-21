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
 private $nodeid;
 private $groupid;
 private $hostid;

	public function __construct($groupid=null, $hostid=null, $style = STYLE_HORISONTAL){
		$this->style = null;

		parent::__construct(NULL,'triggers_info');
		$this->setOrientation($style);
		$this->show_header = true;

		$this->groupid = is_null($groupid) ? 0 : $groupid;
		$this->hostid = is_null($hostid) ? 0 : $hostid;
	}

	public function setOrientation($value){
		if($value != STYLE_HORISONTAL && $value != STYLE_VERTICAL)
			return $this->error('Incorrect value for SetOrientation ['.$value.']');

		$this->style = $value;
	}

	public function hideHeader(){
		$this->show_header = false;
	}

	public function bodyToString(){
		$this->cleanItems();

		$ok = $uncn = $uncl = $info = $warn = $avg = $high = $dis = 0;

		$options = array(
			'monitored' => 1,
			'skipDependent' => 1,
			'output' => API_OUTPUT_SHORTEN
		);

		if($this->hostid > 0)
			$options['hostids'] = $this->hostid;
		else if($this->groupid > 0)
			$options['groupids'] = $this->groupid;


		$triggers = CTrigger::get($options);
		$triggers = zbx_objectValues($triggers, 'triggerid');

		$sql = 'SELECT t.priority,t.value,count(DISTINCT t.triggerid) as cnt '.
				' FROM triggers t '.
				' WHERE '.DBcondition('t.triggerid',$triggers).
				' GROUP BY t.priority,t.value';

		$db_priority = DBselect($sql);
		while($row = DBfetch($db_priority)){
			switch($row['value']){
				case TRIGGER_VALUE_TRUE:
					switch($row['priority']){
						case TRIGGER_SEVERITY_NOT_CLASSIFIED:	$uncl	+= $row['cnt'];	break;
						case TRIGGER_SEVERITY_INFORMATION:	$info	+= $row['cnt'];	break;
						case TRIGGER_SEVERITY_WARNING:		$warn	+= $row['cnt'];	break;
						case TRIGGER_SEVERITY_AVERAGE:		$avg	+= $row['cnt'];	break;
						case TRIGGER_SEVERITY_HIGH:			$high	+= $row['cnt'];	break;
						case TRIGGER_SEVERITY_DISASTER:		$dis	+= $row['cnt'];	break;
					}
				break;
				case TRIGGER_VALUE_FALSE:
					$ok	+= $row['cnt'];
				break;
				default:
					$uncn += $row['cnt'];
				break;
			}
		}

		if($this->show_header){
			$header_str = S_TRIGGERS_INFO.SPACE;

			if(!is_null($this->nodeid)){
				$node = get_node_by_nodeid($this->nodeid);
				if($node > 0) $header_str.= '('.$node['name'].')'.SPACE;
			}

			if(remove_nodes_from_id($this->groupid)>0){
				$group = get_hostgroup_by_groupid($this->groupid);
				$header_str.= S_GROUP.SPACE.'&quot;'.$group['name'].'&quot;';
			}
			else{
				$header_str.= S_ALL_GROUPS;
			}

			$header = new CCol($header_str,'header');
			if($this->style == STYLE_HORISONTAL)
				$header->SetColspan(8);
			$this->addRow($header);
		}

		$trok	= new CCol($ok.SPACE.S_OK,					get_severity_style('ok',false));
		$uncn	= new CCol($uncn.SPACE.S_UNKNOWN, 'unknown');
		$uncl	= new CCol($uncl.SPACE.S_NOT_CLASSIFIED,	get_severity_style(TRIGGER_SEVERITY_NOT_CLASSIFIED,$uncl));
		$info	= new CCol($info.SPACE.S_INFORMATION,		get_severity_style(TRIGGER_SEVERITY_INFORMATION,$info));
		$warn	= new CCol($warn.SPACE.S_WARNING,			get_severity_style(TRIGGER_SEVERITY_WARNING,$warn));
		$avg	= new CCol($avg.SPACE.S_AVERAGE,			get_severity_style(TRIGGER_SEVERITY_AVERAGE,$avg));
		$high	= new CCol($high.SPACE.S_HIGH,				get_severity_style(TRIGGER_SEVERITY_HIGH,$high));
		$dis	= new CCol($dis.SPACE.S_DISASTER,			get_severity_style(TRIGGER_SEVERITY_DISASTER,$dis));


		if(STYLE_HORISONTAL == $this->style){
			$this->addRow(array($trok, $uncn, $uncl, $info, $warn, $avg, $high, $dis));
		}
		else{
			$this->addRow($trok);
			$this->addRow($uncn);
			$this->addRow($uncl);
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
