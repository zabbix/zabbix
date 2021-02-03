/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "db.h"
#include "dbupgrade.h"
#include "../zbxalgo/vectorimpl.h"

/*
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	userid;
	char		*idx;
	zbx_uint64_t	idx2;
	zbx_uint64_t	value_id;
	int		value_int;
	char		*value_str;
	char		*source;
	int		type;
}
zbx_dbpatch_profile_t;

static void	DBpatch_get_key_fields(DB_ROW row, zbx_dbpatch_profile_t *profile, char **subsect, char **field, char **key)
{
	int	tok_idx = 0;
	char	*token;

	ZBX_DBROW2UINT64(profile->id, row[0]);
	ZBX_DBROW2UINT64(profile->userid, row[1]);
	profile->idx = zbx_strdup(profile->idx, row[2]);
	ZBX_DBROW2UINT64(profile->idx2, row[3]);
	ZBX_DBROW2UINT64(profile->value_id, row[4]);
	profile->value_int = atoi(row[5]);
	profile->value_str = zbx_strdup(profile->value_str, row[6]);
	profile->source = zbx_strdup(profile->source, row[7]);
	profile->type = atoi(row[8]);

	token = strtok(profile->idx, ".");

	while (NULL != token)
	{
		token = strtok(NULL, ".");
		tok_idx++;

		if (1 == tok_idx)
		{
			*subsect = zbx_strdup(*subsect, token);
		}
		else if (2 == tok_idx)
		{
			*key = zbx_strdup(*key, token);
		}
		else if (3 == tok_idx)
		{
			*field = zbx_strdup(*field, token);
			break;
		}
	}
}

static int	DBpatch_5030001(void)
{
	int		i, ret = SUCCEED;
	const char	*keys[] =
	{
		"web.items.php.sort",
		"web.items.php.sortorder",
		"web.triggers.php.sort",
		"web.triggers.php.sortorder",
		"web.graphs.php.sort",
		"web.graphs.php.sortorder",
		"web.host_discovery.php.sort",
		"web.host_discovery.php.sortorder",
		"web.httpconf.php.sort",
		"web.httpconf.php.sortorder",
		"web.disc_prototypes.php.sort",
		"web.disc_prototypes.php.sortorder",
		"web.trigger_prototypes.php.sort",
		"web.trigger_prototypes.php.sortorder",
		"web.host_prototypes.php.sort",
		"web.host_prototypes.php.sortorder"
	};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; SUCCEED == ret && i < (int)ARRSIZE(keys); i++)
	{
		char			*subsect = NULL, *field = NULL, *key = NULL;
		DB_ROW			row;
		DB_RESULT		result;
		zbx_dbpatch_profile_t	profile = {0};

		result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
				" from profiles where idx='%s'", keys[i]);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);
			continue;
		}

		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		DBfree_result(result);

		if (NULL == subsect || NULL == field || NULL == key)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to parse profile key fields for key '%s'", keys[i]);
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.hosts.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret &&
				ZBX_DB_OK > DBexecute("delete from profiles where profileid=" ZBX_FS_UI64, profile.id))
		{
			ret = FAIL;
		}

		zbx_free(profile.idx);
		zbx_free(profile.value_str);
		zbx_free(profile.source);
		zbx_free(subsect);
		zbx_free(field);
		zbx_free(key);
	}

	return ret;
}

static int	DBpatch_5030002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where "
			"idx like 'web.items.%%filter%%' or "
			"idx like 'web.triggers.%%filter%%' or "
			"idx like 'web.graphs.%%filter%%' or "
			"idx like 'web.host_discovery.%%filter%%' or "
			"idx like 'web.httpconf.%%filter%%' or "
			"idx like 'web.disc_prototypes.%%filter%%' or "
			"idx like 'web.trigger_prototypes.%%filter%%' or "
			"idx like 'web.host_prototypes.%%filter%%'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030003(void)
{
	int			ret = SUCCEED;
	char			*subsect = NULL, *field = NULL, *key = NULL;
	DB_ROW			row;
	DB_RESULT		result;
	zbx_dbpatch_profile_t	profile = {0};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
			" from profiles where idx in ('web.dashbrd.list.sort','web.dashbrd.list.sortorder')");

	while (NULL != (row = DBfetch(result)))
	{
		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		if (ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2,
				profile.value_id, profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
			break;
		}
	}

	DBfree_result(result);

	zbx_free(profile.idx);
	zbx_free(profile.value_str);
	zbx_free(profile.source);
	zbx_free(subsect);
	zbx_free(field);
	zbx_free(key);

	return ret;
}

static int	DBpatch_5030004(void)
{
	const ZBX_FIELD	field = {"available", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030005(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030006(void)
{
	const ZBX_FIELD	field = {"errors_from", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030007(void)
{
	const ZBX_FIELD	field = {"disable_until", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030008(void)
{
	return DBdrop_field("hosts", "available");
}

static int	DBpatch_5030009(void)
{
	return DBdrop_field("hosts", "ipmi_available");
}

static int	DBpatch_5030010(void)
{
	return DBdrop_field("hosts", "snmp_available");
}

static int	DBpatch_5030011(void)
{
	return DBdrop_field("hosts", "jmx_available");
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("hosts", "disable_until");
}

static int	DBpatch_5030013(void)
{
	return DBdrop_field("hosts", "ipmi_disable_until");
}

static int	DBpatch_5030014(void)
{
	return DBdrop_field("hosts", "snmp_disable_until");
}

static int	DBpatch_5030015(void)
{
	return DBdrop_field("hosts", "jmx_disable_until");
}

static int	DBpatch_5030016(void)
{
	return DBdrop_field("hosts", "errors_from");
}

static int	DBpatch_5030017(void)
{
	return DBdrop_field("hosts", "ipmi_errors_from");
}

static int	DBpatch_5030018(void)
{
	return DBdrop_field("hosts", "snmp_errors_from");
}

static int	DBpatch_5030019(void)
{
	return DBdrop_field("hosts", "jmx_errors_from");
}

static int	DBpatch_5030020(void)
{
	return DBdrop_field("hosts", "error");
}

static int	DBpatch_5030021(void)
{
	return DBdrop_field("hosts", "ipmi_error");
}

static int	DBpatch_5030022(void)
{
	return DBdrop_field("hosts", "snmp_error");
}

static int	DBpatch_5030023(void)
{
	return DBdrop_field("hosts", "jmx_error");
}

static int	DBpatch_5030024(void)
{
	return DBcreate_index("interface", "interface_3", "available", 0);
}

static int	DBpatch_5030025(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.overview.type' or idx='web.actionconf.eventsource'"))
		return FAIL;

	return SUCCEED;
}

/* trigger function conversion to new syntax */

#define ZBX_DBPATCH_FUNCTION_UPDATE_NAME		0x01
#define ZBX_DBPATCH_FUNCTION_UPDATE_PARAM		0x02
#define ZBX_DBPATCH_FUNCTION_UPDATE			(ZBX_DBPATCH_FUNCTION_UPDATE_NAME | \
							ZBX_DBPATCH_FUNCTION_UPDATE_PARAM)

#define ZBX_DBPATCH_FUNCTION_CREATE			0x40
#define ZBX_DBPATCH_FUNCTION_DELETE			0x80

#define ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION		0x01
#define ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION	0x02

#define ZBX_DBPATCH_TRIGGER_UPDATE			(ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION | \
							ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION)

/* Function argument descriptors.                                                */
/* Used in varargs list to describe following parameter mapping to old position. */
/* Terminated with ZBX_DBPATCH_ARG_NONE.                                         */
/* For example:                                                                  */
/* ..., ZBX_DBPATCH_ARG_NUM, 1, ZBX_DBPATCH_ARG_STR, 0, ZBX_DBPATCH_ARG_NONE)    */
/*  meaning first numeric parameter copied from second parameter                 */
/*          second string parameter copied from first parameter                  */
typedef enum
{
	ZBX_DBPATCH_ARG_NONE,		/* terminating descriptor, must be put at the end of the list */
	ZBX_DBPATCH_ARG_HIST,		/* history period followed by sec/num (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_TIME,		/* time value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_NUM,		/* number value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_STR,		/* string value  followed by argument index (int)  */
	ZBX_DBPATCH_ARG_TREND,		/* trend period, followed by period (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_CONST_STR,	/* constant,fffffff followed by string (char *) value */
}
zbx_dbpatch_arg_t;

ZBX_VECTOR_DECL(loc, zbx_strloc_t)
ZBX_VECTOR_IMPL(loc, zbx_strloc_t)

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	itemid;
	char		*name;
	char		*parameter;
	unsigned char	flags;
}
zbx_dbpatch_function_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	itemid;
	zbx_uint64_t	hostid;
	char		*name;
}
zbx_dbpatch_common_function_t;


typedef struct
{
	zbx_uint64_t	triggerid;
	unsigned char	recovery_mode;
	unsigned char	flags;
	char		*expression;
	char		*recovery_expression;
}
zbx_dbpatch_trigger_t;

static void	dbpatch_function_free(zbx_dbpatch_function_t *func)
{
	zbx_free(func->name);
	zbx_free(func->parameter);
	zbx_free(func);
}

static void	dbpatch_common_function_free(zbx_dbpatch_common_function_t *func)
{
	zbx_free(func->name);
	zbx_free(func);
}

static void	dbpatch_trigger_clear(zbx_dbpatch_trigger_t *trigger)
{
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
}

static zbx_dbpatch_function_t	*dbpatch_new_function(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *name,
		const char *parameter, unsigned char flags)
{
	zbx_dbpatch_function_t	*func;

	func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
	func->functionid = functionid;
	func->itemid = itemid;
	func->name = (NULL != name ? zbx_strdup(NULL, name) : NULL);
	func->parameter = (NULL != parameter ? zbx_strdup(NULL, parameter) : NULL);
	func->flags = flags;

	return func;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_update_trigger_expression                                *
 *                                                                            *
 * Purpose: replace {functionid} occurrences in expression with the specified *
 *          replacement string                                                *
 *                                                                            *
 * Return value: SUCCEED - expression was changed                             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_update_trigger_expression(char **expression, zbx_uint64_t functionid, const char *replace)
{
	int		pos = 0, last_pos = 0;
	zbx_token_t	token;
	char		*out = NULL;
	size_t		out_alloc = 0, out_offset = 0;
	zbx_uint64_t	id;

	for (; SUCCEED == zbx_token_find(*expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == is_uint64_n(*expression + token.data.objectid.name.l,
						token.data.objectid.name.r - token.data.objectid.name.l + 1, &id) &&
						functionid == id)
				{
					zbx_strncpy_alloc(&out, &out_alloc, &out_offset,
							*expression + last_pos, token.loc.l - last_pos);
					zbx_strcpy_alloc(&out, &out_alloc, &out_offset, replace);
					last_pos = token.loc.r + 1;
				}
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	if (NULL == out)
		return FAIL;

	zbx_strcpy_alloc(&out, &out_alloc, &out_offset, *expression + last_pos);

	zbx_free(*expression);
	*expression = out;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_update_trigger                                           *
 *                                                                            *
 * Purpose: replace {functionid} occurrences in trigger expression and        *
 *          recovery expression with the specified replacement string         *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_update_trigger(zbx_dbpatch_trigger_t *trigger, zbx_uint64_t functionid, const char *replace)
{
	if (SUCCEED == dbpatch_update_trigger_expression(&trigger->expression, functionid, replace))
		trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION;

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger->recovery_mode)
	{
		if (SUCCEED == dbpatch_update_trigger_expression(&trigger->recovery_expression, functionid, replace))
			trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION;
	}
}

static void	dbpatch_update_func_change(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *prefix,
		zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;
	char		*replace;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "last", "$,#1",
			ZBX_DBPATCH_FUNCTION_UPDATE));

	functionid2 = DBget_maxid("functions");
	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid2, itemid, "last", "$,#2",
			ZBX_DBPATCH_FUNCTION_CREATE));

	replace = zbx_dsprintf(NULL, "%s({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", prefix, functionid, functionid2);
	dbpatch_update_trigger(trigger, functionid, replace);
	zbx_free(replace);
}

static void	dbpatch_update_func_delta(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *parameter,
		zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;
	char		*replace;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "max", parameter,
			ZBX_DBPATCH_FUNCTION_UPDATE));

	functionid2 = DBget_maxid("functions");
	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid2, itemid, "min", parameter,
			ZBX_DBPATCH_FUNCTION_CREATE));

	replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", functionid, functionid2);
	dbpatch_update_trigger(trigger, functionid, replace);
	zbx_free(replace);
}

static void	dbpatch_update_func_diff(zbx_uint64_t functionid, zbx_uint64_t itemid, zbx_dbpatch_trigger_t *trigger,
		zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;
	char		*replace;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "last", "$,#1",
			ZBX_DBPATCH_FUNCTION_UPDATE));

	functionid2 = DBget_maxid("functions");
	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid2, itemid, "last", "$,#2",
			ZBX_DBPATCH_FUNCTION_CREATE));

	replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})", functionid, functionid2);
	dbpatch_update_trigger(trigger, functionid, replace);
	zbx_free(replace);
}

static void	dbpatch_update_func_trenddelta(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *parameter,
		zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;
	char		*replace;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "trendmax", parameter,
			ZBX_DBPATCH_FUNCTION_UPDATE));

	functionid2 = DBget_maxid("functions");
	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid2, itemid, "trendmin", parameter,
			ZBX_DBPATCH_FUNCTION_CREATE));

	replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", functionid, functionid2);
	dbpatch_update_trigger(trigger, functionid, replace);
	zbx_free(replace);
}

static void	dbpatch_update_func_strlen(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *parameter,
		zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	char		*replace;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "last", parameter,
			ZBX_DBPATCH_FUNCTION_UPDATE));

	replace = zbx_dsprintf(NULL, "length({" ZBX_FS_UI64 "})", functionid);
	dbpatch_update_trigger(trigger, functionid, replace);
	zbx_free(replace);
}

static void	dbpatch_update_hist2common(zbx_uint64_t functionid, zbx_uint64_t itemid, int extended,
		zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	char	*str  = NULL;
	size_t	str_alloc = 0, str_offset = 0;

	zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "last", "$",
			ZBX_DBPATCH_FUNCTION_UPDATE));

	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '(');
	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, trigger->expression);
	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, ')');

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, " or ({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})", functionid,
			functionid);

	zbx_free(trigger->expression);
	trigger->expression = str;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_parse_function_params                                    *
 *                                                                            *
 * Purpose: parse function parameter string into parameter location vector    *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_parse_function_params(const char *parameter, zbx_vector_loc_t *params)
{
	const char	*ptr;
	size_t		len, pos, sep = 0, eol;
	zbx_strloc_t	loc;

	eol = strlen(parameter);

	for (ptr = parameter; ptr < parameter + eol; ptr += sep + 1)
	{
		zbx_function_param_parse(ptr, &pos, &len, &sep);

		loc.l = ptr - parameter + (0 < len ? pos : eol - (ptr - parameter));
		loc.r = loc.l + len - 1;
		zbx_vector_loc_append_ptr(params, &loc);
	}

	while (0 < params->values_num && '\0' == parameter[params->values[params->values_num - 1].l])
		--params->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_params                                           *
 *                                                                            *
 * Purpose: convert function parameters into new syntax                       *
 *                                                                            *
 * Parameters: out       - [OUT] the converted parameter string               *
 *             parameter - [IN] the original parameter string                 *
 *             params    - [IN] the parameter locations in original parameter *
 *                              string                                        *
 *             ...       - list of parameter descriptors with parameter data  *
 *                         (see zbx_dbpatch_arg_t enum for parameter list     *
 *                         description)                                       *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_convert_params(char **out, const char *parameter, zbx_vector_loc_t *params, ...)
{
	size_t		out_alloc = 0, out_offset = 0;
	va_list 	args;
	int		index, type;
	zbx_strloc_t	*loc;
	const char	*ptr;

	va_start(args, params);

	zbx_strcpy_alloc(out, &out_alloc, &out_offset, "$");

	while (ZBX_DBPATCH_ARG_NONE != (type = va_arg(args, int)))
	{
		zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ',');

		switch (type)
		{
			case ZBX_DBPATCH_ARG_HIST:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];

					if ('#' == parameter[loc->l])
					{
						zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
								loc->r - loc->l + 1);
					}
					else
					{
						zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
								loc->r - loc->l + 1);
						if (0 != isdigit(parameter[loc->r]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}
				}

				if (-1 != (index = va_arg(args, int)) && index < params->values_num)
				{
					loc = &params->values[index];

					if ('\0' != parameter[loc->l])
					{
						if (',' == (*out)[out_offset - 1])
							zbx_strcpy_alloc(out, &out_alloc, &out_offset, "#1");

						zbx_strcpy_alloc(out, &out_alloc, &out_offset, ":now-");
						zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
								loc->r - loc->l + 1);
						if (0 != isdigit(parameter[loc->r]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}
				}

				break;
			case ZBX_DBPATCH_ARG_TIME:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
							loc->r - loc->l + 1);
					if (0 != isdigit(parameter[loc->r]))
						zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
				}
				break;
			case ZBX_DBPATCH_ARG_NUM:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
							loc->r - loc->l + 1);
				}
				break;
			case ZBX_DBPATCH_ARG_STR:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					if ('"' == parameter[loc->l])
					{
						loc = &params->values[index];
						zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
								loc->r - loc->l + 1);
					}
					else if ('\0' != parameter[loc->l])
					{
						char raw[FUNCTION_PARAM_LEN * 4 + 1], quoted[sizeof(raw)];

						zbx_strlcpy(raw, parameter + loc->l, loc->r - loc->l + 2);
						zbx_escape_string(quoted, sizeof(quoted), raw, "\"\\");
						zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, quoted);
						zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
					}
				}
				break;
			case ZBX_DBPATCH_ARG_TREND:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
							loc->r - loc->l + 1);
				}
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ':');
					zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
							loc->r - loc->l + 1);
				}
				break;
			case ZBX_DBPATCH_ARG_CONST_STR:
				if (NULL != (ptr = va_arg(args, char *)))
				{
					char quoted[MAX_STRING_LEN];

					zbx_escape_string(quoted, sizeof(quoted), ptr, "\"\\");
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, quoted);
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
				}
				break;
		}
	}

	/* trim trailing empty parameters */
	while (',' == (*out)[out_offset - 1])
		(*out)[--out_offset] = '\0';

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_is_numeric_count_pattern                                 *
 *                                                                            *
 * Purpose: check if the pattern can be a numeric value for the specified     *
 *          operation                                                         *
 *                                                                            *
 * Parameters: op      - [IN] the operation                                   *
 *             pattern - [IN] the pattern                                     *
 *                                                                            *
 * Comments: The op and pattern parameters are pointers to trigger expression *
 *           substrings.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_is_numeric_count_pattern(const char *op, const char *pattern)
{
	if (0 == strncmp(op, "eq", ZBX_CONST_STRLEN("eq")) ||
			0 == strncmp(op, "ne", ZBX_CONST_STRLEN("ne")) ||
			0 == strncmp(op, "gt", ZBX_CONST_STRLEN("gt")) ||
			0 == strncmp(op, "ge", ZBX_CONST_STRLEN("ge")) ||
			0 == strncmp(op, "lt", ZBX_CONST_STRLEN("lt")) ||
			0 == strncmp(op, "le", ZBX_CONST_STRLEN("le")))
	{
		return SUCCEED;
	}

	if (0 == strncmp(op, "band", ZBX_CONST_STRLEN("band")))
	{
		const char	*ptr;

		/* op was the next parameter after pattern in the count function - */
		/* if the '/' is located beyond op, it's outside pattern           */
		if (NULL == (ptr = strchr(pattern, '/')) || ptr >= op)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_trigger                                          *
 *                                                                            *
 * Purpose: convert trigger and its functions to use new expression syntax    *
 *                                                                            *
 * Parameters: trigger   - [IN/OUT] the trigger data/updates                  *
 *             functions - [OUT] the function updates                         *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_convert_trigger(zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	DB_ROW				row;
	DB_RESULT			result;
	zbx_uint64_t			functionid, itemid, hostid;
	zbx_vector_loc_t		params;
	zbx_vector_ptr_t		common_functions;
	zbx_vector_uint64_t		hostids;
	zbx_dbpatch_common_function_t	*func;


	zbx_vector_loc_create(&params);
	zbx_vector_ptr_create(&common_functions);
	zbx_vector_uint64_create(&hostids);

	result = DBselect("select f.functionid,f.itemid,f.name,f.parameter,i.value_type,h.hostid"
			" from functions f"
			" join items i"
				" on f.itemid=i.itemid"
			" join hosts h"
				" on i.hostid=h.hostid"
			" where triggerid=" ZBX_FS_UI64
			" order by functionid",
			trigger->triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		char		*parameter = NULL;
		unsigned char	value_type;

		ZBX_STR2UINT64(functionid, row[0]);
		ZBX_STR2UINT64(itemid, row[1]);
		ZBX_STR2UINT64(hostid, row[5]);
		ZBX_STR2UCHAR(value_type, row[4]);

		if (0 == strcmp(row[2], "date") || 0 == strcmp(row[2], "dayofmonth") ||
				0 == strcmp(row[2], "dayofweek") || 0 == strcmp(row[2], "now") ||
				0 == strcmp(row[2], "time"))
		{
			char	replace[FUNCTION_NAME_LEN * 4 + 1];

			zbx_snprintf(replace, sizeof(replace), "%s()", row[2]);
			dbpatch_update_trigger(trigger, functionid, replace);

			func = (zbx_dbpatch_common_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_common_function_t));
			func->functionid = functionid;
			func->itemid = itemid;
			func->hostid = hostid;
			func->name = zbx_strdup(NULL, row[2]);
			zbx_vector_ptr_append(&common_functions, func);

			continue;
		}

		zbx_vector_uint64_append(&hostids, hostid);
		dbpatch_parse_function_params(row[3], &params);

		if (0 == strcmp(row[2], "abschange"))
		{
			if (ITEM_VALUE_TYPE_FLOAT == value_type || ITEM_VALUE_TYPE_UINT64 == value_type)
				dbpatch_update_func_change(functionid, itemid, "abs", trigger, functions);
			else
				dbpatch_update_func_diff(functionid, itemid, trigger, functions);
		}
		else if (0 == strcmp(row[2], "avg") || 0 == strcmp(row[2], "max") || 0 == strcmp(row[2], "min") ||
				0 == strcmp(row[2], "sum"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "change"))
		{
			if (ITEM_VALUE_TYPE_FLOAT == value_type || ITEM_VALUE_TYPE_UINT64 == value_type)
				dbpatch_update_func_change(functionid, itemid, "", trigger, functions);
			else
				dbpatch_update_func_diff(functionid, itemid, trigger, functions);
		}
		else if (0 == strcmp(row[2], "delta"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			dbpatch_update_func_delta(functionid, itemid, parameter, trigger, functions);
		}
		else if (0 == strcmp(row[2], "diff"))
		{
			dbpatch_update_func_diff(functionid, itemid, trigger, functions);
		}
		else if (0 == strcmp(row[2], "fuzzytime") || 0 == strcmp(row[2], "nodata"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_TIME, 0,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "percentile"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NUM, 2,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "logseverity"))
		{
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, "$",
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "trendavg") || 0 == strcmp(row[2], "trendmin") ||
				0 == strcmp(row[2], "trendmax") || 0 == strcmp(row[2], "trendsum") ||
				0 == strcmp(row[2], "trendcount"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_TREND, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "trenddelta"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_TREND, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			dbpatch_update_func_trenddelta(functionid, itemid, parameter, trigger, functions);
		}
		else if (0 == strcmp(row[2], "band"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 2,
					ZBX_DBPATCH_ARG_NUM, 1,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "forecast"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_TIME, 2,
					ZBX_DBPATCH_ARG_STR, 3,
					ZBX_DBPATCH_ARG_STR, 4,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "timeleft"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NUM, 2,
					ZBX_DBPATCH_ARG_STR, 3,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "count"))
		{
			int	arg_type = ZBX_DBPATCH_ARG_STR;

			if (2 <= params.values_num)
			{
				const char	*op, *pattern =  row[3] + params.values[1].l;

				op = (3 <= params.values_num ? row[3] + params.values[2].l : "eq");

				/* set numeric pattern type for numeric items and numeric operators unless */
				/* band operation pattern contains mask (separated by '/')                 */
				if ((ITEM_VALUE_TYPE_FLOAT == value_type || ITEM_VALUE_TYPE_UINT64 == value_type) &&
						SUCCEED == dbpatch_is_numeric_count_pattern(op, pattern))
				{
					arg_type = ZBX_DBPATCH_ARG_NUM;
				}
			}

			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 3,
					ZBX_DBPATCH_ARG_STR, 2,
					arg_type, 1,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "iregexp") || 0 == strcmp(row[2], "regexp"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 1, -1,
					ZBX_DBPATCH_ARG_CONST_STR, row[2],
					ZBX_DBPATCH_ARG_STR, 0,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "find", parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE));
		}
		else if (0 == strcmp(row[2], "str"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 1, -1,
					ZBX_DBPATCH_ARG_CONST_STR, "like",
					ZBX_DBPATCH_ARG_STR, 0,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "find", parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE));
		}
		else if (0 == strcmp(row[2], "last"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}
		else if (0 == strcmp(row[2], "prev"))
		{
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, "last", "$,#2",
					ZBX_DBPATCH_FUNCTION_UPDATE));
		}
		else if (0 == strcmp(row[2], "strlen"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_HIST, 0, 1,
					ZBX_DBPATCH_ARG_NONE);
			dbpatch_update_func_strlen(functionid, itemid, parameter, trigger, functions);
		}
		else if (0 == strcmp(row[2], "logeventid") || 0 == strcmp(row[2], "logsource"))
		{
			dbpatch_convert_params(&parameter, row[3], &params,
					ZBX_DBPATCH_ARG_STR, 0,
					ZBX_DBPATCH_ARG_NONE);
			zbx_vector_ptr_append(functions, dbpatch_new_function(functionid, itemid, NULL, parameter,
					ZBX_DBPATCH_FUNCTION_UPDATE_PARAM));
		}

		zbx_free(parameter);
		zbx_vector_loc_clear(&params);
	}

	DBfree_result(result);

	/* ensure that with history time functions converted to common time functions */
	/* the trigger is still linked to the same hosts                              */
	if (0 != common_functions.values_num)
	{
		int	i, extended = 0;

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < common_functions.values_num; i++)
		{
			func = (zbx_dbpatch_common_function_t *)common_functions.values[i];

			if (FAIL != zbx_vector_uint64_bsearch(&hostids, func->hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				zbx_vector_ptr_append(functions, dbpatch_new_function(func->functionid, 0, NULL, NULL,
						ZBX_DBPATCH_FUNCTION_DELETE));
				continue;
			}

			dbpatch_update_hist2common(func->functionid, func->itemid, extended, trigger, functions);
			extended = 1;
		}
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_ptr_clear_ext(&common_functions, (zbx_clean_func_t)dbpatch_common_function_free);
	zbx_vector_ptr_destroy(&common_functions);
	zbx_vector_loc_destroy(&params);

	if (0 != (trigger->flags & ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION))
	{
		if (zbx_strlen_utf8(trigger->expression) > TRIGGER_EXPRESSION_LEN)
		{
			zabbix_log(LOG_LEVEL_WARNING, "trigger \"" ZBX_FS_UI64 "\" expression is too long: %s",
					trigger->triggerid, trigger->expression);
			return FAIL;
		}
	}

	if (0 != (trigger->flags & ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
	{
		if (zbx_strlen_utf8(trigger->recovery_expression) > TRIGGER_EXPRESSION_LEN)
		{
			zabbix_log(LOG_LEVEL_WARNING, "trigger \"" ZBX_FS_UI64 "\" recovery expression is too long: %s",
					trigger->triggerid, trigger->recovery_expression);
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_5030026(void)
{
	int			i, ret = SUCCEED;
	DB_ROW			row;
	DB_RESULT		result;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	zbx_db_insert_t		db_insert_functions;
	zbx_vector_ptr_t	functions;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_ptr_create(&functions);

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_db_insert_prepare(&db_insert_functions, "functions", "functionid", "itemid", "triggerid", "name",
			"parameter", NULL);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select triggerid,recovery_mode,expression,recovery_expression from triggers"
			" order by triggerid");

	while (NULL != (row = DBfetch(result)))
	{
		char			delim = ' ', *esc;
		zbx_dbpatch_trigger_t	trigger;

		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		ZBX_STR2UCHAR(trigger.recovery_mode, row[1]);
		trigger.expression = zbx_strdup(NULL, row[2]);
		trigger.recovery_expression = zbx_strdup(NULL, row[3]);
		trigger.flags = 0;

		if (SUCCEED == dbpatch_convert_trigger(&trigger, &functions))
		{
			for (i = 0; i < functions.values_num; i++)
			{
				zbx_dbpatch_function_t	*func = (zbx_dbpatch_function_t *)functions.values[i];

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_CREATE))
				{
					zbx_db_insert_add_values(&db_insert_functions, func->functionid,
							func->itemid, trigger.triggerid, func->name, func->parameter);
					continue;
				}

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_DELETE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"delete from functions where functionid=" ZBX_FS_UI64 ";\n",
							func->functionid);
					continue;
				}

				delim = ' ';

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update functions set");
				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_UPDATE_NAME))
				{
					esc = DBdyn_escape_field("functions", "name", func->name);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cname='%s'", delim, esc);
					zbx_free(esc);
					delim = ',';
				}

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_UPDATE_PARAM))
				{
					esc = DBdyn_escape_field("functions", "parameter", func->parameter);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cparameter='%s'", delim, esc);
					zbx_free(esc);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where functionid=" ZBX_FS_UI64
						";\n", func->functionid);
			}

			if (0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE))
			{
				delim = ' ';
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set");

				if (0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION))
				{
					esc = DBdyn_escape_field("triggers", "expression", trigger.expression);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cexpression='%s'", delim,
							esc);
					zbx_free(esc);
					delim = ',';
				}

				if (0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
				{
					esc = DBdyn_escape_field("triggers", "recovery_expression",
							trigger.recovery_expression);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%crecovery_expression='%s'",
							delim, esc);
					zbx_free(esc);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64
						";\n", trigger.triggerid);
			}
		}

		zbx_vector_ptr_clear_ext(&functions, (zbx_clean_func_t)dbpatch_function_free);
		dbpatch_trigger_clear(&trigger);

		if (FAIL == (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			break;

	}

	DBfree_result(result);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	if (SUCCEED == ret)
		zbx_db_insert_execute(&db_insert_functions);

	zbx_db_insert_clean(&db_insert_functions);
	zbx_free(sql);

	zbx_vector_ptr_destroy(&functions);

	return ret;
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)
DBPATCH_ADD(5030015, 0, 1)
DBPATCH_ADD(5030016, 0, 1)
DBPATCH_ADD(5030017, 0, 1)
DBPATCH_ADD(5030018, 0, 1)
DBPATCH_ADD(5030019, 0, 1)
DBPATCH_ADD(5030020, 0, 1)
DBPATCH_ADD(5030021, 0, 1)
DBPATCH_ADD(5030022, 0, 1)
DBPATCH_ADD(5030023, 0, 1)
DBPATCH_ADD(5030024, 0, 1)
DBPATCH_ADD(5030025, 0, 1)
DBPATCH_ADD(5030026, 0, 1)

DBPATCH_END()
