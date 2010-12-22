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
class CHostsInfo extends CTable{
 public $style;
	public function __construct($groupid=0, $style = STYLE_HORISONTAL){
		$this->nodeid = id2nodeid($groupid);
		$this->groupid = $groupid;
		$this->style = null;

		parent::__construct(NULL,"hosts_info");
		$this->setOrientation($style);
	}

	public function setOrientation($value){
		if($value != STYLE_HORISONTAL && $value != STYLE_VERTICAL)
			return $this->error('Incorrect value for SetOrientation ['.$value.']');

		$this->style = $value;
	}

	public function bodyToString(){
		global $USER_DETAILS;
		$this->cleanItems();

		$total = 0;

		$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY,get_current_nodeid(true));

		$cond_from = '';
		if(remove_nodes_from_id($this->groupid)>0){
			$cond_from = ', hosts_groups hg ';
			$cond_where = 'AND hg.hostid=h.hostid AND hg.groupid='.$this->groupid;
		}
		else{
			$cond_where = ' AND '.DBin_node('h.hostid', $this->nodeid);
		}

		$db_host_cnt = DBselect('SELECT COUNT(DISTINCT h.hostid) as cnt '.
								' FROM hosts h'.$cond_from.
								' WHERE h.available='.HOST_AVAILABLE_TRUE.
									' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
									' AND '.DBcondition('h.hostid',$accessible_hosts).
									$cond_where);

		$host_cnt = DBfetch($db_host_cnt);
		$avail = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$db_host_cnt = DBselect('SELECT COUNT(DISTINCT h.hostid) as cnt '.
								' FROM hosts h'.$cond_from.
								' WHERE h.available='.HOST_AVAILABLE_FALSE.
									' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
									' AND '.DBcondition('h.hostid',$accessible_hosts).
									$cond_where);


		$host_cnt = DBfetch($db_host_cnt);
		$notav = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$db_host_cnt = DBselect('SELECT COUNT(DISTINCT h.hostid) as cnt '.
								' FROM hosts h'.$cond_from.
								' WHERE h.available='.HOST_AVAILABLE_UNKNOWN.
									' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
									' AND '.DBcondition('h.hostid',$accessible_hosts).
									$cond_where);


		$host_cnt = DBfetch($db_host_cnt);
		$uncn = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$node = get_node_by_nodeid($this->nodeid);
		$header_str = S_HOSTS_INFO.SPACE;


		if($node > 0) $header_str.= '('.$node['name'].')'.SPACE;



		if(remove_nodes_from_id($this->groupid)>0){
			$group = get_hostgroup_by_groupid($this->groupid);
			$header_str.= S_GROUP.SPACE.'&quot;'.$group['name'].'&quot;';
		}
		else{
			$header_str.= S_ALL_GROUPS;
		}

		$header = new CCol($header_str,"header");
		if($this->style == STYLE_HORISONTAL)
			$header->SetColspan(4);

		$this->addRow($header);

		$avail	= new CCol($avail.'  '.S_AVAILABLE,		'avail');
		$notav	= new CCol($notav.'  '.S_NOT_AVAILABLE,	'notav');
		$uncn	= new CCol($uncn.'  '.S_UNKNOWN,		'uncn');
		$total	= new CCol($total.'  '.S_TOTAL,			'total');

		if($this->style == STYLE_HORISONTAL){
			$this->addRow(array($avail, $notav, $uncn, $total));
		}
		else{
			$this->addRow($avail);
			$this->addRow($notav);
			$this->addRow($uncn);
			$this->addRow($total);
		}

	return parent::bodyToString();
	}
}
?>
