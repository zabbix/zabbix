<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerCepRuleUpdate extends CControllerCepRuleGeneral {

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules(existing: true));

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = array_filter([
				'form_errors' => $form_errors,
				'error' => !$form_errors
					? [
						'title' =>_('Cannot update complex event processing rule'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
					: null
			]);

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function doAction() {
		$result = API::CepRule()->update($this->prepareApiRequest());

		$output = [];
		if ($result && $this->getInput('_cep_rule_reset', false) == 1) {
			$response = $this->requestCepRuleReset($this->getInput('cepruleid'));

			if (!$response['success']) {
				error(_('Complex event processing rule reset failed.'));
				error($response['error']);
			}
		}

		if ($result) {
			$output['success']['title'] = _('Complex event processing rule updated');
			$output['success']['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'ceprule.list')
				->setArgument('page', CPagerHelper::loadPage('ceprule.list', null))
				->getUrl();

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update complex event processing rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}

	protected function requestCepRuleReset(string $cepruleid): array {
		['ZBX_SERVER' => $host, 'ZBX_SERVER_PORT' => $port] = ZBase::getConfig();
		$server = new CZabbixServer($host, $port,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $server->resetCepRule((array) $cepruleid, CSessionHelper::getId());

		return [
			'success' => $result,
			'error' => $server->getError(),
			'debug' => $server->getDebug()
		];
	}
}
