<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


abstract class CWidgetFieldMultiSelectView extends CWidgetFieldView {

	protected CMultiSelect $multiselect;

	protected array $data;

	public function getLabel(): ?CLabel {
		$label = parent::getLabel();

		return $label !== null
			? $label->setForId($this->getForId())
			: null;
	}

	public function getJavaScript(): string {
		return $this->multiselect->getPostJS();
	}

	protected function getMultiselect($object_name, $popup_parameters, $filter_preselect_fields = []): CMultiSelect {
		$options = [
			'name' => $this->getForId(),
			'object_name' => $object_name,
			'multiple' => $this->field->isMultiple(),
			'data' => $this->data,
			'popup' => [
				'parameters' => [
					'dstfrm' => $this->form_name,
					'dstfld1' => zbx_formatDomId($this->getForId())
				] + $popup_parameters
			] + $filter_preselect_fields,
			'add_post_js' => false
		];

		$this->multiselect = (new CMultiSelect($options))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired($this->isRequired());

		return $this->multiselect;
	}

	private function getForId(): string {
		return $this->field->getName().($this->field->isMultiple() ? '[]' : '').'_ms';
	}
}
