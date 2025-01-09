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
				->onClick('const form = this.closest("form");'.
					/*
					 * Save the original form action.
					 * Function getAttribute()/setAttribute() is used instead of .action, because there are many
					 * buttons with name 'action' and .action selects these buttons.
					 */
					'if (!form.dataset.action) {
						form.dataset.action = form.getAttribute("action");
					}'.
					'form.setAttribute("action", '. json_encode(
						(new CUrl('zabbix.php'))
							->setArgument('action', $action)
							->setArgument('format', CExportWriterFactory::YAML)
							->setArgument('backurl', $back_url)
							->getUrl()
					).');'
				),
			(new CButton('export'))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass(ZBX_ICON_CHEVRON_DOWN_SMALL)
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
