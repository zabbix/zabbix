<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


use Zabbix\Widgets\Fields\CWidgetFieldHostPatternSelect;

class CWidgetFieldHostPatternSelectView extends CWidgetFieldView {

	private string $placeholder = '';

	private array $filter_preselect = [];

	public function __construct(CWidgetFieldHostPatternSelect $field) {
		$this->field = $field;
		$this->placeholder = _('patterns');
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function getFocusableElementId(): string {
		return zbx_formatDomId($this->field->getName().'[]').'_ms';
	}

	public function getView(): CPatternSelect {
		$options = [
			'name' => $this->field->getName().'[]',
			'object_name' => 'hosts',
			'data' => $this->field->getValue(),
			'placeholder' => $this->placeholder,
			'wildcard_allowed' => 1,
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $this->form_name,
					'dstfld1' => zbx_formatDomId($this->field->getName().'[]')
				]
			],
			'add_post_js' => false
		];

		if ($this->filter_preselect) {
			$options['popup']['filter_preselect'] = $this->filter_preselect;
		}

		return (new CPatternSelect($options))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setEnabled(!$this->isDisabled())
			->setAriaRequired($this->isRequired());
	}

	public function getJavaScript(): string {
		$field_id = zbx_formatDomId($this->field->getName().'[]');

		return 'jQuery("#'.$field_id.'").multiSelect();';
	}

	public function setFilterPreselect(array $filter_preselect): self {
		$this->filter_preselect = $filter_preselect;

		return $this;
	}
}
