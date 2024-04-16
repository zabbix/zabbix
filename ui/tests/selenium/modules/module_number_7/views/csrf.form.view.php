<?php

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get($data['action'])))->removeId())
	->addVar('action', $data['action'])
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$grid = new CFormGrid();

$grid->addItem([
	new CLabel(_('Text')),
	new CFormField(
		(new CInput('text', 'text', $data['text']))
	)
]);

$form->addItem((new CTabView())
	->addTab('default', null, $grid)
	->setFooter(makeFormFooter(
		(new CSubmit('preview', _('Submit')))->setAttribute('value', 1)
	))
);

(new CHtmlPage())
	->setTitle(_('CSRF token test'))
	->addItem($form)
	->show();
