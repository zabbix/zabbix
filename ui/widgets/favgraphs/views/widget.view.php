<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Favorite graphs widget view.
 *
 * @var CView $this
 * @var array $data
 */

$table = (new CTableInfo())->setNoDataMessage(_('No graphs added.'));

foreach ($data['graphs'] as $graph) {
	$url = (new CUrl('history.php'))
		->setArgument('action', HISTORY_GRAPH)
		->setArgument('itemids', [$graph['itemid']]);

	$table->addRow([
		$data['allowed_ui_latest_data']
			? new CLink($graph['label'], $url)
			: $graph['label'],
		(new CCol(
			(new CButtonIcon(ZBX_ICON_REMOVE_SMALLER, _('Delete')))
				->setAttribute('data-itemid', $graph['itemid'])
				->setAttribute('aria-label', _xs('Remove, %1$s', 'screen reader', $graph['label']))
				->onClick('rm4favorites("itemid", this.dataset.itemid);')
		))->addClass(ZBX_STYLE_LIST_TABLE_ACTIONS)
	]);
}

(new CWidgetView($data))
	->addItem($table)
	->show();
