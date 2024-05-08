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


use Zabbix\Widgets\Fields\CWidgetFieldPatternSelect;

abstract class CWidgetFieldPatternSelectView extends CWidgetFieldView {

	protected array $filter_preselect = [];

	protected array $popup_parameters = [];

	protected string $placeholder = '';

	protected bool $wildcard_allowed = false;

	public function __construct(CWidgetFieldPatternSelect $field) {
		$this->field = $field;
		$this->placeholder = _('patterns');
	}

	abstract protected function getObjectName(): string;

	public function getId(): string {
		return zbx_formatDomId($this->getName().'[]');
	}

	public function getLabel(): ?CLabel {
		$label = parent::getLabel();

		if ($label !== null) {
			$label->setFor($this->getId().'_ms');
		}

		return $label;
	}

	public function getView(): CPatternSelect {
		$options = [
			'name' => $this->getName().'[]',
			'object_name' => $this->getObjectName(),
			'data' => $this->field->getValue(),
			'disabled' => $this->isDisabled(),
			'placeholder' => $this->placeholder,
			'popup' => [
				'parameters' => [
					'dstfrm' => $this->form_name,
					'dstfld1' => $this->getId()
				] + $this->getPopupParameters()
			],
			'add_post_js' => false
		];

		if ($this->filter_preselect) {
			$options['popup']['filter_preselect'] = $this->filter_preselect;
		}

		if ($this->wildcard_allowed) {
			$options['wildcard_allowed'] = true;
		}

		return (new CPatternSelect($options))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired($this->isRequired());
	}

	public function getJavaScript(): string {
		return 'jQuery("#'.$this->getId().'").multiSelect();';
	}

	public function setFilterPreselect(array $filter_preselect): self {
		$this->filter_preselect = $filter_preselect;

		return $this;
	}

	public function setPopupParameter(string $name, $value): self {
		$this->popup_parameters[$name] = $value;

		return $this;
	}

	protected function getPopupParameters(): array {
		return $this->popup_parameters;
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function setWildcardAllowed(bool $wildcard_allowed = true): self {
		$this->wildcard_allowed = $wildcard_allowed;

		return $this;
	}
}
