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
class CServerInfo extends CTable{
	public function __construct(){
		parent::__construct(NULL,'server_info');
	}

	public function bodyToString(){
		global $USER_DETAILS;

		$this->cleanItems();

		$status = get_status();

		if($status['zabbix_server'] == S_YES)
			$server = new CSpan(S_RUNNING,'off');
		else
			$server = new CSpan(S_NOT_RUNNING,'on');

		$header = new CCol('Zabbix '.S_SERVER_INFO,'header');
		$this->addRow($header);
		$this->addRow('Updated: '.date('r',time()));
		$this->addRow(new CCol(array('Refreshed every: '.$USER_DETAILS['refresh'].' sec ',
				'(',new CLink('refresh now','http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']),')')));
		$this->addRow(S_NUMBER_OF_USERS_SHORT.': '.$status['users_count'].'('.$status['users_online'].')');
		$this->addRow(new CCol(array('Logged in as ', new CLink($USER_DETAILS['alias'],'profile.php'))));
		$this->addRow(new CCol(array(new CLink('Zabbix server','report1.php'),' is ',$server)),'status');
		//$this->addRow(S_VALUES_STORED.': '.$status['history_count']);
		//$this->addRow(S_TRENDS_STORED.': '.$status['trends_count']);
		$this->addRow(new CCol(array(S_NUMBER_OF_HOSTS_SHORT.': '.$status['hosts_count'].'(',
			new CSpan($status['hosts_count_monitored'],'off'),'/',
			new CSpan($status['hosts_count_not_monitored'],'on'),'/',
			new CSpan($status['hosts_count_template'],'unknown'),')')));
		$this->addRow(new CCol(array(S_NUMBER_OF_ITEMS_SHORT.': '.$status['items_count'].'(',
			new CSpan($status['items_count_monitored'],'off'),'/',
			new CSpan($status['items_count_disabled'],'on'),'/',
			new CSpan($status['items_count_not_supported'],'unknown'),')')));
		$this->addRow(new CCol(array(S_NUMBER_OF_TRIGGERS_SHORT.': '.	$status['triggers_count'].
			'('.$status['triggers_count_enabled'].'/'.$status['triggers_count_disabled'].')'.'[',
			new CSpan($status['triggers_count_on'],'on'),'/',
			new CSpan($status['triggers_count_unknown'],'unknown'),'/',
			new CSpan($status['triggers_count_off'],'off'),']'
			)));

//			$this->addRow(S_NUMBER_OF_EVENTS.': '.$status['events_count']);
//			$this->addRow(S_NUMBER_OF_ALERTS.': '.$status['alerts_count']);

	return parent::bodyToString();
	}
}

?>
