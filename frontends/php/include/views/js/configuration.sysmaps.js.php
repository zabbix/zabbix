<script type="text/x-jquery-tmpl" id="mapElementFormTpl">
	<?= (new CDiv(new CTag('h4', true, _('Map element'))))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD)
			->addClass(ZBX_STYLE_CURSOR_MOVE)
			->setId('formDragHandler')
			->toString()
	?>
	<?= (new CForm())
			->cleanItems()
			->setName('selementForm')
			->setId('selementForm')
			->addVar('elementid', '')
			->addItem(
				(new CFormList())
					->addRow(_('Type'),
						(new CComboBox('elementtype', null, null, [
							SYSMAP_ELEMENT_TYPE_HOST => _('Host'),
							SYSMAP_ELEMENT_TYPE_MAP => _('Map'),
							SYSMAP_ELEMENT_TYPE_TRIGGER => _('Trigger'),
							SYSMAP_ELEMENT_TYPE_HOST_GROUP => _('Host group'),
							SYSMAP_ELEMENT_TYPE_IMAGE => _('Image')
						]))->setId('elementType')
					)
					->addRow(_('Show'),
						(new CRadioButtonList('elementsubtype', null))
							->addValue(_('Host group'), SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP, 'subtypeHostGroup')
							->addValue(_('Host group elements'), SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS,
								'subtypeHostGroupElements'
							)
							->setModern(true),
						'subtypeRow'
					)
					->addRow(_('Area type'),
						(new CRadioButtonList('areatype', null))
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
						(new CRadioButtonList(null, SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID))
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
					->addRow(_('Label location'),
						new CComboBox('label_location', null, null, [
							MAP_LABEL_LOC_DEFAULT => _('Default'),
							MAP_LABEL_LOC_BOTTOM => _('Bottom'),
							MAP_LABEL_LOC_LEFT => _('Left'),
							MAP_LABEL_LOC_RIGHT => _('Right'),
							MAP_LABEL_LOC_TOP => _('Top')
						])
					)
					->addRow(_('Host group'),
						(new CMultiSelect([
							'name' => 'elementNameHostGroup',
							'objectName' => 'hostGroup'
						]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
						'hostGroupSelectRow'
					)
					->addRow(_('Host'),
						(new CMultiSelect([
							'name' => 'elementNameHost',
							'objectName' => 'hosts'
						]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
						'hostSelectRow'
					)
					->addRow(_('Trigger'), [
						new CVar('elementExpressionTrigger', ''),
						(new CTextBox('elementName'))
							->setReadonly(true)
							->setId('elementNameTrigger')
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick('PopUp("popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid'.
								'&dstfld2=elementNameTrigger&dstfld3=elementExpressionTrigger&srctbl=triggers'.
								'&srcfld1=triggerid&srcfld2=description&srcfld3=expression&with_triggers=1'.
								'&real_hosts=1&noempty=1")')
					], 'triggerSelectRow')
					->addRow(_('Map'), [
						(new CTextBox('elementName'))
							->setReadonly(true)
							->setId('elementNameMap')
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick('PopUp("popup.php?srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name'.
								'&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameMap&writeonly=1'.
								'&excludeids[]=#{sysmapid}")'
							)
					], 'mapSelectRow')
					->addRow(_('Application'), [
						(new CTextBox('application'))
							->setId('application')
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->setId('application-select')
							->addClass(ZBX_STYLE_BTN_GREY)
					], 'application-select-row')
					->addRow(_('Automatic icon selection'),
						new CCheckBox('use_iconmap'),
						'useIconMapRow'
					)
					->addRow(_('Icons'),
						(new CDiv(
							(new CTable())
								->addRow([new CLabel(_('Default'), 'iconid_off'), new CComboBox('iconid_off')])
								->addRow(
									(new CRow([new CLabel(_('Problem'), 'iconid_on'), new CComboBox('iconid_on')]))
										->setId('iconProblemRow')
								)
								->addRow(
									(new CRow([
										new CLabel(_('Maintenance'), 'iconid_maintenance'),
										new CComboBox('iconid_maintenance')
									]))->setId('iconMainetnanceRow')
								)
								->addRow(
									(new CRow([
										new CLabel(_('Disabled'), 'iconid_disabled'),
										new CComboBox('iconid_disabled')
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
					], 'areaSizeRow')
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

<script type="text/x-jquery-tmpl" id="mapMassFormTpl">
	<?= (new CDiv(new CTag('h4', true, _('Mass update elements'))))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD)
			->addClass(ZBX_STYLE_CURSOR_MOVE)
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
						new CLabel([(new CCheckBox('chkbox_label'))->setId('chkboxLabel'), _('Label')], 'chkboxLabel'),
						(new CTextArea('label'))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setRows(2)
							->setId('massLabel')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_label_location'))->setId('chkboxLabelLocation'),
							_('Label location')
						], 'chkboxLabelLocation'),
						(new CComboBox('label_location', null, null, [
							MAP_LABEL_LOC_DEFAULT => _('Default'),
							MAP_LABEL_LOC_BOTTOM => _('Bottom'),
							MAP_LABEL_LOC_LEFT => _('Left'),
							MAP_LABEL_LOC_RIGHT => _('Right'),
							MAP_LABEL_LOC_TOP => _('Top')
						]))->setId('massLabelLocation')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_use_iconmap'))->setId('chkboxMassUseIconmap'),
							_('Automatic icon selection')
						], 'chkboxMassUseIconmap'),
						(new CCheckBox('use_iconmap'))->setId('massUseIconmap')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_iconid_off'))->setId('chkboxMassIconidOff'),
							_('Icon (default)')
						], 'chkboxMassIconidOff'),
						(new CComboBox('iconid_off'))->setId('massIconidOff')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_iconid_on'))->setId('chkboxMassIconidOn'),
							_('Icon (problem)')
						], 'chkboxMassIconidOn'),
						(new CComboBox('iconid_on'))->setId('massIconidOn')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_iconid_maintenance'))->setId('chkboxMassIconidMaintenance'),
							_('Icon (maintenance)')
						], 'chkboxMassIconidMaintenance'),
						(new CComboBox('iconid_maintenance'))->setId('massIconidMaintenance')
					)
					->addRow(
						new CLabel([
							(new CCheckBox('chkbox_iconid_disabled'))->setId('chkboxMassIconidDisabled'),
							_('Icon (disabled)')
						], 'chkboxMassIconidDisabled'),
						(new CComboBox('iconid_disabled'))->setId('massIconidDisabled')
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
	<?= (new CRow(['#{elementType}', '#{elementName}']))->toString() ?>
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
					->addRow(_('Connect to'), (new CComboBox('selementid2')), 'link-connect-to')
					->addRow(_('Type (OK)'),
						(new CComboBox('drawtype', null, null, [
							GRAPH_ITEM_DRAWTYPE_LINE => _('Line'),
							GRAPH_ITEM_DRAWTYPE_BOLD_LINE => _('Bold line'),
							GRAPH_ITEM_DRAWTYPE_DOT => _('Dot'),
							GRAPH_ITEM_DRAWTYPE_DASHED_LINE => _('Dashed line')
						]))
					)
					->addRow(_('Colour (OK)'),
						new CColor('color', '#{color}', false)
					)
					->addRow(_('Link indicators'),
						(new CDiv([
							(new CTable())
								->setHeader([_('Trigger'), _('Type'), _('Colour'), _('Action')])
								->setAttribute('style', 'width: 100%;')
								->setId('linkTriggerscontainer'),
							(new CButton(null, _('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->onClick('PopUp("popup.php?srctbl=triggers&srcfld1=triggerid&real_hosts=1'.
									'&reference=linktrigger&multiselect=1&writeonly=1&with_triggers=1&noempty=1");'
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
				(new CComboBox('linktrigger_#{linktriggerid}_drawtype', null, null, [
					GRAPH_ITEM_DRAWTYPE_LINE => _('Line'),
					GRAPH_ITEM_DRAWTYPE_BOLD_LINE => _('Bold line'),
					GRAPH_ITEM_DRAWTYPE_DOT => _('Dot'),
					GRAPH_ITEM_DRAWTYPE_DASHED_LINE => _('Dashed line')
				]))
			],
			new CColor('linktrigger_#{linktriggerid}_color', '#{color}', false),
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

<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(data) {
		if (data.object === 'name') {
			jQuery('#application').val(data.values[0].name);
		}
		else if (data.object === 'linktrigger') {
			ZABBIX.apps.map.object.linkForm.addNewTriggers(data.values);
		}
	}
</script>
