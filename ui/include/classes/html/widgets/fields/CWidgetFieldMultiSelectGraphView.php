<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGraph;

class CWidgetFieldMultiSelectGraphView extends CWidgetFieldMultiSelectView {

	public function __construct(CWidgetFieldMultiSelectGraph $field) {
		parent::__construct($field);
	}

	protected function getObjectName(): string {
		return 'graphs';
	}

	protected function getObjectLabels(): array {
		return ['object' => _('Graph'), 'objects' => _('Graphs')];
	}

	protected function getPopupParameters(): array {
		$parameters = $this->popup_parameters + [
			'srctbl' => 'graphs',
			'srcfld1' => 'graphid',
			'srcfld2' => 'name',
			'with_graphs' => true
		];

		return $parameters + ($this->field->isTemplateDashboard()
			? [
				'hostid' => $this->field->getTemplateId(),
				'hide_host_filter' => false
			]
			: [
				'real_hosts' => true
			]
		);
	}
}
