<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CSeverity extends CList {

	/**
	 * @param string $options['name']
	 * @param int    $options['value']		(optional) Default: TRIGGER_SEVERITY_NOT_CLASSIFIED
	 * @param bool   $options['all']		(optional)
	 * @param bool	 $enabled				If set to false, radio buttons (severities) are marked as disabled.
	 */
	public function __construct(array $options = [], $enabled = true) {
		parent::__construct();

		$id = zbx_formatDomId($options['name']);

		$this->addClass(ZBX_STYLE_RADIO_SEGMENTED);
		$this->setId($id);

		if (!array_key_exists('value', $options)) {
			$options['value'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}

		$severity_from = (array_key_exists('all', $options) && $options['all'])
			? -1
			: TRIGGER_SEVERITY_NOT_CLASSIFIED;

		$config = select_config();

		for ($severity = $severity_from; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$name = ($severity == -1) ? _('all') : getSeverityName($severity, $config);
			$class = ($severity == -1) ? null : getSeverityStyle($severity);

			$radio = (new CInput('radio', $options['name'], $severity))
				->setId(zbx_formatDomId($options['name'].'_'.$severity));

			if ($severity === $options['value']) {
				$radio->setAttribute('checked', 'checked');
			}

			$enabled ? $radio->removeAttribute('disabled') : $radio->setAttribute('disabled', 'disabled');

			parent::addItem(
				(new CListItem([
					$radio, new CLabel($name, $options['name'].'_'.$severity)
				]))->addClass($class)
			);
		}
	}
}
