/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "lld_worker.h"
#include "lld.h"
#include "lld_protocol.h"

#include "../events/events.h"

#include "zbxtimekeeper.h"
#include "zbxnix.h"
#include "zbxlog.h"
#include "zbxipcservice.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbx_item_constants.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxalgo.h"
#include "zbxhash.h"

typedef struct
{
	zbx_dc_item_t			item;
	int				errcode;
	zbx_timespec_t			ts;
	unsigned char			meta;
	zbx_uint64_t			lastlogsize;
	int				mtime;
	zbx_hashset_t			entries;
	zbx_vector_lld_entry_ptr_t	entries_sorted;
	zbx_vector_lld_macro_path_ptr_t	macro_paths;
	zbx_jsonobj_t			source;
}
zbx_lld_value_t;

/******************************************************************************
 *                                                                            *
 * Purpose: registers LLD worker with LLD manager                             *
 *                                                                            *
 * Parameters: socket - [IN] connections socket                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_register_worker(zbx_ipc_socket_t *socket)
{
	pid_t	ppid;

	ppid = getppid();

	zbx_ipc_socket_write(socket, ZBX_IPC_LLD_REGISTER, (unsigned char *)&ppid, sizeof(ppid));
}


/******************************************************************************
 *                                                                            *
 * Purpose: initialize LLD value                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_value_init(zbx_lld_value_t *lld_value)
{
	zbx_hashset_create_ext(&lld_value->entries, 0, lld_entry_hash, lld_entry_compare,
			lld_entry_clear_wrapper, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_vector_lld_entry_ptr_create(&lld_value->entries_sorted);
	lld_value->errcode = FAIL;

	zbx_vector_lld_macro_path_ptr_create(&lld_value->macro_paths);

	zbx_jsonobj_init(&lld_value->source);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear LLD value                                                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_value_clear(zbx_lld_value_t *lld_value)
{
	zbx_vector_lld_entry_ptr_destroy(&lld_value->entries_sorted);
	zbx_hashset_destroy(&lld_value->entries);
	zbx_dc_config_clean_items(&lld_value->item, &lld_value->errcode, 1);

	zbx_vector_lld_macro_path_ptr_clear_ext(&lld_value->macro_paths, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&lld_value->macro_paths);

	zbx_jsonobj_clear(&lld_value->source);

	memset(lld_value, 0, sizeof(zbx_lld_value_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush processed, meta or error LLD value                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_flush_value(zbx_lld_value_t *lld_value, unsigned char state, const char *error)
{
	zbx_item_diff_t	diff;

	diff.flags = ZBX_FLAGS_ITEM_DIFF_UNSET;

	if (ITEM_STATE_UNKNOWN != state && state != lld_value->item.state)
	{
		diff.state = state;
		diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

		if (ITEM_STATE_NORMAL == state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became supported",
					lld_value->item.host.host, lld_value->item.key_orig);

			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, lld_value->item.itemid,
					&lld_value->ts, ITEM_STATE_NORMAL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0,
					NULL, NULL, NULL);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became not supported: %s",
					lld_value->item.host.host, lld_value->item.key_orig, error);

			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, lld_value->item.itemid,
					&lld_value->ts, ITEM_STATE_NOTSUPPORTED, NULL, NULL, NULL, 0, 0, NULL, 0, NULL,
					0, NULL, NULL, error);
		}

		zbx_db_begin();
		zbx_process_events(NULL, NULL, NULL);
		zbx_db_commit();

		zbx_clean_events();
	}

	/* with successful LLD processing LLD error will be set to empty string */
	if (NULL != error)
	{
		zbx_sha512_hash(error, diff.error_hash);

		if (0 != memcmp(lld_value->item.error_hash, diff.error_hash, sizeof(lld_value->item.error_hash)))
		{
			diff.error = error;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;
		}
	}

	if (0 != lld_value->meta)
	{
		if (lld_value->item.lastlogsize != lld_value->lastlogsize)
		{
			diff.lastlogsize = lld_value->lastlogsize;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE;
		}
		if (lld_value->item.mtime != lld_value->mtime)
		{
			diff.mtime = lld_value->mtime;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
		}
	}

	if (ZBX_FLAGS_ITEM_DIFF_UNSET != diff.flags)
	{
		zbx_vector_item_diff_ptr_t	diffs;
		char				*sql = NULL;
		size_t				sql_alloc = 0, sql_offset = 0;

		zbx_vector_item_diff_ptr_create(&diffs);
		diff.itemid = lld_value->item.itemid;
		zbx_vector_item_diff_ptr_append(&diffs, &diff);

		zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, &diffs, ZBX_FLAGS_ITEM_DIFF_UPDATE_DB);

		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

		zbx_dc_config_items_apply_changes(&diffs);

		zbx_vector_item_diff_ptr_destroy(&diffs);
		zbx_free(sql);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare LLD value                                                 *
 *                                                                            *
 ******************************************************************************/
static int	lld_prepare_value(const zbx_ipc_message_t *message, zbx_lld_value_t *lld_value)
{
	zbx_uint64_t	itemid, hostid;
	char		*error = NULL, *value = NULL;
	int		ret = FAIL;

	zbx_lld_deserialize_item_value(message->data, &itemid, &hostid, &value, &lld_value->ts, &lld_value->meta,
			&lld_value->lastlogsize, &lld_value->mtime, &error);

	zbx_dc_config_get_items_by_itemids(&lld_value->item, &itemid, &lld_value->errcode, 1);

	if (SUCCEED != lld_value->errcode)
		goto out;

	if (NULL != value)
	{
		if (FAIL == zbx_jsonobj_open(value, &lld_value->source))
		{
			error = zbx_strdup(NULL, zbx_json_strerror());
		}
		else
		{
			if (SUCCEED == zbx_lld_macro_paths_get(itemid, &lld_value->macro_paths, &error))
			{
				lld_extract_entries(&lld_value->entries, &lld_value->entries_sorted, &lld_value->source,
						&lld_value->macro_paths, &error);
			}
		}
	}

	/* if there was an error or no value - flush the data immediately */

	unsigned char	state;

	if (NULL != error)
		state = ITEM_STATE_NOTSUPPORTED;
	else if (NULL != value)
		state = ITEM_STATE_NORMAL;
	else
		state = ITEM_STATE_UNKNOWN;

	if (ITEM_STATE_NORMAL != state)
	{
		lld_flush_value(lld_value, state, error);
		ret = FAIL;

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(error);
	zbx_free(value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare cached LLD value with the new value                       *
 *                                                                            *
 ******************************************************************************/
static int	lld_compare_value(const zbx_ipc_message_t *message, zbx_lld_value_t *lld_value)
{
	char		*value = NULL, *error = NULL;
	zbx_jsonobj_t	json;
	int		ret = FAIL;
	zbx_hashset_t	entries;

	zbx_hashset_create_ext(&entries, (size_t)lld_value->entries.num_data, lld_entry_hash, lld_entry_compare,
			lld_entry_clear_wrapper, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_lld_deserialize_value(message->data, &value);

	if (FAIL == zbx_jsonobj_open(value, &json))
		goto out;

	if (SUCCEED == (ret = lld_extract_entries(&entries, NULL, &json, &lld_value->macro_paths, &error)))
		ret = lld_compare_entries(&lld_value->entries, &entries);

	zbx_jsonobj_clear(&json);
out:
	zbx_free(value);
	zbx_free(error);

	zbx_hashset_destroy(&entries);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process LLD value                                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_value(zbx_lld_value_t *lld_value)
{
	char		*error = NULL;
	unsigned char	state;

	if (SUCCEED == lld_process_discovery_rule(&lld_value->item, &lld_value->entries_sorted, &error))
		state = ITEM_STATE_NORMAL;
	else
		state = ITEM_STATE_NOTSUPPORTED;

	lld_flush_value(lld_value, state, error);

	zbx_free(error);
}

ZBX_THREAD_ENTRY(lld_worker_thread, args)
{
	char			*error = NULL;
	zbx_ipc_socket_t	lld_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;
	zbx_uint64_t		processed_num = 0;
	zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_lld_value_t		lld_value = {0};

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&lld_socket, ZBX_IPC_SERVICE_LLD, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to lld manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	lld_register_worker(&lld_socket);

	time_stat = zbx_time();

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */
		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed " ZBX_FS_UI64 " LLD rules, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}
#undef STAT_INTERVAL
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		if (SUCCEED != zbx_ipc_socket_read(&lld_socket, &message))
		{
			if (ZBX_IS_RUNNING())
				zabbix_log(LOG_LEVEL_CRIT, "cannot read LLD manager service request");
			exit(EXIT_FAILURE);
		}
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(get_process_type_string(process_type), time_read);

		switch (message.code)
		{
			case ZBX_IPC_LLD_PREPARE_VALUE:
				if (0 != lld_value.item.itemid)
				{
					THIS_SHOULD_NEVER_HAPPEN;
					lld_value_clear(&lld_value);
				}

				lld_value_init(&lld_value);
				if (SUCCEED == lld_prepare_value(&message, &lld_value))
				{
					zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_NEXT, NULL, 0);
				}
				else
				{
					lld_value_clear(&lld_value);
					zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_DONE, NULL, 0);
					processed_num++;
				}
				break;
			case ZBX_IPC_LLD_CHECK_VALUE:
				if (0 == lld_value.item.itemid)
				{
					THIS_SHOULD_NEVER_HAPPEN;
					zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_DONE, NULL, 0);
					break;
				}

				if (SUCCEED == lld_compare_value(&message, &lld_value))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "detected duplicate value for LLD rule "
							ZBX_FS_UI64, lld_value.item.itemid);
					zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_NEXT, NULL, 0);
					break;
				}
				ZBX_FALLTHROUGH;
			case ZBX_IPC_LLD_PROCESS:
				lld_process_value(&lld_value);
				lld_value_clear(&lld_value);
				zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_DONE, NULL, 0);
				processed_num++;
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	if (0 != lld_value.item.itemid)
		lld_value_clear(&lld_value);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
