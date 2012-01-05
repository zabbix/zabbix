<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
function getRegexpByRegexpId($regexpid) {
	$sql = 'SELECT re.* '.
					' FROM regexps re '.
					' WHERE '.DBin_node('re.regexpid').
						' AND regexpid='.$regexpid;

	$db_regexp = DBfetch(DBselect($sql));
return $db_regexp;
}

// Author: Aly
// function add_regexp($regexp=array()){
function addRegexp($regexp = array()) {
	$db_fields = array('name' => null,
						'test_string' => '',
					);

	if(!check_db_fields($db_fields, $regexp)){
		error(_('Incorrect arguments passed to function').' [add_regexp]');
		return false;
	}

	$sql = 'SELECT regexpid FROM regexps WHERE name='.zbx_dbstr($regexp['name']);
	if(DBfetch(DBselect($sql))){
		info(_s('Regular expression "%s" already exists.', $regexp['name']));
		return false;
	}

	$regexpid = get_dbid('regexps','regexpid');

	$result = DBexecute('INSERT INTO regexps (regexpid,name,test_string) '.
				' VALUES ('.$regexpid.','.
						zbx_dbstr($regexp['name']).','.
						zbx_dbstr($regexp['test_string']).')');

return $result?$regexpid:false;
}

/**
 * Updates the given regular expression.
 *
 * @param $regexpid
 * @param array $regexp
 *
 * @return bool
 */
function updateRegexp($regexpid, array $regexp = array()) {
	$db_fields = array(
		'name' => null,
		'test_string' => '',
	);

	if (!check_db_fields($db_fields, $regexp)) {
		error(_('Incorrect arguments passed to function').' [updateRegexp]');
		return false;
	}

	$dbRegexp = DB::find('regexps', array(
		'name' => $regexp['name']
	));
	if ($dbRegexp) {
		$dbRegexp = reset($dbRegexp);
		if (bccomp($regexpid, $dbRegexp['regexpid']) != 0) {
			info(_s('Regular expression "%s" already exists.', $regexp['name']));

			return false;
		}
	}

	return DB::updateByPk('regexps', $regexpid, $regexp);
}

/**
 * Deletes the given regular expressions.
 *
 * @param $regexpIds
 *
 * @return bool
 */
function deleteRegexp($regexpIds) {
	return DB::delete('regexps', array(
		'regexpid' => $regexpIds
	));
}


/**
 * Updates the given expressions. Existing expressions will be updated, new ones created an missing
 * will be deleted.
 *
 * @param $regexpId
 * @param array $expressions
 *
 * @return bool
 */
function updateExpressions($regexpId, array $expressions) {

	// fetch existing expressions
	$dbExpressions = DB::find('expressions', array(
		'regexpid' => $regexpId
	));
	$dbExpressions = zbx_toHash($dbExpressions, 'expressionid');

	// handle the given expressions
	foreach ($expressions as $expression) {

		// if this is an existing expression - update it
		if (isset($expression['expressionid'])) {
			$expressionid = $expression['expressionid'];

			DB::updateByPk('expressions', $expressionid, $expression);
			unset($dbExpressions[$expressionid]);
		}
		// if the expression is new - create it
		else {
			$def = array(
				'expression' => null,
				'expression_type' => null,
				'case_sensitive' => 0,
				'exp_delimiter' => ',',
				'regexpid' => $regexpId
			);
			if(!check_db_fields($def, $expression)){
				error(_('Incorrect arguments passed to function').' [updateExpressions]');
				return false;
			}

			DB::insert('expressions', array($expression));
		}
	}

	// delete remaining expressions
	DB::delete('expressions', array(
		'expressionid' => zbx_objectValues($dbExpressions, 'expressionid')
	));

	return true;
}

// Author: Aly
// function expression_type2str($expression_type)
function expression_type2str($expression_type) {
	switch ($expression_type) {
		case EXPRESSION_TYPE_INCLUDED:
			$str = _('Character string included');
			break;
		case EXPRESSION_TYPE_ANY_INCLUDED:
			$str = _('Any character string included');
			break;
		case EXPRESSION_TYPE_NOT_INCLUDED:
			$str = _('Character string not included');
			break;
		case EXPRESSION_TYPE_TRUE:
			$str = _('Result is TRUE');
			break;
		case EXPRESSION_TYPE_FALSE:
			$str = _('Result is FALSE');
			break;
		default:
			$str = _('Test string');
	}
	return $str;
}
?>
