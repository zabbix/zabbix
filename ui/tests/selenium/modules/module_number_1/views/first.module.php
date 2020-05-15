<?php

(new CWidget())
	->addItem(
		(new CTag('h1', true, 'If You see this message - 1st module is working'))
			->addStyle('font-size: 64px;text-align: center;color: red;padding: 50px 0;')
	)->show();
