<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$drules = API::DRule()->get(array(
	'output' => array('druleid', 'name'),
	'selectDHosts' => array('status'),
	'filter' => array('status' => DHOST_STATUS_ACTIVE)
));
CArrayHelper::sort($drules, array('name'));

foreach ($drules as &$drule) {
	$drule['up'] = 0;
	$drule['down'] = 0;

	foreach ($drule['dhosts'] as $dhost){
		if (DRULE_STATUS_DISABLED == $dhost['status']) {
			$drule['down']++;
		}
		else {
			$drule['up']++;
		}
	}
}
unset($drule);

$header = array(
	new CCol(_('Discovery rule')),
	new CCol(_x('Up', 'discovery results in dashboard')),
	new CCol(_x('Down', 'discovery results in dashboard'))
);

$table = new CTableInfo();
$table->setHeader($header, 'header');

foreach ($drules as $drule) {
	$table->addRow(array(
		new CLink($drule['name'], 'zabbix.php?action=discovery.view&druleid='.$drule['druleid']),
		new CSpan($drule['up'], 'green'),
		new CSpan($drule['down'], ($drule['down'] != 0) ? 'red' : 'green')
	));
}

$script = new CJsScript(get_js('jQuery("#'.WIDGET_DISCOVERY_STATUS.'_footer").html("'.
	_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
));

$widget = new CDiv(array($table, $script));
$widget->show();
