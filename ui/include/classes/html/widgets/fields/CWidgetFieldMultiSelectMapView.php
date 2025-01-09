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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectMap;

class CWidgetFieldMultiSelectMapView extends CWidgetFieldMultiSelectView {

	public function __construct(CWidgetFieldMultiSelectMap $field) {
		parent::__construct($field);
	}

	protected function getObjectName(): string {
		return 'sysmaps';
	}

	protected function getObjectLabels(): array {
		return ['object' => _('Map'), 'objects' => _('Maps')];
	}

	protected function getPopupParameters(): array {
		return [
			'srctbl' => 'sysmaps',
			'srcfld1' => 'sysmapid'
		];
	}
}
