<?php declare(strict_types = 1);

namespace Modules\Example_A\Actions;

use CController as CAction;

class Test extends CAction {

	public function init() {
		$this->disableSIDvalidation();
	}

	/**
	 * @inheritDoc
	 */
	protected function checkPermissions() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function checkInput() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function doAction() {
		$response = new \CControllerResponseData([]);
		$response->setTitle('1st Module');

		$this->setResponse($response);
	}
}
