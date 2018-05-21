<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$output = [];

// Create form.
$services_form = (new CForm())
	->cleanItems()
	->setName('services_form');

if (array_key_exists('service', $data)) {
	$services_form->addItem((new CVar('serviceid', $data['service']['serviceid']))->removeId());
}

// Create table.
$services_table = (new CTableInfo())
	->setHeader([
		array_key_exists('db_cservices', $data)
			? (new CColHeader(
					(new CCheckBox('all_services'))
						->onClick("javascript: checkAll('".$services_form->getName()."', 'all_services', 'services');")
				))->addClass(ZBX_STYLE_CELL_WIDTH)
			: null,
		_('Service'),
		_('Status calculation'),
		_('Trigger')
	]);

$js_action_onclick = ' jQuery(this).removeAttr("onclick");';
$js_action_onclick .= ' overlayDialogueDestroy(jQuery(this).closest("[data-dialogueid]").attr("data-dialogueid"));';
$js_action_onclick .= ' return false;';

// Add table rows.
if (array_key_exists('db_pservices', $data)) {
	// Add root item.
	if ($data['parentid'] == 0) {
		$description = new CSpan(_('root'));
	}
	else {
		$description = (new CLink(_('root'), '#'))
			->onClick('javascript:
				jQuery(\'#parent_name\', window.document).val('.zbx_jsvalue(_('root')).');
				jQuery(\'#parentname\', window.document).val('.zbx_jsvalue(_('root')).');
				jQuery(\'#parentid\', window.document).val('.zbx_jsvalue(0).');'.
				$js_action_onclick
			);
	}

	$services_table->addRow([$description, _('Note'), '-']);

	foreach ($data['db_pservices'] as $db_service) {
		if (bccomp($data['parentid'], $db_service['serviceid']) == 0) {
			$description = new CSpan($db_service['name']);
		}
		else {
			$description = (new CLink($db_service['name'], '#'))
				->addClass('link')
				->onClick('javascript:
					jQuery(\'#parent_name\', window.document).val('.zbx_jsvalue($db_service['name']).');
					jQuery(\'#parentname\', window.document).val('.zbx_jsvalue($db_service['name']).');
					jQuery(\'#parentid\', window.document).val('.zbx_jsvalue($db_service['serviceid']).');'.
					$js_action_onclick
				);
		}

		$services_table->addRow([$description, serviceAlgorithm($db_service['algorithm']), $db_service['trigger']]);
	}

	$output['buttons'] = null;
}
elseif (array_key_exists('db_cservices', $data)) {
	foreach ($data['db_cservices'] as $service) {
		$description = (new CLink($service['name'], '#'))
			->addClass('service-name')
			->setId('service-name-'.$service['serviceid'])
			->setAttribute('data-name', $service['name'])
			->setAttribute('data-serviceid', $service['serviceid'])
			->setAttribute('data-trigger', $service['trigger']);

		$cb = (new CCheckBox('services['.$service['serviceid'].']', $service['serviceid']))
			->addClass('service-select');

		$services_table->addRow([$cb, $description, serviceAlgorithm($service['algorithm']), $service['trigger']]);
	}

	$output['script_inline'] =
		'jQuery(document).ready(function() {'.
			'jQuery(".service-name").click(function() {'.
				'var e = jQuery(this);'.
				'window.add_child_service(e.data("name"), e.data("serviceid"), e.data("trigger"));'.
				$js_action_onclick.
			'});'.

			'cookie.init();'.
			'chkbxRange.init();'.
		'});'.

		'var addSelectedServices = function() {'.
			'var e;'.
			'jQuery(".service-select:checked").each(function(key, cb) {'.
				'e = jQuery("#service-name-" + jQuery(cb).val());'.
				'window.add_child_service(e.data("name"), e.data("serviceid"), e.data("trigger"));'.
			'});'.

			'return true;'.
		'};';

	$output['buttons'] = [
		[
			'title' => _('Select'),
			'class' => '',
			'action' => 'return addSelectedServices();'
		]
	];
}

$services_form->addItem($services_table);

$output += [
	'header' => $data['title'],
	'body' => (new CDiv($services_form))->toString()
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
