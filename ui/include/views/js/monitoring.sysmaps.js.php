<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

$shape_border_types = [
	SYSMAP_SHAPE_BORDER_TYPE_NONE		=> _('None'),
	SYSMAP_SHAPE_BORDER_TYPE_SOLID		=> '———',
	SYSMAP_SHAPE_BORDER_TYPE_DOTTED		=> '· · · ·',
	SYSMAP_SHAPE_BORDER_TYPE_DASHED		=> '- - - -'
];

$horizontal_align_types = [
	SYSMAP_SHAPE_LABEL_HALIGN_LEFT		=> _('Left'),
	SYSMAP_SHAPE_LABEL_HALIGN_CENTER	=> _('Center'),
	SYSMAP_SHAPE_LABEL_HALIGN_RIGHT		=> _('Right')
];

$vertical_align_types = [
	SYSMAP_SHAPE_LABEL_VALIGN_TOP		=> _('Top'),
	SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE	=> _('Middle'),
	SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM	=> _('Bottom')
];

/**
 * Get font select element.
 *
 * @param string $name
 *
 * @return CSelect
 */
function createFontSelect(string $name): CSelect {
	return (new CSelect($name))
		->setId($name)
		->addOptionGroup((new CSelectOptionGroup(_('Serif')))->addOptions(CSelect::createOptionsFromArray([
			0 => 'Georgia',
			1 => 'Palatino',
			2 => 'Times New Roman'
		])))
		->addOptionGroup((new CSelectOptionGroup(_('Sans-Serif')))->addOptions(CSelect::createOptionsFromArray([
			3 => 'Arial',
			4 => 'Arial Black',
			5 => 'Comic Sans',
			6 => 'Impact',
			7 => 'Lucida Sans',
			8 => 'Tahoma',
			9 => 'Helvetica',
			10 => 'Verdana'
		])))
		->addOptionGroup((new CSelectOptionGroup(_('Monospace')))->addOptions(CSelect::createOptionsFromArray([
			11 => 'Courier New',
			12 => 'Lucida Console'
		])));
}
?>
<script type="text/x-jquery-tmpl" id="mapElementFormTpl">
	<?= (new CDiv([
			(new CTag('h4', true, _('Map element'))),
			(new CLink(null, CDocHelper::getUrl(CDocHelper::POPUP_MAP_ELEMENT)))
				->setTitle(_('Help'))
				->addClass(ZBX_STYLE_ICON_DOC_LINK)
				->setTarget('_blank')
		]))
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_HEAD)
			->setId('formDragHandler')
			->toString()
	?>
	<?= (new CForm())
			->cleanItems()
			->setName('selementForm')
			->setId('selementForm')
			->addItem(
				(new CFormList())
					->addRow(new CLabel(_('Type'), 'label-elementtype'),
						(new CSelect('elementtype'))
							->setFocusableElementId('label-elementtype')
							->addOptions(CSelect::createOptionsFromArray([
								SYSMAP_ELEMENT_TYPE_HOST => _('Host'),
								SYSMAP_ELEMENT_TYPE_MAP => _('Map'),
								SYSMAP_ELEMENT_TYPE_TRIGGER => _('Trigger'),
								SYSMAP_ELEMENT_TYPE_HOST_GROUP => _('Host group'),
								SYSMAP_ELEMENT_TYPE_IMAGE => _('Image')
							]))
							->setId('elementType')
					)
					->addRow(_('Show'),
						(new CRadioButtonList('elementsubtype', SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP))
							->addValue(_('Host group'), SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP, 'subtypeHostGroup')
							->addValue(_('Host group elements'), SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS,
								'subtypeHostGroupElements'
							)
							->setModern(true),
						'subtypeRow'
					)
					->addRow(_('Area type'),
						(new CRadioButtonList('areatype', SYSMAP_ELEMENT_AREA_TYPE_FIT))
							->addValue(_('Fit to map'), SYSMAP_ELEMENT_AREA_TYPE_FIT, 'areaTypeAuto')
							->addValue(_('Custom size'), SYSMAP_ELEMENT_AREA_TYPE_CUSTOM, 'areaTypeCustom')
							->setModern(true),
						'areaTypeRow'
					)
					->addRow(new CLabel(_('Area size'), 'areaSizeWidth'), [
						_('Width'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('width'))
							->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							->setId('areaSizeWidth'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						_('Height'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('height'))
							->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							->setId('areaSizeHeight')
					], 'areaSizeRow')
					->addRow(_('Placing algorithm'),
						(new CRadioButtonList('viewtype', SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID))
							->addValue(_('Grid'), SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID)
							->setModern(true),
						'areaPlacingRow'
					)
					->addRow(_('Label'),
						(new CTextArea('label'))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setRows(2)
							->setId('elementLabel')
					)
					->addRow(new CLabel(_('Label location'), 'label-label-location'),
						(new CSelect('label_location'))
							->setFocusableElementId('label-label-location')
							->addOptions(CSelect::createOptionsFromArray([
								MAP_LABEL_LOC_DEFAULT => _('Default'),
								MAP_LABEL_LOC_BOTTOM => _('Bottom'),
								MAP_LABEL_LOC_LEFT => _('Left'),
								MAP_LABEL_LOC_RIGHT => _('Right'),
								MAP_LABEL_LOC_TOP => _('Top')
							]))
					)
					->addRow((new CLabel(_('Host group'), 'elementNameHostGroup_ms'))->setAsteriskMark(),
						(new CMultiSelect([
							'name' => 'elementNameHostGroup',
							'object_name' => 'hostGroup',
							'multiple' => false
						]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setAriaRequired(),
						'hostGroupSelectRow'
					)
					->addRow((new CLabel(_('Host'), 'elementNameHost_ms'))->setAsteriskMark(),
						(new CMultiSelect([
							'name' => 'elementNameHost',
							'object_name' => 'hosts',
							'multiple' => false
						]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setAriaRequired(),
						'hostSelectRow'
					)
					->addRow((new CLabel(_('Triggers'), 'triggerContainer'))->setAsteriskMark(), [
						(new CDiv([
							(new CTable())
								->setHeader(['', _('Name'), (new CColHeader(_('Action')))->addStyle('padding: 0 5px;')])
								->setId('triggerContainer')
								->setAttribute('style', 'width: 100%;')
								->addClass('ui-sortable')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					], 'triggerListRow')
					->addRow((new CLabel(_('New triggers'), 'elementNameTriggers_ms')),
						(new CDiv([
							(new CMultiSelect([
								'name' => 'elementNameTriggers',
								'object_name' => 'triggers',
								'popup' => [
									'parameters' => [
										'srctbl' => 'triggers',
										'srcfld1' => 'triggerid',
										'dstfrm' => 'selementForm',
										'dstfld1' => 'elementNameTriggers',
										'with_triggers' => true,
										'editable' => 1,
										'real_hosts' => true
									]
								]
							]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
							new CDiv(
								(new CButton(null, _('Add')))
									->addClass(ZBX_STYLE_BTN_LINK)
									->setId('newSelementTriggers')
						)]))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
						'triggerSelectRow'
					)
					->addRow((new CLabel(_('Map'), 'elementName'))->setAsteriskMark(), [
						(new CTextBox('elementName'))
							->setReadonly(true)
							->setId('elementNameMap')
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setAriaRequired(),
						(new CVar('elements[0][sysmapid]', 0, 'sysmapid')),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick(
								'return PopUp("popup.generic", jQuery.extend('.json_encode([
									'srctbl' => 'sysmaps',
									'srcfld1' => 'sysmapid',
									'srcfld2' => 'name',
									'dstfrm' => 'selementForm',
									'dstfld1' => 'sysmapid',
									'dstfld2' => 'elementNameMap'
								]).', {excludeids: [#{sysmapid}]}), {dialogue_class: "modal-popup-generic"});'
							)
					], 'mapSelectRow')
					->addRow(_('Tags'),
						(new CDiv([
							(new CTable())
								->setId('selement-tags')
								->addRow(
									(new CCol(
										(new CRadioButtonList('evaltype', TAG_EVAL_TYPE_AND_OR))
											->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
											->addValue(_('Or'), TAG_EVAL_TYPE_OR)
											->setModern(true)
									))->setColSpan(4)
								)
								->addRow(
									(new CCol(
										(new CButton('tags_add', _('Add')))
											->addClass(ZBX_STYLE_BTN_LINK)
											->addClass('element-table-add')
											->removeId()
									))->setColSpan(3)
								)
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
						'tags-select-row'
					)
					->addRow(_('Automatic icon selection'),
						new CCheckBox('use_iconmap'),
						'useIconMapRow'
					)
					->addRow(_('Icons'),
						(new CDiv(
							(new CTable())
								->addRow([
									new CLabel(_('Default'), 'label-iconid-off'),
									(new CSelect('iconid_off'))
										->setId('iconid_off')
										->setFocusableElementId('label-iconid-off')
										->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
								])
								->addRow(
									(new CRow([
										new CLabel(_('Problem'), 'label-iconid-on'),
										(new CSelect('iconid_on'))
											->setId('iconid_on')
											->setFocusableElementId('label-iconid-on')
											->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									]))
										->setId('iconProblemRow')
								)
								->addRow(
									(new CRow([
										new CLabel(_('Maintenance'), 'label-iconid-maintenance'),
										(new CSelect('iconid_maintenance'))
											->setId('iconid_maintenance')
											->setFocusableElementId('label-iconid-maintenance')
											->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									]))->setId('iconMainetnanceRow')
								)
								->addRow(
									(new CRow([
										new CLabel(_('Disabled'), 'label-iconid-disabled'),
										(new CSelect('iconid_disabled'))
											->setId('iconid_disabled')
											->setFocusableElementId('label-iconid-disabled')
											->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									]))->setId('iconDisabledRow')
								)
								->setAttribute('style', 'width: 100%;')
						))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					)
					->addRow(new CLabel(_('Coordinates'), 'x'), [
						_('X'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('x'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						_('Y'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('y'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					])
					->addRow(_('URLs'),
						(new CDiv([
							(new CTable())
								->setHeader([_('Name'), _('URL'), _('Action')])
								->setId('urlContainer')
								->setAttribute('style', 'width: 100%;'),
							(new CButton(null, _('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->setId('newSelementUrl')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					)
					->addItem([
						(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
						(new CDiv([
							(new CButton(null, _('Apply')))
								->addClass('element-edit-control')
								->setId('elementApply'),
							(new CButton(null, _('Remove')))
								->addClass('element-edit-control')
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('elementRemove'),
							(new CButton(null, _('Close')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('elementClose')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
							->addClass(ZBX_STYLE_TFOOT_BUTTONS)
					])
			)
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="mapShapeFormTpl">
	<?= (new CDiv([
			(new CTag('h4', true, _('Map shape'))),
			(new CLink(null, CDocHelper::getUrl(CDocHelper::POPUP_MAP_SHAPE)))
				->setTitle(_('Help'))
				->addClass(ZBX_STYLE_ICON_DOC_LINK)
				->setTarget('_blank')
		]))
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_HEAD)
			->setId('shapeDragHandler')
			->toString().
		(new CForm())
			->cleanItems()
			->setName('shapeForm')
			->setId('shapeForm')
			->addVar('sysmap_shapeid', '')
			->addItem(
				(new CFormList())
					->addRow(_('Shape'), [
						(new CRadioButtonList('type', SYSMAP_SHAPE_TYPE_RECTANGLE))
							->addValue(_('Rectangle'), SYSMAP_SHAPE_TYPE_RECTANGLE, null, 'jQuery.colorpicker("hide")')
							->addValue(_('Ellipse'), SYSMAP_SHAPE_TYPE_ELLIPSE, null, 'jQuery.colorpicker("hide")')
							->addValue(_('Line'), SYSMAP_SHAPE_TYPE_LINE, null, 'jQuery.colorpicker("hide")')
							->setModern(true),
						new CVar('', '', 'last_shape_type')
					])
					->addRow(_('Text'),
						(new CDiv([
							(new CTextArea('text'))
								->addStyle('margin-bottom: 4px;')
								->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
								->setRows(3),
							BR(),
							_('Font'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							createFontSelect('font'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							_('Font size'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('font_size'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							_('Color'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CColor('font_color', '#{color}'))->appendColorPickerJs(false),
							BR(),
							_('Horizontal align'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSelect('text_halign'))
								->setValue(SYSMAP_SHAPE_LABEL_HALIGN_CENTER)
								->addOptions(CSelect::createOptionsFromArray($horizontal_align_types))
								->setAttribute('style', 'margin-top: 4px'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							_('Vertical align'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSelect('text_valign'))
								->setValue(SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE)
								->addOptions(CSelect::createOptionsFromArray($vertical_align_types))
						]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR),
						'shape-text-row'
					)
					->addRow(_('Background'),
						(new CDiv([
							_('Color'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CColor('background_color', '#{color}'))->appendColorPickerJs(false)
						]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR),
						'shape-background-row'
					)
					->addRow((new CSpan())
							->addClass('switchable-content')
							->setAttribute('data-value', _('Border'))
							->setAttribute('data-value-2', _('Line')),
						(new CDiv([
							_('Type'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSelect('border_type'))
								->addOptions(CSelect::createOptionsFromArray($shape_border_types)),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							_('Width'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('border_width'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							_('Color'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CColor('border_color', '#{color}'))->appendColorPickerJs(false)
						]))
							->addClass(ZBX_STYLE_NOWRAP)
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					)
					->addRow((new CSpan())
							->addClass('switchable-content')
							->setAttribute('data-value', _('Coordinates'))
							->setAttribute('data-value-2', _('Points')),
						(new CDiv([
							(new CSpan())
								->addClass('switchable-content')
								->setAttribute('data-value', _('X'))
								->setAttribute('data-value-2', _('X1')),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('x'))
								->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
								->setId('shapeX'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSpan())
								->addClass('switchable-content')
								->setAttribute('data-value', _('Y'))
								->setAttribute('data-value-2', _('Y1')),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('y'))
								->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
								->setId('shapeY')
						]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					)
					->addRow((new CSpan())
							->addClass('switchable-content')
							->setAttribute('data-value', _('Size'))
							->setAttribute('data-value-2', ''),
						(new CDiv([
							(new CSpan())
								->addClass('switchable-content')
								->setAttribute('data-value', _('Width'))
								->setAttribute('data-value-2', _('X2')),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('width'))
								->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
								->setId('shapeAreaSizeWidth'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSpan())
								->addClass('switchable-content')
								->setAttribute('data-value', _('Height'))
								->setAttribute('data-value-2', _('Y2')),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CTextBox('height'))
								->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
								->setId('shapeAreaSizeHeight')
						]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					)
					->addItem([
						(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
						(new CDiv([
							(new CButton(null, _('Apply')))
								->addClass('shape-edit-control')
								->setId('shapeApply'),
							(new CButton(null, _('Remove')))
								->addClass('shape-edit-control')
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('shapeRemove'),
							(new CButton(null, _('Close')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('shapeClose')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
							->addClass(ZBX_STYLE_TFOOT_BUTTONS)
					])
			)
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="mapMassShapeFormTpl">
	<?= (new CDiv([
			(new CTag('h4', true, _('Mass update shapes'))),
			(new CLink(null, CDocHelper::getUrl(CDocHelper::POPUP_MAP_MASSUPDATE_SHAPES)))
				->setTitle(_('Help'))
				->addClass(ZBX_STYLE_ICON_DOC_LINK)
				->setTarget('_blank')
		]))
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_HEAD)
			->setId('massShapeDragHandler')
			->toString().
		(new CForm())
			->cleanItems()
			->setName('shapeForm')
			->setId('massShapeForm')
			->addItem(
				(new CFormList())
					->addRow((new CCheckBox('chkbox_type'))
							->setId('chkboxType')
							->setLabel(_('Shape')),
						(new CRadioButtonList('mass_type', SYSMAP_SHAPE_TYPE_RECTANGLE))
							->addValue(_('Rectangle'), SYSMAP_SHAPE_TYPE_RECTANGLE)
							->addValue(_('Ellipse'), SYSMAP_SHAPE_TYPE_ELLIPSE)
							->setModern(true),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_text'))
							->setId('chkboxText')
							->setLabel(_('Text')),
						(new CTextArea('mass_text'))
								->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
								->setRows(2),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_font'))
							->setId('chkboxFont')
							->setLabel(_('Font')),
						createFontSelect('mass_font'),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_font_size'))
							->setId('chkboxFontSize')
							->setLabel(_('Font size')),
						(new CTextBox('mass_font_size'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_font_color'))
							->setId('chkboxFontColor')
							->setLabel(_('Font color')),
						(new CColor('mass_font_color', '#{color}'))->appendColorPickerJs(false),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_text_halign'))
							->setId('chkboxTextHalign')
							->setLabel(_('Horizontal align')),
						(new CSelect('mass_text_halign'))
							->setId('mass_text_halign')
							->setValue(SYSMAP_SHAPE_LABEL_HALIGN_CENTER)
							->addOptions(CSelect::createOptionsFromArray($horizontal_align_types)),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_text_valign'))
							->setId('chkboxTextValign')
							->setLabel(_('Vertical align')),
						(new CSelect('mass_text_valign'))
							->setId('mass_text_valign')
							->setValue(SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE)
							->addOptions(CSelect::createOptionsFromArray($vertical_align_types)),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_background'))
							->setId('chkboxBackground')
							->setLabel(_('Background color')),
						(new CColor('mass_background_color', '#{color}'))->appendColorPickerJs(false),
						null, 'shape_figure_row'
					)
					->addRow((new CCheckBox('chkbox_border_type'))
							->setId('chkboxBorderType')
							->setLabel((new CDiv())
								->addClass('form-input-margin')
								->addClass('switchable-content')
								->setAttribute('data-value', _('Border type'))
								->setAttribute('data-value-2', _('Line type'))
							),
						(new CSelect('mass_border_type'))
							->setId('mass_border_type')
							->addOptions(CSelect::createOptionsFromArray($shape_border_types))
					)
					->addRow((new CCheckBox('chkbox_border_width'))
							->setId('chkboxBorderWidth')
							->setLabel((new CDiv())
								->addClass('form-input-margin')
								->addClass('switchable-content')
								->setAttribute('data-value', _('Border width'))
								->setAttribute('data-value-2', _('Line width'))
							),
						(new CTextBox('mass_border_width'))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					)
					->addRow((new CCheckBox('chkbox_border_color'))
							->setId('chkboxBorderColor')
							->setLabel((new CDiv())
								->addClass('form-input-margin')
								->addClass('switchable-content')
								->setAttribute('data-value', _('Border color'))
								->setAttribute('data-value-2', _('Line color'))
							),
						(new CColor('mass_border_color', '#{color}'))->appendColorPickerJs(false)
					)
					->addItem([
						(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
						(new CDiv([
							(new CButton(null, _('Apply')))
								->addClass('shape-edit-control')
								->setId('shapeMassApply'),
							(new CButton(null, _('Remove')))
								->addClass('shape-edit-control')
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('shapeMassRemove'),
							(new CButton(null, _('Close')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('shapeMassClose')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
							->addClass(ZBX_STYLE_TFOOT_BUTTONS)
					])
			)
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="mapMassFormTpl">
	<?= (new CDiv([
			(new CTag('h4', true, _('Mass update elements'))),
			(new CLink(null, CDocHelper::getUrl(CDocHelper::POPUP_MAP_MASSUPDATE_ELEMENTS)))
				->setTitle(_('Help'))
				->addClass(ZBX_STYLE_ICON_DOC_LINK)
				->setTarget('_blank')
		]))
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_HEAD)
			->setId('massDragHandler')
			->toString()
	?>
	<?= (new CForm())
			->cleanItems()
			->setId('massForm')
			->addItem(
				(new CFormList())
					->addRow(_('Selected elements'),
						(new CDiv(
							(new CTable())
								->setHeader([_('Type'), _('Name')])
								->setAttribute('style', 'width: 100%;')
								->setId('massList')
						))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					)
					->addRow(
						(new CCheckBox('chkbox_label'))
							->setId('chkboxLabel')
							->setLabel(_('Label')),
						(new CTextArea('label'))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setRows(2)
							->setId('massLabel')
					)
					->addRow(
						(new CCheckBox('chkbox_label_location'))
							->setId('chkboxLabelLocation')
							->setLabel(_('Label location')),
						(new CSelect('label_location'))
							->addOptions(CSelect::createOptionsFromArray([
								MAP_LABEL_LOC_DEFAULT => _('Default'),
								MAP_LABEL_LOC_BOTTOM => _('Bottom'),
								MAP_LABEL_LOC_LEFT => _('Left'),
								MAP_LABEL_LOC_RIGHT => _('Right'),
								MAP_LABEL_LOC_TOP => _('Top')
							]))
							->setId('massLabelLocation')
					)
					->addRow(
						(new CCheckBox('chkbox_use_iconmap'))
							->setId('chkboxMassUseIconmap')
							->setLabel(_('Automatic icon selection'))
							->setEnabled($data['sysmap']['iconmapid'] !== '0'),
						(new CCheckBox('use_iconmap'))->setId('massUseIconmap')
					)
					->addRow(
						(new CCheckBox('chkbox_iconid_off'))
							->setId('chkboxMassIconidOff')
							->setLabel(_('Icon (default)')),
						(new CSelect('iconid_off'))
							->setId('massIconidOff')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CCheckBox('chkbox_iconid_on'))
							->setId('chkboxMassIconidOn')
							->setLabel(_('Icon (problem)')),
						(new CSelect('iconid_on'))
							->setId('massIconidOn')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CCheckBox('chkbox_iconid_maintenance'))
							->setId('chkboxMassIconidMaintenance')
							->setLabel(_('Icon (maintenance)')),
						(new CSelect('iconid_maintenance'))
							->setId('massIconidMaintenance')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CCheckBox('chkbox_iconid_disabled'))
							->setId('chkboxMassIconidDisabled')
							->setLabel(_('Icon (disabled)')),
						(new CSelect('iconid_disabled'))
							->setId('massIconidDisabled')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addItem([
						(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
						(new CDiv([
							(new CButton(null, _('Apply')))
								->addClass('element-edit-control')
								->setId('massApply'),
							(new CButton(null, _('Remove')))
								->addClass('element-edit-control')
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('massRemove'),
							(new CButton(null, _('Close')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('massClose')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
							->addClass(ZBX_STYLE_TFOOT_BUTTONS)
					])
			)
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="mapMassFormListRow">
	<?= (new CRow(['#{elementType}', '#{*elementName}']))->toString() ?>
</script>

<script type="text/x-jquery-tmpl" id="linkFormTpl">
	<?= (new CFormList())
		->addRow(_('Links'),
			(new CDiv(
				(new CTable())
					->setHeader([_('Element name'), _('Link indicators'), _('Action')])
					->setAttribute('style', 'width: 100%;')
					->setId('element-links')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
			null, 'element-links'
		)
		->addRow(_('Links'),
			(new CDiv(
				(new CTable())
					->setHeader([_('From'), _('To'), _('Link indicators'), _('Action')])
					->setAttribute('style', 'width: 100%;')
					->setId('mass-element-links')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
			null, 'element-links'
		)
		->setId('mapLinksContainer')
		->toString()
	?>
	<?= (new CForm())
			->cleanItems()
			->setId('linkForm')
			->addVar('selementid1', '')
			->addItem(
				(new CFormList())
					->addRow(_('Label'),
						(new CTextArea('label'))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setRows(2)
							->setId('linklabel')
					)
					->addRow(new CLabel(_('Connect to'), 'label-selementid2'), (new CSelect('selementid2'))
							->setFocusableElementId('label-selementid2')
							->setId('selementid2'),
						'link-connect-to'
					)
					->addRow(new CLabel(_('Type (OK)'), 'label-drawtype'),
						(new CSelect('drawtype'))
							->setFocusableElementId('label-drawtype')
							->addOptions(CSelect::createOptionsFromArray([
								GRAPH_ITEM_DRAWTYPE_LINE => _('Line'),
								GRAPH_ITEM_DRAWTYPE_BOLD_LINE => _('Bold line'),
								GRAPH_ITEM_DRAWTYPE_DOT => _('Dot'),
								GRAPH_ITEM_DRAWTYPE_DASHED_LINE => _('Dashed line')
							]))
					)
					->addRow(_('Color (OK)'),
						(new CColor('color', '#{color}'))->appendColorPickerJs(false)
					)
					->addRow(_('Link indicators'),
						(new CDiv([
							(new CTable())
								->setHeader([_('Trigger'), _('Type'), _('Color'), _('Action')])
								->setAttribute('style', 'width: 100%;')
								->setId('linkTriggerscontainer'),
							(new CButton(null, _('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->onClick(
									'return PopUp("popup.generic", '.json_encode([
										'srctbl' => 'triggers',
										'srcfld1' => 'triggerid',
										'reference' => 'linktrigger',
										'multiselect' => '1',
										'real_hosts' => '1',
										'with_triggers' => '1'
									]).', {dialogue_class: "modal-popup-generic"});'
								)
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					)
					->addItem([
						(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
						(new CDiv([
							(new CButton(null, _('Apply')))->setId('formLinkApply'),
							(new CButton(null, _('Remove')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('formLinkRemove'),
							(new CButton(null, _('Close')))
								->addClass(ZBX_STYLE_BTN_ALT)
								->setId('formLinkClose')
						]))
							->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
							->addClass(ZBX_STYLE_TFOOT_BUTTONS)
					])
			)
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="elementLinkTableRowTpl">
	<?= (new CRow([
			'#{toElementName}',
			(new CCol())->addClass('element-urls'),
			(new CCol(
				(new CButton(null, _('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('openlink')
					->setAttribute('data-linkid', '#{linkid}')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="massElementLinkTableRowTpl">
	<?= (new CRow([
			'#{fromElementName}',
			'#{toElementName}',
			(new CCol())->addClass('element-urls'),
			(new CCol(
				(new CButton(null, _('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('openlink')
					->setAttribute('data-linkid', '#{linkid}')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="linkTriggerRow">
	<?= (new CRow([
			'#{desc_exp}',
			[
				new CVar('linktrigger_#{linktriggerid}_desc_exp', '#{desc_exp}'),
				new CVar('linktrigger_#{linktriggerid}_triggerid', '#{triggerid}'),
				new CVar('linktrigger_#{linktriggerid}_linktriggerid', '#{linktriggerid}'),
				(new CSelect('linktrigger_#{linktriggerid}_drawtype'))
					->setId('linktrigger_#{linktriggerid}_drawtype')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_ITEM_DRAWTYPE_LINE => _('Line'),
						GRAPH_ITEM_DRAWTYPE_BOLD_LINE => _('Bold line'),
						GRAPH_ITEM_DRAWTYPE_DOT => _('Dot'),
						GRAPH_ITEM_DRAWTYPE_DASHED_LINE => _('Dashed line')
					]))
			],
			(new CColor('linktrigger_#{linktriggerid}_color', '#{color}'))->appendColorPickerJs(false),
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('triggerRemove')
					->setAttribute('data-linktriggerid', '#{linktriggerid}')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('linktrigger_#{linktriggerid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="selementFormUrls">
	<?= (new CRow([
			(new CTextBox('url_#{selementurlid}_name', '#{name}'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CTextBox('url_#{selementurlid}_url', '#{url}'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('jQuery("#urlrow_#{selementurlid}").remove();')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('urlrow_#{selementurlid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(['tag_field_name' => 'tags']); ?>
</script>

<script type="text/x-jquery-tmpl" id="selementFormTriggers">
	<?= (new CRow([
			(new CCol([
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('top: 0px;'),
				(new CSpan())->addClass('ui-icon ui-icon-arrowthick-2-n-s move '.ZBX_STYLE_TD_DRAG_ICON)
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CCol([(new CDiv('#{name}'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)]))
				->addStyle('padding: 0 5px;')
				->addClass('#{class_name}'),
			(new CCol([
				(new CVar('element_id[#{triggerid}]', '#{triggerid}')),
				(new CVar('element_name[#{triggerid}]', '#{name}')),
				(new CVar('element_priority[#{triggerid}]', '#{priority}')),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addStyle('margin: 0 5px;')
					->onClick('jQuery("#triggerrow_#{triggerid}").remove();')
			]))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('triggerrow_#{triggerid}')
			->toString()
	?>
</script>

<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(data) {
		if (data.object === 'linktrigger') {
			ZABBIX.apps.map.object.linkForm.addNewTriggers(data.values);
		}
	}
</script>
