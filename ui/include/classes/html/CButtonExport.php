<?php declare(strict_types = 1);
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


class CButtonExport extends CList {

	/**
	 * Create CButtonExport instance.
	 *
	 * @param string $action              Export controller action.
	 * @param string $back_url            URL to redirect back to once export is complete.
	 */
	public function __construct(string $action, string $back_url) {
		parent::__construct([
			(new CSubmit('export', _('Export')))
				->removeAttribute('id')
				->removeAttribute('name')
				->removeAttribute('value')
				->addClass(ZBX_STYLE_BTN_ALT)
				->onClick('var $_form = jQuery(this).closest("form");'.
					// Save the original form action.
					'if (!$_form.data("action")) {'.
						'$_form.data("action", $_form.attr("action"));'.
					'}'.
					'$_form.attr("action", '.json_encode(
						(new CUrl('zabbix.php'))
							->setArgument('action', $action)
							->setArgument('format', CExportWriterFactory::YAML)
							->setArgument('backurl', $back_url)
							->getUrl()
					).');'
				),
			(new CButton('export', '&#8203;'))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON)
				->setMenuPopup([
					'type' => 'dropdown',
					'data' => [
						'submit_form' => true,
						'items' => [
							[
								'label' => _('YAML'),
								'url' => (new CUrl('zabbix.php'))
											->setArgument('action', $action)
											->setArgument('format', CExportWriterFactory::YAML)
											->setArgument('backurl', $back_url)
											->getUrl()
							],
							[
								'label' => _('XML'),
								'url' => (new CUrl('zabbix.php'))
											->setArgument('action', $action)
											->setArgument('format', CExportWriterFactory::XML)
											->setArgument('backurl', $back_url)
											->getUrl()
							],
							[
								'label' => _('JSON'),
								'url' => (new CUrl('zabbix.php'))
											->setArgument('action', $action)
											->setArgument('format', CExportWriterFactory::JSON)
											->setArgument('backurl', $back_url)
											->getUrl()
							]
						]
					]
				])
		]);

		$this->addClass(ZBX_STYLE_BTN_SPLIT);
	}
}
