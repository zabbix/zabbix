/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "logfiles/logfiles.h"
#include "comms.h"
#include "threads.h"
#include "zbxjson.h"
#include "alias.h"
#include "metrics.h"

extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
extern ZBX_THREAD_LOCAL char		*CONFIG_HOSTNAME;

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

#include "zbxcrypto.h"

static ZBX_THREAD_LOCAL ZBX_ACTIVE_BUFFER	buffer;
static ZBX_THREAD_LOCAL zbx_vector_ptr_t	active_metrics;
static ZBX_THREAD_LOCAL zbx_vector_ptr_t	regexps;
static ZBX_THREAD_LOCAL char			*session_token;
static ZBX_THREAD_LOCAL zbx_uint64_t		last_valueid = 0;

static void	init_active_metrics(void)
{
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_vector_ptr_clear_ext(&active_metrics, (zbx_clean_func_t)free_active_metric);
	zbx_vector_ptr_destroy(&active_metrics);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#endif

static int	get_min_nextcheck(void)
{
	int	i, min = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		const ZBX_ACTIVE_METRIC	*metric = (const ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		if (metric->nextcheck < min || -1 == min)
			min = metric->nextcheck;
	}

	if (-1 == min)
		min = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, min);

	return min;
}

static void	add_check(const char *key, const char *key_orig, int refresh, zbx_uint64_t lastlogsize, int mtime)
{
	ZBX_ACTIVE_METRIC	*metric;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' refresh:%d lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__func__, key, refresh, lastlogsize, mtime);

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

		goto out;
	}

	metric = (ZBX_ACTIVE_METRIC *)zbx_malloc(NULL, sizeof(ZBX_ACTIVE_METRIC));

	/* add new metric */
	metric->key = zbx_strdup(NULL, key);
	metric->key_orig = zbx_strdup(NULL, key_orig);
	metric->refresh = refresh;
	metric->nextcheck = 0;
	metric->state = ITEM_STATE_NORMAL;
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
	const char		*p;
	size_t			name_alloc = 0, key_orig_alloc = 0;
	char			*name = NULL, *key_orig = NULL, expression[MAX_STRING_LEN],
				tmp[MAX_STRING_LEN], exp_delimiter;
	zbx_uint64_t		lastlogsize;
	struct zbx_json_parse	jp;
	struct zbx_json_parse	jp_data, jp_row;
	ZBX_ACTIVE_METRIC	*metric;
	zbx_vector_str_t	received_metrics;
	int			delay, mtime, expression_type, case_sensitive, i, j, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&received_metrics);

	if (SUCCEED != zbx_json_open(str, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, tmp, sizeof(tmp), NULL))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

	if (0 != strcmp(tmp, ZBX_PROTO_VALUE_SUCCESS))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, tmp, sizeof(tmp), NULL))
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

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_KEY, &name, &name_alloc, NULL) ||
				'\0' == *name)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_KEY);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_KEY_ORIG, &key_orig, &key_orig_alloc,
				NULL) || '\0' == *key_orig)
		{
			size_t offset = 0;
			zbx_strcpy_alloc(&key_orig, &key_orig_alloc, &offset, name);
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DELAY, tmp, sizeof(tmp), NULL) ||
				'\0' == *tmp)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_DELAY);
			continue;
		}

		delay = atoi(tmp);

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LASTLOGSIZE, tmp, sizeof(tmp), NULL) ||
				SUCCEED != is_uint64(tmp, &lastlogsize))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_LASTLOGSIZE);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_MTIME, tmp, sizeof(tmp), NULL) ||
				'\0' == *tmp)
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

			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, "name", &name, &name_alloc, NULL))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "name");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression", expression, sizeof(expression),
					NULL) || '\0' == *expression)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "expression");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression_type", tmp, sizeof(tmp), NULL) ||
					'\0' == *tmp)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "expression_type");
				continue;
			}

			expression_type = atoi(tmp);

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "exp_delimiter", tmp, sizeof(tmp), NULL))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", "exp_delimiter");
				continue;
			}

			exp_delimiter = tmp[0];

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "case_sensitive", tmp,
					sizeof(tmp), NULL) || '\0' == *tmp)
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
	zbx_vector_str_clear_ext(&received_metrics, zbx_str_free);
	zbx_vector_str_destroy(&received_metrics);
	zbx_free(key_orig);
	zbx_free(name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/*********************************************************************************
 *                                                                               *
 * Function: process_config_item                                                 *
 *                                                                               *
 * Purpose: process configuration item and set it value to respective parameter  *
 *                                                                               *
 * Parameters: json   - pointer to JSON structure where to put resulting value   *
 *             config - pointer to configuration parameter                       *
 *             length - length of configuration parameter                        *
 *             proto  - configuration parameter prototype                        *
 *                                                                               *
 ********************************************************************************/
static void process_config_item(struct zbx_json *json, char *config, size_t length, const char *proto)
{
	char		**value;
	AGENT_RESULT	result;
	const char	*config_name;
	const char	*config_type;

	if (CONFIG_HOST_METADATA_ITEM == config)
	{
		config_name = "HostMetadataItem";
		config_type = "metadata";
	}
	else /* CONFIG_HOST_INTERFACE_ITEM */
	{
		config_name = "HostInterfaceItem";
		config_type = "interface";
	}

	init_result(&result);

	if (SUCCEED == process(config, PROCESS_LOCAL_COMMAND | PROCESS_WITH_ALIAS, &result) &&
			NULL != (value = GET_STR_RESULT(&result)) && NULL != *value)
	{
		if (SUCCEED != zbx_is_utf8(*value))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get host %s using \"%s\" item specified by"
					" \"%s\" configuration parameter: returned value is not"
					" an UTF-8 string",config_type, config, config_name);
		}
		else
		{
			if (length < zbx_strlen_utf8(*value))
			{
				size_t	bytes;

				zabbix_log(LOG_LEVEL_WARNING, "the returned value of \"%s\" item specified by"
						" \"%s\" configuration parameter is too long,"
						" using first %d characters", config, config_name, (int)length);

				bytes = zbx_strlen_utf8_nchars(*value, length);
				(*value)[bytes] = '\0';
			}
			zbx_json_addstring(json, proto, *value, ZBX_JSON_TYPE_STRING);
		}
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "cannot get host %s using \"%s\" item specified by"
				" \"%s\" configuration parameter",config_type, config,config_name);

	free_result(&result);
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
	static ZBX_THREAD_LOCAL int	last_ret = SUCCEED;
	int				ret;
	char				*tls_arg1, *tls_arg2;
	zbx_socket_t			s;
	struct zbx_json			json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%hu", __func__, host, port);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (NULL != CONFIG_HOST_METADATA)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST_METADATA, CONFIG_HOST_METADATA, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != CONFIG_HOST_METADATA_ITEM)
	{
		process_config_item(&json, CONFIG_HOST_METADATA_ITEM, HOST_METADATA_LEN, ZBX_PROTO_TAG_HOST_METADATA);
	}

	if (NULL != CONFIG_HOST_INTERFACE)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INTERFACE, CONFIG_HOST_INTERFACE, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != CONFIG_HOST_INTERFACE_ITEM)
	{
		process_config_item(&json, CONFIG_HOST_INTERFACE_ITEM, HOST_INTERFACE_LEN, ZBX_PROTO_TAG_INTERFACE);
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
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];
	char			info[MAX_STRING_LEN];
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() response:'%s'", __func__, response);

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL);

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (SUCCEED == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info), NULL))
		zabbix_log(LOG_LEVEL_DEBUG, "info from server: '%s'", info);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
	ZBX_ACTIVE_BUFFER_ELEMENT	*el;
	int				ret = SUCCEED, i, now;
	char				*tls_arg1, *tls_arg2;
	zbx_timespec_t			ts;
	const char			*err_send_step = "";
	zbx_socket_t			s;
	struct zbx_json 		json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%d entries:%d/%d",
			__func__, host, port, buffer.count, CONFIG_BUFFER_SIZE);

	if (0 == buffer.count)
		goto ret;

	now = (int)time(NULL);

	if (CONFIG_BUFFER_SIZE / 2 > buffer.pcount && CONFIG_BUFFER_SIZE > buffer.count &&
			CONFIG_BUFFER_SEND > now - buffer.lastsent)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() now:%d lastsent:%d now-lastsent:%d BufferSend:%d; will not send now",
				__func__, now, buffer.lastsent, now - buffer.lastsent, CONFIG_BUFFER_SEND);
		goto ret;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_AGENT_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_SESSION, session_token, ZBX_JSON_TYPE_STRING);
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

		zbx_json_adduint64(&json, ZBX_PROTO_TAG_ID, el->id);

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
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
			if (SUCCEED == (ret = zbx_tcp_recv(&s)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "JSON back [%s]", s.buffer);

				if (NULL == s.buffer || SUCCEED != check_response(s.buffer))
				{
					ret = FAIL;
					zabbix_log(LOG_LEVEL_DEBUG, "NOT OK");
				}
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
		const char *value, unsigned char state, zbx_uint64_t *lastlogsize, int *mtime,
		unsigned long *timestamp, const char *source, unsigned short *severity, unsigned long *logeventid,
		unsigned char flags)
{
	ZBX_ACTIVE_BUFFER_ELEMENT	*el = NULL;
	int				i, ret = FAIL;
	size_t				sz;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		if (NULL != lastlogsize)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s:%s' lastlogsize:" ZBX_FS_UI64 " value:'%s'",
					__func__, host, key, *lastlogsize, ZBX_NULL2STR(value));
		}
		else
		{
			/* log a dummy lastlogsize to keep the same record format for simpler parsing */
			zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s:%s' lastlogsize:null value:'%s'",
					__func__, host, key, ZBX_NULL2STR(value));
		}
	}

	/* do not sent data from buffer if host/key are the same as previous unless buffer is full already */
	if (0 < buffer.count)
	{
		el = &buffer.data[buffer.count - 1];

		if ((0 != (flags & ZBX_METRIC_FLAG_PERSISTENT) && CONFIG_BUFFER_SIZE / 2 <= buffer.pcount) ||
				CONFIG_BUFFER_SIZE <= buffer.count ||
				0 != strcmp(el->key, key) || 0 != strcmp(el->host, host))
		{
			send_buffer(server, port);
		}
	}

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
	el->id = ++last_valueid;

	if (0 != (ZBX_METRIC_FLAG_PERSISTENT & flags))
		buffer.pcount++;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	need_meta_update(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
		unsigned char old_state, zbx_uint64_t lastlogsize_last, int mtime_last)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:%s", __func__, metric->key);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
static int	process_eventlog_check(char *server, unsigned short port, zbx_vector_ptr_t *regular_expressions,
		ZBX_ACTIVE_METRIC *metric, zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		char **error)
{
	ZBX_UNUSED(server);
	ZBX_UNUSED(port);
	ZBX_UNUSED(regular_expressions);
	ZBX_UNUSED(metric);
	ZBX_UNUSED(process_value_cb);
	ZBX_UNUSED(lastlogsize_sent);
	ZBX_UNUSED(error);

	return FAIL;
}
#else
int	process_eventlog_check(char *server, unsigned short port, zbx_vector_ptr_t *regexps, ZBX_ACTIVE_METRIC *metric,
		zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent, char **error);
#endif

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
	char	*error = NULL;
	int	i, now, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() server:'%s' port:%hu", __func__, server, port);

	now = (int)time(NULL);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		zbx_uint64_t		lastlogsize_last, lastlogsize_sent;
		int			mtime_last, mtime_sent;
		ZBX_ACTIVE_METRIC	*metric;

		metric = (ZBX_ACTIVE_METRIC *)active_metrics.values[i];

		if (metric->nextcheck > now)
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
		{
			ret = process_log_check(server, port, &regexps, metric, process_value, &lastlogsize_sent,
					&mtime_sent, &error);
		}
		else if (0 != (ZBX_METRIC_FLAG_LOG_EVENTLOG & metric->flags))
			ret = process_eventlog_check(server, port, &regexps, metric, process_value, &lastlogsize_sent, &error);
		else
			ret = process_common_check(server, port, metric, &error);

		if (SUCCEED != ret)
		{
			const char	*perror;

			perror = (NULL != error ? error : ZBX_NOTSUPPORTED_MSG);

			metric->state = ITEM_STATE_NOTSUPPORTED;
			metric->error_count = 0;
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

		send_buffer(server, port);
		metric->nextcheck = (int)time(NULL) + metric->refresh;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
	CONFIG_HOSTNAME = zbx_strdup(NULL, ((ZBX_THREAD_ACTIVECHK_ARGS *)((zbx_thread_args_t *)args)->args)->hostname);

	zbx_free(args);

	session_token = zbx_create_token(0);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	init_active_metrics();

	while (ZBX_IS_RUNNING())
	{
		zbx_update_env(zbx_time());

		if ((now = time(NULL)) >= nextsend)
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
				continue;

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
	}

	zbx_free(session_token);

#ifdef _WINDOWS
	zbx_free(activechk_args.host);
	free_active_metrics();

	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#else
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#endif
}
