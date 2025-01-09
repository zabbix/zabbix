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


namespace Widgets\SystemInfo\Includes;

use CSettingsHelper;
use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldRadioButtonList
};

/**
 * System information widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('info_type', _('Show'), [
					ZBX_SYSTEM_INFO_SERVER_STATS => _('System stats'),
					ZBX_SYSTEM_INFO_HAC_STATUS => _('High availability nodes')
				]))->setDefault(ZBX_SYSTEM_INFO_SERVER_STATS)
			)
			->addField(CSettingsHelper::isSoftwareUpdateCheckEnabled()
				? new CWidgetFieldCheckBox('show_software_update_check_details',
					_('Show software update check details')
				)
				: null
			);
	}
}
