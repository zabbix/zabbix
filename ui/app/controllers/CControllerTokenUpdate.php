<?php declare(strict_types=1);
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


class CControllerTokenUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'tokenid'       => 'db token.tokenid|required|fatal',
			'name'          => 'db token.name|required|not_empty',
			'description'   => 'db token.description',
			'expires_state' => 'in 0,1|required',
			'expires_at'    => 'abs_time',
			'status'        => 'db token.status|required|in '.ZBX_AUTH_TOKEN_ENABLED.','.ZBX_AUTH_TOKEN_DISABLED,
			'action_src'    => 'fatal|required|in token.edit,user.token.edit',
			'action_dst'    => 'fatal|required|in token.list,user.token.list,token.view,user.token.view',
			'regenerate'    => 'in 1'
		];

		$validation_result = self::VALIDATION_OK;

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [];

			if ($this->getInput('expires_state') == 1) {
				$fields['expires_at'] = 'required';
			}

			if ($fields) {
				$validator = new CNewValidator($this->getInputAll(), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				if ($validator->isErrorFatal()) {
					$validation_result = self::VALIDATION_FATAL_ERROR;
				}
				elseif ($validator->isError()) {
					$validation_result = self::VALIDATION_ERROR;
				}

				$ret = $validation_result == self::VALIDATION_OK;
			}
		}
		else {
			$validation_result = $this->getValidationError();
		}

		if (!$ret) {
			switch ($validation_result) {
				case self::VALIDATION_ERROR:
					$location = (new CUrl('zabbix.php'))
						->setArgument('tokenid', $this->getInput('tokenid'))
						->setArgument('action', $this->getInput('action_src'));
					$response = new CControllerResponseRedirect($location);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update API token'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		$this->getInputs($token, ['tokenid', 'name', 'description', 'expires_at', 'status']);

		if ($this->getInput('expires_state')) {
			$parser = new CAbsoluteTimeParser();
			$parser->parse($token['expires_at']);

			$token['expires_at'] = $parser
				->getDateTime(true)
				->getTimestamp();
		}
		else {
			$token['expires_at'] = 0;
		}

		$result = API::Token()->update($token);

		if ($result) {
			if ($this->hasInput('regenerate')) {
				['tokenids' => $tokenids] = $result;
				[['userid' => $userid]] = API::Token()->get([
					'output' => ['userid'],
					'tokenids' => $tokenids
				]);
				[['token' => $auth_token]] = API::Token()->generate($tokenids);

				$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
					->setArgumentSID()
					->setArgument('action', $this->getInput('action_dst'))
				);

				[$user] = (CWebUser::$data['userid'] != $userid)
					? API::User()->get([
						'output' => ['username', 'name', 'surname'],
						'userids' => $userid
					])
					: [CWebUser::$data];

				$response->setFormData([
					'name' => $token['name'],
					'user' => getUserFullname($user),
					'auth_token' => $auth_token,
					'expires_at' => $token['expires_at'],
					'description' => $token['description'],
					'status' => $token['status']
				]);
			}
			else {
				$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
					->setArgument('action', $this->getInput('action_dst'))
					->setArgument('page', CPagerHelper::loadPage($this->getInput('action_dst'), null))
				);
				$response->setFormData(['uncheck' => '1']);
			}

			CMessageHelper::setSuccessTitle(_('API token updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', $this->getInput('action_src'))
				->setArgument('tokenid', $this->getInput('tokenid'))
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update API token'));
		}

		$this->setResponse($response);
	}
}
