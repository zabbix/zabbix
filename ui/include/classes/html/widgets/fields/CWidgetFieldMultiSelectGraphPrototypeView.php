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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGraphPrototype;

class CWidgetFieldMultiSelectGraphPrototypeView extends CWidgetFieldMultiSelectView {

	public function __construct(CWidgetFieldMultiSelectGraphPrototype $field) {
		parent::__construct($field);
	}

	protected function getObjectName(): string {
		return 'graph_prototypes';
	}

	protected function getObjectLabels(): array {
		return ['object' => _('Graph prototype'), 'objects' => _('Graph prototypes')];
	}

	protected function getPopupParameters(): array {
		$parameters = $this->popup_parameters + [
			'srctbl' => 'graph_prototypes',
			'srcfld1' => 'graphid',
			'srcfld2' => 'name',
			'with_graph_prototypes' => true
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
