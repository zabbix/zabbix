<?php
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


class CRadioCardList extends CList {
	private const ZBX_STYLE_CLASS = 'radio-card-list';

	private string $name;
	private string $value;

	private bool $enabled = false;
	private bool $readonly = false;

	private bool $autofocused = false;
	private bool $autocomplete = false;

	/**
	 * Array of value elements.
	 *
	 * @property array
	 *     string 'name'       Input form element label.
	 *     string 'value'     Input form element value.
	 *     string 'id'        Input form element id attribute.
	 */
	protected array $values = [];

	public function __construct($name, $value) {
		parent::__construct();

		$this->name = $name;
		$this->value = $value;

		$this->setId(zbx_formatDomId($name));
	}

	public function setValues(array $values): self {
		foreach ($values as $value) {
			$this->addValue($value);
		}

		return $this;
	}

	/**
	 * Add value.
	 *
	 * @param array $value
	 *     string           $value['label']
	 *     string           $value['value']
	 *     CTag|string|null $value['content']
	 *     string|null      $value['id']
	 *     bool             $value['disabled']
	 *
	 * @return CRadioCardList
	 */
	public function addValue(array $value): self {
		$this->values[] = $value;

		return $this;
	}

	public function setEnabled(bool $enabled): self {
		$this->enabled = $enabled;

		return $this;
	}

	public function setReadonly(bool $readonly): self {
		$this->readonly = $readonly;

		return $this;
	}

	/**
	 * Prevent browser to autocomplete input element.
	 */
	public function disableAutocomplete(): self {
		$this->autocomplete = false;

		return $this;
	}

	public function toString($destroy = true) {

		foreach ($this->values as $key => $value) {
			if ($value['id'] === null) {
				$value['id'] = zbx_formatDomId($this->name).'_'.$key;
			}

			$radio = (new CInput('radio', $this->name, $value['value']))
				->setEnabled($this->enabled && !$value['disabled'])
				->onChange($value['on_change'])
				->setId($value['id']);

			if ($value['value'] === $this->value) {
				$radio->setAttribute('checked', 'checked');

				if ($this->autofocused) {
					$radio->setAttribute('autofocus', 'autofocus');
				}
			}

			if (!$this->autocomplete) {
				$radio->setAttribute('autocomplete', 'off');
			}

			if ($this->readonly) {
				$radio->setAttribute('readonly', 'readonly');
			}

			if ($this->modern) {
				$this->addItem((new CListItem([$radio, new CLabel($value['name'], $value['id'])]))->addClass(
					array_key_exists('class', $value) ? $value['class'] : null
				));
			}
			else {
				$radio->addClass(ZBX_STYLE_CHECKBOX_RADIO);
				$this->addItem((new CListItem([$radio, new CLabel([new CSpan(), $value['name']], $value['id'])]))
					->addClass(array_key_exists('class', $value) ? $value['class'] : null)
				);
			}
		}

		if ($this->getAttribute('aria-required') === 'true') {
			$this->setAttribute('role', 'radiogroup');
		}

		return parent::toString($destroy);
	}
}
