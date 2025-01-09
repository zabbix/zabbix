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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectAction;

class CWidgetFieldMultiSelectActionView extends CWidgetFieldMultiSelectView {

	public function __construct(CWidgetFieldMultiSelectAction $field) {
		parent::__construct($field);
	}

	protected function getObjectName(): string {
		return 'actions';
	}

	protected function getObjectLabels(): array {
		return ['object' => _('Action'), 'objects' => _('Actions')];
	}

	protected function getPopupParameters(): array {
		return $this->popup_parameters + [
			'srctbl' => 'actions',
			'srcfld1' => 'actionid',
			'srcfld2' => 'name'
		];
	}
}
