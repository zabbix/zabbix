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


namespace Zabbix\Widgets\Fields;

use CArrayHelper;

class CWidgetFieldMultiSelectHostInventory extends CWidgetFieldMultiSelect {

	public const DEFAULT_VIEW = \CWidgetFieldMultiSelectHostInventoryView::class;

	private array $inventory_fields;

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->inventory_fields = getHostInventories();

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32)
			->setValidationRules(['type' => API_INTS32, 'uniq' => true])
			->setValuesCaptions($this->getValuesCaptions());
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		foreach ($this->getValue() as $nr) {
			if (!array_key_exists($nr, $this->inventory_fields)) {
				return [_s('Invalid parameter "%1$s": %2$s.', _('Inventory fields'), _('invalid inventory field'))];
			}
		}

		return [];
	}

	public function getValuesCaptions(): array {
		$captions = [];

		foreach ($this->getValue() as $nr) {
			if (array_key_exists($nr, $this->inventory_fields)) {
				$captions[$nr] = [
					'id' => $nr,
					'name' => $this->inventory_fields[$nr]['title']
				];
			}
		}

		CArrayHelper::sort($captions, ['name']);

		return $captions;
	}
}
