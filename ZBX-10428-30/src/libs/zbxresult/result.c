/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "zbxalgo.h"
#include "zbxresult.h"

static void	zbx_result_log_init(zbx_result_log_t *log)
{
	log->value = NULL;
	log->source = NULL;
	log->timestamp = 0;
	log->severity = 0;
	log->logeventid = 0;
}

void	zbx_result_init(zbx_result_t *result)
{
	result->type = 0;
	result->meta = 0;

	result->ui64 = 0;
	result->dbl = 0;
	result->str = NULL;
	result->text = NULL;
	result->log = NULL;
	result->msg = NULL;
}

void	zbx_result_log_free(zbx_result_log_t *log)
{
	zbx_free(log->source);
	zbx_free(log->value);
	zbx_free(log);
}

void	zbx_result_free(zbx_result_t *result)
{
	ZBX_UNSET_UI64_RESULT(result);
	ZBX_UNSET_DBL_RESULT(result);
	ZBX_UNSET_STR_RESULT(result);
	ZBX_UNSET_TEXT_RESULT(result);
	ZBX_UNSET_LOG_RESULT(result);
	ZBX_UNSET_MSG_RESULT(result);
}

static void	zbx_result_add_log(zbx_result_t *result, char *value)
{
	result->log = zbx_malloc(result->log, sizeof(zbx_result_log_t));

	zbx_result_log_init(result->log);

	result->log->value = zbx_strdup(result->log->value, value);
	result->type |= AR_LOG;
}

int	zbx_result_set_type(zbx_result_t *result, int value_type, int data_type, char *c)
{
	zbx_uint64_t	value_uint64;
	double		value_double;
	int		ret = FAIL;

	assert(result);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			zbx_rtrim(c, " \"");
			zbx_ltrim(c, " \"+");
			del_zeroes(c);

			switch (data_type)
			{
				case ITEM_DATA_TYPE_BOOLEAN:
					if (SUCCEED == is_boolean(c, &value_uint64))
					{
						ZBX_SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_OCTAL:
					if (SUCCEED == is_uoct(c))
					{
						ZBX_OCT2UINT64(value_uint64, c);
						ZBX_SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_DECIMAL:
					if (SUCCEED == is_uint64(c, &value_uint64))
					{
						ZBX_SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_HEXADECIMAL:
					if (SUCCEED == is_uhex(c))
					{
						ZBX_HEX2UINT64(value_uint64, c);
						ZBX_SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					else if (SUCCEED == is_hex_string(c))
					{
						zbx_remove_whitespace(c);
						ZBX_HEX2UINT64(value_uint64, c);
						ZBX_SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					break;
			}
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_rtrim(c, " \"");
			zbx_ltrim(c, " \"+");

			if (SUCCEED != is_double(c))
				break;

			value_double = atof(c);

			ZBX_SET_DBL_RESULT(result, value_double);
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_STR:
			zbx_replace_invalid_utf8(c);
			ZBX_SET_STR_RESULT(result, zbx_strdup(NULL, c));
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_replace_invalid_utf8(c);
			ZBX_SET_TEXT_RESULT(result, zbx_strdup(NULL, c));
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_replace_invalid_utf8(c);
			zbx_result_add_log(result, c);
			ret = SUCCEED;
			break;
	}

	if (SUCCEED != ret)
	{
		char	*error = NULL;

		zbx_remove_chars(c, "\r\n");
		zbx_replace_invalid_utf8(c);

		if (ITEM_VALUE_TYPE_UINT64 == value_type)
			error = zbx_dsprintf(error,
					"Received value [%s] is not suitable for value type [%s] and data type [%s]",
					c, zbx_item_value_type_string(value_type),
					zbx_item_data_type_string(data_type));
		else
			error = zbx_dsprintf(error,
					"Received value [%s] is not suitable for value type [%s]",
					c, zbx_item_value_type_string(value_type));

		ZBX_SET_MSG_RESULT(result, error);
	}

	return ret;
}

void	zbx_result_set_meta(zbx_result_t *result, zbx_uint64_t lastlogsize, int mtime)
{
	result->lastlogsize = lastlogsize;
	result->mtime = mtime;
	result->meta = 1;
}

static zbx_uint64_t	*zbx_result_get_ui64_value(zbx_result_t *result)
{
	zbx_uint64_t	value;

	assert(result);

	if (0 != ZBX_ISSET_UI64(result))
	{
		/* nothing to do */
	}
	else if (0 != ZBX_ISSET_DBL(result))
	{
		ZBX_SET_UI64_RESULT(result, result->dbl);
	}
	else if (0 != ZBX_ISSET_STR(result))
	{
		zbx_rtrim(result->str, " \"");
		zbx_ltrim(result->str, " \"+");
		del_zeroes(result->str);

		if (SUCCEED != is_uint64(result->str, &value))
			return NULL;

		ZBX_SET_UI64_RESULT(result, value);
	}
	else if (0 != ZBX_ISSET_TEXT(result))
	{
		zbx_rtrim(result->text, " \"");
		zbx_ltrim(result->text, " \"+");
		del_zeroes(result->text);

		if (SUCCEED != is_uint64(result->text, &value))
			return NULL;

		ZBX_SET_UI64_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ZBX_ISSET_UI64(result))
		return &result->ui64;

	return NULL;
}

static double	*zbx_result_get_dbl_value(zbx_result_t *result)
{
	double	value;

	assert(result);

	if (0 != ZBX_ISSET_DBL(result))
	{
		/* nothing to do */
	}
	else if (0 != ZBX_ISSET_UI64(result))
	{
		ZBX_SET_DBL_RESULT(result, result->ui64);
	}
	else if (0 != ZBX_ISSET_STR(result))
	{
		zbx_rtrim(result->str, " \"");
		zbx_ltrim(result->str, " \"+");

		if (SUCCEED != is_double(result->str))
			return NULL;
		value = atof(result->str);

		ZBX_SET_DBL_RESULT(result, value);
	}
	else if (0 != ZBX_ISSET_TEXT(result))
	{
		zbx_rtrim(result->text, " \"");
		zbx_ltrim(result->text, " \"+");

		if (SUCCEED != is_double(result->text))
			return NULL;
		value = atof(result->text);

		ZBX_SET_DBL_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ZBX_ISSET_DBL(result))
		return &result->dbl;

	return NULL;
}

static char	**zbx_result_get_str_value(zbx_result_t *result)
{
	char	*p, tmp;

	assert(result);

	if (0 != ZBX_ISSET_STR(result))
	{
		/* nothing to do */
	}
	else if (0 != ZBX_ISSET_TEXT(result))
	{
		/* NOTE: copy only line */
		for (p = result->text; '\0' != *p && '\r' != *p && '\n' != *p; p++);
		tmp = *p; /* remember result->text character */
		*p = '\0'; /* replace to NUL */
		ZBX_SET_STR_RESULT(result, zbx_strdup(NULL, result->text)); /* copy line */
		*p = tmp; /* restore result->text character */
	}
	else if (0 != ZBX_ISSET_UI64(result))
	{
		ZBX_SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (0 != ZBX_ISSET_DBL(result))
	{
		ZBX_SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ZBX_ISSET_STR(result))
		return &result->str;

	return NULL;
}

static char	**zbx_result_get_text_value(zbx_result_t *result)
{
	assert(result);

	if (0 != ZBX_ISSET_TEXT(result))
	{
		/* nothing to do */
	}
	else if (0 != ZBX_ISSET_STR(result))
	{
		ZBX_SET_TEXT_RESULT(result, zbx_strdup(NULL, result->str));
	}
	else if (0 != ZBX_ISSET_UI64(result))
	{
		ZBX_SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (0 != ZBX_ISSET_DBL(result))
	{
		ZBX_SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ZBX_ISSET_TEXT(result))
		return &result->text;

	return NULL;
}

static zbx_result_log_t	*zbx_result_get_log_value(zbx_result_t *result)
{
	if (0 != ZBX_ISSET_LOG(result))
		return result->log;

	if (0 != result->type)
	{
		result->log = zbx_malloc(result->log, sizeof(zbx_result_log_t));

		zbx_result_log_init(result->log);

		if (0 != ZBX_ISSET_STR(result))
			result->log->value = zbx_strdup(result->log->value, result->str);
		else if (0 != ZBX_ISSET_TEXT(result))
			result->log->value = zbx_strdup(result->log->value, result->text);
		else if (0 != ZBX_ISSET_UI64(result))
			result->log->value = zbx_dsprintf(result->log->value, ZBX_FS_UI64, result->ui64);
		else if (0 != ZBX_ISSET_DBL(result))
			result->log->value = zbx_dsprintf(result->log->value, ZBX_FS_DBL, result->dbl);

		result->type |= AR_LOG;

		return result->log;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_result_get_value_by_type                                     *
 *                                                                            *
 * Purpose: return value of result in special type                            *
 *          if value missing, convert existing value to requested type        *
 *                                                                            *
 * Return value:                                                              *
 *         NULL - if value is missing or can't be converted                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  better use definitions                                          *
 *                ZBX_GET_UI64_RESULT                                         *
 *                ZBX_GET_DBL_RESULT                                          *
 *                ZBX_GET_STR_RESULT                                          *
 *                ZBX_GET_TEXT_RESULT                                         *
 *                ZBX_GET_LOG_RESULT                                          *
 *                ZBX_GET_MSG_RESULT                                          *
 *                                                                            *
 *    AR_MESSAGE - skipped in conversion                                      *
 *                                                                            *
 ******************************************************************************/
void    *zbx_result_get_value_by_type(zbx_result_t *result, int require_type)
{
	assert(result);

	switch (require_type)
	{
		case AR_UINT64:
			return (void *)zbx_result_get_ui64_value(result);
		case AR_DOUBLE:
			return (void *)zbx_result_get_dbl_value(result);
		case AR_STRING:
			return (void *)zbx_result_get_str_value(result);
		case AR_TEXT:
			return (void *)zbx_result_get_text_value(result);
		case AR_LOG:
			return (void *)zbx_result_get_log_value(result);
		case AR_MESSAGE:
			if (0 != ZBX_ISSET_MSG(result))
				return (void *)(&result->msg);
			break;
		default:
			break;
	}

	return NULL;
}

static zbx_result_log_t	*extract_result_log(zbx_log_t *log)
{
	zbx_result_log_t	*result_log;

	result_log = zbx_malloc(NULL, sizeof(zbx_result_log_t));
	zbx_result_log_init(result_log);

	if (NULL != log->value)
		result_log->value = zbx_strdup(result_log->value, log->value);

	if (NULL != log->source)
		result_log->source = zbx_strdup(result_log->source, log->source);

	result_log->timestamp = log->timestamp;
	result_log->severity = log->severity;
	result_log->logeventid = log->logeventid;
	return result_log;
}

static void	extract_result_logs(AGENT_RESULT *agent_result, zbx_vector_ptr_t *add_results)
{
	zbx_result_t	*add_result;
	int		i;

	if (0 == (agent_result->type & AR_LOG))
		return;

	for (i = 0; NULL != agent_result->logs[i]; i++)
	{
		add_result = zbx_malloc(NULL, sizeof(zbx_result_t));
		zbx_result_init(add_result);
		ZBX_SET_LOG_RESULT(add_result, extract_result_log(agent_result->logs[i]));
		add_result->lastlogsize = agent_result->logs[i]->lastlogsize;
		add_result->mtime = agent_result->logs[i]->mtime;
		zbx_vector_ptr_append(add_results, add_result);
	}
}

static void	extract_result_by_type(AGENT_RESULT *agent_result, int type, zbx_vector_ptr_t *add_results)
{
	zbx_result_t	*add_result;

	if (0 == (agent_result->type & type))
		return;

	add_result = zbx_malloc(NULL, sizeof(zbx_result_t));
	zbx_result_init(add_result);

	switch (type)
	{
		case AR_UINT64 :
			ZBX_SET_UI64_RESULT(add_result, agent_result->ui64);
			break;
		case AR_DOUBLE :
			ZBX_SET_DBL_RESULT(add_result, agent_result->dbl);
			break;
		case AR_STRING :
			ZBX_SET_STR_RESULT(add_result, zbx_strdup(NULL, agent_result->str));
			break;
		case AR_TEXT :
			ZBX_SET_TEXT_RESULT(add_result, zbx_strdup(NULL, agent_result->text));
			break;
		case AR_MESSAGE :
			ZBX_SET_MSG_RESULT(add_result, zbx_strdup(NULL, agent_result->msg));
			break;
	}

	zbx_vector_ptr_append(add_results, add_result);
}

void	zbx_extract_results(AGENT_RESULT *agent_result, zbx_vector_ptr_t *add_results)
{
	extract_result_by_type(agent_result, AR_UINT64, add_results);
	extract_result_by_type(agent_result, AR_DOUBLE, add_results);
	extract_result_by_type(agent_result, AR_STRING, add_results);
	extract_result_by_type(agent_result, AR_TEXT, add_results);
	extract_result_by_type(agent_result, AR_MESSAGE, add_results);
	extract_result_logs(agent_result, add_results);
}
