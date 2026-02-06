<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @var CPartial $this
 * @var array    $data
 */

echo (new CForm('GET', 'history.php'))
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH))
	->addItem([
		(new CDiv())->setId('latest'),
		(new CActionButtonList('graphtype', 'itemids', [
			GRAPH_TYPE_STACKED => [
				'name' => _('Display stacked graph'),
				'attributes' => ['data-required' => 'graph', 'data-required-count' => 2]
			],
			GRAPH_TYPE_NORMAL => [
				'name' => _('Display graph'),
				'attributes' => ['data-required' => 'graph']
			],
			'item.execute' => [
				'content' => (new CSimpleButton(_('Execute now')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massexecute-item')
					->addClass('js-no-chkbxrange')
					->setAttribute('data-required', 'execute')
			]
		], 'latest'))->setAddSelectedCountElement(false)
	]);
