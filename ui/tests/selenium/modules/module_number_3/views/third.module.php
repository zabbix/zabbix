<?php

(new CWidget())
	->addItem(
		(new CTag('h1', true, 'You should not see this message'))
			->addStyle('font-size: 192px;text-align: center;color: orange;padding: 250px 0;')
	)->show();
