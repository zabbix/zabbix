<?php

namespace Modules\EmptyNamespace\Actions;

use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
