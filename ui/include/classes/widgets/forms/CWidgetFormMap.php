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


use Widgets\{
	CWidgetField,
	CWidgetForm
};

use Widgets\Fields\{
	CWidgetFieldRadioButtonList,
	CWidgetFieldReference,
	CWidgetFieldSelectResource,
	CWidgetFieldWidgetSelect
};

/**
 * Map widget form.
 */
class CWidgetFormMap extends CWidgetForm {

	public function __construct(array $values, ?string $templateid) {
		parent::__construct(WIDGET_MAP, $values, $templateid);
	}

	protected function addFields(): self {
		parent::addFields();

		$this
			->addField(
				new CWidgetFieldReference()
			)
			->addField(
				(new CWidgetFieldRadioButtonList('source_type', _('Source type'), [
					WIDGET_SYSMAP_SOURCETYPE_MAP => _('Map'),
					WIDGET_SYSMAP_SOURCETYPE_FILTER => _('Map navigation tree')
				]))
					->setDefault(WIDGET_SYSMAP_SOURCETYPE_MAP)
					->setAction('ZABBIX.Dashboard.reloadWidgetProperties()')
			);

		if (array_key_exists('source_type', $this->values)
				&& $this->values['source_type'] == WIDGET_SYSMAP_SOURCETYPE_MAP) {

			$this->addField(
				(new CWidgetFieldSelectResource('sysmapid', _('Map')))
					->setResourceType(WIDGET_FIELD_SELECT_RES_SYSMAP)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);
		}
		else {
			$this->addField(
				(new CWidgetFieldWidgetSelect('filter_widget_reference', _('Filter'), WIDGET_NAV_TREE))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);
		}

		return $this;
	}
}
