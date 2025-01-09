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

use CWidgetsData;
use Zabbix\Widgets\CWidgetField;

class CWidgetFieldMultiSelectOverrideHost extends CWidgetFieldMultiSelectHost {

	public function __construct(string $name = 'override_hostid', string $label = null) {
		parent::__construct($name, $label ?? _('Override host'));

		$this
			->setMultiple(false)
			->setInType(CWidgetsData::DATA_TYPE_HOST_ID)
			->preventDefault()
			->acceptDashboard();
	}

	public function getDefault(): array {
		if ($this->isTemplateDashboard()) {
			return [
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_ID
				)
			];
		}

		return parent::getDefault();
	}

	public function isWidgetAccepted(): bool {
		return !$this->isTemplateDashboard();
	}

	public function validate(bool $strict = false): array {
		if ($strict && $this->isTemplateDashboard()) {
			$this->setValue([
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_ID
				)
			]);
		}

		return parent::validate($strict);
	}
}
