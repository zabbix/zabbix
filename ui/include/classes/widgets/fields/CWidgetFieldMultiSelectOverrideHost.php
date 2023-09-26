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

use CWidgetsData;
use Zabbix\Widgets\CWidgetField;

class CWidgetFieldMultiSelectOverrideHost extends CWidgetFieldMultiSelectHost {

	public function __construct() {
		parent::__construct('override_hostid', _('Override host'));

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
