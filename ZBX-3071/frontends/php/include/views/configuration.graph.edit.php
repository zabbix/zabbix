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


$graphWidget = new CWidget();

if (!empty($this->data['parent_discoveryid'])) {
	$graphWidget->addPageHeader(_('CONFIGURATION OF GRAPH PROTOTYPES'));
	$graphWidget->addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	$graphWidget->addPageHeader(_('CONFIGURATION OF GRAPHS'));
	$graphWidget->addItem(get_header_host_table('graphs', $this->data['hostid']));
}

// create form
$graphForm = new CForm();
$graphForm->setName('graphForm');
$graphForm->addVar('form', $this->data['form']);
$graphForm->addVar('form_refresh', $this->data['form_refresh']);
$graphForm->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
if (!empty($this->data['graphid'])) {
	$graphForm->addVar('graphid', $this->data['graphid']);
}
$graphForm->addVar('ymin_itemid', $this->data['ymin_itemid']);
$graphForm->addVar('ymax_itemid', $this->data['ymax_itemid']);

// create form list
$graphFormList = new CFormList('graphFormList');
if (!empty($this->data['templates'])) {
	$graphFormList->addRow(_('Parent graph'), $this->data['templates']);
}
$graphFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
$graphFormList->addRow(_('Width'), new CNumericBox('width', $this->data['width'], 5));
$graphFormList->addRow(_('Height'), new CNumericBox('height', $this->data['height'], 5));

$graphTypeComboBox = new CComboBox('graphtype', $this->data['graphtype'], 'submit()');
$graphTypeComboBox->addItems(graphType());
$graphFormList->addRow(_('Graph type'), $graphTypeComboBox);

// append items to form list
$itemsTable = new CTable(_('No items defined.'), 'formElementTable');
$itemsTable->attr('style', 'min-width: 700px;');
$itemsTable->attr('id', 'itemsTable');
$itemsTable->setHeader(array(
	new CCol(SPACE, null, null, 15),
	new CCol(SPACE, null, null, 15),
	new CCol(_('Name'), null, null, ($this->data['graphtype'] != GRAPH_TYPE_NORMAL) ? 360 : 280),
	($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED)
		? new CCol(_('Type'), null, null, 80) : null,
	new CCol(_('Function'), null, null, 80),
	($this->data['graphtype'] == GRAPH_TYPE_NORMAL) ? new CCol(_('Draw style'), 'nowrap', null, 80) : null,
	($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED)
		? new CCol(_('Y axis side'), 'nowrap', null, 80) : null,
	new CCol(_('Colour'), null, null, 100),
	new CCol(_('Action'), null, null, 50)
));

if (!empty($this->data['items'])) {
	foreach ($this->data['items'] as $number => $item) {
		if (!empty($item['itemid'])) {
			$host = get_host_by_itemid($item['itemid']);
			$name = $host['name'].': '.itemName(get_item_by_itemid($item['itemid']));
		}
		else {
			$name = _('Select');
		}

		if (empty($item['drawtype'])) {
			$item['drawtype'] = 0;
		}

		if (empty($item['yaxisside'])) {
			$item['yaxisside'] = 0;
		}

		insert_js('loadItem('.$number.', '.$item['gitemid'].', '.$this->data['graphid'].', '.$item['itemid'].', '.
			CJs::encodeJson($name).', '.$item['type'].', '.$item['calc_fnc'].', '.$item['drawtype'].', '.
			$item['yaxisside'].', \''.$item['color'].'\', '.$item['flags'].');',
			true
		);
	}
}

$addButton = new CButton('add_item', _('Add'),
	'return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
		(!empty($this->data['normal_only']) ? '&normal_only=1' : '').
		'&srctbl=items&srcfld1=itemid&srcfld2=name" + getOnlyHostParam(), 800, 600);',
	'link_menu'
);

$addPrototypeButton = null;
if (!empty($this->data['parent_discoveryid'])) {
	$addPrototypeButton = new CButton('add_protoitem', _('Add prototype'),
		'return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
			url_param($this->data['graphtype'], false, 'graphtype').
			url_param('parent_discoveryid').
			(!empty($this->data['normal_only']) ? '&normal_only=1' : '').
			'&srctbl=prototypes&srcfld1=itemid&srcfld2=name", 800, 600);',
		'link_menu'
	);
}
$itemsTable->addRow(new CRow(new CCol(array($addButton, SPACE, SPACE, SPACE, $addPrototypeButton), null, 8), null, 'itemButtonsRow'));

// append legend to form list
$graphFormList->addRow(_('Show legend'), new CCheckBox('legend', $this->data['legend'], null, 1));

// append graph types to form list
if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED) {
	$graphFormList->addRow(_('Show working time'), new CCheckBox('showworkperiod', $this->data['showworkperiod'], null, 1));
	$graphFormList->addRow(_('Show triggers'), new CCheckBox('showtriggers', $this->data['showtriggers'], null, 1));

	if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL) {
		if (is_numeric($this->data['percent_left'])) {
			$this->data['percent_left'] = sprintf('%2.2f', $this->data['percent_left']);
		}
		$percentLeftTextBox = new CTextBox('percent_left', $this->data['percent_left'], 5, 'no', 6);
		$percentLeftCheckbox = new CCheckBox('visible[percent_left]', 1, 'javascript: showHideVisible("percent_left");', 1);

		if (strcmp($this->data['percent_left'], '0.00') == 0) {
			$percentLeftTextBox->attr('style', 'visibility: hidden;');
			$percentLeftCheckbox->setChecked(0);
		}
		$graphFormList->addRow(_('Percentile line (left)'), array($percentLeftCheckbox, SPACE, $percentLeftTextBox));

		if (is_numeric($this->data['percent_right'])) {
			$this->data['percent_right'] = sprintf('%2.2f', $this->data['percent_right']);
		}
		$percentRightTextBox = new CTextBox('percent_right', $this->data['percent_right'], 5, 'no', 6);
		$percentRightCheckbox = new CCheckBox('visible[percent_right]', 1, 'javascript: showHideVisible("percent_right");', 1);

		if (strcmp($this->data['percent_right'], '0.00') == 0) {
			$percentRightTextBox->attr('style', 'visibility: hidden;');
			$percentRightCheckbox->setChecked(0);
		}
		$graphFormList->addRow(_('Percentile line (right)'), array($percentRightCheckbox, SPACE, $percentRightTextBox));
	}

	$yaxisMinData = array();

	$yTypeComboBox = new CComboBox('ymin_type', $this->data['ymin_type'], 'submit();');
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_CALCULATED, _('Calculated'));
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_FIXED, _('Fixed'));
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE, _('Item'));

	$yaxisMinData[] = $yTypeComboBox;

	if ($this->data['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMinData[] = new CTextBox('yaxismin', $this->data['yaxismin'], 7);
	}
	elseif ($this->data['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
		$graphForm->addVar('yaxismin', $this->data['yaxismin']);

		if (!empty($this->data['items'])) {
			$ymin_name = '';
			if (!empty($this->data['ymin_itemid'])) {
				$min_host = get_host_by_itemid($this->data['ymin_itemid']);
				$min_item = get_item_by_itemid($this->data['ymin_itemid']);
				$ymin_name = $min_host['name'].': '.itemName($min_item);
			}

			$yaxisMinData[] = new CTextBox('ymin_name', $ymin_name, 36, 'yes');
			$yaxisMinData[] = new CButton('yaxis_min', _('Select'), 'javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&dstfld1=ymin_itemid'.
					'&dstfld2=ymin_name'.
					'&srctbl=items'.
					'&srcfld1=itemid'.
					'&srcfld2=name" + getOnlyHostParam(), 0, 0, "zbx_popup_item");',
				'formlist'
			);

			// select prototype button
			if (!empty($this->data['parent_discoveryid'])) {
				$yaxisMinData[] = new CButton('yaxis_min', _('Select prototype'), 'javascript: '.
					'return PopUp("popup.php?dstfrm='.$graphForm->getName().
						'&parent_discoveryid='.$this->data['parent_discoveryid'].
						'&dstfld1=ymin_itemid'.
						'&dstfld2=ymin_name'.
						'&srctbl=prototypes'.
						'&srcfld1=itemid'.
						'&srcfld2=name", 0, 0, "zbx_popup_item");',
					'formlist'
				);
			}
		}
		else {
			$yaxisMinData[] = SPACE;
			$yaxisMinData[] = new CSpan(_('Add graph items first'), 'red');
		}
	}
	else {
		$graphForm->addVar('yaxismin', $this->data['yaxismin']);
	}

	$graphFormList->addRow(_('Y axis MIN value'), $yaxisMinData);

	$yaxisMaxData = array();
	$yTypeComboBox = new CComboBox('ymax_type', $this->data['ymax_type'], 'submit()');
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_CALCULATED, _('Calculated'));
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_FIXED, _('Fixed'));
	$yTypeComboBox->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE, _('Item'));

	$yaxisMaxData[] = $yTypeComboBox;

	if ($this->data['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMaxData[] = new CTextBox('yaxismax', $this->data['yaxismax'], 7);
	}
	elseif ($this->data['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
		$graphForm->addVar('yaxismax', $this->data['yaxismax']);

		$ymax_name = '';
		if (!empty($this->data['ymax_itemid'])) {
			$max_host = get_host_by_itemid($this->data['ymax_itemid']);
			$max_item = get_item_by_itemid($this->data['ymax_itemid']);
			$ymax_name = $max_host['name'].': '.itemName($max_item);
		}

		if (!empty($this->data['items'])) {
			$yaxisMaxData[] = new CTextBox('ymax_name', $ymax_name, 36, 'yes');
			$yaxisMaxData[] = new CButton('yaxis_max', _('Select'), 'javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&dstfld1=ymax_itemid'.
					'&dstfld2=ymax_name'.
					'&srctbl=items'.
					'&srcfld1=itemid'.
					'&srcfld2=name" + getOnlyHostParam(), 0, 0, "zbx_popup_item");',
				'formlist'
			);

			// select prototype button
			if (!empty($this->data['parent_discoveryid'])) {
				$yaxisMaxData[] = new CButton('yaxis_min', _('Select prototype'), 'javascript: '.
					'return PopUp("popup.php?dstfrm='.$graphForm->getName().
						'&parent_discoveryid='.$this->data['parent_discoveryid'].
						'&dstfld1=ymax_itemid'.
						'&dstfld2=ymax_name'.
						'&srctbl=prototypes'.
						'&srcfld1=itemid'.
						'&srcfld2=name", 0, 0, "zbx_popup_item");',
					'formlist'
				);
			}
		}
		else {
			$yaxisMaxData[] = SPACE;
			$yaxisMaxData[] = new CSpan(_('Add graph items first'), 'red');
		}
	}
	else {
		$graphForm->addVar('yaxismax', $this->data['yaxismax']);
	}

	$graphFormList->addRow(_('Y axis MAX value'), $yaxisMaxData);
}
else {
	$graphFormList->addRow(_('3D view'), new CCheckBox('graph3d', $this->data['graph3d'], null, 1));
}

$graphFormList->addRow(_('Items'), new CDiv($itemsTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append tabs to form
$graphTab = new CTabView(array('remember' => true));
if (!$this->data['form_refresh']) {
	$graphTab->setSelected(0);
}
$graphTab->addTab('graphTab', _('Graph'), $graphFormList);

/*
 * Preview tab
 */
$chartImage = new CImg('chart3.php?period=3600');
$chartImage->preload();

$graphPreviewTable = new CTable(null, 'graph');
$graphPreviewTable->addRow(new CDiv($chartImage, null, 'previewChar'));
$graphTab->addTab('previewTab', _('Preview'), $graphPreviewTable);
$graphForm->addItem($graphTab);

// append buttons to form
$saveButton = new CSubmit('save', _('Save'));
$cancelButton = new CButtonCancel(url_param('parent_discoveryid'));
if (!empty($this->data['graphid'])) {
	$deleteButton = new CButtonDelete(_('Delete graph?'), url_param('graphid').url_param('parent_discoveryid'));
	$cloneButton = new CSubmit('clone', _('Clone'));

	if (!empty($this->data['templateid'])) {
		$saveButton->setEnabled(false);
		$deleteButton->setEnabled(false);
		$cloneButton->setEnabled(false);
	}

	$graphForm->addItem(makeFormFooter($saveButton, array($cloneButton, $deleteButton, $cancelButton)));
}
else {
	$graphForm->addItem(makeFormFooter($saveButton, $cancelButton));
}

// insert js (depended from some variables inside the file)
insert_show_color_picker_javascript();
require_once dirname(__FILE__).'/js/configuration.graph.edit.js.php';

// append form to widget
$graphWidget->addItem($graphForm);

return $graphWidget;
