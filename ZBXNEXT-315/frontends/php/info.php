<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = 'S_ZABBIX_INFO';
	$page['file'] = 'info.php';
	$page['hist_arg'] = array();

include_once('include/page_header.php');
?>
<?php

	function get_zabbix_info(){
		global $DB;

		$zabbix = array(
			S_ZABBIX_VERSION => ZABBIX_VERSION,
			S_ZABBIX_API_VERSION => ZABBIX_API_VERSION,
		);


		$php_extensions = get_loaded_extensions();
		foreach($php_extensions as $key => $ext){
			$version = phpversion($ext);
			if($version){
				$php_extensions[$key] = $ext.'('.$version.')';
			}
		}

		$php = array(
			S_PHP_VERSION => PHP_VERSION,
			S_SYSTEM => PHP_OS,
			S_SAPI => PHP_SAPI,
			S_INT_MAX => PHP_INT_MAX,
			S_INT_SIZE => PHP_INT_SIZE,
			S_EXTENSIONS => implode(', ', $php_extensions),
		);


		switch($DB['TYPE']){
			case 'MYSQL':
				$sql = 'SHOW VARIABLES LIKE '.zbx_dbstr('version');
				$db_version = DBfetch(DBselect($sql));
				$db_version = $db_version['Value'];
				break;
			case 'POSTGRESQL':
				$sql = 'SELECT version();';
				$db_version = DBfetch(DBselect($sql));
				$db_version = $db_version['version'];
				break;
			case 'ORACLE':
				$sql = 'SELECT * FROM v$version WHERE banner LIKE '.zbx_dbstr('Oracle%');
				$db_version = DBfetch(DBselect($sql));
				$db_version = $db_version['banner'];
				break;
		}
		$db_info = array(
			S_DATABASE => $DB['TYPE'],
			S_VERSION => $db_version,
		);

		return array(
			'zabbix' => $zabbix,
			'php' => $php,
			'db' => $db_info,
			'server' => array(),
		);
	}

	$info_wdgt = new CWidget();
	$info_wdgt->addPageHeader(S_ZABBIX_INFO);

	$data = get_zabbix_info();

	$zabbix_table = new CTable(null, 'zabbixinfo');
	$zabbix_table->setCaption(S_ZABBIX_INFO);
	foreach($data['zabbix'] as $param => $value){
		$zabbix_table->addRow(array($param, $value));
	}
	$info_wdgt->addItem($zabbix_table);


	$php_table = new CTable(null, 'zabbixinfo');
	$php_table->setCaption(S_PHP_INFO);
	foreach($data['php'] as $param => $value){
		$php_table->addRow(array($param, $value));
	}
	$info_wdgt->addItem($php_table);


	$db_table = new CTable(null, 'zabbixinfo');
	$db_table->setCaption(S_DATABASE_INFO);
	foreach($data['db'] as $param => $value){
		$db_table->addRow(array($param, $value));
	}
	$info_wdgt->addItem($db_table);


	$info_wdgt->show();

	include_once('include/page_footer.php');
?>
