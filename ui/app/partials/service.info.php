<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 */

$parents = [];

while ($parent = array_shift($data['service']['parents'])) {
	$parents[] = (new CLink($parent['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'service.list')
			->setArgument('serviceid', $parent['serviceid'])
	))->setAttribute('data-serviceid', $parent['serviceid']);

	$parents[] = CViewHelper::showNum($parent['children']);

	if (!$data['service']['parents']) {
		break;
	}

	$parents[] = ', ';
}

(new CDiv([
	(new CDiv())
		->addClass(ZBX_STYLE_SERVICE_INFO_GRID)
		->addItem([
			(new CDiv($data['service']['name']))->addClass(ZBX_STYLE_SERVICE_NAME),
			(new CDiv(
				$data['is_editable']
					? (new CButton(null))
						->addClass(ZBX_STYLE_BTN_EDIT)
						->addClass('js-edit-service')
						->setAttribute('data-serviceid', $data['service']['serviceid'])
					: null
			))->addClass(ZBX_STYLE_SERVICE_ACTIONS)
		]),
	(new CDiv())
		->addClass(ZBX_STYLE_SERVICE_INFO_GRID)
		->addItem([
			(new CDiv(_('Parent services')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($parents))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('Status')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv(
				(new CDiv(CSeverityHelper::getName((int) $data['service']['status'])))->addClass(ZBX_STYLE_SERVICE_STATUS))
			)->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('SLA')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv(($data['service']['showsla'] == SERVICE_SHOW_SLA_ON)
				? sprintf('%.4f', $data['service']['goodsla'])
				: ''
			))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('Tags')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($data['service']['tags']))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
]))
	->addClass(ZBX_STYLE_SERVICE_INFO)
	->addClass('service-status-'.CSeverityHelper::getStyle((int) $data['service']['status']))
	->show();
