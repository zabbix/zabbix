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
require_once('include/config.inc.php');
require_once('include/discovery.inc.php');

$page['hist_arg'] = array('druleid');
$page['file'] = 'discovery.php';
$page['title'] = 'S_STATUS_OF_DISCOVERY';

include_once('include/page_header.php');
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'druleid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('ip',ZBX_SORT_UP);

?>
<?php
/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.discovery.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

	$dscvry_wdgt = new CWidget('hat_discovery');

// HEADER
	$r_form = new CForm();
	$r_form->setMethod('get');

	$druleid = get_request('druleid', 0);
	$fullscreen = get_request('fullscreen', 0);

	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$dscvry_wdgt->addPageHeader(S_STATUS_OF_DISCOVERY_BIG, $fs_icon);

// 2nd header
	$cmbDRules = new CComboBox('druleid',$druleid,'submit()');
	$cmbDRules->addItem(0,S_ALL_SMALL);

	$options = array(
		'filter' => array(
			'status' => DRULE_STATUS_ACTIVE
		),
		'output' => API_OUTPUT_EXTEND
	);
	$drules = CDRule::get($options);

	order_result($drules, 'name');
	foreach($drules as $dnum => $drule){
		$cmbDRules->addItem(
			$drule['druleid'],
			get_node_name_by_elid($drule['druleid'], null, ': ').$drule['name']
		);
	}
	$r_form->addVar('fullscreen', $fullscreen);
	$r_form->addItem(array(S_DISCOVERY_RULE.SPACE,$cmbDRules));

//	$dscvry_wdgt->addHeader(array(S_FOUND.': ',$numrows), $r_form);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$dscvry_wdgt->addHeader(S_DISCOVERY_RULES_BIG, $r_form);
//	$dscvry_wdgt->addHeader($numrows);
//-------------


	$sortfield = getPageSortField('ip');
	$sortorder = getPageSortOrder();

	$options = array(
		'selectHosts' => array('hostid', 'host', 'status'),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'limitSelects' => 1
	);

	if($druleid>0) $options['druleids'] = $druleid;
	else $options['druleids'] = zbx_objectValues($drules,'druleid');

	$dservices = CDService::get($options);
	$gMacros = CUserMacro::get(array(
		'output' => API_OUTPUT_EXTEND,
		'globalmacro' => 1
	));
	$gMacros = zbx_toHash($gMacros, 'macro');

	$services = array();
	foreach($dservices as $dsnum => $dservice){
		$key_ = $dservice['key_'];
		if(!zbx_empty($key_)){
			if(isset($gMacros[$key_])) $key_ = $gMacros[$key_]['value'];
			$key_ = ': '.$key_;
		}

		$service_name = discovery_check_type2str($dservice['type']).
				discovery_port2str($dservice['type'], $dservice['port']).
				$key_;

		$services[$service_name] = 1;
	}
	ksort($services);

	$header = array(
		is_show_all_nodes() ? new CCol(S_NODE, 'left') : null,
		make_sorting_header(S_DISCOVERED_DEVICE,'ip'),
		new CCol(S_MONITORED_HOST, 'left'),
		new CCol(array(S_UPTIME.'/',S_DOWNTIME),'left')
	);

	$css = getUserTheme($USER_DETAILS);
	$vTextColor = ($css == 'css_od.css')?'&color=white':'';
	foreach($services as $name => $foo) {
		$header[] = new CImg('vtext.php?text='.urlencode($name).$vTextColor);
	}

	$table = new CTableInfo();
	$table->setHeader($header,'vertical_header');

	$options = array(
		'filter' => array(
			'status' => DRULE_STATUS_ACTIVE
		),
		'selectDHosts' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	);
	if($druleid>0) $options['druleids'] = $druleid;

	$drules = CDRule::get($options);
	order_result($drules, 'name');

	$options = array(
		'druleids' => zbx_objectValues($drules, 'druleid'),
		'selectDServices' => API_OUTPUT_REFER,
		'output' => API_OUTPUT_REFER
	);
	$db_dhosts = CDHost::get($options);
	$db_dhosts = zbx_toHash($db_dhosts, 'dhostid');

	$db_dservices = zbx_toHash($dservices, 'dserviceid');

//SDII($db_dservices);
	foreach($drules as $dnum => $drule){
		$discovery_info = array();

		$dhosts = $drule['dhosts'];
		foreach($dhosts as $dhnum => $dhost){
			if(DHOST_STATUS_DISABLED == $dhost['status']){
				$hclass = 'disabled';
				$htime = $dhost['lastdown'];
			}
			else{
				$hclass = 'enabled';
				$htime = $dhost['lastup'];
			}

// $primary_ip stores the primary host ip of the dhost
			if(isset($primary_ip)) unset($primary_ip);

			$dservices = $db_dhosts[$dhost['dhostid']]['dservices'];
			foreach($dservices as $snum => $dservice){
				$dservice = $db_dservices[$dservice['dserviceid']];

				$hostName = '';

				$host = reset($db_dservices[$dservice['dserviceid']]['hosts']);
				if(!is_null($host)) $hostName = $host['host'];

				if(isset($primary_ip)){
					if($primary_ip === $dservice['ip']) $htype = 'primary';
					else $htype = 'slave';
				}
				else{
					$primary_ip = $dservice['ip'];
					$htype = 'primary';
				}

				if(!isset($discovery_info[$dservice['ip']])){
					$discovery_info[$dservice['ip']] = array(
						'ip' => $dservice['ip'],
						'type' => $htype,
						'class' => $hclass,
						'host' => $hostName,
						'time' => $htime,
						'druleid' => $dhost['druleid']
					);
				}

				$class = 'active';
				$time = 'lastup';
				if(DSVC_STATUS_DISABLED == $dservice['status']){
					$class = 'inactive';
					$time = 'lastdown';
				}

				$key_ = $dservice['key_'];
				if(!zbx_empty($key_)){
					if(isset($gMacros[$key_])) $key_ = $gMacros[$key_]['value'];
					$key_ = ': '.$key_;
				}

				$service_name = discovery_check_type2str($dservice['type']).
						discovery_port2str($dservice['type'], $dservice['port']).
						$key_;

				$discovery_info[$dservice['ip']]['services'][$service_name] = array('class' => $class, 'time' => $dservice[$time]);
			}
		}

		if($druleid == 0 && !empty($discovery_info)){
			$col = new CCol(array(bold($drule['name']),	SPACE.'('.count($discovery_info).SPACE.S_ITEMS.')'));
			$col->setColSpan(count($services) + 3);

			$table->addRow(array(get_node_name_by_elid($drule['druleid']),$col));
		}

		order_result($discovery_info, $_REQUEST['sort'], $_REQUEST['sortorder']);

		foreach($discovery_info as $ip => $h_data){
			$table_row = array(
				get_node_name_by_elid($h_data['druleid']),
				$h_data['type'] == 'primary' ? new CSpan($ip, $h_data['class']) : new CSpan(SPACE.SPACE.$ip),
				new CSpan(empty($h_data['host']) ? '-' : $h_data['host']),
				new CSpan((($h_data['time'] == 0 || $h_data['type'] === 'slave') ?
						'' : convert_units(time() - $h_data['time'], 'uptime')), $h_data['class'])
				);

			foreach($services as $name => $foo){
				$class = null;
				$time = SPACE;

				$hint = new CDiv(SPACE, $class);



				$hintTable = null;
				if(isset($h_data['services'][$name])){
					$class = $h_data['services'][$name]['class'];
					$time = $h_data['services'][$name]['time'];

					$hintTable = new CTableInfo();
					$hintTable->setAttribute('style','width: auto;');

					if ($class == 'active') {
						$hintTable->setHeader(S_UP_TIME);
					}
					else if ($class == 'inactive') {
						$hintTable->setHeader(S_DOWN_TIME);
					}

					$timeColumn = new CCol(zbx_date2age($h_data['services'][$name]['time']), $class);
					$hintTable->addRow($timeColumn);
					//$hint->setHint($hintTable);
				}

				$c = new CCol($hint, $class);
				if(!is_null($hintTable)){
					$c->setHint($hintTable);
				}
				$table_row[] = $c;
			}
			$table->addRow($table_row);
		}
	}

	$dscvry_wdgt->addItem($table);
	$dscvry_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
