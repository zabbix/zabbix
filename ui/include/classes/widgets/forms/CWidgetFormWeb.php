<?php
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


/**
 * Web widget form.
 */
class CWidgetFormWeb extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_WEB);

		$this->data = self::convertDottedKeys($this->data);

		// Host groups.
		$field_groups = new CWidgetFieldMsGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}

		$this->fields[$field_groups->getName()] = $field_groups;

		// Exclude host groups.
		$field_exclude_groups = new CWidgetFieldMsGroup('exclude_groupids', _('Exclude host groups'));

		if (array_key_exists('exclude_groupids', $this->data)) {
			$field_exclude_groups->setValue($this->data['exclude_groupids']);
		}

		$this->fields[$field_exclude_groups->getName()] = $field_exclude_groups;

		// Hosts field.
		$field_hosts = new CWidgetFieldMsHost('hostids', _('Hosts'));
		$field_hosts->filter_preselect_host_group_field = 'groupids_';

		if (array_key_exists('hostids', $this->data)) {
			$field_hosts->setValue($this->data['hostids']);
		}

		$this->fields[$field_hosts->getName()] = $field_hosts;

		// Tag evaltype (And/Or).
		$field_evaltype = (new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
			TAG_EVAL_TYPE_AND_OR => _('And/Or'),
			TAG_EVAL_TYPE_OR => _('Or')
		]))
			->setDefault(TAG_EVAL_TYPE_AND_OR)
			->setModern(true);

		if (array_key_exists('evaltype', $this->data)) {
			$field_evaltype->setValue($this->data['evaltype']);
		}

		$this->fields[$field_evaltype->getName()] = $field_evaltype;

		// Tags array: tag, operator and value. No label, because it belongs to previous group.
		$field_tags = new CWidgetFieldTags('tags', '');

		if (array_key_exists('tags', $this->data)) {
			$field_tags->setValue($this->data['tags']);
		}

		$this->fields[$field_tags->getName()] = $field_tags;

		// Show hosts in maintenance.
		$field_maintenance = (new CWidgetFieldCheckBox('maintenance', _('Show hosts in maintenance')))
			->setDefault(1);

		if (array_key_exists('maintenance', $this->data)) {
			$field_maintenance->setValue($this->data['maintenance']);
		}

		$this->fields[$field_maintenance->getName()] = $field_maintenance;
	}
}
