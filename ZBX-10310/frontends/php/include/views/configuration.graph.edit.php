<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$widget = new CWidget();

if (!empty($this->data['parent_discoveryid'])) {
	$widget->setTitle(_('Graph prototypes'))
		->addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	$widget->setTitle(_('Graphs'))
		->addItem(get_header_host_table('graphs', $this->data['hostid']));
}

// create form
$graphForm = (new CForm())
	->setName('graphForm')
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('ymin_itemid', $this->data['ymin_itemid'])
	->addVar('ymax_itemid', $this->data['ymax_itemid']);
if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
if (!empty($this->data['graphid'])) {
	$graphForm->addVar('graphid', $this->data['graphid']);
}

// create form list
$graphFormList = new CFormList('graphFormList');
$is_templated = (bool) $this->data['templates'];
if ($is_templated) {
	$graphFormList->addRow(_('Parent graphs'), $this->data['templates']);
}

$graphFormList
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['name'], $is_templated))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Width'),
		(new CNumericBox('width', $this->data['width'], 5, $is_templated))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Height'),
		(new CNumericBox('height', $this->data['height'], 5, $is_templated))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Graph type'),
		(new CComboBox('graphtype', $this->data['graphtype'], 'submit()', graphType()))->setEnabled(!$is_templated)
	)
	->addRow(_('Show legend'),
		(new CCheckBox('show_legend'))
			->setChecked($this->data['show_legend'] == 1)
			->setEnabled(!$is_templated)
	);

// append graph types to form list
if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED) {
	$graphFormList->addRow(_('Show working time'),
		(new CCheckBox('show_work_period'))
			->setChecked($this->data['show_work_period'] == 1)
			->setEnabled(!$is_templated)
	);
	$graphFormList->addRow(_('Show triggers'),
		(new CCheckbox('show_triggers'))
			->setchecked($this->data['show_triggers'] == 1)
			->setEnabled(!$is_templated)
	);

	if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL) {
		// percent left
		$percentLeftTextBox = (new CTextBox('percent_left', $this->data['percent_left'], $is_templated, 7))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
		$percentLeftCheckbox = (new CCheckBox('visible[percent_left]'))
			->setChecked(true)
			->onClick('javascript: showHideVisible("percent_left");')
			->setEnabled(!$is_templated);

		if(isset($this->data['visible']) && isset($this->data['visible']['percent_left'])) {
			$percentLeftCheckbox->setChecked(true);
		}
		elseif ($this->data['percent_left'] == 0) {
			$percentLeftTextBox->addStyle('visibility: hidden;');
			$percentLeftCheckbox->setChecked(false);
		}

		$graphFormList->addRow(_('Percentile line (left)'), [$percentLeftCheckbox, SPACE, $percentLeftTextBox]);

		// percent right
		$percentRightTextBox = (new CTextBox('percent_right', $this->data['percent_right'], $is_templated, 7))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
		$percentRightCheckbox = (new CCheckBox('visible[percent_right]'))
			->setChecked(true)
			->onClick('javascript: showHideVisible("percent_right");')
			->setEnabled(!$is_templated);

		if(isset($this->data['visible']) && isset($this->data['visible']['percent_right'])) {
			$percentRightCheckbox->setChecked(true);
		}
		elseif ($this->data['percent_right'] == 0) {
			$percentRightTextBox->addStyle('visibility: hidden;');
			$percentRightCheckbox->setChecked(false);
		}

		$graphFormList->addRow(_('Percentile line (right)'), [$percentRightCheckbox, SPACE, $percentRightTextBox]);
	}

	$yaxisMinData = [(new CComboBox('ymin_type', $this->data['ymin_type'], null, [
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]))->setEnabled(!$is_templated)];

	if ($this->data['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMinData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMinData[] = (new CTextBox('yaxismin', $this->data['yaxismin'], $is_templated))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
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

		$yaxisMinData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMinData[] = (new CTextBox('ymin_name', $ymin_name, $is_templated))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
		$yaxisMinData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMinData[] = (new CButton('yaxis_min', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&dstfld1=ymin_itemid'.
					'&dstfld2=ymin_name'.
					'&srctbl=items'.
					'&srcfld1=itemid'.
					'&srcfld2=name'.
					'&numeric=1'.
					'&writeonly=1" + getOnlyHostParam(), 0, 0, "zbx_popup_item");')
			->setEnabled(!$is_templated);

		// select prototype button
		if (!empty($this->data['parent_discoveryid'])) {
			$yaxisMinData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
			$yaxisMinData[] = (new CButton('yaxis_min_prototype', _('Select prototype')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: '.
					'return PopUp("popup.php?dstfrm='.$graphForm->getName().
						'&parent_discoveryid='.$this->data['parent_discoveryid'].
						'&dstfld1=ymin_itemid'.
						'&dstfld2=ymin_name'.
						'&srctbl=item_prototypes'.
						'&srcfld1=itemid'.
						'&srcfld2=name'.
						'&numeric=1", 0, 0, "zbx_popup_item");');
		}
	}
	else {
		$graphForm->addVar('yaxismin', $this->data['yaxismin']);
	}

	$graphFormList->addRow(_('Y axis MIN value'), $yaxisMinData);

	$yaxisMaxData = [(new CComboBox('ymax_type', $this->data['ymax_type'], null, [
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]))->setEnabled(!$is_templated)];

	if ($this->data['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED) {
		$yaxisMaxData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMaxData[] = (new CTextBox('yaxismax', $this->data['yaxismax'], $is_templated))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
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

		$yaxisMaxData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMaxData[] = (new CTextBox('ymax_name', $ymax_name, $is_templated))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
		$yaxisMaxData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$yaxisMaxData[] = (new CButton('yaxis_max', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('javascript: '.
				'return PopUp("popup.php?dstfrm='.$graphForm->getName().
					'&dstfld1=ymax_itemid'.
					'&dstfld2=ymax_name'.
					'&srctbl=items'.
					'&srcfld1=itemid'.
					'&srcfld2=name'.
					'&numeric=1'.
					'&writeonly=1" + getOnlyHostParam(), 0, 0, "zbx_popup_item");')
			->setEnabled(!$is_templated);

		// select prototype button
		if (!empty($this->data['parent_discoveryid'])) {
			$yaxisMaxData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
			$yaxisMaxData[] = (new CButton('yaxis_max_prototype', _('Select prototype')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: '.
					'return PopUp("popup.php?dstfrm='.$graphForm->getName().
						'&parent_discoveryid='.$this->data['parent_discoveryid'].
						'&dstfld1=ymax_itemid'.
						'&dstfld2=ymax_name'.
						'&srctbl=item_prototypes'.
						'&srcfld1=itemid'.
						'&srcfld2=name'.
						'&numeric=1", 0, 0, "zbx_popup_item");');
		}
	}
	else {
		$graphForm->addVar('yaxismax', $this->data['yaxismax']);
	}

	$graphFormList->addRow(_('Y axis MAX value'), $yaxisMaxData);
}
else {
	$graphFormList->addRow(_('3D view'),
		(new CCheckBox('show_3d'))
			->setChecked($this->data['show_3d'] == 1)
			->setEnabled(!$is_templated)
	);
}

// append items to form list
$itemsTable = (new CTable())
	->setId('itemsTable')
	->setHeader([
		(new CColHeader()),
		(new CColHeader()),
		(new CColHeader(_('Name'))),
		($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED)
			? (new CColHeader(_('Type')))
			: null,
		(new CColHeader(_('Function'))),
		($this->data['graphtype'] == GRAPH_TYPE_NORMAL)
			? (new CColHeader(_('Draw style')))
				->addClass(ZBX_STYLE_NOWRAP)
			: null,
		($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED)
			? (new CColHeader(_('Y axis side')))
				->addClass(ZBX_STYLE_NOWRAP)
			: null,
		(new CColHeader(_('Colour'))),
		$is_templated ? null : (new CColHeader(_('Action')))
	]);

$itemsTable->addRow(
	(new CRow(
		$is_templated
			? null
			: (new CCol(
				new CHorList([
					(new CButton('add_item', _('Add')))
						->onClick('return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
							($this->data['normal_only'] ? '&normal_only=1' : '').
							'&srctbl=items&srcfld1=itemid&srcfld2=name&numeric=1" + getOnlyHostParam());')
						->addClass(ZBX_STYLE_BTN_LINK),
					$this->data['parent_discoveryid']
						? (new CButton('add_protoitem', _('Add prototype')))
							->onClick('return PopUp("popup.php?writeonly=1&multiselect=1&dstfrm='.$graphForm->getName().
								url_param($this->data['graphtype'], false, 'graphtype').
								url_param('parent_discoveryid').($this->data['normal_only'] ? '&normal_only=1' : '').
								'&srctbl=item_prototypes&srcfld1=itemid&srcfld2=name&numeric=1");')
							->addClass(ZBX_STYLE_BTN_LINK)
						: null
				])
			))->setColSpan(8)
	))->setId('itemButtonsRow')
);

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

$graphFormList->addRow(_('Items'), (new CDiv($itemsTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

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
$graphPreviewTable = (new CTable())
	->addStyle('width: 100%;')
	->addRow(
		(new CRow(
			(new CDiv())->setId('previewChar')
		))->addClass(ZBX_STYLE_CENTER)
	);
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
			new CButtonCancel(url_param('parent_discoveryid').url_param('hostid', $this->data['hostid']))
		]
	));
}
else {
	$graphTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('parent_discoveryid').url_param('hostid', $this->data['hostid']))]
	));
}

// insert js (depended from some variables inside the file)
insert_show_color_picker_javascript();
require_once dirname(__FILE__).'/js/configuration.graph.edit.js.php';

$graphForm->addItem($graphTab);

// append form to widget
$widget->addItem($graphForm);

return $widget;
