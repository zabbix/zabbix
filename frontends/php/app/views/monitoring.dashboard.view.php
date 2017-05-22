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


$this->addJsFile('dashboard.grid.js');
$this->addJsFile('multiselect.js');
$this->includeJSfile('app/views/monitoring.dashboard.view.js.php');

$is_new = !array_key_exists('dashboardid', $data['dashboard']);

$sharing_form = include 'monitoring.dashboard.sharing_form.php';
$edit_form = include 'monitoring.dashboard.edit_form.php';
$breadcrumbs = include 'monitoring.dashboard.breadcrumbs.php';

$dashboard_data = [];
if (!$is_new) {
	$dashboard_data = [
		'id' => $data['dashboard']['dashboardid']
	];
}

$edit_button = new CButton('dashbrd-edit',_('Edit dashboard'));
if (!$data['dashboard']['editable']) {
	$edit_button->setAttribute('disabled', 'disabled');
}

(new CWidget())
	->setTitle($data['dashboard']['name'])
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			// 'Edit dashboard' should be first one in list,
			// because it will be visually replaced by last item of new list, when clicked
			->addItem($edit_button)
			->addItem((new CButton(SPACE))
				->addClass(ZBX_STYLE_BTN_ACTION)
				->setTitle(_('Actions'))
				->setAttribute(
					'data-menu-popup',
					CJs::encodeJson([
						'type' => 'dashboard',
						'label' => _('Actions'),
						'items' => [
							[
								'name' => 'sharing',
								'label' => _('Sharing'),
								'form_data' => [
									'dashboardid' => $is_new ? null : $data['dashboard']['dashboardid'],
								],
								'disabled' => !$data['dashboard']['editable'] || $is_new
							]
						]
					])
				)
			)
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem((new CList())
		->addItem($breadcrumbs)
		->addClass(ZBX_STYLE_OBJECT_GROUP)
	)
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER))
	->addItem($edit_form)
	->addItem($sharing_form)
	->show();

/*
 * Javascript
 */
// activating blinking
$this->addPostJS('jqBlink.blink();');

// Initialize dashboard grid
$this->addPostJS(
	'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER.'")'.
		'.dashboardGrid()'.
		'.dashboardGrid("setDashboardData", '.CJs::encodeJson($dashboard_data).')'.
		'.dashboardGrid("setWidgetDefaults", '.CJs::encodeJson($data['widgetDefaults']).')'.
		'.dashboardGrid("addWidgets", '.CJs::encodeJson($data['grid_widgets']).');'
);

if ($is_new) {
	// Edit Form should be opened after multiselect initialization
	$this->addPostJS(
		'jQuery(document).on("' . $user_multiselect->getJsEventName() . '", function() {
				showEditMode();
				dashbrd_config();
			});'
	);
}
