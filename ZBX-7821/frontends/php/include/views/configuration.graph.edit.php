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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$graphWidget = new CWidget();

if (!empty($this->data['parent_discoveryid'])) {
	$graphWidget->setTitle(_('Graph prototypes'))->
		addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	$graphWidget->setTitle(_('Graphs'))->
		addItem(get_header_host_table('graphs', $this->data['hostid']));
}

// create form
$graphForm = new CForm();
$graphForm->setName('graphForm');
$graphForm->addVar('form', $this->data['form']);
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
	$graphFormList->addRow(_('Parent graphs'), $this->data['templates']);
}
$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->setAttribute('autofocus', 'autofocus');
$graphFormList->addRow(_('Name'), $nameTextBox);
$graphFormList->addRow(_('Width'), new CNumericBox('width', $this->data['width'], 5));
$graphFormList->addRow(_('Height'), new CNumericBox('height', $this->data['height'], 5));

$graphFormList->addRow(_('Graph type'), new CComboBox('graphtype', $this->data['graphtype'], 'submit()', graphType()));

// append legend to form list
$graphFormList->addRow(_('Show legend'), new CCheckBox('show_legend', $this->data['show_legend'], null, 1));

// append graph types to form list
if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED) {
	$graphFormList->addRow(_('Show working time'), new CCheckBox('show_work_period', $this->data['show_work_period'], null, 1));
	$graphFormList->addRow(_('Show triggers'), new CCheckBox('show_triggers', $this->data['show_triggers'], null, 1));

	if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL) {
		// percent left
		$percentLeftTextBox = new CTextBox('percent_left', $this->data['percent_left'], 6, false, 7);
		$percentLeftCheckbox = new CCheckBox('visible[percent_left]', 1, 'javascript: showHideVisible("percent_left");', 1);

		if(isset($this->data['visible']) && isset($this->data['visible']['percent_left'])) {
			$percentLeftTextBox->setAttribute('style', '');
			$percentLeftCheckbox->setChecked(1);
		}
		elseif ($this->data['percent_left'] == 0) {
			$percentLeftTextBox->setAttribute('style', 'visibility: hidden;');
			$percentLeftCheckbox->setChecked(0);
		}

		$graphFormList->addRow(_('Percentile line (left)'), [$percentLeftCheckbox, SPACE, $percentLeftTextBox]);

		// percent right
		$percentRightTextBox = new CTextBox('percent_right', $this->data['percent_right'], 6, false, 7);
		$percentRightCheckbox = new CCheckBox('visible[percent_right]', 1, 'javascript: showHideVisible("percent_right");', 1);

		if(isset($this->data['visible']) && isset($this->data['visible']['percent_right'])) {
			$percentRightTextBox->setAttribute('style', '');
			$percentRightCheckbox->setChecked(1);
		}
		elseif ($this->data['percent_right'] == 0) {
			$percentRightTextBox->setAttribute('style', 'visibility: hidden;');
			$percentRightCheckbox->setChecked(0);
		}


		$graphFormList->addRow(_('Percentile line (right)'), [$percentRightCheckbox, SPACE, $percentRightTextBox]);
	}

	$yaxisMinData = [];
	$yaxisMinData[] = new CComboBox('ymin_type', $this->data['ymin_type'], null, [
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]);

	if ($this->data['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMinData[] = new CTextBox('yaxismin', $this->data['yaxismin'], 7);
	}
	elseif ($this->data['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
		$graphForm->addVar('yaxismin', $this->data['yaxismin']);

		$ymin_name = '';
		if (!empty($this->data['ymin_itemid'])) {
			$min_host = get_host_by_itemid($this->data['ymin_itemid']);

			$minItems = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($this->data['ymin_itemid'])]);
			$minItem = reset($minItems);

			$ymin_name = $min_host['name'].NAME_DELIMITER.$minItem['name_expanded'];
		}

		$yaxisMinData[] = new CTextBox('ymin_name', $ymin_name, 36, true);
		$yaxisMinData[] = new CButton('yaxis_min', _('Select'), 'javascript: '.
			'return PopUp("popup.php?dstfrm='.$graphForm->getName().
				'&dstfld1=ymin_itemid'.
				'&dstfld2=ymin_name'.
				'&srctbl=items'.
				'&srcfld1=itemid'.
				'&srcfld2=name'.
				'&numeric=1'.
				'&writeonly=1" + getOnlyHostParam(), 0, 0, "zbx_popup_item");',
			'button-form'
		);

		// select prototype button
		if (!empty($this->data['parent_discoveryid'])) {
			$yaxisMinData[] = new CButton('yaxis_min_prototype', _('Select prototype'), 'javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&parent_discoveryid='.$this->data['parent_discoveryid'].
					'&dstfld1=ymin_itemid'.
					'&dstfld2=ymin_name'.
					'&srctbl=item_prototypes'.
					'&srcfld1=itemid'.
					'&srcfld2=name'.
					'&numeric=1", 0, 0, "zbx_popup_item");',
				'button-form'
			);
		}
	}
	else {
		$graphForm->addVar('yaxismin', $this->data['yaxismin']);
	}

	$graphFormList->addRow(_('Y axis MIN value'), $yaxisMinData);

	$yaxisMaxData = [];
	$yaxisMaxData[] = new CComboBox('ymax_type', $this->data['ymax_type'], null, [
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]);

	if ($this->data['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMaxData[] = new CTextBox('yaxismax', $this->data['yaxismax'], 7);
	}
	elseif ($this->data['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
		$graphForm->addVar('yaxismax', $this->data['yaxismax']);

		$ymax_name = '';
		if (!empty($this->data['ymax_itemid'])) {
			$max_host = get_host_by_itemid($this->data['ymax_itemid']);

			$maxItems = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($this->data['ymax_itemid'])]);
			$maxItem = reset($maxItems);

			$ymax_name = $max_host['name'].NAME_DELIMITER.$maxItem['name_expanded'];
		}

		$yaxisMaxData[] = new CTextBox('ymax_name', $ymax_name, 36, true);
		$yaxisMaxData[] = new CButton('yaxis_max', _('Select'), 'javascript: '.
			'return PopUp("popup.php?dstfrm='.$graphForm->getName().
				'&dstfld1=ymax_itemid'.
				'&dstfld2=ymax_name'.
				'&srctbl=items'.
				'&srcfld1=itemid'.
				'&srcfld2=name'.
				'&numeric=1'.
				'&writeonly=1" + getOnlyHostParam(), 0, 0, "zbx_popup_item");',
			'button-form'
		);

		// select prototype button
		if (!empty($this->data['parent_discoveryid'])) {
			$yaxisMaxData[] = new CButton('yaxis_max_prototype', _('Select prototype'), 'javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&parent_discoveryid='.$this->data['parent_discoveryid'].
					'&dstfld1=ymax_itemid'.
					'&dstfld2=ymax_name'.
					'&srctbl=item_prototypes'.
					'&srcfld1=itemid'.
					'&srcfld2=name'.
					'&numeric=1", 0, 0, "zbx_popup_item");',
				'button-form'
			);
		}
	}
	else {
		$graphForm->addVar('yaxismax', $this->data['yaxismax']);
	}

	$graphFormList->addRow(_('Y axis MAX value'), $yaxisMaxData);
}
else {
	$graphFormList->addRow(_('3D view'), new CCheckBox('show_3d', $this->data['show_3d'], null, 1));
}

// append items to form list
$itemsTable = (new CTable())->
	addClass('formElementTable');
$itemsTable->setAttribute('style', 'min-width: 700px;');
$itemsTable->setAttribute('id', 'itemsTable');
$itemsTable->setHeader([
	(new CCol(SPACE))->setWidth(15),
	(new CCol(SPACE))->setWidth(15),
	(new CCol(_('Name')))->setWidth(($this->data['graphtype'] == GRAPH_TYPE_NORMAL) ? 280 : 360),
	($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED)
		? (new CCol(_('Type')))->setWidth(80) : null,
	(new CCol(_('Function')))->setWidth(80),
	($this->data['graphtype'] == GRAPH_TYPE_NORMAL) ? (new CCol(_('Draw style')))->addClass(ZBX_STYLE_NOWRAP)->setWidth(80) : null,
	($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED)
		? (new CCol(_('Y axis side')))->addClass(ZBX_STYLE_NOWRAP)->setWidth(80) : null,
	(new CCol(_('Colour')))->setWidth(100),
	(new CCol(_('Action')))->setWidth(50)
]);

$addButton = new CButton('add_item', _('Add'),
	'return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
		($this->data['normal_only'] ? '&normal_only=1' : '').
		'&srctbl=items&srcfld1=itemid&srcfld2=name&numeric=1" + getOnlyHostParam());',
	'link_menu'
);

$addPrototypeButton = null;
if ($this->data['parent_discoveryid']) {
	$addPrototypeButton = new CButton('add_protoitem', _('Add prototype'),
		'return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
			url_param($this->data['graphtype'], false, 'graphtype').
			url_param('parent_discoveryid').
			($this->data['normal_only'] ? '&normal_only=1' : '').
			'&srctbl=item_prototypes&srcfld1=itemid&srcfld2=name&numeric=1");',
		'link_menu'
	);
}
$itemsTable->addRow(new CRow(
	(new CCol([$addButton, SPACE, SPACE, SPACE, $addPrototypeButton]))->setColSpan(8),
	null,
	'itemButtonsRow'
));

foreach ($this->data['items'] as $n => $item) {
	$name = $item['host'].NAME_DELIMITER.$item['name_expanded'];

	if (zbx_empty($item['drawtype'])) {
		$item['drawtype'] = 0;
	}

	if (zbx_empty($item['yaxisside'])) {
		$item['yaxisside'] = 0;
	}

	insert_js('loadItem('.$n.', '.CJs::encodeJson($item['gitemid']).', '.$this->data['graphid'].', '.$item['itemid'].', '.
		CJs::encodeJson($name).', '.$item['type'].', '.$item['calc_fnc'].', '.$item['drawtype'].', '.
		$item['yaxisside'].', \''.$item['color'].'\', '.$item['flags'].');',
		true
	);
}

$graphFormList->addRow(_('Items'), new CDiv($itemsTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append tabs to form
$graphTab = new CTabView();
if (!$this->data['form_refresh']) {
	$graphTab->setSelected(0);
}
$graphTab->addTab(
	'graphTab',
	empty($this->data['parent_discoveryid']) ? _('Graph') : _('Graph prototype'), $graphFormList
);

/*
 * Preview tab
 */
$chartImage = new CImg('chart3.php?period=3600');
$chartImage->preload();

$graphPreviewTable = (new CTable())->
	addClass('center')->
	addClass('maxwidth');
$graphPreviewTable->addRow(new CDiv($chartImage, null, 'previewChar'));
$graphTab->addTab('previewTab', _('Preview'), $graphPreviewTable);

// append buttons to form
if (!empty($this->data['graphid'])) {
	$updateButton = new CSubmit('update', _('Update'));
	$deleteButton = new CButtonDelete(
		$this->data['parent_discoveryid'] ? _('Delete graph prototype?') : _('Delete graph?'),
		url_params(['graphid', 'parent_discoveryid', 'hostid'])
	);

	if (!empty($this->data['templateid'])) {
		$updateButton->setEnabled(false);
		$deleteButton->setEnabled(false);
	}

	$graphTab->setFooter(makeFormFooter(
		$updateButton,
		[
			new CSubmit('clone', _('Clone')),
			$deleteButton,
			new CButtonCancel(url_param('parent_discoveryid'))
		]
	));
}
else {
	$graphTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('parent_discoveryid'))]
	));
}

// insert js (depended from some variables inside the file)
insert_show_color_picker_javascript();
require_once dirname(__FILE__).'/js/configuration.graph.edit.js.php';

$graphForm->addItem($graphTab);

// append form to widget
$graphWidget->addItem($graphForm);

return $graphWidget;
