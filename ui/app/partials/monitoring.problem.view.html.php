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


/**
 * @var CPartial $this
 * @var array    $data
 */

$allowed = [
	'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
	'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
	'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
	'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
	'suppress_problems' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
	'rank_change' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
];

echo (new CForm('post', 'zabbix.php'))
	->setId('problem_form')
	->setName('problem')
	->addItem([
		(new CDiv())->setId('problems'),
		(new CActionButtonList('action', 'eventids', [
			'acknowledge.edit' => [
				'content' => (new CSimpleButton(_('Mass update')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-massupdate-problem')
					->addClass('js-no-chkbxrange')
					->setEnabled($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
						|| $allowed['close'] || $allowed['suppress_problems'] || $allowed['rank_change']
					)
			]
		], 'problem'))->setAddSelectedCountElement(false)
	]);
