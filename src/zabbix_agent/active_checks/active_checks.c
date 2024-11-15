/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "active_checks.h"

#include "../agent_conf/agent_conf.h"
#include "../logfiles/logfiles.h"
#include "../metrics/metrics.h"

#include "zbxcfg.h"
#include "zbxlog.h"
#include "zbxsysinfo.h"
#include "zbxcommshigh.h"
#include "zbxthreads.h"
#include "zbxcrypto.h"
#include "zbxjson.h"
#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbx_item_constants.h"
#include "zbxalgo.h"
#include "zbxparam.h"
#include "zbxexpr.h"

#if defined(ZABBIX_SERVICE)
#	include "zbxwinservice.h"
#elif !defined(_WINDOWS)
#	include "zbxnix.h"
#endif

typedef struct
{
	zbx_uint64_t	itemid;
	char		*value;
	unsigned char	state;
	zbx_uint64_t	lastlogsize;
	int		timestamp;
	char		*source;
	int		severity;
	zbx_timespec_t	ts;
	int		logeventid;
	int		mtime;
	unsigned char	flags;
	zbx_uint64_t	id;
}
active_buffer_element_t;

ZBX_PTR_VECTOR_DECL(command_result_ptr, struct zbx_command_result *)
typedef struct zbx_command_result
{
	zbx_uint64_t	id;
	char		*value;
	unsigned char	state;
}
zbx_command_result_t;
ZBX_PTR_VECTOR_IMPL(command_result_ptr, zbx_command_result_t *)

typedef struct
{
	active_buffer_element_t	*data;
	int			count;
	int			pcount;
	int			lastsent;
	int			first_error;
}
active_buffer_t;

typedef struct _zbx_active_command_t zbx_active_command_t;
ZBX_PTR_VECTOR_DECL(active_command_ptr, zbx_active_command_t *)
struct _zbx_active_command_t
{
	zbx_uint64_t		command_id;
	char			*key;
	int			timeout;
};
ZBX_PTR_VECTOR_IMPL(active_command_ptr, zbx_active_command_t *)

static ZBX_THREAD_LOCAL active_buffer_t			buffer;
static ZBX_THREAD_LOCAL	zbx_vector_command_result_ptr_t	command_results;
static ZBX_THREAD_LOCAL zbx_vector_active_metrics_ptr_t	active_metrics;
static ZBX_THREAD_LOCAL	zbx_vector_active_command_ptr_t	active_commands;
static ZBX_THREAD_LOCAL zbx_hashset_t			commands_hash;
static ZBX_THREAD_LOCAL zbx_vector_expression_t		regexps;
static ZBX_THREAD_LOCAL char				*session_token;
static ZBX_THREAD_LOCAL zbx_uint64_t			last_valueid = 0;
static ZBX_THREAD_LOCAL zbx_vector_pre_persistent_t	pre_persistent_vec;	/* used for staging of data going */
										/* into persistent files */
/* used for deleting inactive persistent files */
static ZBX_THREAD_LOCAL zbx_vector_persistent_inactive_t	persistent_inactive_vec;

#define ZBX_HISTORY_UPLOAD_ENABLED	0
#define ZBX_HISTORY_UPLOAD_DISABLED	(-1)

static ZBX_THREAD_LOCAL int	history_upload = ZBX_HISTORY_UPLOAD_ENABLED;

typedef struct
{
	zbx_uint64_t	id;
	time_t		ttl;
}
zbx_cmd_hash_t;

#ifndef _WINDOWS
static volatile sig_atomic_t	need_update_userparam;
#endif

static void	send_back_unsupported_item(zbx_uint64_t itemid, const char *key, char *error, const char *config_hostname,
		zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, int config_buffer_send, int config_buffer_size);

static void	init_active_metrics(int config_buffer_size)
{
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == buffer.data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "buffer: first allocation for %d elements", config_buffer_size);
		sz = (size_t)config_buffer_size * sizeof(active_buffer_element_t);
		buffer.data = (active_buffer_element_t *)zbx_malloc(buffer.data, sz);
		memset(buffer.data, 0, sz);
		buffer.count = 0;
		buffer.pcount = 0;
		buffer.lastsent = (int)time(NULL);
		buffer.first_error = 0;
	}

	zbx_vector_command_result_ptr_create(&command_results);
	zbx_vector_active_metrics_ptr_create(&active_metrics);
	zbx_vector_active_command_ptr_create(&active_commands);
	zbx_hashset_create(&commands_hash, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_expression_create(&regexps);
	zbx_vector_pre_persistent_create(&pre_persistent_vec);
	zbx_vector_persistent_inactive_create(&persistent_inactive_vec);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	free_active_metric(zbx_active_metric_t *metric)
{
	zbx_free(metric->key);
	zbx_free(metric->delay);

	for (int i = 0; i < metric->logfiles_num; i++)
		zbx_free(metric->logfiles[i].filename);

	zbx_free(metric->logfiles);
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	zbx_free(metric->persistent_file_name);
#endif
	zbx_free(metric);
}

static void	free_active_command(zbx_active_command_t *command)
{
	zbx_free(command->key);
	zbx_free(command);
}

static void	free_command_result(zbx_command_result_t *result)
{
	zbx_free(result->value);
	zbx_free(result);
}

static void	clean_command_hash(void)
{
	zbx_cmd_hash_t		*cmd_hash;
	zbx_hashset_iter_t	iter;

	zbx_hashset_iter_reset(&commands_hash, &iter);

	while (NULL != (cmd_hash = (zbx_cmd_hash_t *)zbx_hashset_iter_next(&iter)))
	{
		int			i;
		zbx_command_result_t	*result, result_loc;

		if (cmd_hash->ttl > time(NULL))
			continue;

		result_loc.id = cmd_hash->id;

		if (FAIL != (i = zbx_vector_command_result_ptr_search(&command_results, &result_loc,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			result = (zbx_command_result_t *)command_results.values[i];
			zbx_vector_command_result_ptr_remove_noorder(&command_results, i);
			free_command_result(result);
		}

		zbx_hashset_iter_remove(&iter);
	}
}

#ifdef _WINDOWS
static void	free_active_metrics(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_expression_destroy(&regexps);

	zbx_vector_active_metrics_ptr_clear_ext(&active_metrics, (zbx_clean_func_t)free_active_metric);
	zbx_vector_active_metrics_ptr_destroy(&active_metrics);

	zbx_vector_command_result_ptr_clear_ext(&command_results, (zbx_clean_func_t)free_command_result);
	zbx_vector_command_result_ptr_destroy(&command_results);

	zbx_vector_active_command_ptr_clear_ext(&active_commands, (zbx_clean_func_t)free_command_result);
	zbx_vector_active_command_ptr_destroy(&active_commands);
	zbx_hashset_destroy(&commands_hash);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#endif

static int	get_min_nextcheck(void)
{
	int	min = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < active_metrics.values_num; i++)
	{
		const zbx_active_metric_t	*metric = (const zbx_active_metric_t *)active_metrics.values[i];

		if (metric->nextcheck < min || -1 == min)
			min = metric->nextcheck;
	}

	if (-1 == min)
		min = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, min);

	return min;
}

static void	add_check(const char *key, zbx_uint64_t itemid, const char *delay, zbx_uint64_t lastlogsize, int mtime,
		int timeout)
{
	zbx_active_metric_t	*metric;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' refresh:%s lastlogsize:" ZBX_FS_UI64 " mtime:%d timeout:%d",
			__func__, key, delay, lastlogsize, mtime, timeout);

	for (int i = 0; i < active_metrics.values_num; i++)
	{
		metric = active_metrics.values[i];

		if (metric->itemid != itemid)
			continue;

		if (0 != strcmp(metric->key, key))
		{
			zbx_free(metric->key);
			metric->key = zbx_strdup(NULL, key);
			metric->lastlogsize = lastlogsize;
			metric->mtime = mtime;
			metric->big_rec = 0;
			metric->use_ino = 0;
			metric->error_count = 0;

			for (int j = 0; j < metric->logfiles_num; j++)
				zbx_free(metric->logfiles[j].filename);

			zbx_free(metric->logfiles);
			metric->logfiles_num = 0;
			metric->start_time = 0.0;
			metric->processed_bytes = 0;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
			if (NULL != metric->persistent_file_name)
			{
				char	*error = NULL;

				zabbix_log(LOG_LEVEL_DEBUG, "%s() removing persistent file '%s'",
						__func__, metric->persistent_file_name);

				zbx_remove_from_persistent_inactive_list(&persistent_inactive_vec, metric->itemid);

				if (SUCCEED != zbx_remove_persistent_file(metric->persistent_file_name, &error))
				{
					/* log error and continue operation */
					zabbix_log(LOG_LEVEL_WARNING, "cannot remove persistent file \"%s\": %s",
							metric->persistent_file_name, error);
					zbx_free(error);
				}

				zbx_free(metric->persistent_file_name);
			}
#endif
		}
#if !defined(_WINDOWS) && !defined(__MINGW32__)
		else if (NULL != metric->persistent_file_name)
		{
			/* the metric is active, but it could have been placed on inactive list earlier */
			zbx_remove_from_persistent_inactive_list(&persistent_inactive_vec, metric->itemid);
		}
#endif
		/* replace metric */
		if (0 != strcmp(metric->delay, delay))
		{
			metric->nextcheck = 0;
			metric->delay = zbx_strdup(metric->delay, delay);
		}

		metric->timeout = timeout;

		goto out;
	}

	metric = (zbx_active_metric_t *)zbx_malloc(NULL, sizeof(zbx_active_metric_t));

	/* add new metric */
	metric->itemid = itemid;
	metric->key = zbx_strdup(NULL, key);
	metric->delay = zbx_strdup(NULL, delay);
	metric->nextcheck = 0;
	metric->state = ITEM_STATE_NORMAL;
	metric->lastlogsize = lastlogsize;
	metric->mtime = mtime;
	metric->timeout = timeout;
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
	else if (0 == strncmp(metric->key, "eventlog", 8))
	{
		if ('[' == metric->key[8])
			metric->flags |= ZBX_METRIC_FLAG_LOG_EVENTLOG;
		else if (0 == strncmp(metric->key + 8, ".count[", 7))
			metric->flags |= ZBX_METRIC_FLAG_LOG_EVENTLOG | ZBX_METRIC_FLAG_LOG_COUNT;
	}

	metric->start_time = 0.0;
	metric->processed_bytes = 0;
	metric->persistent_file_name = NULL;	/* initialized but not used on Microsoft Windows */

	zbx_vector_active_metrics_ptr_append(&active_metrics, metric);
out:
	if (0 == metric->nextcheck)
	{
		char	*error = NULL;
		int	nextcheck = 0, scheduling = FAIL;

		if (SUCCEED == zbx_get_agent_item_nextcheck(metric->itemid, metric->delay, (int)time(NULL),
				&nextcheck, &scheduling, &error))
		{
			/* first poll of new items without scheduling checks must be done as soon as possible */
			if (SUCCEED == scheduling)
				metric->nextcheck = nextcheck;
		}
		else
		{
			/* item nextcheck is calculated when item is being polled - */
			/* invalid interval error will be generated then            */
			zbx_free(error);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	add_command(const char *key, zbx_uint64_t id, int timeout)
{
	zbx_active_command_t	*command;
	zbx_cmd_hash_t		cmd_hash_loc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' id:" ZBX_FS_UI64 " timeout: %d", __func__, key, id, timeout);

	if (NULL == zbx_hashset_search(&commands_hash, &id))
	{
		cmd_hash_loc.id = id;
		cmd_hash_loc.ttl = time(NULL) + SEC_PER_HOUR;

		zbx_hashset_insert(&commands_hash, &cmd_hash_loc, sizeof(cmd_hash_loc));

		command = (zbx_active_command_t *)zbx_malloc(NULL, sizeof(zbx_active_command_t));

		command->key = zbx_strdup(NULL, key);
		command->command_id = id;
		command->timeout = timeout;
		zbx_vector_active_command_ptr_append(&active_commands, command);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/********************************************************************************
 *                                                                              *
 * Purpose: parses list of active checks received from server                   *
 *                                                                              *
 * Parameters:                                                                  *
 *     str                   - [IN] NULL terminated string received from server *
 *     host                  - [IN] address of host                             *
 *     port                  - [IN] port number on host                         *
 *     config_revision_local - [IN/OUT] revision of processed configuration     *
 *     config_timeout        - [IN] global timeout value for checks without     *
 *                                  timeouts                                    *
 *                                                                              *
 * Comments:                                                                    *
 *    String is represented as "ZBX_EOF" termination list, with '\n'            *
 *    delimiter between elements.                                               *
 *    Each element represented as:                                              *
 *           <key>:<refresh time>:<last log size>:<modification time>           *
 *                                                                              *
 ********************************************************************************/
static int	parse_list_of_checks(char *str, const char *host, unsigned short port,
		zbx_uint32_t *config_revision_local, int config_timeout, const char *config_hostname,
		zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls, const char *config_source_ip,
		int config_buffer_send, int config_buffer_size)
{
	const char		*p;
	size_t			name_alloc = 0, delay_alloc = 0;
	char			*name = NULL, *delay = NULL, expression[MAX_STRING_LEN], tmp[MAX_STRING_LEN] = {0},
				exp_delimiter, error[MAX_STRING_LEN];
	zbx_uint64_t		lastlogsize, itemid;
	struct zbx_json_parse	jp, jp_data, jp_row;
	zbx_active_metric_t	*metric;
	zbx_vector_uint64_t	received_itemids;
	int			mtime, expression_type, case_sensitive, timeout, i, ret = FAIL;
	zbx_uint32_t		config_revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&received_itemids);

	if (SUCCEED != zbx_json_open(str, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp), NULL))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks from [%s:%hu]: %s", host, port,
					tmp);
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: cannot find tag: %s",
					ZBX_PROTO_TAG_RESPONSE);
		}

		goto out;
	}

	if (0 != strcmp(tmp, ZBX_PROTO_VALUE_SUCCESS))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, tmp, sizeof(tmp), NULL))
			zabbix_log(LOG_LEVEL_ERR, "no active checks on server [%s:%hu]: %s", host, port, tmp);
		else
			zabbix_log(LOG_LEVEL_ERR, "no active checks on server");

		ret = SUCCEED;
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_CONFIG_REVISION, tmp, sizeof(tmp), NULL))
	{
		config_revision = 0;
	}
	else if (FAIL == zbx_is_uint32(tmp, &config_revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "\"%s\" is not a valid revision", tmp);
		goto out;
	}

	if (FAIL != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_HISTORY_UPLOAD, tmp, sizeof(tmp), NULL) &&
			0 == strcmp(tmp, ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED))
	{
		history_upload = ZBX_HISTORY_UPLOAD_DISABLED;
	}
	else
		history_upload = ZBX_HISTORY_UPLOAD_ENABLED;

	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		if (0 != *config_revision_local)
		{
			ret = SUCCEED;
			goto out;
		}

		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s", zbx_json_strerror());

		goto out;
	}

	if (*config_revision_local > config_revision)
	{
		zbx_hashset_iter_t	iter;

		zbx_hashset_iter_reset(&commands_hash, &iter);

		while (NULL != zbx_hashset_iter_next(&iter))
			zbx_hashset_iter_remove(&iter);

		zbx_vector_active_command_ptr_clear_ext(&active_commands, free_active_command);
		zbx_vector_command_result_ptr_clear_ext(&command_results, free_command_result);
	}

	*config_revision_local = config_revision;

	p = NULL;
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
/* {"data":[{"key":"system.cpu.num",...,...},{...},...]}
 *          ^------------------------------^
 */		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
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

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ITEMID, tmp, sizeof(tmp), NULL) ||
				SUCCEED != zbx_is_uint64(tmp, &itemid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_ITEMID);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_DELAY, &delay, &delay_alloc, NULL) ||
				'\0' == *delay)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_DELAY);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LASTLOGSIZE, tmp, sizeof(tmp), NULL) ||
				SUCCEED != zbx_is_uint64(tmp, &lastlogsize))
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

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TIMEOUT, tmp, sizeof(tmp), NULL) ||
				'\0' == *tmp)
		{
			timeout = config_timeout;
		}
		else if (FAIL == zbx_validate_item_timeout(tmp, &timeout, error, sizeof(error)))
		{
			send_back_unsupported_item(itemid, name, error, config_hostname, addrs, config_tls,
					config_timeout, config_source_ip, config_buffer_send, config_buffer_size);

			continue;
		}

		add_check(zbx_alias_get(name), itemid, delay, lastlogsize, mtime, timeout);

		/* remember what was received */
		zbx_vector_uint64_append(&received_itemids, itemid);
	}

	zbx_vector_uint64_sort(&received_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* remove what wasn't received */
	for (i = 0; i < active_metrics.values_num; i++)
	{
		int	found = 0;

		metric = active_metrics.values[i];

		found = zbx_vector_uint64_bsearch(&received_itemids, metric->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (FAIL == found)
		{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
			if (NULL != metric->persistent_file_name)
			{
				zbx_add_to_persistent_inactive_list(&persistent_inactive_vec, metric->itemid,
						metric->persistent_file_name);
			}
#endif
			zbx_vector_active_metrics_ptr_remove_noorder(&active_metrics, i);
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
				zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active checks: %s",
						zbx_json_strerror());
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

			zbx_add_regexp_ex(&regexps, name, expression, expression_type, exp_delimiter, case_sensitive);
		}
	}

	ret = SUCCEED;
out:

	zbx_vector_uint64_destroy(&received_itemids);
	zbx_free(delay);
	zbx_free(name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	parse_list_of_commands(char *str, int config_timeout)
{
	const char		*p;
	char			*cmd = NULL, tmp[MAX_STRING_LEN], error[MAX_STRING_LEN], *key = NULL;
	int			timeout, ret = FAIL;
	zbx_uint64_t		command_id;
	struct zbx_json_parse	jp, jp_data, jp_row;
	size_t			cmd_alloc = 0, key_alloc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_json_open(str, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active commands: %s", zbx_json_strerror());
		goto out;
	}

	if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_COMMANDS, &jp_data))
	{
		p = NULL;
		while (NULL != (p = zbx_json_next(&jp_data, p)))
		{
			size_t	offset = 0;

			if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse list of active commands: %s",
						zbx_json_strerror());
				goto out;
			}

			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_COMMAND, &cmd, &cmd_alloc,
					NULL) || '\0' == *cmd)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"",
						ZBX_PROTO_TAG_COMMAND);
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_WAIT, tmp, sizeof(tmp), NULL) ||
					'\0' == *tmp)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"",
						ZBX_PROTO_TAG_WAIT);
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TIMEOUT, tmp, sizeof(tmp), NULL) ||
				'\0' == *tmp)
			{
				timeout = config_timeout;
			}
			else if (FAIL == zbx_validate_item_timeout(tmp, &timeout, error, sizeof(error)))
			{

				zabbix_log(LOG_LEVEL_ERR, "failed to validate timeout \"%d\", error: %s ", timeout,
						error);

				continue;
			}

			if (SUCCEED != zbx_quote_key_param(&cmd, 0))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Invalid command \"%s\"", cmd);
				continue;
			}

			if (0 == atoi(tmp))
			{
				zbx_snprintf_alloc(&key, &key_alloc, &offset, "system.run[%s,nowait]",
						cmd);
			}
			else
				zbx_snprintf_alloc(&key, &key_alloc, &offset, "system.run[%s,wait]",cmd);

			if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ID, tmp, sizeof(tmp), NULL) ||
							SUCCEED != zbx_is_uint64(tmp, &command_id))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_ID);
				continue;
			}

			add_command(key, command_id, timeout);
		}
	}

	ret = SUCCEED;
out:
	zbx_free(key);
	zbx_free(cmd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/************************************************************************************************
 *                                                                                              *
 * Purpose: processes configuration item and sets its value to respective parameter             *
 *                                                                                              *
 * Parameters:                                                                                  *
 *     json                      - [OUT] pointer to JSON structure where to put resulting value *
 *     config                    - [IN] pointer to configuration parameter                      *
 *     length                    - [IN] length of configuration parameter                       *
 *     proto                     - [IN] configuration parameter prototype                       *
 *     config_host_metadata_item - [IN]                                                         *
 *                                                                                              *
 ************************************************************************************************/
static void	process_config_item(struct zbx_json *json, const char *config, size_t length, const char *proto,
		const char *config_host_metadata_item)
{
	char		**value;
	AGENT_RESULT	result;
	const char	*config_name, *config_type;

	if (config_host_metadata_item == config)
	{
		config_name = "HostMetadataItem";
		config_type = "metadata";
	}
	else /* config_host_interface_item */
	{
		config_name = "HostInterfaceItem";
		config_type = "interface";
	}

	zbx_init_agent_result(&result);

	if (SUCCEED == zbx_execute_agent_check(config, ZBX_PROCESS_LOCAL_COMMAND | ZBX_PROCESS_WITH_ALIAS, &result,
			ZBX_CHECK_TIMEOUT_UNDEFINED) && NULL != (value = ZBX_GET_STR_RESULT(&result)) && NULL != *value)
	{
		if (SUCCEED != zbx_is_utf8(*value))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get host %s using \"%s\" item specified by"
					" \"%s\" configuration parameter: returned value is not"
					" a UTF-8 string",config_type, config, config_name);
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
				" \"%s\" configuration parameter", config_type, config, config_name);

	zbx_free_agent_result(&result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of active checks from Zabbix server                *
 *                                                                            *
 * Return value: returns SUCCEED on successful parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 ******************************************************************************/
static int	refresh_active_checks(zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls,
		zbx_uint32_t *config_revision_local, int config_timeout, const char *config_source_ip,
		const char *config_listen_ip, int config_listen_port, const char *config_hostname,
		const char *config_host_metadata, const char *config_host_metadata_item,
		const char *config_host_interface, const char *config_host_interface_item, int config_buffer_send,
		int config_buffer_size)
{
	static ZBX_THREAD_LOCAL int	last_ret = SUCCEED;
	int				ret, level;
	struct zbx_json			json;
	char				*data = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%hu", __func__, ((zbx_addr_t *)addrs->values[0])->ip,
			((zbx_addr_t *)addrs->values[0])->port);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, config_hostname, ZBX_JSON_TYPE_STRING);

	if (NULL != config_host_metadata)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST_METADATA, config_host_metadata, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != config_host_metadata_item)
	{
#define HOST_METADATA_LEN	65535	/* UTF-8 characters, not bytes */
		process_config_item(&json, config_host_metadata_item, HOST_METADATA_LEN, ZBX_PROTO_TAG_HOST_METADATA,
				config_host_metadata_item);
#undef HOST_METADATA_LEN
	}

	if (NULL != config_host_interface)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INTERFACE, config_host_interface, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != config_host_interface_item)
	{
		process_config_item(&json, config_host_interface_item, HOST_INTERFACE_LEN, ZBX_PROTO_TAG_INTERFACE,
				config_host_metadata_item);
	}

	if (NULL != config_listen_ip)
	{
		char	*p;

		if (NULL != (p = strchr(config_listen_ip, ',')))
			*p = '\0';

		zbx_json_addstring(&json, ZBX_PROTO_TAG_IP, config_listen_ip, ZBX_JSON_TYPE_STRING);

		if (NULL != p)
			*p = ',';
	}

	if (ZBX_DEFAULT_AGENT_PORT != config_listen_port)
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_PORT, (zbx_uint64_t)config_listen_port);

	zbx_json_adduint64(&json, ZBX_PROTO_TAG_CONFIG_REVISION, (zbx_uint64_t)*config_revision_local);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_SESSION, session_token, ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json, ZBX_PROTO_TAG_VARIANT, ZBX_PROGRAM_VARIANT_AGENT);

	level = SUCCEED != last_ret ? LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING;

	ret = zbx_comms_exchange_with_redirect(config_source_ip, addrs, config_timeout, config_timeout, 0, level,
			config_tls, json.buffer, NULL, NULL, &data, NULL);

	if (SUCCEED == ret && '\0' == *data)
	{
		zbx_free(data);
		zabbix_log(LOG_LEVEL_WARNING, "Received empty response from active check configuration update");
		ret = FAIL;

		zbx_addrs_failover(addrs);
	}

	if (SUCCEED == ret)
	{
		int	rc;

		if (SUCCEED != last_ret)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Active check configuration update from [%s:%hu]"
					" is working again", ((zbx_addr_t *)addrs->values[0])->ip,
					((zbx_addr_t *)addrs->values[0])->port);
		}

		rc = parse_list_of_checks(data, ((zbx_addr_t *)addrs->values[0])->ip,
				((zbx_addr_t *)addrs->values[0])->port, config_revision_local, config_timeout,
				config_hostname, addrs, config_tls, config_source_ip, config_buffer_send,
				config_buffer_size);

		rc |= parse_list_of_commands(data, config_timeout);

		if (SUCCEED != rc)
			zbx_addrs_failover(addrs);

		zbx_free(data);
	}
	else
	{
		if (RECV_ERROR == ret)
		{
			/* server is unaware if configuration is actually delivered and saves session */
			*config_revision_local = 0;
		}
	}

	if (SUCCEED != ret && SUCCEED == last_ret)
		zabbix_log(LOG_LEVEL_WARNING, "Active check configuration update started to fail");

	last_ret = ret;

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks whether JSON response is SUCCEED                           *
 *                                                                            *
 * Parameters: response - [IN] JSON response from Zabbix trapper              *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 * Comments: zabbix_sender has almost the same function!                      *
 *                                                                            *
 ******************************************************************************/
static int	check_response(const char *response)
{
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN], info[MAX_STRING_LEN];
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() response:'%s'", __func__, response);

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL);

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (SUCCEED == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info), NULL))
		zabbix_log(LOG_LEVEL_DEBUG, "info from server: '%s'", info);

	if (SUCCEED == ret && FAIL != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_HISTORY_UPLOAD, value, sizeof(value),
			NULL) && 0 == strcmp(value, ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED))
	{
		history_upload = ZBX_HISTORY_UPLOAD_DISABLED;
	}
	else
		history_upload = ZBX_HISTORY_UPLOAD_ENABLED;


	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	format_metric_results(struct zbx_json *json, int now, int config_buffer_send, int config_buffer_size)
{
	active_buffer_element_t	*el;
	int			i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_HISTORY_UPLOAD_ENABLED != history_upload)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot send buffer: server has paused history upload");
		goto ret;
	}

	if (config_buffer_size / 2 > buffer.pcount && config_buffer_size > buffer.count &&
			config_buffer_send > now - buffer.lastsent)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() now:%d lastsent:%d now-lastsent:%d BufferSend:%d; will not send now",
				__func__, now, buffer.lastsent, now - buffer.lastsent, config_buffer_send);
		goto ret;
	}

	if (0 == buffer.count)
		goto ret;

	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < buffer.count; i++)
	{
		el = &buffer.data[i];

		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, ZBX_PROTO_TAG_ITEMID, el->itemid);

		if (NULL != el->value)
			zbx_json_addstring(json, ZBX_PROTO_TAG_VALUE, el->value, ZBX_JSON_TYPE_STRING);

		if (ITEM_STATE_NOTSUPPORTED == el->state)
		{
			zbx_json_adduint64(json, ZBX_PROTO_TAG_STATE, ITEM_STATE_NOTSUPPORTED);
		}
		else
		{
			/* add item meta information only for items in normal state */
			if (0 != (ZBX_METRIC_FLAG_LOG & el->flags))
				zbx_json_adduint64(json, ZBX_PROTO_TAG_LASTLOGSIZE, el->lastlogsize);
			if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & el->flags))
				zbx_json_addint64(json, ZBX_PROTO_TAG_MTIME, el->mtime);
		}

		if (0 != el->timestamp)
			zbx_json_addint64(json, ZBX_PROTO_TAG_LOGTIMESTAMP, el->timestamp);

		if (NULL != el->source)
			zbx_json_addstring(json, ZBX_PROTO_TAG_LOGSOURCE, el->source, ZBX_JSON_TYPE_STRING);

		if (0 != el->severity)
			zbx_json_addint64(json, ZBX_PROTO_TAG_LOGSEVERITY, el->severity);

		if (0 != el->logeventid)
			zbx_json_addint64(json, ZBX_PROTO_TAG_LOGEVENTID, el->logeventid);

		zbx_json_adduint64(json, ZBX_PROTO_TAG_ID, el->id);

		zbx_json_addint64(json, ZBX_PROTO_TAG_CLOCK, el->ts.sec);
		zbx_json_addint64(json, ZBX_PROTO_TAG_NS, el->ts.ns);
		zbx_json_close(json);
	}

	zbx_json_close(json);
	ret = SUCCEED;
ret:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	format_command_results(struct zbx_json *json)
{
	int			i;
	zbx_command_result_t	*result;

	if (0 == command_results.values_num)
		return FAIL;

	zbx_json_addarray(json, ZBX_PROTO_TAG_COMMANDS);

	for (i = 0; i < command_results.values_num; i++)
	{
		result = (zbx_command_result_t *)command_results.values[i];

		if (NULL == result->value)
			continue;

		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, ZBX_PROTO_TAG_ID, result->id);

		if (ITEM_STATE_NOTSUPPORTED == result->state)
			zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, result->value, ZBX_JSON_TYPE_STRING);
		else
			zbx_json_addstring(json, ZBX_PROTO_TAG_VALUE, result->value, ZBX_JSON_TYPE_STRING);

		zbx_json_close(json);
	}

	zbx_json_close(json);

	return SUCCEED;
}

static void	clear_metric_results(zbx_vector_addr_ptr_t *addrs, zbx_vector_pre_persistent_t *prep_vec, int now,
		int ret)
{
	int			i;
	active_buffer_element_t	*el;

	if (SUCCEED == ret)
	{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
		zbx_write_persistent_files(prep_vec);
		zbx_clean_pre_persistent_elements(prep_vec);
#else
		ZBX_UNUSED(prep_vec);
#endif
		/* free buffer */
		for (i = 0; i < buffer.count; i++)
		{
			el = &buffer.data[i];

			zbx_free(el->value);
			zbx_free(el->source);
		}
		buffer.count = 0;
		buffer.pcount = 0;

		buffer.lastsent = now;

		if (0 != buffer.first_error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "active check data upload to [%s:%hu] is working again",
					((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port);
			buffer.first_error = 0;
		}
	}
	else
	{
		if (0 == buffer.first_error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Active check data upload started to fail");
			buffer.first_error = now;
		}
	}
}

static char	*connect_callback(void *data)
{
	zbx_json_t	*json = (zbx_json_t *)data;

	zbx_timespec_t	ts;

	zbx_timespec(&ts);

	zbx_json_addint64(json, ZBX_PROTO_TAG_CLOCK, ts.sec);
	zbx_json_addint64(json, ZBX_PROTO_TAG_NS, ts.ns);

	return json->buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends value stored in buffer to Zabbix server                     *
 *                                                                            *
 * Parameters:                                                                *
 *   addrs              - [IN] vector with pair of Zabbix server IP or        *
 *                             Hostname and port number                       *
 *   prep_vec           - [IN/OUT] vector with data for writing into          *
 *                                 persistent files                           *
 *   config_tls         - [IN]                                                *
 *   config_timeout     - [IN]                                                *
 *   config_source_ip   - [IN]                                                *
 *   config_buffer_send - [IN]                                                *
 *   config_buffer_size - [IN]                                                *
 *                                                                            *
 * Return value: SUCCEED if:                                                  *
 *                    - no need to send data now (buffer empty or has enough  *
 *                      free elements, or recently sent)                      *
 *                    - data successfully sent to server (proxy)              *
 *               FAIL - error when sending data                               *
 *                                                                            *
 ******************************************************************************/
static int	send_buffer(zbx_vector_addr_ptr_t *addrs, zbx_vector_pre_persistent_t *prep_vec,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size)
{
	int			ret = SUCCEED, ret_metrics, ret_commands, now, level;
	char			*data = NULL;
	struct zbx_json		json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' port:%d entries:%d/%d",
			__func__, ((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port,
			buffer.count, config_buffer_size);

	now = (int)time(NULL);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_AGENT_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_SESSION, session_token, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json, ZBX_PROTO_TAG_VARIANT, ZBX_PROGRAM_VARIANT_AGENT);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, config_hostname, ZBX_JSON_TYPE_STRING);

	ret_metrics = format_metric_results(&json, now, config_buffer_send, config_buffer_size);
	ret_commands = format_command_results(&json);

	if (FAIL == ret_metrics && FAIL == ret_commands)
		goto ret;

	level = 0 == buffer.first_error ? LOG_LEVEL_WARNING : LOG_LEVEL_DEBUG;

	ret = zbx_comms_exchange_with_redirect(config_source_ip, addrs, MIN(buffer.count * config_timeout, 60),
			config_timeout, 0, level, config_tls, json.buffer, connect_callback, &json, &data, NULL);

	if (SUCCEED == ret)
	{
		if (NULL == data || SUCCEED != check_response(data))
		{
			ret = FAIL;
			zabbix_log(LOG_LEVEL_DEBUG, "NOT OK");

			zbx_addrs_failover(addrs);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "OK");

		zbx_free(data);
	}

	if (SUCCEED == ret_metrics)
		clear_metric_results(addrs, prep_vec, now, ret);

	if (SUCCEED == ret && SUCCEED == ret_commands)
		zbx_vector_command_result_ptr_clear_ext(&command_results, free_command_result);
ret:
	zbx_json_free(&json);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************************
 *                                                                                        *
 * Purpose: buffers new value or sends whole buffer to server                             *
 *                                                                                        *
 * Parameters:                                                                            *
 *   addrs              - [IN] In C agent - vector with a pair of Zabbix server IP or     *
 *                             Hostname and port number. In Agent2 it is not used (NULL). *
 *   agent2_result      - [IN] NULL in C agent. In Agent2 it is used for passing          *
 *                             address of buffer where to store matching log              *
 *                             records. It is here to have the same function              *
 *                             prototype as in Agent2.                                    *
 *   itemid             - [IN] item identifier
 *   host               - [IN] name of host in Zabbix database                            *
 *   key                - [IN] name of metric                                             *
 *   value              - [IN] key value or error message why item became NOTSUPPORTED    *
 *   state              - [IN] ITEM_STATE_NORMAL or ITEM_STATE_NOTSUPPORTED               *
 *   lastlogsize        - [IN] size of read logfile                                       *
 *   mtime              - [IN] time of last file modification                             *
 *   timestamp          - [IN] timestamp of read value                                    *
 *   source             - [IN] name of logged data source                                 *
 *   severity           - [IN] severity of logged data sources                            *
 *   logeventid         - [IN] application-specific identifier for the event; used        *
 *                             for monitoring of Windows event logs                       *
 *   flags              - [IN] metric flags                                               *
 *   config_tls         - [IN]                                                            *
 *   config_timeout     - [IN]                                                            *
 *   config_source_ip   - [IN]                                                            *
 *   config_buffer_send - [IN]                                                            *
 *   config_buffer_size - [IN]                                                            *
 *                                                                                        *
 * Return value: returns SUCCEED on successful parsing,                                   *
 *               FAIL on other cases                                                      *
 *                                                                                        *
 * Comments: ATTENTION! This function's address and pointers to arguments                 *
 *           are described in Zabbix defined type "zbx_process_value_func_t"              *
 *           and used when calling process_log(), process_logrt() and                     *
 *           zbx_read2(). If you ever change this process_value() arguments               *
 *           or return value do not forget to synchronize changes with the                *
 *           defined type "zbx_process_value_func_t" and implementations of               *
 *           process_log(), process_logrt(), zbx_read2() and their callers.               *
 *                                                                                        *
 ******************************************************************************************/
static int	process_value(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_uint64_t itemid,
		const char *host, const char *key, const char *value, unsigned char state, zbx_uint64_t *lastlogsize,
		const int *mtime, const unsigned long *timestamp, const char *source,
		const unsigned short *severity, const unsigned long *logeventid, unsigned char flags,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		int config_buffer_send, int config_buffer_size)
{
	active_buffer_element_t	*el = NULL;
	int			i, ret = FAIL;
	size_t			sz;

	ZBX_UNUSED(agent2_result);

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

	/* do not send data from buffer if host/key are the same as previous unless buffer is full already */
	if (0 < buffer.count)
	{
		el = &buffer.data[buffer.count - 1];

		if ((0 != (flags & ZBX_METRIC_FLAG_PERSISTENT) && config_buffer_size / 2 <= buffer.pcount) ||
				config_buffer_size <= buffer.count || el->itemid != itemid)
		{
			send_buffer(addrs, &pre_persistent_vec, config_tls, config_timeout, config_source_ip,
					host, config_buffer_send, config_buffer_size);
		}
	}

	if (0 != (ZBX_METRIC_FLAG_PERSISTENT & flags) && config_buffer_size / 2 <= buffer.pcount)
	{
		zabbix_log(LOG_LEVEL_WARNING, "buffer is full, cannot store persistent value");
		goto out;
	}

	if (config_buffer_size > buffer.count)
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
				if (el->itemid == itemid)
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
			zabbix_log(LOG_LEVEL_DEBUG, "remove element [%d] itemid:" ZBX_FS_UI64, i, el->itemid);

			zbx_free(el->value);
			zbx_free(el->source);
		}

		sz = (size_t)(config_buffer_size - i - 1) * sizeof(active_buffer_element_t);
		memmove(&buffer.data[i], &buffer.data[i + 1], sz);

		zabbix_log(LOG_LEVEL_DEBUG, "buffer full: new element %d", buffer.count - 1);

		el = &buffer.data[config_buffer_size - 1];
	}

	memset(el, 0, sizeof(active_buffer_element_t));
	el->itemid = itemid;
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

	/* If conditions are met then send buffer now. It is necessary for synchronization */
	/* between sending data to server and writing of persistent files. */
	if ((0 != (flags & ZBX_METRIC_FLAG_PERSISTENT) && config_buffer_size / 2 <= buffer.pcount) ||
			config_buffer_size <= buffer.count)
	{
		send_buffer(addrs, &pre_persistent_vec, config_tls, config_timeout, config_source_ip, host,
				config_buffer_send, config_buffer_size);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	process_remote_command_value(const char *value, zbx_uint64_t id, unsigned char state)
{
	zbx_command_result_t	*result;

	result = (zbx_command_result_t *)zbx_malloc(NULL, sizeof(zbx_command_result_t));

	if (NULL != value)
		result->value = zbx_strdup(NULL, value);
	else
		result->value = NULL;

	result->id = id;
	result->state = state;
	zbx_vector_command_result_ptr_append(&command_results, result);
}

static int	need_meta_update(zbx_active_metric_t *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
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
static int	process_eventlog_check(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_vector_expression_t *regular_expressions, zbx_active_metric_t *metric,
		zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size,
		int config_eventlog_max_lines_per_second, char **error)
{
	ZBX_UNUSED(addrs);
	ZBX_UNUSED(agent2_result);
	ZBX_UNUSED(regular_expressions);
	ZBX_UNUSED(metric);
	ZBX_UNUSED(process_value_cb);
	ZBX_UNUSED(lastlogsize_sent);
	ZBX_UNUSED(config_tls);
	ZBX_UNUSED(config_timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_hostname);
	ZBX_UNUSED(config_buffer_send);
	ZBX_UNUSED(config_buffer_size);
	ZBX_UNUSED(config_eventlog_max_lines_per_second);
	ZBX_UNUSED(error);

	return FAIL;
}
#else
int	process_eventlog_check(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_vector_expression_t *regexps, zbx_active_metric_t *metric, zbx_process_value_func_t process_value_cb,
		zbx_uint64_t *lastlogsize_sent, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size, int config_eventlog_max_lines_per_second, char **error);
#endif

static int	process_common_check(zbx_vector_addr_ptr_t *addrs, zbx_active_metric_t *metric,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size, char **error)
{
	int		ret;
	AGENT_RESULT	result;
	char		**pvalue;

	zbx_init_agent_result(&result);

	if (ZBX_CHECK_TIMEOUT_UNDEFINED == metric->timeout)
	{
		SET_MSG_RESULT(&result, zbx_strdup(NULL, "Unsupported timeout value."));
		*error = zbx_strdup(*error, *ZBX_GET_MSG_RESULT(&result));
		ret = NOTSUPPORTED;
	}
	else if (SUCCEED != (ret = zbx_execute_agent_check(metric->key, 0, &result, metric->timeout)))
	{
		if (NULL != (pvalue = ZBX_GET_MSG_RESULT(&result)))
			*error = zbx_strdup(*error, *pvalue);
		goto out;
	}

	if (NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "for key [%s] received value [%s]", metric->key, *pvalue);

		process_value(addrs, NULL, metric->itemid, config_hostname, metric->key, *pvalue, ITEM_STATE_NORMAL,
				NULL, NULL, NULL, NULL, NULL, NULL, metric->flags, config_tls, config_timeout,
				config_source_ip, config_buffer_send, config_buffer_size);
	}
out:
	zbx_free_agent_result(&result);

	return ret;
}

static void	process_command(zbx_active_command_t *command)
{
	AGENT_RESULT	result;
	char		**pvalue, *empty = "", *error = ZBX_NOTSUPPORTED_MSG;
	unsigned char	state = ITEM_STATE_NORMAL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:%s", __func__, command->key);

	zbx_init_agent_result(&result);

	if (SUCCEED != zbx_execute_agent_check(command->key, 0, &result, command->timeout))
	{
		state = ITEM_STATE_NOTSUPPORTED;
		if (NULL == (pvalue = ZBX_GET_MSG_RESULT(&result)))
			pvalue = &error;
	}
	else if (NULL == (pvalue = ZBX_GET_TEXT_RESULT(&result)))
		pvalue = &empty;

	process_remote_command_value(*pvalue, command->command_id, state);

	zbx_free_agent_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() state:%d result:%s", __func__, state, *pvalue);
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
/********************************************************************************
 *                                                                              *
 * Purpose: initializes element of preparation vector with available data       *
 *                                                                              *
 * Parameters:                                                                  *
 *     lastlogsize   - [IN] lastlogize value to write into persistent data file *
 *     mtime         - [IN] mtime value to write into persistent data file      *
 *     prep_vec_elem - [IN/OUT] element of vector to initialize                 *
 *                                                                              *
 * Comments: This is a minimal initialization for using before sending status   *
 *           updates or meta-data. It initializes only 2 attributes to be       *
 *           usable without any data about log files.                           *
 *                                                                              *
 ********************************************************************************/
static void	zbx_minimal_init_prep_vec_data(zbx_uint64_t lastlogsize, int mtime, zbx_pre_persistent_t *prep_vec_elem)
{
	if (NULL != prep_vec_elem->filename)
		zbx_free(prep_vec_elem->filename);	/* filename == NULL should be checked when preparing JSON */
							/* for writing as most attributes are not initialized */
	prep_vec_elem->processed_size = lastlogsize;
	prep_vec_elem->mtime = mtime;
}

static void	zbx_fill_prep_vec_element(zbx_vector_pre_persistent_t *prep_vec, zbx_uint64_t itemid,
		const char *persistent_file_name, const struct st_logfile *logfile, const zbx_uint64_t lastlogsize,
		const int mtime)
{
	/* index in preparation vector */
	int	idx = zbx_find_or_create_prep_vec_element(prep_vec, itemid, persistent_file_name);

	if (NULL != logfile)
	{
		zbx_init_prep_vec_data(logfile, prep_vec->values + idx);
		zbx_update_prep_vec_data(logfile, logfile->processed_size, prep_vec->values + idx);
	}
	else
		zbx_minimal_init_prep_vec_data(lastlogsize, mtime, prep_vec->values + idx);
}
#endif	/* not WINDOWS, not __MINGW32__ */

static void	process_active_commands(zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls,
		int config_timeout, const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() server:'%s' port:%hu", __func__, addrs->values[0]->ip,
			addrs->values[0]->port);

	for (i = 0; i < active_commands.values_num; i++)
		process_command((zbx_active_command_t *)active_commands.values[i]);

	zbx_vector_active_command_ptr_clear_ext(&active_commands, free_active_command);

	send_buffer(addrs, &pre_persistent_vec, config_tls, config_timeout, config_source_ip, config_hostname,
			config_buffer_send, config_buffer_size);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_active_checks(zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls,
		int config_timeout, const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size, int config_eventlog_max_lines_per_second, int config_max_lines_per_second)
{
	char	*error = NULL;
	int	i, now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() server:'%s' port:%hu", __func__, addrs->values[0]->ip,
			addrs->values[0]->port);

	now = (int)time(NULL);

	for (i = 0; i < active_metrics.values_num; i++)
	{
		zbx_uint64_t		lastlogsize_last, lastlogsize_sent;
		int			mtime_last, mtime_sent, ret, scheduling = FAIL;
		zbx_active_metric_t	*metric = active_metrics.values[i];

		if (metric->nextcheck > now)
			continue;

		if (SUCCEED != zbx_get_agent_item_nextcheck(metric->itemid, metric->delay, now, &metric->nextcheck,
				&scheduling, &error))
		{
			process_value(addrs, NULL, metric->itemid, config_hostname, metric->key, error,
					ITEM_STATE_NOTSUPPORTED, &metric->lastlogsize, &metric->mtime, NULL, NULL, NULL,
					NULL, metric->flags, config_tls, config_timeout, config_source_ip,
					config_buffer_send, config_buffer_size);

			metric->state = ITEM_STATE_NOTSUPPORTED;
			metric->error_count = 0;

			zbx_free(error);

			continue;
		}

		/* for meta information update we need to know if something was sent at all during the check */
		lastlogsize_last = metric->lastlogsize;
		mtime_last = metric->mtime;

		lastlogsize_sent = metric->lastlogsize;
		mtime_sent = metric->mtime;

		if (0 != ((ZBX_METRIC_FLAG_LOG_LOG | ZBX_METRIC_FLAG_LOG_LOGRT) & metric->flags))
		{
			ret = process_log_check(addrs, NULL, &regexps, metric, process_value, &lastlogsize_sent,
					&mtime_sent, &error, &pre_persistent_vec, config_tls, config_timeout,
					config_source_ip, config_hostname, config_buffer_send, config_buffer_size,
					config_max_lines_per_second);
		}
		else if (0 != (ZBX_METRIC_FLAG_LOG_EVENTLOG & metric->flags))
		{
			ret = process_eventlog_check(addrs, NULL, &regexps, metric, process_value, &lastlogsize_sent,
					config_tls, config_timeout, config_source_ip, config_hostname,
					config_buffer_send, config_buffer_size, config_eventlog_max_lines_per_second,
					&error);
		}
		else
			ret = process_common_check(addrs, metric, config_tls, config_timeout, config_source_ip,
					config_hostname, config_buffer_send, config_buffer_size, &error);

		if (SUCCEED != ret)
		{
			const char	*perror = (NULL != error ? error : ZBX_NOTSUPPORTED_MSG);

			metric->state = ITEM_STATE_NOTSUPPORTED;
			metric->error_count = 0;
			metric->processed_bytes = 0;

			zabbix_log(LOG_LEVEL_WARNING, "active check \"%s\" is not supported: %s", metric->key, perror);
#if !defined(_WINDOWS) && !defined(__MINGW32__)
			/* only for log*[] items */
			if (0 != ((ZBX_METRIC_FLAG_LOG_LOG | ZBX_METRIC_FLAG_LOG_LOGRT) & metric->flags) &&
					NULL != metric->persistent_file_name)
			{
				const struct st_logfile	*logfile = NULL;

				if (0 < metric->logfiles_num)
				{
					logfile = find_last_processed_file_in_logfiles_list(metric->logfiles,
							metric->logfiles_num);
				}

				zbx_fill_prep_vec_element(&pre_persistent_vec, metric->itemid,
						metric->persistent_file_name, logfile, metric->lastlogsize,
						metric->mtime);
			}
#endif
			process_value(addrs, NULL, metric->itemid, config_hostname, metric->key, perror,
					ITEM_STATE_NOTSUPPORTED, &metric->lastlogsize, &metric->mtime, NULL, NULL, NULL,
					NULL, metric->flags, config_tls, config_timeout, config_source_ip,
					config_buffer_send, config_buffer_size);

			zbx_free(error);
		}
		else
		{
			if (0 == metric->error_count)
			{
				unsigned char	old_state = metric->state;

				if (ITEM_STATE_NOTSUPPORTED == metric->state)
				{
					/* item became supported */
					metric->state = ITEM_STATE_NORMAL;
				}

				if (SUCCEED == need_meta_update(metric, lastlogsize_sent, mtime_sent, old_state,
						lastlogsize_last, mtime_last))
				{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
					if (NULL != metric->persistent_file_name)
					{
						const struct st_logfile	*logfile = NULL;

						if (0 < metric->logfiles_num)
						{
							logfile = find_last_processed_file_in_logfiles_list(
									metric->logfiles, metric->logfiles_num);
						}

						zbx_fill_prep_vec_element(&pre_persistent_vec, metric->itemid,
								metric->persistent_file_name, logfile,
								metric->lastlogsize, metric->mtime);
					}
#endif
					/* meta information update */
					process_value(addrs, NULL, metric->itemid, config_hostname, metric->key, NULL,
							metric->state, &metric->lastlogsize, &metric->mtime, NULL, NULL,
							NULL, NULL, metric->flags, config_tls, config_timeout,
							config_source_ip, config_buffer_send, config_buffer_size);
				}

				/* remove "new metric" flag */
				metric->flags &= ~ZBX_METRIC_FLAG_NEW;
			}
		}

		send_buffer(addrs, &pre_persistent_vec, config_tls, config_timeout, config_source_ip, config_hostname,
				config_buffer_send, config_buffer_size);

		if (metric->nextcheck <= (now = (int)time(NULL)))
		{
			/* reschedule metric if polling took it past is scheduled next poll */

			if (SUCCEED != zbx_get_agent_item_nextcheck(metric->itemid, metric->delay, now,
					&metric->nextcheck, &scheduling, &error))
			{
				/* while not likely that another nextcheck calculation with the same     */
				/* delay could result in an error - still it can be handled and reported */
				process_value(addrs, NULL, metric->itemid, config_hostname, metric->key, error,
						ITEM_STATE_NOTSUPPORTED, &metric->lastlogsize, &metric->mtime, NULL,
						NULL, NULL, NULL, metric->flags, config_tls, config_timeout,
						config_source_ip, config_buffer_send, config_buffer_size);

				metric->state = ITEM_STATE_NOTSUPPORTED;
				metric->error_count = 0;

				zbx_free(error);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates schedules of active checks and buffer by specified time   *
 *          delta                                                             *
 *                                                                            *
 * Parameters: delta - [IN] time delta in seconds                             *
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
		zbx_active_metric_t	*metric = active_metrics.values[i];
		metric->nextcheck += delta;
	}

	buffer.lastsent += delta;
}

#ifndef _WINDOWS
static void	zbx_active_checks_sigusr_handler(int flags)
{
	if (ZBX_RTC_USER_PARAMETERS_RELOAD == ZBX_RTC_GET_MSG(flags))
		need_update_userparam = 1;
}
#endif

static void	send_heartbeat_msg(zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_heartbeat_frequency)
{
	static ZBX_THREAD_LOCAL int	last_ret = SUCCEED;
	int				ret, level;
	struct zbx_json			json;
	char				*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_ACTIVE_CHECK_HEARTBEAT, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, config_hostname, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json, ZBX_PROTO_TAG_HEARTBEAT_FREQ, config_heartbeat_frequency);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json, ZBX_PROTO_TAG_VARIANT, ZBX_PROGRAM_VARIANT_AGENT);

	level = SUCCEED != last_ret ? LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING;

	ret = zbx_comms_exchange_with_redirect(config_source_ip, addrs, config_timeout, config_timeout, 0, level,
			config_tls, json.buffer, NULL, NULL, NULL, &error);

	if (SUCCEED == ret)
	{
		if (last_ret == FAIL)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Successfully sent heartbeat message to [%s]:%d",
					((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port);
		}
	}
	else
	{
		zabbix_log(level, "Unable to send heartbeat message to [%s]:%d [%s]",
				((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port, error);

		zbx_free(error);
	}

	last_ret = ret;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

ZBX_THREAD_ENTRY(active_checks_thread, args)
{
	zbx_thread_activechk_args	activechk_args, *activechks_args_in;
	time_t				nextcheck = 0, nextrefresh = 0, nextsend = 0, now, delta, lastcheck = 0,
					heartbeat_nextcheck = 0, lash_cmd_hash_check = 0;
	zbx_uint32_t			config_revision_local = 0;
	zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;

	activechks_args_in = (zbx_thread_activechk_args *)((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_vector_addr_ptr_create(&activechk_args.addrs);

	zbx_addr_copy(&activechk_args.addrs, &(activechks_args_in->addrs));
	const char	*config_hostname = zbx_strdup(NULL, activechks_args_in->config_hostname);

	zbx_free(args);

	session_token = zbx_create_token(0);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(activechks_args_in->zbx_config_tls, activechks_args_in->zbx_get_program_type_cb_arg, NULL);
#endif
	init_active_metrics(activechks_args_in->config_buffer_size);
	zbx_cfg_set_process_num(process_num);

#ifndef _WINDOWS
	zbx_set_sigusr_handler(zbx_active_checks_sigusr_handler);
#endif

	if (0 != activechks_args_in->config_heartbeat_frequency)
		heartbeat_nextcheck = time(NULL);

	while (ZBX_IS_RUNNING())
	{
#ifndef _WINDOWS
		if (1 == need_update_userparam)
		{
			zbx_setproctitle("active checks #%d [reloading user parameters]", process_num);
			reload_user_parameters(process_type, process_num, activechks_args_in->config_file);
			need_update_userparam = 0;
		}
#endif

		zbx_update_env(get_process_type_string(process_type), zbx_time());

		if ((now = time(NULL)) >= nextsend)
		{
			send_buffer(&activechk_args.addrs, &pre_persistent_vec, activechks_args_in->zbx_config_tls,
					activechks_args_in->config_timeout, activechks_args_in->config_source_ip,
					config_hostname, activechks_args_in->config_buffer_send,
					activechks_args_in->config_buffer_size);

			nextsend = time(NULL) + 1;
		}

		if (heartbeat_nextcheck != 0 && now >= heartbeat_nextcheck)
		{
			heartbeat_nextcheck = now + activechks_args_in->config_heartbeat_frequency;
			send_heartbeat_msg(&activechk_args.addrs, activechks_args_in->zbx_config_tls,
					activechks_args_in->config_timeout, activechks_args_in->config_source_ip,
					config_hostname, activechks_args_in->config_heartbeat_frequency);
		}

		if (now >= nextrefresh)
		{
			zbx_setproctitle("active checks #%d [getting list of active checks]", process_num);

			if (FAIL == refresh_active_checks(&activechk_args.addrs, activechks_args_in->zbx_config_tls,
					&config_revision_local, activechks_args_in->config_timeout,
					activechks_args_in->config_source_ip, activechks_args_in->config_listen_ip,
					activechks_args_in->config_listen_port, config_hostname,
					activechks_args_in->config_host_metadata,
					activechks_args_in->config_host_metadata_item,
					activechks_args_in->config_host_interface,
					activechks_args_in->config_host_interface_item, activechks_args_in->config_buffer_send, activechks_args_in->config_buffer_size))
			{
				nextrefresh = time(NULL) + 60;
			}
			else
			{
				nextrefresh = time(NULL) + activechks_args_in->config_refresh_active_checks;
				nextcheck = 0;
			}
#if !defined(_WINDOWS) && !defined(__MINGW32__)
			zbx_remove_inactive_persistent_files(&persistent_inactive_vec);
#endif
		}

		if (0 != active_commands.values_num)
		{
			process_active_commands(&activechk_args.addrs, activechks_args_in->zbx_config_tls,
					activechks_args_in->config_timeout, activechks_args_in->config_source_ip,
					config_hostname, activechks_args_in->config_buffer_send,
					activechks_args_in->config_buffer_size);
		}

		if (now >= nextcheck && activechks_args_in->config_buffer_size / 2 > buffer.pcount)
		{
			zbx_setproctitle("active checks #%d [processing active checks]", process_num);

			process_active_checks(&activechk_args.addrs, activechks_args_in->zbx_config_tls,
					activechks_args_in->config_timeout, activechks_args_in->config_source_ip,
					config_hostname, activechks_args_in->config_buffer_send,
					activechks_args_in->config_buffer_size,
					activechks_args_in->config_eventlog_max_lines_per_second,
					activechks_args_in->config_max_lines_per_second);

			if (activechks_args_in->config_buffer_size / 2 <= buffer.pcount)
			{
				/* failed to complete processing active checks */
				continue;
			}

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

				if (0 != heartbeat_nextcheck)
					heartbeat_nextcheck += delta;
			}

			zbx_setproctitle("active checks #%d [idle 1 sec]", process_num);
			zbx_sleep(1);
		}

		lastcheck = now;

		if (now > (lash_cmd_hash_check + SEC_PER_HOUR))
		{
			clean_command_hash();
			lash_cmd_hash_check = now;
		}
	}

	zbx_free(session_token);

#ifdef _WINDOWS
	zbx_vector_addr_ptr_clear_ext(&activechk_args.addrs, (zbx_clean_func_t)zbx_addr_free);
	zbx_vector_addr_ptr_destroy(&activechk_args.addrs);
	free_active_metrics();

	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#else
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#endif
}

static void	send_back_unsupported_item(zbx_uint64_t itemid, const char *key, char *error, const char *config_hostname,
		zbx_vector_addr_ptr_t *addrs, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, int config_buffer_send, int config_buffer_size)
{
	zabbix_log(LOG_LEVEL_WARNING, "active check \"%s\" is not supported: %s", key, error);

	process_value(addrs, NULL, itemid, config_hostname, key, error, ITEM_STATE_NOTSUPPORTED,
			NULL, NULL, NULL, NULL, NULL, NULL, 0, config_tls, config_timeout, config_source_ip,
			config_buffer_send, config_buffer_size);
}
