#include "log.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxhttp.h"
#include "zbxcacheconfig.h"
#include <event.h>
#include <event2/thread.h>
#include "poller.h"
#include "zbxserver.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbx_rtc_constants.h"
#include "zbxrtc.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "httpagent_async.h"

static void	async_items(evutil_socket_t fd, short events, void *arg)
{
	zbx_dc_item_t		item, *items;
	AGENT_RESULT		results[ZBX_MAX_HTTPAGENT_ITEMS];
	int			errcodes[ZBX_MAX_HTTPAGENT_ITEMS];
	zbx_timespec_t		timespec;
	int			i, num;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = &item;
	num = zbx_dc_config_get_poller_items(poller_config->poller_type, poller_config->config_timeout,
			poller_config->processing, &items);

	if (0 == num)
		goto exit;

	zbx_prepare_items(items, errcodes, num, results, MACRO_EXPAND_YES);

	for (i = 0; i < num; i++)
		errcodes[i] = async_httpagent_add(&items[i], &results[i], poller_config);

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &timespec, ITEM_STATE_NOTSUPPORTED, results[i].msg);

			zbx_vector_uint64_append(&poller_config->itemids, items[i].itemid);
			zbx_vector_int32_append(&poller_config->errcodes, errcodes[i]);
			zbx_vector_int32_append(&poller_config->lastclocks, timespec.sec);
		}
	}

	zbx_preprocessor_flush();
	zbx_clean_items(items, num, results);
	zbx_dc_config_clean_items(items, NULL, num);

	if (items != &item)
		zbx_free(items);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	poller_config->queued += num;
}

static void	poller_requeue_items(zbx_poller_config_t *poller_config)
{
	int	nextcheck;

	if (0 == poller_config->itemids.values_num)
		return;

	zbx_dc_poller_requeue_items(poller_config->itemids.values, poller_config->lastclocks.values,
			poller_config->errcodes.values, poller_config->itemids.values_num,
			ZBX_POLLER_TYPE_HTTPAGENT, &nextcheck);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() requeued:%d", __func__, poller_config->itemids.values_num);

	zbx_vector_uint64_clear(&poller_config->itemids);
	zbx_vector_int32_clear(&poller_config->lastclocks);
	zbx_vector_int32_clear(&poller_config->errcodes);

	if (FAIL != nextcheck && nextcheck <= time(NULL))
		event_active(poller_config->async_items_timer, 0, 0);
}

ZBX_THREAD_ENTRY(httpagent_poller_thread, args)
{
	zbx_thread_poller_args	*poller_args_in = (zbx_thread_poller_args *)(((zbx_thread_args_t *)args)->args);

	double			sec, total_sec = 0.0;
	time_t			last_stat_time;
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	struct timeval		tv = {1, 0};
	zbx_poller_config_t	poller_config = {.queued = 0, .processed = 0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_rtc_subscribe(process_type, process_num, NULL, 0, poller_args_in->config_comms->config_timeout, &rtc);
	http_agent_poller_init(&poller_config, poller_args_in, async_items);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;
		struct timeval	tv_pending;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 == evtimer_pending(poller_config.async_items_timer, &tv_pending))
			evtimer_add(poller_config.async_items_timer, &tv);

		event_base_loop(poller_config.base, EVLOOP_ONCE);

		poller_requeue_items(&poller_config);

		total_sec += zbx_time() - sec;

		if (STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			zbx_setproctitle("%s #%d [got %d values, queued %d in " ZBX_FS_DBL " sec]",
				get_process_type_string(process_type), process_num, poller_config.processed,
				poller_config.queued, total_sec);

			poller_config.processed = 0;
			poller_config.queued = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, 0) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}
	}

	http_agent_poller_destroy(&poller_config);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
