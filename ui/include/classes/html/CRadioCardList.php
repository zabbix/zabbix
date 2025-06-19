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

	public const ZBX_STYLE_CLASS = 'radio-card-list';
	public const ZBX_STYLE_CLASS_CARD = 'radio-card';
	public const ZBX_STYLE_CLASS_LABEL = 'radio-card-label';
	public const ZBX_STYLE_CLASS_SELECTOR = 'radio-card-selector';

	private string $name;
	private string $value;

	private bool $enabled = true;
	private bool $readonly = false;

	private bool $autofocused = false;
	private bool $autocomplete = false;

	/**
	 * Array of value elements.
	 */
	protected array $values = [];

	public function __construct($name, $value = '') {
		parent::__construct();

		$this->name = $name;
		$this->value = $value;

		$this
			->setId(zbx_formatDomId($name))
			->addClass(self::ZBX_STYLE_CLASS);
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
			$value += ['id' => null, 'disabled' => null, 'on_change' => null];
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

			$this->addItem(
				(new CListItem([
					(new CLabel([
						$value['label'],
						(new CSpan($radio))->addClass(self::ZBX_STYLE_CLASS_SELECTOR)
					]))->addClass(self::ZBX_STYLE_CLASS_LABEL),
					$value['content'] ?? null
				]))->addClass(self::ZBX_STYLE_CLASS_CARD)
			);

		}

		if ($this->getAttribute('aria-required') === 'true') {
			$this->setAttribute('role', 'radiogroup');
		}

		return parent::toString($destroy);
	}
}
