<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

$widgetConfig = new CWidgetConfig();
$formFields = $data['dialogue']['fields'];
$widgetType = $formFields['type'];


$form = (new CForm('post'))
	->cleanItems()
	->setId('widget_dialogue_form')
	->setName('widget_dialogue_form');

$formList = (new CFormList())
	->addRow(_('Type'), new CComboBox('type', $widgetType, 'updateConfigDialogue()', $widgetConfig->getKnownWidgetTypesWNames()));

/*
 * Screen item: Clock
 */
if ($widgetType == WIDGET_CLOCK) {

	$time_type = array_key_exists('time_type', $formFields) ? $formFields['time_type'] : TIME_TYPE_LOCAL;
	$caption = array_key_exists('caption', $formFields) ? $formFields['caption'] : '';
	$itemId = array_key_exists('itemid', $formFields) ? $formFields['itemid'] : 0;

	if ($caption === '' && $time_type === TIME_TYPE_HOST && $itemId > 0) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_', 'name'],
			'selectHosts' => ['name'],
			'itemids' => $itemId,
			'webitems' => true
		]);

		if ($items) {
			$items = CMacrosResolverHelper::resolveItemNames($items);

			$item = reset($items);
			$host = reset($item['hosts']);
			$caption = $host['name'].NAME_DELIMITER.$item['name_expanded'];
		}
	}

	$formList->addRow(_('Time type'), new CComboBox('time_type', $time_type, 'updateConfigDialogue()', [
		TIME_TYPE_LOCAL => _('Local time'),
		TIME_TYPE_SERVER => _('Server time'),
		TIME_TYPE_HOST => _('Host time')
	]));

	if ($time_type == TIME_TYPE_HOST) {
		$form->addVar('itemid', $itemId);

		$selectButton = (new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick("javascript: return PopUp('popup.php?dstfrm=".$form->getName().'&dstfld1=itemid'.
					"&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&real_hosts=1');");
		$cell = (new CDiv([
			(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$selectButton
		]))->addStyle('display: flex;'); // TODO VM: move style to scss
		$formList->addRow(_('Item'), $cell);
	}
}

// URL field
if (in_array($widgetType, [WIDGET_URL])) {
	$url = array_key_exists('url', $formFields) ? $formFields['url'] : '';
	$formList->addRow(_('URL'), (new CTextBox('url', $url))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
}

// Width and height fields
if (in_array($widgetType, [WIDGET_CLOCK])) {
	$width = array_key_exists('width', $formFields) ? $formFields['width'] : 0;
	$height = array_key_exists('height', $formFields) ? $formFields['height'] : 0;
	$formList->addRow(_('Width'), (new CNumericBox('width', $width, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH));
	$formList->addRow(_('Height'), (new CNumericBox('height', $height, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH));
}

$form->addItem($formList);

$output = [
	'body' => $form->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
