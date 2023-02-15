<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Zabbix\Widgets\Fields;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldSelectResource extends CWidgetField {

	public const RESOURCE_TYPE_SYSMAP = 1;

	public const DEFAULT_VALUE = '0';

	private string $resource_type;

	private array $popup_options = [
		'srctbl' => null,
		'srcfld1' => null,
		'srcfld2' => null,
		'dstfld1' => null,
		'dstfld2' => null,
		'dstfrm' => null
	];

	/**
	 * Select resource type widget field. Will create text box field with select button,
	 * that will allow selecting specified resource.
	 */
	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->popup_options = array_merge($this->popup_options, [
			'dstfld1' => $this->name,
			'dstfld2' => $this->name.'_caption'
		]);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function getResourceType(): int {
		return $this->resource_type;
	}

	public function setResourceType(int $resource_type): self {
		$this->resource_type = $resource_type;

		if ($this->resource_type == self::RESOURCE_TYPE_SYSMAP) {
			$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_MAP);

			$this->popup_options = array_merge($this->popup_options, [
				'srctbl' => 'sysmaps',
				'srcfld1' => 'sysmapid',
				'srcfld2' => 'name'
			]);
		}

		return $this;
	}

	public function setFlags(int $flags): self {
		parent::setFlags($flags);

		if (($flags & self::FLAG_NOT_EMPTY) !== 0) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules();
		}

		return $this;
	}

	public function getPopupOptions(string $form_name): array {
		return array_merge($this->popup_options, [
			'dstfrm' => $form_name
		]);
	}
}
