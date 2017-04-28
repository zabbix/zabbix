<?php

$widget = (new CWidget())
	->setTitle(_('Dashboards'));

$dashboardForm = (new CForm())
	->setName('dashboardForm');

$dashboardTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_dashboards'))
				->onClick("checkAll('".$dashboardForm->getName()."', 'all_dashboards', 'dashboardids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sort_order'])
	]);

foreach ($data['dashboards'] as $dashboard) {
	if ($dashboard['editable']) {
		$checkbox = new CCheckBox('dashboardids['.$dashboard['dashboardid'].']', $dashboard['dashboardid']);
	}
	else {
		$checkbox = (new CCheckBox('dashboardids['.$dashboard['dashboardid'].']', $dashboard['dashboardid']))
			->setAttribute('disabled', 'disabled');
	}
	$dashboardTable->addRow([$checkbox, new CLink($dashboard['name'], $dashboard['view_link'])]);
}
$buttons = [
	'dashboard.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected dashboards?')]
];

$dashboardForm->addItem(
	[
		$dashboardTable,
		$data['paging'],
		new CActionButtonList(
			'action',
			'dashboardids',
			$buttons,
			$data['checkbox_cookie_suffix']
		)
	]
);

$widget->addItem($dashboardForm);
$widget->show();
