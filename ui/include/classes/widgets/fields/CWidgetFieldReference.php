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

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldReference extends CWidgetField {

	public const DEFAULT_VALUE = '';

	// This field name is reserved by Zabbix for this particular use case. See comments below.
	public const FIELD_NAME = 'reference';

	/**
	 * Reference widget field. If added to widget, will generate unique value across the dashboard
	 * and will be saved to database. This field should be used to save relations between widgets.
	 */
	public function __construct() {
		/*
		 * All reference fields for all widgets on dashboard should share the same name.
		 * It is needed to make possible search if value is not taken by some other widget in same dashboard.
		 */
		parent::__construct(self::FIELD_NAME);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($strict && $errors) {
			$this->setValue('');
			$errors = [];
		}

		return $errors;
	}
}
