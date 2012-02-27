<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
require_once dirname(__FILE__).'/js/configuration.triggers.edit.js.php';

$triggersWidget = new CWidget();
$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGERS'));

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('massupdate', $this->data['massupdate']);
$triggersForm->addVar('go', $this->data['go']);
if ($this->data['parent_discoveryid']) {
	$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
foreach ($this->data['g_triggerid'] as $triggerid) {
	$triggersForm->addVar('g_triggerid['.$triggerid.']', $triggerid);
}

// create form list
$triggersFormList = new CFormList('triggersFormList');

// append severity to form list
$labelNotClassified = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_NOT_CLASSIFIED]), 'severity_'.TRIGGER_SEVERITY_NOT_CLASSIFIED, 'severity_label_'.TRIGGER_SEVERITY_NOT_CLASSIFIED);
$labelNotClassified->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');
$labelNotClassified->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');
$labelNotClassified->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');

$labelInformation = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_INFORMATION]), 'severity_'.TRIGGER_SEVERITY_INFORMATION, 'severity_label_'.TRIGGER_SEVERITY_INFORMATION);
$labelInformation->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_INFORMATION.');');
$labelInformation->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_INFORMATION.');');
$labelInformation->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_INFORMATION.');');

$labelWarning = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_WARNING]), 'severity_'.TRIGGER_SEVERITY_WARNING, 'severity_label_'.TRIGGER_SEVERITY_WARNING);
$labelWarning->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_WARNING.');');
$labelWarning->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_WARNING.');');
$labelWarning->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_WARNING.');');

$labelAverage = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_AVERAGE]), 'severity_'.TRIGGER_SEVERITY_AVERAGE, 'severity_label_'.TRIGGER_SEVERITY_AVERAGE);
$labelAverage->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_AVERAGE.');');
$labelAverage->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_AVERAGE.');');
$labelAverage->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_AVERAGE.');');

$labelHigh = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_HIGH]), 'severity_'.TRIGGER_SEVERITY_HIGH, 'severity_label_'.TRIGGER_SEVERITY_HIGH);
$labelHigh->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_HIGH.');');
$labelHigh->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_HIGH.');');
$labelHigh->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_HIGH.');');

$labelDisaster = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_DISASTER]), 'severity_'.TRIGGER_SEVERITY_DISASTER, 'severity_label_'.TRIGGER_SEVERITY_DISASTER);
$labelDisaster->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_DISASTER.');');
$labelDisaster->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_DISASTER.');');
$labelDisaster->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_DISASTER.');');

$severityDiv = new CDiv(
	array(
		new CRadioButton('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED, null, 'severity_'.TRIGGER_SEVERITY_NOT_CLASSIFIED, $this->data['priority'] == TRIGGER_SEVERITY_NOT_CLASSIFIED),
		$labelNotClassified,
		new CRadioButton('priority', TRIGGER_SEVERITY_INFORMATION, null, 'severity_'.TRIGGER_SEVERITY_INFORMATION, $this->data['priority'] == TRIGGER_SEVERITY_INFORMATION),
		$labelInformation,
		new CRadioButton('priority', TRIGGER_SEVERITY_WARNING, null, 'severity_'.TRIGGER_SEVERITY_WARNING, $this->data['priority'] == TRIGGER_SEVERITY_WARNING),
		$labelWarning,
		new CRadioButton('priority', TRIGGER_SEVERITY_AVERAGE, null, 'severity_'.TRIGGER_SEVERITY_AVERAGE, $this->data['priority'] == TRIGGER_SEVERITY_AVERAGE),
		$labelAverage,
		new CRadioButton('priority', TRIGGER_SEVERITY_HIGH, null, 'severity_'.TRIGGER_SEVERITY_HIGH, $this->data['priority'] == TRIGGER_SEVERITY_HIGH),
		$labelHigh,
		new CRadioButton('priority', TRIGGER_SEVERITY_DISASTER, null, 'severity_'.TRIGGER_SEVERITY_DISASTER, $this->data['priority'] == TRIGGER_SEVERITY_DISASTER),
		$labelDisaster
	)
);
$severityDiv->setAttribute('id', 'priority_div');

$triggersFormList->addRow(
	array(
		_('Severity'),
		SPACE,
		new CVisibilityBox('visible[priority]', !empty($this->data['visible']['priority']) ? 'yes' : 'no', 'priority_div', _('Original')),
	),
	$severityDiv
);

// append dependencies to form list
if (empty($this->data['parent_discoveryid'])) {
	$dependenciesTable = new CTable(_('No dependencies defined.'), 'formElementTable');
	$dependenciesTable->setAttribute('style', 'min-width: 500px;');
	$dependenciesTable->setAttribute('id', 'dependenciesTable');
	$dependenciesTable->setHeader(array(
		_('Name'),
		_('Action')
	));

	foreach ($this->data['dependencies'] as $dependency) {
		$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

		$row = new CRow(array(
			$dependency['host'].': '.$dependency['description'],
			new CButton('remove', _('Remove'), 'javascript: removeDependency(\''.$dependency['triggerid'].'\');', 'link_menu')
		));
		$row->setAttribute('id', 'dependency_'.$dependency['triggerid']);
		$dependenciesTable->addRow($row);
	}

	$dependenciesDiv = new CDiv(
		array(
			$dependenciesTable,
			new CButton('btn1', _('Add'),
				'return PopUp(\'popup.php?dstfrm=massupdate&dstact=add_dependency&reference=deptrigger'.
				'&dstfld1=new_dependency[]&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1'.
				'\', 1000, 700);',
				'link_menu'
			)
		),
		'objectgroup inlineblock border_dotted ui-corner-all'
	);
	$dependenciesDiv->setAttribute('id', 'dependencies_div');

	$triggersFormList->addRow(
		array(
			_('Dependencies'),
			SPACE,
			new CVisibilityBox('visible[dependencies]', !empty($this->data['visible']['dependencies']) ? 'yes' : 'no', 'dependencies_div', _('Original'))
		),
		$dependenciesDiv
	);
}

// append tabs to form
$triggersTab = new CTabView();
$triggersTab->addTab('triggersTab', _('Triggers massupdate'), $triggersFormList);
$triggersForm->addItem($triggersTab);

// append buttons to form
$triggersForm->addItem(makeFormFooter(
	array(new CSubmit('mass_save', _('Save'))),
	array(new CButtonCancel(url_param('groupid').url_param('parent_discoveryid')))
));

$triggersWidget->addItem($triggersForm);
return $triggersWidget;
?>
