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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectMediaType;

class CWidgetFieldMultiSelectMediaTypeView extends CWidgetFieldMultiSelectView {

	public function __construct(CWidgetFieldMultiSelectMediaType $field) {
		parent::__construct($field);
	}

	protected function getObjectName(): string {
		return 'media_types';
	}

	protected function getObjectLabels(): array {
		return ['object' => _('Media type'), 'objects' => _('Media types')];
	}

	protected function getPopupParameters(): array {
		return $this->popup_parameters + [
			'srctbl' => 'media_types',
			'srcfld1' => 'mediatypeid',
			'srcfld2' => 'name'
		];
	}
}
