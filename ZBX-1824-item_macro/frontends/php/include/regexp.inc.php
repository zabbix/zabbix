<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
function get_regexp_by_regexpid($regexpid){
	$sql = 'SELECT re.* '.
					' FROM regexps re '.
					' WHERE '.DBin_node('re.regexpid').
						' AND regexpid='.$regexpid;

	$db_regexp = DBfetch(DBselect($sql));
return $db_regexp;
}

// Author: Aly
// function add_regexp($regexp=array()){
function add_regexp($regexp=array()){
	$db_fields = array('name' => null,
						'test_string' => '',
					);

	if(!check_db_fields($db_fields, $regexp)){
		error(S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION.' [add_regexp]');
		return false;
	}

	$sql = 'SELECT regexpid FROM regexps WHERE name='.zbx_dbstr($regexp['name']);
	if(DBfetch(DBselect($sql))){
		info(S_REGULAR_EXPRESSION.' ['.$regexp['name'].'] '.S_ALREADY_EXISTS_SMALL);
		return false;
	}

	$regexpid = get_dbid('regexps','regexpid');

	$result = DBexecute('INSERT INTO regexps (regexpid,name,test_string) '.
				' VALUES ('.$regexpid.','.
						zbx_dbstr($regexp['name']).','.
						zbx_dbstr($regexp['test_string']).')');

return $result?$regexpid:false;
}

// Author: Aly
// function update_regexp($regexpid, $regexp=array())
function update_regexp($regexpid, $regexp=array()){
	$db_fields = array('name' => null,
						'test_string' => '',
					);

	if(!check_db_fields($db_fields, $regexp)){
		error(S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION.' [update_regexp]');
		return false;
	}

	$sql = 'SELECT regexpid FROM regexps WHERE name='.zbx_dbstr($regexp['name']);
	if($db_regexp = DBfetch(DBselect($sql))){
		if(bccomp($regexpid,$db_regexp['regexpid']) != 0){
			info(S_REGULAR_EXPRESSION.' ['.$regexp['name'].'] '.S_ALREADY_EXISTS_SMALL);
			return false;
		}
	}

	$sql = 'UPDATE regexps SET '.
				' name='.zbx_dbstr($regexp['name']).','.
				' test_string='.zbx_dbstr($regexp['test_string']).
			' WHERE regexpid='.$regexpid;
	$result = DBexecute($sql);
return $result;
}

// Author: Aly
// function delete_regexp($regexpids)
function delete_regexp($regexpids){
	zbx_value2array($regexpids);

// delete expressions first
	delete_expressions_by_regexpid($regexpids);
	$result = DBexecute('DELETE FROM regexps WHERE '.DBcondition('regexpid',$regexpids));

return $result;
}

// Author: Aly
// function add_expression($expression = array())
function add_expression($regexpid, $expression = array()){
	$db_fields = array('expression' => null,
						'expression_type' => null,
						'case_sensitive' => 0,
						'exp_delimiter' => ',',
					);

	if(!check_db_fields($db_fields, $expression)){
		error(S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION.' [add_expression]');
		return false;
	}

	$expressionid = get_dbid('expressions','expressionid');

	$result = DBexecute('INSERT INTO expressions (expressionid,regexpid,expression,expression_type,case_sensitive,exp_delimiter) '.
				' VALUES ('.$expressionid.','.
						$regexpid.','.
						zbx_dbstr($expression['expression']).','.
						$expression['expression_type'].','.
						$expression['case_sensitive'].','.
						zbx_dbstr($expression['exp_delimiter']).')');

return $result?$expressionid:false;
}

// Author: Aly
// function delete_expression_by_regexpid($regexpids)
function delete_expressions_by_regexpid($regexpids){
	zbx_value2array($regexpids);
	$sql = 'DELETE FROM expressions WHERE '.DBcondition('regexpid',$regexpids);
return DBexecute($sql);
}

// Author: Aly
// function delete_expression($expressionids)
function delete_expression($expressionids){
	zbx_value2array($expressionids);
	$sql = 'DELETE FROM expressions WHERE '.DBcondition('expressionid',$expressionids);
return DBexecute($sql);
}

// Author: Aly
// function expression_type2str($expression_type)
function expression_type2str($expression_type){
	switch($expression_type){
		case EXPRESSION_TYPE_INCLUDED:
			$str = S_CHARACTER_STRING_INCLUDED;
			break;
		case EXPRESSION_TYPE_ANY_INCLUDED:
			$str = S_ANY_CHARACTER_STRING_INCLUDED;
			break;
		case EXPRESSION_TYPE_NOT_INCLUDED:
			$str = S_CHARACTER_STRING_NOT_INCLUDED;
			break;
		case EXPRESSION_TYPE_TRUE:
			$str = S_RESULT_IS_TRUE;
			break;
		case EXPRESSION_TYPE_FALSE:
			$str = S_RESULT_IS_FALSE;
			break;
		default:
			$str = S_UNKNOWN;
	}
return $str;
}
?>
