/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "active.h"
#include "zbxconf.h"

#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "logfiles.h"
#ifdef _WINDOWS
#	include "eventlog.h"
#	include "winmeta.h"
#	include <delayimp.h>
#endif
#include "comms.h"
#include "threads.h"
#include "zbxjson.h"
#include "alias.h"

extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

#include "../libs/zbxcrypto/tls.h"

ZBX_THREAD_LOCAL static ZBX_ACTIVE_BUFFER	buffer;
ZBX_THREAD_LOCAL static zbx_vector_ptr_t	active_metrics;
ZBX_THREAD_LOCAL static zbx_vector_ptr_t	regexps;

#ifdef _WINDOWS
LONG WINAPI	DelayLoadDllExceptionFilter(PEXCEPTION_POINTERS excpointers)
{
	LONG		disposition = EXCEPTION_EXECUTE_HANDLER;
	PDelayLoadInfo	delayloadinfo = (PDelayLoadInfo)(excpointers->ExceptionRecord->ExceptionInformation[0]);

	switch (excpointers->ExceptionRecord->ExceptionCode)
	{
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_MOD_NOT_FOUND):
			zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
					delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			break;
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_PROC_NOT_FOUND):
			if (delayloadinfo->dlp.fImportByName)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
						delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function ordinal %d was not found in %s",
						delayloadinfo->dlp.dwOrdinal, delayloadinfo->szDll);
			}
			break;
		default:
			disposition = EXCEPTION_CONTINUE_SEARCH;
			break;
	}

	return disposition;
}
#endif

static void	init_active_metrics(void)
{
	const char	*__function_name = "init_active_metrics";
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == buffer.data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "buffer: first allocation for %d elements", CONFIG_BUFFER_SIZE);
		sz = CONFIG_BUFFER_SIZE * sizeof(ZBX_ACTIVE_BUFFER_ELEMENT);
		buffer.data = (ZBX_ACTIVE_BUFFER_ELEMENT *)zbx_malloc(buffer.data, sz);
		memset(buffer.data, 0, sz);
		buffer.count = 0;
		buffer.pcount = 0;
		buffer.lastsent = (int)time(NULL);
		buffer.first_error = 0;
	}

	zbx_vector_ptr_create(&active_metrics);
	zbx_vector_ptr_create(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	free_active_metric(ZBX_ACTIVE_METRIC *metric)
{
	int	i;

	zbx_free(metric->key);
	zbx_free(metric->key_orig);

	for (i = 0; i < metric->logfiles_num; i++)
		zbx_free(metric->logfiles[i].filename);

	zbx_free(metric->logfiles);
	zbx_free(metric);
}

#ifdef _WINDOWS
static void	free_active_metrics(void)
{
	const char	*__function_name = "free_active_metrics";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_vector_ptr_clear_ext(&active_metrics, (zbx_clean_func_t)free_active_metric);
	zbx_vector_ptr_destroy(&active_metrics);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
#endif

static int	metric_ready_to_process(const ZBX_ACTIVE_METRIC *metric)
{
	if (ITEM_STATE_NOTSUPPORTED == metric->state && 0 == metric->refresh_unsupported)
		return FAIL;

	return SUCCEED;
}

static int	get_min_nextcheck(void)
{
	const char	*__function_name = "get_min_nextcheck";
	int		i, min = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		const ZBX_ACTIVE_METRIC	*metric = (const ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		if (SUCCEED != metric_ready_to_process(metric))
			continue;

		if (metric->nextcheck < min || -1 == min)
			min = metric->nextcheck;
	}

	if (-1 == min)
		min = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, min);

	return min;
}

static void	add_check(const char *key, const char *key_orig, int refresh, zbx_uint64_t lastlogsize, int mtime)
{
	const char		*__function_name = "add_check";
	ZBX_ACTIVE_METRIC	*metric;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' refresh:%d lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__function_name, key, refresh, lastlogsize, mtime);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		metric = (ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		if (0 != strcmp(metric->key_orig, key_orig))
			continue;

		if (0 != strcmp(metric->key, key))
		{
			int	j;

			zbx_free(metric->key);
			metric->key = zbx_strdup(NULL, key);
			metric->lastlogsize = lastlogsize;
			metric->mtime = mtime;
			metric->big_rec = 0;
			metric->use_ino = 0;
			metric->error_count = 0;

			for (j = 0; j < metric->logfiles_num; j++)
				zbx_free(metric->logfiles[j].filename);

			zbx_free(metric->logfiles);
			metric->logfiles_num = 0;
			metric->start_time = 0.0;
			metric->processed_bytes = 0;
		}

		/* replace metric */
		if (metric->refresh != refresh)
		{
			metric->nextcheck = 0;
			metric->refresh = refresh;
		}

		if (ITEM_STATE_NOTSUPPORTED == metric->state)
		{
			/* Currently receiving list of active checks works as a signal to refresh unsupported */
			/* items. Hopefully in the future this will be controlled by server (ZBXNEXT-2633). */
			metric->refresh_unsupported = 1;
			metric->start_time = 0.0;
			metric->processed_bytes = 0;
		}

		goto out;
	}

	metric = (ZBX_ACTIVE_METRIC *)zbx_malloc(NULL, sizeof(ZBX_ACTIVE_METRIC));

	/* add new metric */
	metric->key = zbx_strdup(NULL, key);
	metric->key_orig = zbx_strdup(NULL, key_orig);
	metric->refresh = refresh;
	metric->nextcheck = 0;
	metric->state = ITEM_STATE_NORMAL;
	metric->refresh_unsupported = 0;
	metric->lastlogsize = lastlogsize;
	metric->mtime = mtime;
	/* existing log[], log.count[] and eventlog[] data can be skipped */
	metric->skip_old_data = (0 != metric->lastlogsize ? 0 : 1);
	metric->big_rec = 0;
	metric->use_ino = 0;
	metric->error_count = 0;
	metric->logfiles_num = 0;
	metric->logfiles = NULL;
	metric->flags = ZBX_METRIC_FLAG_NEW;

	if ('l' == metric->key[0] && 'o' == metric->key[1] && 'g' == metric->key[2])
	{
		if ('[' == metric->key[3])					/* log[ */
			metric->flags |= ZBX_METRIC_FLAG_LOG_LOG;
		else if (0 == strncmp(metric->key + 3, "rt[", 3))		/* logrt[ */
			metric->flags |= ZBX_METRIC_FLAG_LOG_LOGRT;
		else if (0 == strncmp(metric->key + 3, ".count[", 7))		/* log.count[ */
			metric->flags |= ZBX_METRIC_FLAG_LOG_LOG | ZBX_METRIC_FLAG_LOG_COUNT;
		else if (0 == strncmp(metric->key + 3, "rt.count[", 9))		/* logrt.count[ */
			metric->flags |= ZBX_METRIC_FLAG_LOG_LOGRT | ZBX_METRIC_FLAG_LOG_COUNT;
	}
	else if (0 == strncmp(metric->key, "eventlog[", 9))
		metric->flags |= ZBX_METRIC_FLAG_LOG_EVENTLOG;

	metric->start_time = 0.0;
	metric->processed_bytes = 0;

	zbx_vector_ptr_append(&active_metrics, metric);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: mode_parameter_is_skip                                           *
 *                                                                            *
 * Purpose: test log[] or log.count[] item key if <mode> parameter is set to  *
 *          'skip'                                                            *
 *                                                                            *
 * Return value: SUCCEED - <mode> parameter is set to 'skip'                  *
 *               FAIL - <mode> is not 'skip' or error                         *
 *                                                                            *
 ******************************************************************************/
static int	mode_parameter_is_skip(unsigned char flags, const char *itemkey)
{
	AGENT_REQUEST	request;
	const char	*skip;
	int		ret = FAIL, max_num_parameters;

	if (0 == (ZBX_METRIC_FLAG_LOG_COUNT & flags))	/* log[] */
		max_num_parameters = 7;
	else						/* log.count[] */
		max_num_parameters = 6;

	init_request(&request);

	if (SUCCEED == parse_item_key(itemkey, &request) && 0 < get_rparams_num(&request) &&
			max_num_parameters >= get_rparams_num(&request) && NULL != (skip = get_rparam(&request, 4)) &&
			0 == strcmp(skip, "skip"))
	{
		ret = SUCCEED;
	}

	free_request(&request);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_list_of_checks                                             *
 *                                                                            *
 * Purpose: Parse list of active checks received from server                  *
 *                                                                            *
 * Parameters: str  - NULL terminated string received from server             *
 *             host - address of host                                         *
 *             port - port number on host                                     *
 *                                                                            *
 * Return value: returns SUCCEED on successful parsing,                       *
 *               FAIL on an incorrect format of string                        *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexei Vladishev (new json protocol)             *
 *                                                                            *
 * Comments:                                                                  *
 *    String represented as "ZBX_EOF" termination list                        *
 *    With '\n' delimiter between elements.                                   *
 *    Each element represented as:                                            *
 *           <key>:<refresh time>:<last log size>:<modification time>         *
 *                                                                            *
 ******************************************************************************/
static int	parse_list_of_checks(char *str, const char *host, unsigned short port)
{
	const char		*__function_name = "parse_list_of_checks";
	const char		*p;
	char			name[MAX_STRING_LEN], key_orig[MAX_STRING_LEN], expression[MAX_STRING_LEN],
				tmp[MAX_STRING_LEN], exp_delimiter;
	zbx_uint64_t		lastlogsize;
	struct zbx_json_parse	jp;
	struct zbx_json_parse	jp_data, jp_row;
	ZBX_ACTIVE_METRIC	*metric;
	zbx_vector_str_t	received_metrics;
	int			delay, mtime, expression_type, case_sensitive, i, j, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_str_create(&received_metrics);

	if (SUCCEED != zbx_json_open(str, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, tmp, sizeof(tmp)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

	if (0 != strcmp(tmp, ZBX_PROTO_VALUE_SUCCESS))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, tmp, sizeof(tmp)))
			zabbix_log(LOG_LEVEL_WARNING, "no active checks on server [%s:%hu]: %s", host, port, tmp);
		else
			zabbix_log(LOG_LEVEL_WARNING, "no active checks on server");

		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

 	p = NULL;
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
/* {"data":[{"key":"system.cpu.num",...,...},{...},...]}
 *          ^------------------------------^
 */ 		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, name, sizeof(name)) || '\0' == *name)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_KEY);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY_ORIG, key_orig, sizeof(key_orig))
				|| '\0' == *key_orig) {
			zbx_strlcpy(key_orig, name, sizeof(key_orig));
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DELAY, tmp, sizeof(tmp)) || '\0' == *tmp)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_DELAY);
			continue;
		}

		delay = atoi(tmp);

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LASTLOGSIZE, tmp, sizeof(tmp)) ||
				SUCCEED != is_uint64(tmp, &lastlogsize))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_LASTLOGSIZE);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_MTIME, tmp, sizeof(tmp)) || '\0' == *tmp)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_MTIME);
			mtime = 0;
		}
		else
			mtime = atoi(tmp);

		add_check(zbx_alias_get(name), key_orig, delay, lastlogsize, mtime);

		/* remember what was received */
		zbx_vector_str_append(&received_metrics, zbx_strdup(NULL, key_orig));
	}

	/* remove what wasn't received */
	for (i = 0; i < active_metrics.values_num; i++)
	{
		int	found = 0;

		metric = (ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		/* 'Do-not-delete' exception for log[] and log.count[] items with <mode> parameter set to 'skip'. */
		/* We need to keep their state, namely 'skip_old_data', in case the items become NOTSUPPORTED as */
		/* server might not send them in a new active check list. */

		if (0 != (ZBX_METRIC_FLAG_LOG_LOG & metric->flags) && ITEM_STATE_NOTSUPPORTED == metric->state &&
				0 == metric->skip_old_data && SUCCEED == mode_parameter_is_skip(metric->flags,
				metric->key))
		{
			continue;
		}

		for (j = 0; j < received_metrics.values_num; j++)
		{
			if (0 == strcmp(metric->key_orig, received_metrics.values[j]))
			{
				found = 1;
				break;
			}
		}

		if (0 == found)
		{
			zbx_vector_ptr_remove_noorder(&active_metrics, i);
			free_active_metric(metric);
			i--;	/* consider the same index on the next run */
		}
	}

	zbx_regexp_clean_expressions(&regexps);

	if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_REGEXP, &jp_data))
	{
	 	p = NULL;
		while (NULL != (p = zbx_json_next(&jp_data, p)))
		{
/* {"regexp":[{"name":"regexp1",...,...},{...},...]}
 *            ^------------------------^
 */			if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
				goto out;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "name", name, sizeof(name)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "name");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression", expression, sizeof(expression)) ||
					'\0' == *expression)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "expression");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression_type", tmp, sizeof(tmp)) ||
					'\0' == *tmp)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "expression_type");
				continue;
			}

			expression_type = atoi(tmp);

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "exp_delimiter", tmp, sizeof(tmp)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "exp_delimiter");
				continue;
			}

			exp_delimiter = tmp[0];

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "case_sensitive", tmp,
					sizeof(tmp)) || '\0' == *tmp)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "case_sensitive");
				continue;
			}

			case_sensitive = atoi(tmp);

			add_regexp_ex(&regexps, name, expression, expression_type, exp_delimiter, case_sensitive);
		}
	}

	ret = SUCCEED;
out:
	zbx_vector_str_clear_ext(&received_metrics, zbx_ptr_free);
	zbx_vector_str_destroy(&received_metrics);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: refresh_active_checks                                            *
 *                                                                            *
 * Purpose: Retrieve from Zabbix server list of active checks                 *
 *                                                                            *
 * Parameters: host - IP or Hostname of Zabbix server                         *
 *             port - port of Zabbix server                                   *
 *                                                                            *
 * Return value: returns SUCCEED on successful parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexei Vladishev (new json protocol)             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	refresh_active_checks(const char *host, unsigned short port)
{
	const char	*__function_name = "refresh_active_checks";

	ZBX_THREAD_LOCAL static int	last_ret = SUCCEED;
	int				ret;
	char				*tls_arg1, *tls_arg2;
	zbx_socket_t			s;
	struct zbx_json			json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%hu", __function_name, host, port);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (NULL != CONFIG_HOST_METADATA)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST_METADATA, CONFIG_HOST_METADATA, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != CONFIG_HOST_METADATA_ITEM)
	{
		char		**value;
		AGENT_RESULT	result;

		init_result(&result);

		if (SUCCEED == process(CONFIG_HOST_METADATA_ITEM, PROCESS_LOCAL_COMMAND | PROCESS_WITH_ALIAS, &result) &&
				NULL != (value = GET_STR_RESULT(&result)) && NULL != *value)
		{
			if (SUCCEED != zbx_is_utf8(*value))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot get host metadata using \"%s\" item specified by"
						" \"HostMetadataItem\" configuration parameter: returned value is not"
						" an UTF-8 string", CONFIG_HOST_METADATA_ITEM);
			}
			else
			{
				if (HOST_METADATA_LEN < zbx_strlen_utf8(*value))
				{
					size_t	bytes;

					zabbix_log(LOG_LEVEL_WARNING, "the returned value of \"%s\" item specified by"
							" \"HostMetadataItem\" configuration parameter is too long,"
							" using first %d characters", CONFIG_HOST_METADATA_ITEM,
							HOST_METADATA_LEN);

					bytes = zbx_strlen_utf8_nchars(*value, HOST_METADATA_LEN);
					(*value)[bytes] = '\0';
				}
				zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST_METADATA, *value, ZBX_JSON_TYPE_STRING);
			}
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "cannot get host metadata using \"%s\" item specified by"
					" \"HostMetadataItem\" configuration parameter", CONFIG_HOST_METADATA_ITEM);

		free_result(&result);
	}

	if (NULL != CONFIG_LISTEN_IP)
	{
		char	*p;

		if (NULL != (p = strchr(CONFIG_LISTEN_IP, ',')))
			*p = '\0';

		zbx_json_addstring(&json, ZBX_PROTO_TAG_IP, CONFIG_LISTEN_IP, ZBX_JSON_TYPE_STRING);

		if (NULL != p)
			*p = ',';
	}

	if (ZBX_DEFAULT_AGENT_PORT != CONFIG_LISTEN_PORT)
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_PORT, CONFIG_LISTEN_PORT);

	switch (configured_tls_connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
			tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = CONFIG_TLS_PSK_IDENTITY;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
			goto out;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, CONFIG_TIMEOUT,
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "sending [%s]", json.buffer);

		if (SUCCEED == (ret = zbx_tcp_send(&s, json.buffer)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "before read");

			if (SUCCEED == (ret = zbx_tcp_recv(&s)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "got [%s]", s.buffer);

				if (SUCCEED != last_ret)
				{
					zabbix_log(LOG_LEVEL_WARNING, "active check configuration update from [%s:%hu]"
							" is working again", host, port);
				}
				parse_list_of_checks(s.buffer, host, port);
			}
		}

		zbx_tcp_close(&s);
	}
out:
	if (SUCCEED != ret && SUCCEED == last_ret)
	{
		zabbix_log(LOG_LEVEL_WARNING,
				"active check configuration update from [%s:%hu] started to fail (%s)",
				host, port, zbx_socket_strerror());
	}

	last_ret = ret;

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_response                                                   *
 *                                                                            *
 * Purpose: Check whether JSON response is SUCCEED                            *
 *                                                                            *
 * Parameters: JSON response from Zabbix trapper                              *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: zabbix_sender has almost the same function!                      *
 *                                                                            *
 ******************************************************************************/
static int	check_response(char *response)
{
	const char		*__function_name = "check_response";

	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];
	char			info[MAX_STRING_LEN];
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() response:'%s'", __function_name, response);

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value));

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (SUCCEED == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info)))
		zabbix_log(LOG_LEVEL_DEBUG, "info from server: '%s'", info);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_buffer                                                      *
 *                                                                            *
 * Purpose: Send value stored in the buffer to Zabbix server                  *
 *                                                                            *
 * Parameters: host - IP or Hostname of Zabbix server                         *
 *             port - port number                                             *
 *                                                                            *
 * Return value: returns SUCCEED on successful sending,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	send_buffer(const char *host, unsigned short port)
{
	const char			*__function_name = "send_buffer";
	ZBX_ACTIVE_BUFFER_ELEMENT	*el;
	int				ret = SUCCEED, i, now;
	char				*tls_arg1, *tls_arg2;
	zbx_timespec_t			ts;
	const char			*err_send_step = "";
	zbx_socket_t			s;
	struct zbx_json 		json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%d entries:%d/%d",
			__function_name, host, port, buffer.count, CONFIG_BUFFER_SIZE);

	if (0 == buffer.count)
		goto ret;

	now = (int)time(NULL);

	if (CONFIG_BUFFER_SIZE / 2 > buffer.pcount && CONFIG_BUFFER_SIZE > buffer.count &&
			CONFIG_BUFFER_SEND > now - buffer.lastsent)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() now:%d lastsent:%d now-lastsent:%d BufferSend:%d; will not send now",
				__function_name, now, buffer.lastsent, now - buffer.lastsent, CONFIG_BUFFER_SEND);
		goto ret;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_AGENT_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < buffer.count; i++)
	{
		el = &buffer.data[i];

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, el->host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, el->key, ZBX_JSON_TYPE_STRING);

		if (NULL != el->value)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, el->value, ZBX_JSON_TYPE_STRING);

		if (ITEM_STATE_NOTSUPPORTED == el->state)
		{
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_STATE, ITEM_STATE_NOTSUPPORTED);
		}
		else
		{
			/* add item meta information only for items in normal state */
			if (0 != (ZBX_METRIC_FLAG_LOG & el->flags))
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_LASTLOGSIZE, el->lastlogsize);
			if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & el->flags))
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_MTIME, el->mtime);
		}

		if (0 != el->timestamp)
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LOGTIMESTAMP, el->timestamp);

		if (NULL != el->source)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_LOGSOURCE, el->source, ZBX_JSON_TYPE_STRING);

		if (0 != el->severity)
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LOGSEVERITY, el->severity);

		if (0 != el->logeventid)
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LOGEVENTID, el->logeventid);

		zbx_json_adduint64(&json, ZBX_PROTO_TAG_CLOCK, el->ts.sec);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_NS, el->ts.ns);
		zbx_json_close(&json);
	}

	zbx_json_close(&json);

	switch (configured_tls_connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
			tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = CONFIG_TLS_PSK_IDENTITY;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
			goto out;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, MIN(buffer.count * CONFIG_TIMEOUT, 60),
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		zbx_timespec(&ts);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_CLOCK, ts.sec);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_NS, ts.ns);

		zabbix_log(LOG_LEVEL_DEBUG, "JSON before sending [%s]", json.buffer);

		if (SUCCEED == (ret = zbx_tcp_send(&s, json.buffer)))
		{
			if (SUCCEED == zbx_tcp_recv(&s))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "JSON back [%s]", s.buffer);

				if (NULL == s.buffer || SUCCEED != check_response(s.buffer))
					zabbix_log(LOG_LEVEL_DEBUG, "NOT OK");
				else
					zabbix_log(LOG_LEVEL_DEBUG, "OK");
			}
			else
				err_send_step = "[recv] ";
		}
		else
			err_send_step = "[send] ";

		zbx_tcp_close(&s);
	}
	else
		err_send_step = "[connect] ";
out:
	zbx_json_free(&json);

	if (SUCCEED == ret)
	{
		/* free buffer */
		for (i = 0; i < buffer.count; i++)
		{
			el = &buffer.data[i];

			zbx_free(el->host);
			zbx_free(el->key);
			zbx_free(el->value);
			zbx_free(el->source);
		}
		buffer.count = 0;
		buffer.pcount = 0;
		buffer.lastsent = now;
		if (0 != buffer.first_error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "active check data upload to [%s:%hu] is working again",
					host, port);
			buffer.first_error = 0;
		}
	}
	else
	{
		if (0 == buffer.first_error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "active check data upload to [%s:%hu] started to fail (%s%s)",
					host, port, err_send_step, zbx_socket_strerror());
			buffer.first_error = now;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "send value error: %s%s", err_send_step, zbx_socket_strerror());
	}
ret:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_value                                                    *
 *                                                                            *
 * Purpose: Buffer new value or send the whole buffer to the server           *
 *                                                                            *
 * Parameters: server      - IP or Hostname of Zabbix server                  *
 *             port        - port of Zabbix server                            *
 *             host        - name of host in Zabbix database                  *
 *             key         - name of metric                                   *
 *             value       - key value or error message why an item became    *
 *                           NOTSUPPORTED                                     *
 *             state       - ITEM_STATE_NORMAL or ITEM_STATE_NOTSUPPORTED     *
 *             lastlogsize - size of read logfile                             *
 *             mtime       - time of last file modification                   *
 *             timestamp   - timestamp of read value                          *
 *             source      - name of logged data source                       *
 *             severity    - severity of logged data sources                  *
 *             logeventid  - the application-specific identifier for          *
 *                           the event; used for monitoring of Windows        *
 *                           event logs                                       *
 *             flags       - metric flags                                     *
 *                                                                            *
 * Return value: returns SUCCEED on successful parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: ATTENTION! This function's address and pointers to arguments     *
 *           are described in Zabbix defined type "zbx_process_value_func_t"  *
 *           and used when calling process_log(), process_logrt() and         *
 *           zbx_read2(). If you ever change this process_value() arguments   *
 *           or return value do not forget to synchronize changes with the    *
 *           defined type "zbx_process_value_func_t" and implementations of   *
 *           process_log(), process_logrt(), zbx_read2() and their callers.   *
 *                                                                            *
 ******************************************************************************/
static int	process_value(const char *server, unsigned short port, const char *host, const char *key,
		const char *value, unsigned char state, zbx_uint64_t *lastlogsize, const int *mtime,
		unsigned long *timestamp, const char *source, unsigned short *severity, unsigned long *logeventid,
		unsigned char flags)
{
	const char			*__function_name = "process_value";
	ZBX_ACTIVE_BUFFER_ELEMENT	*el = NULL;
	int				i, ret = FAIL;
	size_t				sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s:%s' value:'%s'", __function_name, host, key, ZBX_NULL2STR(value));

	send_buffer(server, port);

	if (0 != (ZBX_METRIC_FLAG_PERSISTENT & flags) && CONFIG_BUFFER_SIZE / 2 <= buffer.pcount)
	{
		zabbix_log(LOG_LEVEL_WARNING, "buffer is full, cannot store persistent value");
		goto out;
	}

	if (CONFIG_BUFFER_SIZE > buffer.count)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "buffer: new element %d", buffer.count);
		el = &buffer.data[buffer.count];
		buffer.count++;
	}
	else
	{
		if (0 == (ZBX_METRIC_FLAG_PERSISTENT & flags))
		{
			for (i = 0; i < buffer.count; i++)
			{
				el = &buffer.data[i];
				if (0 == strcmp(el->host, host) && 0 == strcmp(el->key, key))
					break;
			}
		}

		if (0 != (ZBX_METRIC_FLAG_PERSISTENT & flags) || i == buffer.count)
		{
			for (i = 0; i < buffer.count; i++)
			{
				el = &buffer.data[i];
				if (0 == (ZBX_METRIC_FLAG_PERSISTENT & el->flags))
					break;
			}
		}

		if (NULL != el)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "remove element [%d] Key:'%s:%s'", i, el->host, el->key);

			zbx_free(el->host);
			zbx_free(el->key);
			zbx_free(el->value);
			zbx_free(el->source);
		}

		sz = (CONFIG_BUFFER_SIZE - i - 1) * sizeof(ZBX_ACTIVE_BUFFER_ELEMENT);
		memmove(&buffer.data[i], &buffer.data[i + 1], sz);

		zabbix_log(LOG_LEVEL_DEBUG, "buffer full: new element %d", buffer.count - 1);

		el = &buffer.data[CONFIG_BUFFER_SIZE - 1];
	}

	memset(el, 0, sizeof(ZBX_ACTIVE_BUFFER_ELEMENT));
	el->host = zbx_strdup(NULL, host);
	el->key = zbx_strdup(NULL, key);
	if (NULL != value)
		el->value = zbx_strdup(NULL, value);
	el->state = state;

	if (NULL != source)
		el->source = strdup(source);
	if (NULL != severity)
		el->severity = *severity;
	if (NULL != lastlogsize)
		el->lastlogsize = *lastlogsize;
	if (NULL != mtime)
		el->mtime = *mtime;
	if (NULL != timestamp)
		el->timestamp = *timestamp;
	if (NULL != logeventid)
		el->logeventid = (int)*logeventid;

	zbx_timespec(&el->ts);
	el->flags = flags;

	if (0 != (ZBX_METRIC_FLAG_PERSISTENT & flags))
		buffer.pcount++;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	need_meta_update(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
		unsigned char old_state, zbx_uint64_t lastlogsize_last, int mtime_last)
{
	const char	*__function_name = "need_meta_update";

	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:%s", __function_name, metric->key);

	if (0 != (ZBX_METRIC_FLAG_LOG & metric->flags))
	{
		/* meta information update is needed if:                                              */
		/* - lastlogsize or mtime changed since we last sent within this check                */
		/* - nothing was sent during this check and state changed from notsupported to normal */
		/* - nothing was sent during this check and it's a new metric                         */
		if (lastlogsize_sent != metric->lastlogsize || mtime_sent != metric->mtime ||
				(lastlogsize_last == lastlogsize_sent && mtime_last == mtime_sent &&
						(old_state != metric->state ||
						0 != (ZBX_METRIC_FLAG_NEW & metric->flags))))
		{
			/* needs meta information update */
			ret = SUCCEED;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	global_regexp_exists(const char *name)
{
	int	i;

	if (0 == regexps.values_num)
		return FAIL;

	for (i = 0; i < regexps.values_num; i++)
	{
		zbx_expression_t	*regexp = (zbx_expression_t *)regexps.values[i];

		if (0 == strcmp(regexp->name, name))
			break;
	}

	return (i == regexps.values_num ? FAIL : SUCCEED);
}

static int	check_number_of_parameters(unsigned char flags, const AGENT_REQUEST *request, char **error)
{
	int	parameter_num, max_parameter_num;

	if (0 == (parameter_num = get_rparams_num(request)))
	{
		*error = zbx_strdup(*error, "Invalid number of parameters.");
		return FAIL;
	}

	if (0 != (ZBX_METRIC_FLAG_LOG_LOG & flags) && 0 != (ZBX_METRIC_FLAG_LOG_COUNT & flags))	/* log.count */
		max_parameter_num = 6;
	else if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags) && 0 == (ZBX_METRIC_FLAG_LOG_COUNT & flags))	/* logrt */
		max_parameter_num = 8;
	else
		max_parameter_num = 7;	/* log or logrt.count */

	if (max_parameter_num < parameter_num)
	{
		*error = zbx_strdup(*error, "Too many parameters.");
		return FAIL;
	}

	return SUCCEED;
}

static int	init_max_lines_per_sec(int is_count_item, const AGENT_REQUEST *request, int *max_lines_per_sec,
		char **error)
{
	const char	*p;
	int		rate;

	if (NULL == (p = get_rparam(request, 3)) || '\0' == *p)
	{
		if (0 == is_count_item)				/* log[], logrt[] */
			*max_lines_per_sec = CONFIG_MAX_LINES_PER_SECOND;
		else						/* log.count[], logrt.count[] */
			*max_lines_per_sec = MAX_VALUE_LINES_MULTIPLIER * CONFIG_MAX_LINES_PER_SECOND;

		return SUCCEED;
	}

	if (MIN_VALUE_LINES > (rate = atoi(p)) ||
			(0 == is_count_item && MAX_VALUE_LINES < rate) ||
			(1 == is_count_item && MAX_VALUE_LINES_MULTIPLIER * MAX_VALUE_LINES < rate))
	{
		*error = zbx_strdup(*error, "Invalid fourth parameter.");
		return FAIL;
	}

	*max_lines_per_sec = rate;
	return SUCCEED;
}

static int	init_max_delay(int is_count_item, const AGENT_REQUEST *request, float *max_delay, char **error)
{
	const char	*max_delay_str;
	float		max_delay_tmp;
	int		max_delay_par_nr;

	/* <maxdelay> is parameter 6 for log[], logrt[], but parameter 5 for log.count[], logrt.count[] */

	if (0 == is_count_item)
		max_delay_par_nr = 6;
	else
		max_delay_par_nr = 5;

	if (NULL == (max_delay_str = get_rparam(request, max_delay_par_nr)) || '\0' == *max_delay_str)
	{
		*max_delay = 0.0f;
		return SUCCEED;
	}

	if (SUCCEED != is_double(max_delay_str) || 0.0f > (max_delay_tmp = (float)atof(max_delay_str)))
	{
		*error = zbx_dsprintf(*error, "Invalid %s parameter.", (5 == max_delay_par_nr) ? "sixth" : "seventh");
		return FAIL;
	}

	*max_delay = max_delay_tmp;
	return SUCCEED;
}

static int	init_rotation_type(unsigned char flags, const AGENT_REQUEST *request, int *rotation_type, char **error)
{
	if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags))
	{
		char	*options;
		int	options_par_nr;

		if (0 == (ZBX_METRIC_FLAG_LOG_COUNT & flags))	/* logrt */
			options_par_nr = 7;
		else						/* logrt.count */
			options_par_nr = 6;

		if (NULL != (options = get_rparam(request, options_par_nr)) && '\0' != *options)
		{
			if (0 == strcmp(options, "copytruncate"))
			{
				*rotation_type = ZBX_LOG_ROTATION_LOGCPT;
				return SUCCEED;
			}

			if (0 != strcmp(options, "rotate"))
			{
				*error = zbx_dsprintf(*error, "Invalid %s parameter.", (6 == options_par_nr) ?
						"seventh" : "eighth");
				return FAIL;
			}
		}
	}

	*rotation_type = ZBX_LOG_ROTATION_LOGRT;	/* default */
	return SUCCEED;
}

static int	process_log_check(char *server, unsigned short port, ZBX_ACTIVE_METRIC *metric,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, char **error)
{
	AGENT_REQUEST		request;
	const char		*filename, *regexp, *encoding, *skip, *output_template;
	char			*encoding_uc = NULL;
	int			max_lines_per_sec, ret = FAIL, s_count, p_count, s_count_orig, is_count_item,
				mtime_orig, big_rec_orig, logfiles_num_new = 0, jumped = 0, rotation_type;
	zbx_uint64_t		lastlogsize_orig;
	float			max_delay;
	struct st_logfile	*logfiles_new = NULL;

	if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags))
		is_count_item = 1;
	else
		is_count_item = 0;

	init_request(&request);

	/* Expected parameters by item: */
	/* log        [file,       <regexp>,<encoding>,<maxlines>,    <mode>,<output>,<maxdelay>]            7 params */
	/* log.count  [file,       <regexp>,<encoding>,<maxproclines>,<mode>,         <maxdelay>]            6 params */
	/* logrt      [file_regexp,<regexp>,<encoding>,<maxlines>,    <mode>,<output>,<maxdelay>, <options>] 8 params */
	/* logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,         <maxdelay>, <options>] 7 params */

	if (SUCCEED != parse_item_key(metric->key, &request))
	{
		*error = zbx_strdup(*error, "Invalid item key format.");
		goto out;
	}

	if (SUCCEED != check_number_of_parameters(metric->flags, &request, error))
		goto out;

	/* parameter 'file' or 'file_regexp' */

	if (NULL == (filename = get_rparam(&request, 0)) || '\0' == *filename)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		goto out;
	}

	/* parameter 'regexp' */

	if (NULL == (regexp = get_rparam(&request, 1)))
	{
		regexp = "";
	}
	else if ('@' == *regexp && SUCCEED != global_regexp_exists(regexp + 1))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", regexp + 1);
		goto out;
	}

	/* parameter 'encoding' */

	if (NULL == (encoding = get_rparam(&request, 2)))
	{
		encoding = "";
	}
	else
	{
		encoding_uc = zbx_strdup(encoding_uc, encoding);
		zbx_strupper(encoding_uc);
		encoding = encoding_uc;
	}

	/* parameter 'maxlines' or 'maxproclines' */
	if (SUCCEED !=  init_max_lines_per_sec(is_count_item, &request, &max_lines_per_sec, error))
		goto out;

	/* parameter 'mode' */

	if (NULL == (skip = get_rparam(&request, 4)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		metric->skip_old_data = 0;
	}
	else if (0 != strcmp(skip, "skip"))
	{
		*error = zbx_strdup(*error, "Invalid fifth parameter.");
		goto out;
	}

	/* parameter 'output' (not used for log.count[], logrt.count[]) */
	if (1 == is_count_item || (NULL == (output_template = get_rparam(&request, 5))))
		output_template = "";

	/* parameter 'maxdelay' */
	if (SUCCEED != init_max_delay(is_count_item, &request, &max_delay, error))
		goto out;

	/* parameter 'options' */
	if (SUCCEED != init_rotation_type(metric->flags, &request, &rotation_type, error))
		goto out;

	/* jumping over fast growing log files is not supported with 'copytruncate' */
	if (ZBX_LOG_ROTATION_LOGCPT == rotation_type && 0.0f != max_delay)
	{
		*error = zbx_strdup(*error, "maxdelay > 0 is not supported with copytruncate option.");
		goto out;
	}

	/* do not flood Zabbix server if file grows too fast */
	s_count = max_lines_per_sec * metric->refresh;

	/* do not flood local system if file grows too fast */
	if (0 == is_count_item)
	{
		p_count = MAX_VALUE_LINES_MULTIPLIER * s_count;	/* log[], logrt[] */
	}
	else
	{
		/* In log.count[] and logrt.count[] items the variable 's_count' (max number of lines allowed to be */
		/* sent to server) is used for counting matching lines in logfile(s). 's_count' is counted from max */
		/* value down towards 0. */

		p_count = s_count_orig = s_count;

		/* remember current state, we may need to restore it if log.count[] or logrt.count[] result cannot */
		/* be sent to server */

		lastlogsize_orig = metric->lastlogsize;
		mtime_orig = metric->mtime;
		big_rec_orig = metric->big_rec;

		/* process_logrt() may modify old log file list 'metric->logfiles' but currently modifications are */
		/* limited to 'retry' flag in existing list elements. We do not preserve original 'retry' flag values */
		/* as there is no need to "rollback" their modifications if log.count[] or logrt.count[] result can */
		/* not be sent to server. */
	}

	ret = process_logrt(metric->flags, filename, &metric->lastlogsize, &metric->mtime, lastlogsize_sent, mtime_sent,
			&metric->skip_old_data, &metric->big_rec, &metric->use_ino, error, &metric->logfiles,
			&metric->logfiles_num, &logfiles_new, &logfiles_num_new, encoding, &regexps, regexp,
			output_template, &p_count, &s_count, process_value, server, port, CONFIG_HOSTNAME,
			metric->key_orig, &jumped, max_delay, &metric->start_time, &metric->processed_bytes,
			rotation_type);

	if (0 == is_count_item && NULL != logfiles_new)
	{
		/* for log[] and logrt[] items - switch to the new log file list */

		destroy_logfile_list(&metric->logfiles, NULL, &metric->logfiles_num);
		metric->logfiles = logfiles_new;
		metric->logfiles_num = logfiles_num_new;
	}

	if (SUCCEED == ret)
	{
		metric->error_count = 0;

		if (1 == is_count_item)
		{
			/* send log.count[] or logrt.count[] item value to server */

			int	match_count;			/* number of matching lines */
			char	buf[ZBX_MAX_UINT64_LEN];

			match_count = s_count_orig - s_count;

			zbx_snprintf(buf, sizeof(buf), "%d", match_count);

			if (SUCCEED == process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, buf,
					ITEM_STATE_NORMAL, &metric->lastlogsize, &metric->mtime, NULL, NULL, NULL, NULL,
					metric->flags | ZBX_METRIC_FLAG_PERSISTENT) || 0 != jumped)
			{
				/* if process_value() fails (i.e. log(rt).count result cannot be sent to server) but */
				/* a jump took place to meet <maxdelay> then we discard the result and keep the state */
				/* after jump */

				*lastlogsize_sent = metric->lastlogsize;
				*mtime_sent = metric->mtime;

				/* switch to the new log file list */
				destroy_logfile_list(&metric->logfiles, NULL, &metric->logfiles_num);
				metric->logfiles = logfiles_new;
				metric->logfiles_num = logfiles_num_new;
			}
			else
			{
				/* unable to send data and no jump took place, restore original state to try again */
				/* during the next check */

				metric->lastlogsize = lastlogsize_orig;
				metric->mtime =  mtime_orig;
				metric->big_rec = big_rec_orig;

				/* the old log file list 'metric->logfiles' stays in its place, drop the new list */
				destroy_logfile_list(&logfiles_new, NULL, &logfiles_num_new);
			}
		}
	}
	else
	{
		metric->error_count++;

		if (1 == is_count_item)
		{
			/* restore original state to try again during the next check */

			metric->lastlogsize = lastlogsize_orig;
			metric->mtime =  mtime_orig;
			metric->big_rec = big_rec_orig;

			/* the old log file list 'metric->logfiles' stays in its place, drop the new list */
			destroy_logfile_list(&logfiles_new, NULL, &logfiles_num_new);
		}

		/* suppress first two errors */
		if (3 > metric->error_count)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "suppressing log(rt)(.count) processing error #%d: %s",
					metric->error_count, NULL != *error ? *error : "unknown error");

			zbx_free(*error);
			ret = SUCCEED;
		}
	}
out:
	zbx_free(encoding_uc);
	free_request(&request);

	return ret;
}

static int	process_eventlog_check(char *server, unsigned short port, ZBX_ACTIVE_METRIC *metric,
		zbx_uint64_t *lastlogsize_sent, char **error)
{
	int 		ret = FAIL;

#ifdef _WINDOWS
	AGENT_REQUEST	request;
	const char	*filename, *pattern, *key_severity, *key_source, *key_logeventid, *maxlines_persec, *skip,
			*str_severity;
	int		rate, s_count, p_count, match = SUCCEED, send_err = SUCCEED;
	char		*value = NULL, *provider = NULL, *source = NULL, str_logeventid[8];
	zbx_uint64_t	lastlogsize;
	unsigned long	timestamp, logeventid;
	unsigned short	severity;
	OSVERSIONINFO	versionInfo;
	zbx_uint64_t	keywords;
	EVT_HANDLE	eventlog6_render_context = NULL;
	EVT_HANDLE	eventlog6_query = NULL;
	zbx_uint64_t	eventlog6_firstid = 0;
	zbx_uint64_t	eventlog6_lastid = 0;

	init_request(&request);

	if (SUCCEED != parse_item_key(metric->key, &request))
	{
		*error = zbx_strdup(*error, "Invalid item key format.");
		goto out;
	}

	if (0 == get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Invalid number of parameters.");
		goto out;
	}

	if (7 < get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Too many parameters.");
		goto out;
	}

	if (NULL == (filename = get_rparam(&request, 0)) || '\0' == *filename)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		goto out;
	}

	if (NULL == (pattern = get_rparam(&request, 1)))
	{
		pattern = "";
	}
	else if ('@' == *pattern && SUCCEED != global_regexp_exists(pattern + 1))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", pattern + 1);
		goto out;
	}

	if (NULL == (key_severity = get_rparam(&request, 2)))
	{
		key_severity = "";
	}
	else if ('@' == *key_severity && SUCCEED != global_regexp_exists(key_severity + 1))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_severity + 1);
		goto out;
	}

	if (NULL == (key_source = get_rparam(&request, 3)))
	{
		key_source = "";
	}
	else if ('@' == *key_source && SUCCEED != global_regexp_exists(key_source + 1))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_source + 1);
		goto out;
	}

	if (NULL == (key_logeventid = get_rparam(&request, 4)))
	{
		key_logeventid = "";
	}
	else if ('@' == *key_logeventid && SUCCEED != global_regexp_exists(key_logeventid + 1))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_logeventid + 1);
		goto out;
	}

	if (NULL == (maxlines_persec = get_rparam(&request, 5)) || '\0' == *maxlines_persec)
	{
		rate = CONFIG_MAX_LINES_PER_SECOND;
	}
	else if (MIN_VALUE_LINES > (rate = atoi(maxlines_persec)) || MAX_VALUE_LINES < rate)
	{
		*error = zbx_strdup(*error, "Invalid sixth parameter.");
		goto out;
	}

	if (NULL == (skip = get_rparam(&request, 6)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		metric->skip_old_data = 0;
	}
	else if (0 != strcmp(skip, "skip"))
	{
		*error = zbx_strdup(*error, "Invalid seventh parameter.");
		goto out;
	}

	s_count = 0;
	p_count = 0;
	lastlogsize = metric->lastlogsize;

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);

	if (versionInfo.dwMajorVersion >= 6)	/* Windows Vista, 7 or Server 2008 */
	{
		__try
		{
			if (SUCCEED != initialize_eventlog6(filename, &lastlogsize, &eventlog6_firstid,
					&eventlog6_lastid, &eventlog6_render_context, &eventlog6_query))
			{
				finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
				goto out;
			}

			while (SUCCEED == (ret = process_eventlog6(filename, &lastlogsize, &timestamp, &provider,
					&source, &severity, &value, &logeventid, &eventlog6_firstid, &eventlog6_lastid,
					&eventlog6_render_context, &eventlog6_query, &keywords, metric->skip_old_data)))
			{
				metric->skip_old_data = 0;

				/* End of file. */
				/* The eventlog could become empty, must save `lastlogsize'. */
				if (NULL == value)
				{
					metric->lastlogsize = lastlogsize;
					break;
				}

				switch (severity)
				{
					case WINEVENT_LEVEL_LOG_ALWAYS:
					case WINEVENT_LEVEL_INFO:
						if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_FAILURE))
						{
							severity = ITEM_LOGTYPE_FAILURE_AUDIT;
							str_severity = AUDIT_FAILURE;
							break;
						}
						else if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_SUCCESS))
						{
							severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
							str_severity = AUDIT_SUCCESS;
							break;
						}
						else
							severity = ITEM_LOGTYPE_INFORMATION;
							str_severity = INFORMATION_TYPE;
							break;
					case WINEVENT_LEVEL_WARNING:
						severity = ITEM_LOGTYPE_WARNING;
						str_severity = WARNING_TYPE;
						break;
					case WINEVENT_LEVEL_ERROR:
						severity = ITEM_LOGTYPE_ERROR;
						str_severity = ERROR_TYPE;
						break;
					case WINEVENT_LEVEL_CRITICAL:
						severity = ITEM_LOGTYPE_CRITICAL;
						str_severity = CRITICAL_TYPE;
						break;
					case WINEVENT_LEVEL_VERBOSE:
						severity = ITEM_LOGTYPE_VERBOSE;
						str_severity = VERBOSE_TYPE;
						break;
				}

				zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", logeventid);

				if (0 == p_count)
				{
					int	ret1, ret2, ret3, ret4;

					if (FAIL == (ret1 = regexp_match_ex(&regexps, value, pattern,
							ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the second parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret2 = regexp_match_ex(&regexps, str_severity, key_severity,
							ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the third parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret3 = regexp_match_ex(&regexps, provider, key_source,
							ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fourth parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret4 = regexp_match_ex(&regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fifth parameter.");
						match = FAIL;
					}

					if (FAIL == match)
					{
						zbx_free(source);
						zbx_free(provider);
						zbx_free(value);

						ret = FAIL;
						break;
					}

					match = (ZBX_REGEXP_MATCH == ret1 && ZBX_REGEXP_MATCH == ret2 &&
							ZBX_REGEXP_MATCH == ret3 && ZBX_REGEXP_MATCH == ret4);
				}
				else
				{
					match = (ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, value, pattern,
								ZBX_CASE_SENSITIVE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, str_severity,
								key_severity, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, provider,
								key_source, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, str_logeventid,
								key_logeventid, ZBX_CASE_SENSITIVE));
				}

				if (1 == match)
				{
					send_err = process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, value,
							ITEM_STATE_NORMAL, &lastlogsize, NULL, &timestamp, provider,
							&severity, &logeventid,
							metric->flags | ZBX_METRIC_FLAG_PERSISTENT);

					if (SUCCEED == send_err)
					{
						*lastlogsize_sent = lastlogsize;
						s_count++;
					}
				}
				p_count++;

				zbx_free(source);
				zbx_free(provider);
				zbx_free(value);

				if (SUCCEED == send_err)
				{
					metric->lastlogsize = lastlogsize;
				}
				else
				{
					/* buffer is full, stop processing active checks */
					/* till the buffer is cleared */
					lastlogsize = metric->lastlogsize;
					break;
				}

				/* do not flood Zabbix server if file grows too fast */
				if (s_count >= (rate * metric->refresh))
					break;

				/* do not flood local system if file grows too fast */
				if (p_count >= (MAX_VALUE_LINES_MULTIPLIER * rate * metric->refresh))
					break;

			}	/* while processing an eventlog */

			finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
		}
		__except (DelayLoadDllExceptionFilter(GetExceptionInformation()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to process eventlog");
		}
	}
	else if (versionInfo.dwMajorVersion < 6)    /* Windows versions before Vista */
	{
		while (SUCCEED == (ret = process_eventlog(filename, &lastlogsize, &timestamp, &source, &severity,
				&value, &logeventid, metric->skip_old_data)))
		{
			metric->skip_old_data = 0;

			/* End of file. */
			/* The eventlog could become empty, must save `lastlogsize'. */
			if (NULL == value)
			{
				metric->lastlogsize = lastlogsize;
				break;
			}

			switch (severity)
			{
				case EVENTLOG_SUCCESS:
				case EVENTLOG_INFORMATION_TYPE:
					severity = ITEM_LOGTYPE_INFORMATION;
					str_severity = INFORMATION_TYPE;
					break;
				case EVENTLOG_WARNING_TYPE:
					severity = ITEM_LOGTYPE_WARNING;
					str_severity = WARNING_TYPE;
					break;
				case EVENTLOG_ERROR_TYPE:
					severity = ITEM_LOGTYPE_ERROR;
					str_severity = ERROR_TYPE;
					break;
				case EVENTLOG_AUDIT_FAILURE:
					severity = ITEM_LOGTYPE_FAILURE_AUDIT;
					str_severity = AUDIT_FAILURE;
					break;
				case EVENTLOG_AUDIT_SUCCESS:
					severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
					str_severity = AUDIT_SUCCESS;
					break;
			}

			zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", logeventid);

			if (0 == p_count)
			{
				int	ret1, ret2, ret3, ret4;

				if (FAIL == (ret1 = regexp_match_ex(&regexps, value, pattern, ZBX_CASE_SENSITIVE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the second parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret2 = regexp_match_ex(&regexps, str_severity, key_severity,
						ZBX_IGNORE_CASE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the third parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret3 = regexp_match_ex(&regexps, source, key_source,
						ZBX_IGNORE_CASE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the fourth parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret4 = regexp_match_ex(&regexps, str_logeventid, key_logeventid,
						ZBX_CASE_SENSITIVE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the fifth parameter.");
					match = FAIL;
				}

				if (FAIL == match)
				{
					zbx_free(source);
					zbx_free(value);

					ret = FAIL;
					break;
				}

				match = (ZBX_REGEXP_MATCH == ret1 && ZBX_REGEXP_MATCH == ret2 &&
						ZBX_REGEXP_MATCH == ret3 && ZBX_REGEXP_MATCH == ret4);
			}
			else
			{
				match = (ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, value, pattern,
							ZBX_CASE_SENSITIVE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, str_severity,
							key_severity, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, source,
							key_source, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(&regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE));
			}

			if (1 == match)
			{
				send_err = process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, value,
						ITEM_STATE_NORMAL, &lastlogsize, NULL, &timestamp, source, &severity,
						&logeventid, metric->flags | ZBX_METRIC_FLAG_PERSISTENT);

				if (SUCCEED == send_err)
				{
					*lastlogsize_sent = lastlogsize;
					s_count++;
				}
			}
			p_count++;

			zbx_free(source);
			zbx_free(value);

			if (SUCCEED == send_err)
			{
				metric->lastlogsize = lastlogsize;
			}
			else
			{
				/* buffer is full, stop processing active checks */
				/* till the buffer is cleared */
				lastlogsize = metric->lastlogsize;
				break;
			}

			/* do not flood Zabbix server if file grows too fast */
			if (s_count >= (rate * metric->refresh))
				break;

			/* do not flood local system if file grows too fast */
			if (p_count >= (MAX_VALUE_LINES_MULTIPLIER * rate * metric->refresh))
				break;
		} /* while processing an eventlog */
	}
out:
	free_request(&request);
#else	/* not _WINDOWS */
	ZBX_UNUSED(server);
	ZBX_UNUSED(port);
	ZBX_UNUSED(metric);
	ZBX_UNUSED(lastlogsize_sent);
	ZBX_UNUSED(error);
#endif	/* _WINDOWS */

	return ret;
}

static int	process_common_check(char *server, unsigned short port, ZBX_ACTIVE_METRIC *metric, char **error)
{
	int		ret;
	AGENT_RESULT	result;
	char		**pvalue;

	init_result(&result);

	if (SUCCEED != (ret = process(metric->key, 0, &result)))
	{
		if (NULL != (pvalue = GET_MSG_RESULT(&result)))
			*error = zbx_strdup(*error, *pvalue);
		goto out;
	}

	if (NULL != (pvalue = GET_TEXT_RESULT(&result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "for key [%s] received value [%s]", metric->key, *pvalue);

		process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, *pvalue, ITEM_STATE_NORMAL, NULL, NULL,
				NULL, NULL, NULL, NULL, metric->flags);
	}
out:
	free_result(&result);

	return ret;
}

static void	process_active_checks(char *server, unsigned short port)
{
	const char	*__function_name = "process_active_checks";
	char		*error = NULL;
	int		i, now, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() server:'%s' port:%hu", __function_name, server, port);

	now = (int)time(NULL);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		zbx_uint64_t		lastlogsize_last, lastlogsize_sent;
		int			mtime_last, mtime_sent;
		ZBX_ACTIVE_METRIC	*metric;

		metric = (ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		if (metric->nextcheck > now)
			continue;

		if (SUCCEED != metric_ready_to_process(metric))
			continue;

		/* for meta information update we need to know if something was sent at all during the check */
		lastlogsize_last = metric->lastlogsize;
		mtime_last = metric->mtime;

		lastlogsize_sent = metric->lastlogsize;
		mtime_sent = metric->mtime;

		/* before processing make sure refresh is not 0 to avoid overload */
		if (0 == metric->refresh)
		{
			ret = FAIL;
			error = zbx_strdup(error, "Incorrect update interval.");
		}
		else if (0 != ((ZBX_METRIC_FLAG_LOG_LOG | ZBX_METRIC_FLAG_LOG_LOGRT) & metric->flags))
			ret = process_log_check(server, port, metric, &lastlogsize_sent, &mtime_sent, &error);
		else if (0 != (ZBX_METRIC_FLAG_LOG_EVENTLOG & metric->flags))
			ret = process_eventlog_check(server, port, metric, &lastlogsize_sent, &error);
		else
			ret = process_common_check(server, port, metric, &error);

		if (SUCCEED != ret)
		{
			const char	*perror;

			perror = (NULL != error ? error : ZBX_NOTSUPPORTED_MSG);

			metric->state = ITEM_STATE_NOTSUPPORTED;
			metric->refresh_unsupported = 0;
			metric->error_count = 0;
			metric->start_time = 0.0;
			metric->processed_bytes = 0;

			zabbix_log(LOG_LEVEL_WARNING, "active check \"%s\" is not supported: %s", metric->key, perror);

			process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, perror, ITEM_STATE_NOTSUPPORTED,
					&metric->lastlogsize, &metric->mtime, NULL, NULL, NULL, NULL, metric->flags);

			zbx_free(error);
		}
		else
		{
			if (0 == metric->error_count)
			{
				unsigned char	old_state;

				old_state = metric->state;

				if (ITEM_STATE_NOTSUPPORTED == metric->state)
				{
					/* item became supported */
					metric->state = ITEM_STATE_NORMAL;
					metric->refresh_unsupported = 0;
				}

				if (SUCCEED == need_meta_update(metric, lastlogsize_sent, mtime_sent, old_state,
						lastlogsize_last, mtime_last))
				{
					/* meta information update */
					process_value(server, port, CONFIG_HOSTNAME, metric->key_orig, NULL,
							metric->state, &metric->lastlogsize, &metric->mtime, NULL, NULL,
							NULL, NULL, metric->flags);
				}

				/* remove "new metric" flag */
				metric->flags &= ~ZBX_METRIC_FLAG_NEW;
			}
		}

		metric->nextcheck = (int)time(NULL) + metric->refresh;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: update_schedule                                                  *
 *                                                                            *
 * Purpose: update active check and send buffer schedule by the specified     *
 *          time delta                                                        *
 *                                                                            *
 * Parameters: delta - [IN] the time delta in seconds                         *
 *                                                                            *
 * Comments: This function is used to update checking and sending schedules   *
 *           if the system time was rolled back.                              *
 *                                                                            *
 ******************************************************************************/
static void	update_schedule(int delta)
{
	int	i;

	for (i = 0; i < active_metrics.values_num; i++)
	{
		ZBX_ACTIVE_METRIC	*metric = (ZBX_ACTIVE_METRIC *)active_metrics.values[i];
		metric->nextcheck += delta;
	}

	buffer.lastsent += delta;
}

ZBX_THREAD_ENTRY(active_checks_thread, args)
{
	ZBX_THREAD_ACTIVECHK_ARGS activechk_args;

	time_t	nextcheck = 0, nextrefresh = 0, nextsend = 0, now, delta, lastcheck = 0;

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	activechk_args.host = zbx_strdup(NULL, ((ZBX_THREAD_ACTIVECHK_ARGS *)((zbx_thread_args_t *)args)->args)->host);
	activechk_args.port = ((ZBX_THREAD_ACTIVECHK_ARGS *)((zbx_thread_args_t *)args)->args)->port;

	zbx_free(args);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	init_active_metrics();

	while (ZBX_IS_RUNNING())
	{
		zbx_handle_log();

		now = time(NULL);

		if (now >= nextsend)
		{
			send_buffer(activechk_args.host, activechk_args.port);
			nextsend = time(NULL) + 1;
		}

		if (now >= nextrefresh)
		{
			zbx_setproctitle("active checks #%d [getting list of active checks]", process_num);

			if (FAIL == refresh_active_checks(activechk_args.host, activechk_args.port))
			{
				nextrefresh = time(NULL) + 60;
			}
			else
			{
				nextrefresh = time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
			}
		}

		if (now >= nextcheck && CONFIG_BUFFER_SIZE / 2 > buffer.pcount)
		{
			zbx_setproctitle("active checks #%d [processing active checks]", process_num);

			process_active_checks(activechk_args.host, activechk_args.port);

			if (CONFIG_BUFFER_SIZE / 2 <= buffer.pcount)	/* failed to complete processing active checks */
				goto next;

			nextcheck = get_min_nextcheck();
			if (FAIL == nextcheck)
				nextcheck = time(NULL) + 60;
		}
		else
		{
			if (0 > (delta = now - lastcheck))
			{
				zabbix_log(LOG_LEVEL_WARNING, "the system time has been pushed back,"
						" adjusting active check schedule");
				update_schedule((int)delta);
				nextcheck += delta;
				nextsend += delta;
				nextrefresh += delta;
			}

			zbx_setproctitle("active checks #%d [idle 1 sec]", process_num);
			zbx_sleep(1);
		}

		lastcheck = now;
next:
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#else
		;
#endif
	}

#ifdef _WINDOWS
	zbx_free(activechk_args.host);
	free_active_metrics();

	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#endif
}
