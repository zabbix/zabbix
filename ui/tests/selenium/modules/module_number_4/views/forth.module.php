<?php

(new CWidget())
	->addItem(
		(new CTag('h1', true, '4th module - cannot be enabled together with 1st module'))
			->addStyle('font-size: 64px;text-align: center;color: black;padding: 250px 0;')
	)->show();
