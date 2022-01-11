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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$widget_view = include('include/classes/widgets/views/widget.'.$data['dialogue']['type'].'.form.view.php');

$form = $widget_view['form']
	->addClass('dashboard-grid-widget-'.$data['dialogue']['type']);

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;'));

$output = [
	'header' => $data['unique_id'] !== null ? _s('Edit widget') : _s('Add widget'),
	'body' => '',
	'buttons' => [
		[
			'title' => $data['unique_id'] !== null ? _s('Apply') : _s('Add'),
			'class' => 'dialogue-widget-save',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ZABBIX.Dashboard.applyWidgetProperties();'
		]
	],
	'data' => [
		'original_properties' => [
			'type' => $data['dialogue']['type'],
			'unique_id' => $data['unique_id'],
			'dashboard_page_unique_id' => $data['dashboard_page_unique_id']
		]
	]
];

if (($messages = getMessages()) !== null) {
	$output['body'] .= $messages->toString();
}

$output['body'] .= $form->toString();

if (array_key_exists('jq_templates', $widget_view)) {
	foreach ($widget_view['jq_templates'] as $id => $jq_template) {
		$output['body'] .= '<script type="text/x-jquery-tmpl" id="'.$id.'">'.$jq_template.'</script>';
	}
}

if (array_key_exists('scripts', $widget_view)) {
	$output['body'] .= get_js(implode("\n", $widget_view['scripts']));
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
