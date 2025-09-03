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


namespace Widgets\Geomap\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldIntegerBox,
	CWidgetFieldLatLng,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTags
};

use Widgets\Geomap\Widget;

/**
 * Geomap widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('tags')
			)
			->addField(
				new CWidgetFieldLatLng('default_view', _('Initial view'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('clustering_mode', _('Clustering'), [
					Widget::CLUSTERING_MODE_AUTO => _('Auto'),
					Widget::CLUSTERING_MODE_MANUAL => _('Zoom level')
				]))->setDefault(Widget::CLUSTERING_MODE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('clustering_zoom_level', _('Zoom level'), 0, ZBX_GEOMAP_MAX_ZOOM))
					->setDefault(0)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($this->getFieldValue('clustering_mode') == Widget::CLUSTERING_MODE_AUTO) {
			$this->getField('clustering_zoom_level')->setValue(0);
		}

		return [];
	}
}
