<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Overview');
$page['file'] = 'overview.php';
$page['hist_arg'] = array('groupid', 'type');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS',0);
define('SHOW_DATA',1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
	'view_style' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
	'type' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
);
check_fields($fields);


// js templates
require_once dirname(__FILE__).'/include/views/js/general.script.confirm.js.php';

$_REQUEST['view_style'] = get_request('view_style', CProfile::get('web.overview.view.style', STYLE_TOP));
CProfile::update('web.overview.view.style', $_REQUEST['view_style'], PROFILE_TYPE_INT);

$_REQUEST['type'] = get_request('type', CProfile::get('web.overview.type', SHOW_TRIGGERS));
CProfile::update('web.overview.type', $_REQUEST['type'], PROFILE_TYPE_INT);

$options = array(
	'groups' => array(
		'monitored_hosts' => 1,
		'with_monitored_items' => 1,
		'with_monitored_triggers' => ($_REQUEST['type'] == SHOW_TRIGGERS) ? 1 : null,
	),
	'hosts' => array(
		'monitored_hosts' => 1,
		'with_monitored_items' => 1,
		'with_monitored_triggers' => ($_REQUEST['type'] == SHOW_TRIGGERS) ? 1 : null,
	),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;


$form = new CForm('get');
$form->addItem(array(_('Group'), $pageFilter->getGroupsCB(true)));

$cmbType = new CComboBox('type', $_REQUEST['type'], 'submit()');
$cmbType->addItem(SHOW_TRIGGERS, _('Triggers'));
$cmbType->addItem(SHOW_DATA, _('Data'));
$form->addItem(array(_('Type'), $cmbType));

$help_table = new CTableInfo();
$help_table->setAttribute('style', 'width: 200px');

if ($_REQUEST['type'] == SHOW_TRIGGERS) {
	$help_table->addRow(array(new CCol(SPACE, 'normal'), _('Disabled')));
}

for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
	$help_table->addRow(array(getSeverityCell($i), _('Enabled')));
}

$help_table->addRow(array(
	new CCol(SPACE, 'trigger_unknown'),
	_('Unknown')
));

if ($_REQUEST['type'] == SHOW_TRIGGERS) {
	// blinking preview in help popup (only if blinking is enabled)
	$config = select_config();
	if ($config['blink_period'] > 0) {
		$col = new CCol(SPACE, 'not_classified');
		$col->setAttribute('style', 'background-image: url(images/gradients/blink.gif); '.
				'background-position: top left; background-repeat: repeat;');
		$help_table->addRow(array($col, _s("Age less than %s", convertUnitsS($config['blink_period']))));
	}

	$help_table->addRow(array(new CCol(SPACE), _('No trigger')));
}
else {
	$help_table->addRow(array(new CCol(SPACE), _('Disabled or no trigger')));
}

$help = new CHelp('web.view.php', 'right');
$help->setHint($help_table, '', '', true, false, true);

$over_wdgt = new CWidget();
// Header
$over_wdgt->addPageHeader(_('OVERVIEW'), array(
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])),
	SPACE,
	$help
));

// 2nd header
$form_l = new CForm('get');
$form_l->addVar('groupid', $_REQUEST['groupid']);

$cmbStyle = new CComboBox('view_style', $_REQUEST['view_style'], 'submit()');
$cmbStyle->addItem(STYLE_TOP, _('Top'));
$cmbStyle->addItem(STYLE_LEFT, _('Left'));

$form_l->additem(array(_('Hosts location'), $cmbStyle));

$over_wdgt->addHeader(_('Overview'), $form);
$over_wdgt->addHeader($form_l);


if ($_REQUEST['type'] == SHOW_DATA) {
	$table = get_items_data_overview(array_keys($pageFilter->hosts), $_REQUEST['view_style']);
}
elseif ($_REQUEST['type'] == SHOW_TRIGGERS) {
	$table = get_triggers_overview(array_keys($pageFilter->hosts), $_REQUEST['view_style']);
}

$over_wdgt->addItem($table);
$over_wdgt->show();


require_once dirname(__FILE__).'/include/page_footer.php';
