<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CScreenUrl extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		// prevent from resolving macros in configuration page
		if ($this->mode != SCREEN_MODE_PREVIEW && $this->mode != SCREEN_MODE_SLIDESHOW) {
			return $this->getOutput(
				new CIFrame($this->screenitem['url'], $this->screenitem['width'], $this->screenitem['height'], 'auto')
			);
		}
		elseif ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid == 0) {
			return $this->getOutput(new CTableInfo(_('No host selected.')));
		}

		$resolveHostMacros = ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM || $this->isTemplatedScreen);

		$url = CMacrosResolverHelper::resolveScreenElementURL(array(
			'config' => $resolveHostMacros ? 'screenElementURL' : 'screenElementURLUser',
			'url' => $this->screenitem['url'],
			'hostid' => $resolveHostMacros ? $this->hostid : 0
		));

		$this->screenitem['url'] = $url ? $url : $this->screenitem['url'];

		return $this->getOutput(
			new CIFrame($this->screenitem['url'], $this->screenitem['width'], $this->screenitem['height'], 'auto')
		);
	}
}
