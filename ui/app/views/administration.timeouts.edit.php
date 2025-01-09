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


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.timeouts.edit.js.php');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('timeouts')))->removeId())
	->setId('timeouts-form')
	->setName('timeouts_form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'timeouts.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$form_grid = (new CFormGrid())
	->addItem(
		(new CFormFieldset(_('Timeouts for item types')))
			->addItem([
				(new CLabel(_('Zabbix agent'), 'timeout_zabbix_agent'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_zabbix_agent', $data['timeout_zabbix_agent'], false,
						DB::getFieldLength('config', 'timeout_zabbix_agent')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
						->setAttribute('autofocus', 'autofocus')
				)
			])
			->addItem([
				(new CLabel(_('Simple check'), 'timeout_simple_check'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_simple_check', $data['timeout_simple_check'], false,
						DB::getFieldLength('config', 'timeout_simple_check')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('SNMP agent'), 'timeout_snmp_agent'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_snmp_agent', $data['timeout_snmp_agent'], false,
						DB::getFieldLength('config', 'timeout_snmp_agent')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('External check'), 'timeout_external_check'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_external_check', $data['timeout_external_check'], false,
						DB::getFieldLength('config', 'timeout_external_check')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Database monitor'), 'timeout_db_monitor'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_db_monitor', $data['timeout_db_monitor'], false,
						DB::getFieldLength('config', 'timeout_db_monitor')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('HTTP agent'), 'timeout_http_agent'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_http_agent', $data['timeout_http_agent'], false,
						DB::getFieldLength('config', 'timeout_http_agent')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('SSH agent'), 'timeout_ssh_agent'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_ssh_agent', $data['timeout_ssh_agent'], false,
						DB::getFieldLength('config', 'timeout_ssh_agent')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('TELNET agent'), 'timeout_telnet_agent'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_telnet_agent', $data['timeout_telnet_agent'], false,
						DB::getFieldLength('config', 'timeout_telnet_agent')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Script'), 'timeout_script'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_script', $data['timeout_script'], false,
						DB::getFieldLength('config', 'timeout_script')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Browser'), 'timeout_browser'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout_browser', $data['timeout_browser'], false,
						DB::getFieldLength('config', 'timeout_browser')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
	)
	->addItem(
		(new CFormFieldset(_('Network timeouts for UI')))
			->addItem([
				(new CLabel(_('Communication'), 'socket_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('socket_timeout', $data['socket_timeout'], false,
						DB::getFieldLength('config', 'socket_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Connection'), 'connect_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('connect_timeout', $data['connect_timeout'], false,
						DB::getFieldLength('config', 'connect_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Media type test'), 'media_type_test_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('media_type_test_timeout', $data['media_type_test_timeout'], false,
						DB::getFieldLength('config', 'media_type_test_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Script execution'), 'script_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('script_timeout', $data['script_timeout'], false,
						DB::getFieldLength('config', 'script_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Item test'), 'item_test_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('item_test_timeout', $data['item_test_timeout'], false,
						DB::getFieldLength('config', 'item_test_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Scheduled report test'), 'report_test_timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('report_test_timeout', $data['report_test_timeout'], false,
						DB::getFieldLength('config', 'report_test_timeout')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
	)
	->addItem(
		new CFormActions(new CSubmit('update', _('Update')), [new CButton('reset-defaults', _('Reset defaults'))])
	);

$form->addItem(
	(new CTabView())->addTab('timeouts-tab', _('Timeouts'), $form_grid)
);

(new CHtmlPage())
	->setTitle(_('Timeouts'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_TIMEOUTS))
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'default_timeouts' => [
			'timeout_zabbix_agent' => DB::getDefault('config', 'timeout_zabbix_agent'),
			'timeout_simple_check' => DB::getDefault('config', 'timeout_simple_check'),
			'timeout_snmp_agent' => DB::getDefault('config', 'timeout_snmp_agent'),
			'timeout_external_check' => DB::getDefault('config', 'timeout_external_check'),
			'timeout_db_monitor' => DB::getDefault('config', 'timeout_db_monitor'),
			'timeout_http_agent' => DB::getDefault('config', 'timeout_http_agent'),
			'timeout_ssh_agent' => DB::getDefault('config', 'timeout_ssh_agent'),
			'timeout_telnet_agent' => DB::getDefault('config', 'timeout_telnet_agent'),
			'timeout_script' => DB::getDefault('config', 'timeout_script'),
			'timeout_browser' => DB::getDefault('config', 'timeout_browser'),
			'socket_timeout' => DB::getDefault('config', 'socket_timeout'),
			'connect_timeout' => DB::getDefault('config', 'connect_timeout'),
			'media_type_test_timeout' => DB::getDefault('config', 'media_type_test_timeout'),
			'script_timeout' => DB::getDefault('config', 'script_timeout'),
			'item_test_timeout' => DB::getDefault('config', 'item_test_timeout'),
			'report_test_timeout' => DB::getDefault('config', 'report_test_timeout')
		]
	]).');
'))
	->setOnDocumentReady()
	->show();
