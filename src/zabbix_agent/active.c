/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#include "common.h"
#include "active.h"

#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "logfiles.h"
#include "eventlog.h"
#include "comms.h"
#include "threads.h"
#include "zbxjson.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

static ZBX_ACTIVE_METRIC *active_metrics = NULL;
static ZBX_ACTIVE_BUFFER buffer;

static void	init_active_metrics()
{
	zabbix_log( LOG_LEVEL_DEBUG, "In init_active_metrics()");

	if(NULL == active_metrics)
	{
		active_metrics = calloc(sizeof(ZBX_ACTIVE_METRIC), 1);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Metrics are already initialised.");
	}
}

static void	disable_all_metrics()
{
	int i;

 	zabbix_log( LOG_LEVEL_DEBUG, "In disable_all_metrics()");

	if(NULL == active_metrics) 
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No meters to desabling.");
		return;
	}

	for(i=0; NULL != active_metrics[i].key; i++)
	{
		active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}
}


static void	free_active_metrics(void)
{
	int i;

	zabbix_log( LOG_LEVEL_DEBUG, "In free_active_metrics()");

	if(NULL == active_metrics)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Metrics are already freed.");
		return;
	}

	for(i = 0; NULL != active_metrics[i].key;i++)
	{
		zbx_free(active_metrics[i].key);
		active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}

	zbx_free(active_metrics);
	active_metrics = NULL;
}

static int	get_min_nextcheck()
{
	int i;
	int min = -1;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_min_nextcheck()");

	for(i = 0; NULL != active_metrics[i].key; i++)
	{
		if(ITEM_STATUS_ACTIVE != active_metrics[i].status)
			continue;

		if(active_metrics[i].nextcheck < min || ((-1) == min))
			min = active_metrics[i].nextcheck;
	}

	if((-1) == min)
		return	FAIL;

	return min;
}

static void	add_check(char *key, int refresh, long lastlogsize)
{
	int i;

	zabbix_log( LOG_LEVEL_WARNING, "In add_check('%s', %i, %li)", key, refresh, lastlogsize);

	for(i=0; NULL != active_metrics[i].key; i++)
	{
		if(strcmp(active_metrics[i].key,key) != 0)
			continue;

		/* replace metric */
		if(active_metrics[i].refresh != refresh)
		{
			active_metrics[i].nextcheck = 0;
		}
		active_metrics[i].refresh	= refresh;
		/* active_metrics[i].lastlogsize	= lastlogsize; *//* don't update lastlogsize for exsted items */
		active_metrics[i].status	= ITEM_STATUS_ACTIVE;

		return;
	}

	/* add new metric */
	active_metrics[i].key		= strdup(key);
	active_metrics[i].refresh	= refresh;
	active_metrics[i].nextcheck	= 0;
	active_metrics[i].status	= ITEM_STATUS_ACTIVE;
	active_metrics[i].lastlogsize	= lastlogsize;

	/* move to the last metric */
	i++;

	/* allocate memory for last metric */
	active_metrics	= zbx_realloc(active_metrics, (i+1) * sizeof(ZBX_ACTIVE_METRIC));

	/* inicialize last metric */
	memset(&active_metrics[i], 0, sizeof(ZBX_ACTIVE_METRIC));
}

/******************************************************************************
 *                                                                            *
 * Function: parse_list_of_checks                                             *
 *                                                                            *
 * Purpose: Parse list of active checks received from server                  *
 *                                                                            *
 * Parameters: str - NULL terminated string received from server              *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on an incoorrect format of string                       *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexei Vladishev (new json protocol)             *
 *                                                                            *
 * Comments:                                                                  *
 *    String reprents as "ZBX_EOF" termination list                           *
 *    With '\n' delimeter between elements.                                   *
 *    Each element represents as:                                             *
 *           <key>:<refresh time>:<last log size>                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_list_of_checks(char *str)
{
	const char	*p;
	char	key[MAX_STRING_LEN], delay[MAX_STRING_LEN], lastlogsize[MAX_STRING_LEN];
	char	result[MAX_STRING_LEN];
	int	ret = SUCCEED;

	struct	zbx_json_parse jp;
	struct	zbx_json_parse   jp_data, jp_row;

	zabbix_log(LOG_LEVEL_WARNING, "In parse_list_of_checks() [%s]",
		str);

	disable_all_metrics();

	if(SUCCEED == ret && SUCCEED != zbx_json_open(str, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't open jason object");
		ret = FAIL;
	}

	if(SUCCEED == ret && SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, result, sizeof(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s",
			zbx_json_strerror());
		ret = FAIL;
	}

	if(SUCCEED == ret && 0 != strcmp(result,ZBX_PROTO_VALUE_SUCCESS))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Unsucesfull response received from server");
		ret = FAIL;
	}

	if(SUCCEED == ret && NULL == (p = zbx_json_pair_by_name(&jp, ZBX_PROTO_TAG_DATA)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't find \"%s\" tag",
			ZBX_PROTO_TAG_DATA);
		ret = FAIL;
	}

	if(SUCCEED == ret && FAIL == zbx_json_brackets_open(p, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't proceed jason request. %s",
				zbx_json_strerror());
		ret = FAIL;
	}

	if(SUCCEED == ret)
	{
	 	p = NULL;
		while (SUCCEED == ret && NULL != (p = zbx_json_next(&jp_data, p)))
		{
/* {"request":"ZBX_SENDER_DATA","data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *                                      ^------------------------------^
 */ 			if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s",
					zbx_json_strerror());
				ret = FAIL;
				break;
			}

			*delay = '\0';
			*key = '\0';
			*lastlogsize = '\0';

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, key, sizeof(key)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_KEY);
				continue;
			}

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DELAY, delay, sizeof(delay)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_DELAY);
				continue;
			}

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGLASTSIZE, lastlogsize, sizeof(lastlogsize)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_LOGLASTSIZE);
				continue;
			}

			if (*key && *delay && *lastlogsize)
				add_check(key, atoi(delay), atoi(lastlogsize));
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_active_checks                                                *
 *                                                                            *
 * Purpose: Retrive from ZABBIX server list of active checks                  *
 *                                                                            *
 * Parameters: host - IP or Hostname of ZABBIX server                         *
 *             port - port of ZABBIX server                                   *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexei Vladishev (new json protocol)             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_active_checks(
	const char		*host, 
	unsigned short	port
	)
{

	zbx_sock_t	s;

	char	*buf;

	int	ret;

	struct zbx_json json;

	zabbix_log( LOG_LEVEL_WARNING, "get_active_checks('%s',%u)", host, port);

	zbx_json_init(&json, 8*1024);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, CONFIG_TIMEOUT))) {
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", json.buffer);

		if( SUCCEED == (ret = zbx_tcp_send(&s, json.buffer)) )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Before read");

			if( SUCCEED == (ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE)) )
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Got [%s]", buf);
				parse_list_of_checks(buf);
			}
		}
		zbx_tcp_close(&s);
	}

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Get active checks error: %s", zbx_tcp_strerror());
	}

	zbx_json_free(&json);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_response                                                   *
 *                                                                            *
 * Purpose: Check if json response is SUCCEED                                 *
 *                                                                            *
 * Parameters: result SUCCEED or FAIL                                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: zabbix_sender has exactly the same function!                     *
 *                                                                            *
 ******************************************************************************/
static int	check_response(char *response)
{
	struct 		zbx_json_parse jp;
	const char 	*p;
	char		value[MAX_STRING_LEN];
	char		info[MAX_STRING_LEN];

	int	ret = SUCCEED;

	ret = zbx_json_open(response, &jp);

	if(SUCCEED == ret)
	{
		if (NULL == (p = zbx_json_pair_by_name(&jp, ZBX_PROTO_TAG_RESPONSE))
				|| NULL == zbx_json_decodevalue(p, value, sizeof(value)))
		{
			ret = FAIL;
		}
	}

	if(SUCCEED == ret)
	{
		if(strcmp(value, ZBX_PROTO_VALUE_SUCCESS) != 0)
		{
			ret = FAIL;
		}
	}

	if (NULL != (p = zbx_json_pair_by_name(&jp, ZBX_PROTO_TAG_INFO))
			&& NULL != zbx_json_decodevalue(p, info, sizeof(info)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Info from server: %s",
			info);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_buffer                                                      *
 *                                                                            *
 * Purpose: Send value stgored in the buffer to ZABBIX server                 *
 *                                                                            *
 * Parameters: host - IP or Hostname of ZABBIX server                         *
 *             port - port number                                             *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	send_buffer(
		const char		*host,
		unsigned short	port
	)
{
	zbx_sock_t	s;
	char		*buf = NULL;
	int		ret = SUCCEED;
	struct zbx_json json;
	int		i;
	char		tmp[MAX_STRING_LEN];
	static int	lastsent = 0;
	int		now;

	zabbix_log( LOG_LEVEL_WARNING, "In send_buffer('%s','%d')",
		host, port);

	zabbix_log( LOG_LEVEL_DEBUG, "Values in the buffer %d Max %d",
		buffer.count,
		CONFIG_BUFFER_SIZE);

	now = time(NULL);
	if(buffer.count < CONFIG_BUFFER_SIZE && now-lastsent < CONFIG_BUFFER_SEND)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Will not send now. Now %d lastsent %d < %d",
			now,
			lastsent,
			CONFIG_BUFFER_SEND);
		return ret;
	}

	zbx_json_init(&json, 8*1024);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);

	zbx_snprintf(tmp, sizeof(tmp), "%d", (int)time(NULL));
	zbx_json_addstring(&json, ZBX_PROTO_TAG_CLOCK, tmp, ZBX_JSON_TYPE_INT);

	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	for(i=0;i<buffer.count;i++)
	{
		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, buffer.data[i].host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, buffer.data[i].key, ZBX_JSON_TYPE_STRING);
		zbx_snprintf(tmp, sizeof(tmp), "%d", buffer.data[i].timestamp);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_CLOCK, tmp, ZBX_JSON_TYPE_INT);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, buffer.data[i].value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, MIN(buffer.count*CONFIG_TIMEOUT, 60)))) {

		zabbix_log(LOG_LEVEL_DEBUG, "JSON before sending [%s]",
			json.buffer);

		ret = zbx_tcp_send(&s, json.buffer);

		if( SUCCEED == ret )
		{
			if( SUCCEED == (ret = zbx_tcp_recv(&s, &buf)) )
			{
				zabbix_log( LOG_LEVEL_DEBUG, "JSON back [%s]",
					buf);
				if( !buf || check_response(buf) != SUCCEED )
				{
					zabbix_log( LOG_LEVEL_WARNING, "NOT OK");
				}
				else
				{
					zabbix_log( LOG_LEVEL_WARNING, "OK");
				}
			} else
				zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [recv] %s", zbx_tcp_strerror());
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [send] %s", zbx_tcp_strerror());

		zbx_tcp_close(&s);
	} else
		zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [connect] %s", zbx_tcp_strerror());

	zbx_json_free(&json);

	if(SUCCEED == ret)
	{
		/* free buffer */
		for(i=0;i<buffer.count;i++)
		{
			if(buffer.data[i].host != NULL)		zbx_free(buffer.data[i].host);
			if(buffer.data[i].key != NULL)		zbx_free(buffer.data[i].key);
			if(buffer.data[i].value != NULL)	zbx_free(buffer.data[i].value);
		}
		buffer.count = 0;
	}

	if(SUCCEED == ret)	lastsent = now;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_value                                                    *
 *                                                                            *
 * Purpose: Buffer new value or send the whole buffer to the server           *
 *                                                                            *
 * Parameters: host - IP or Hostname of ZABBIX server                         *
 *             port - port of ZABBIX server                                   *
 *             hostname - name of host in ZABBIX database                     *
 *             key - name of metric                                           *
 *             value - string version os key value                            *
 *             lastlogsize - size of readed logfile                           *
 *             timestamp - timestamp of readed value                          *
 *             source - name of logged data source                            *
 *             severity - severity of logged data sources                     *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_value(
		const char		*server,
		unsigned short	port,
		const char		*host,
		const char		*key,
		const char		*value,
		long			*lastlogsize,
		unsigned long	*timestamp,
		const char		*source, 
		unsigned short	*severity
)
{
	int ret = SUCCEED;
	int i;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_value('%s','%s','%s')",
		host, key, value);

	send_buffer(server,port);

	/* Called first time, allocate memory */
	if(NULL == buffer.data)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Buffer: first allocation for %d elements",
			CONFIG_BUFFER_SIZE);
		buffer.data = zbx_malloc(buffer.data, CONFIG_BUFFER_SIZE*sizeof(ZBX_ACTIVE_BUFFER_ELEMENT));
		memset(buffer.data, 0, CONFIG_BUFFER_SIZE*sizeof(ZBX_ACTIVE_BUFFER_ELEMENT));
		buffer.count = 0;
	}

	if(buffer.count < CONFIG_BUFFER_SIZE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Buffer: new element %d", buffer.count);
		memset(&buffer.data[buffer.count], 0, sizeof(ZBX_ACTIVE_BUFFER_ELEMENT));
		buffer.data[buffer.count].host = strdup(host);
		buffer.data[buffer.count].key = strdup(key);
		buffer.data[buffer.count].timestamp = (int)time(NULL);
		buffer.data[buffer.count].value = strdup(value);
		buffer.count++;
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Buffer full: new element %d", buffer.count);

		if(buffer.data[0].host != NULL)	zbx_free(buffer.data[0].host);
		if(buffer.data[0].key != NULL)	zbx_free(buffer.data[0].key);
		if(buffer.data[0].value != NULL)	zbx_free(buffer.data[0].value);
		memmove(&buffer.data[0],&buffer.data[1], (CONFIG_BUFFER_SIZE-1)*sizeof(ZBX_ACTIVE_BUFFER_ELEMENT));

		buffer.data[CONFIG_BUFFER_SIZE-1].host = strdup(host);
		buffer.data[CONFIG_BUFFER_SIZE-1].key = strdup(key);
		buffer.data[CONFIG_BUFFER_SIZE-1].timestamp = (int)time(NULL);
		buffer.data[CONFIG_BUFFER_SIZE-1].value = strdup(value);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "BUFFER");
		for(i=0;i<buffer.count;i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, " Host %s Key %s Values %s", buffer.data[i].host, buffer.data[i].key, buffer.data[i].value);
		}

	return ret;
}


static void	process_active_checks(char *server, unsigned short port)
{
	register int	i, s_count, p_count;
	char		**pvalue;
	int		now, send_err = SUCCEED, ret;
	unsigned long	timestamp;
	char		*source = NULL;
	char		*value = NULL;
	unsigned short	severity;
	long		lastlogsize;
	char		params[MAX_STRING_LEN];
	char		filename[MAX_STRING_LEN];
	char		pattern[MAX_STRING_LEN];

	AGENT_RESULT	result;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_active_checks('%s',%u)",server, port);

	init_result(&result);

	now = (int)time(NULL);

	for(i=0; NULL != active_metrics[i].key && SUCCEED == send_err; i++)
	{
		if(active_metrics[i].nextcheck > now)			continue;
		if(active_metrics[i].status != ITEM_STATUS_ACTIVE)	continue;

		/* Special processing for log files */
		if(strncmp(active_metrics[i].key,"log[",4) == 0)
		{
			do{ /* simple try realization */
				if (parse_command(active_metrics[i].key, NULL, 0, params, MAX_STRING_LEN) != 2)
					break;
				
				if (num_param(params) > 2)
					break;

				if (get_param(params, 1, filename, sizeof(filename)) != 0)
					break;

				if (get_param(params, 2, pattern, sizeof(pattern)) != 0)
					*pattern = '\0';

				s_count = 0;
				p_count = 0;
				lastlogsize = active_metrics[i].lastlogsize;
				while (SUCCEED == (ret = process_log(filename, &lastlogsize, &value))) {
					if (!value) /* EOF */
						break;

					if ('\0' == *pattern || NULL != zbx_regexp_match(value, pattern, NULL)) {
						send_err = process_value(
									server,
									port,
									CONFIG_HOSTNAME,
									active_metrics[i].key,
									value,
									&lastlogsize,
									NULL,
									NULL,
									NULL
								);
						s_count++;
					}
					p_count++;

					zbx_free(value);

					if (SUCCEED == send_err)
						active_metrics[i].lastlogsize = lastlogsize;
					else
						lastlogsize = active_metrics[i].lastlogsize;

					/* Do not flood ZABBIX server if file grows too fast */
					if(s_count >= (MAX_LINES_PER_SECOND * active_metrics[i].refresh))	break;

					/* Do not flood local system if file grows too fast */
					if(p_count >= (4 * MAX_LINES_PER_SECOND * active_metrics[i].refresh))	break;
				}

				if( FAIL == ret )
				{
					active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.",
						active_metrics[i].key);

					send_err = process_value(
								server,
								port,
								CONFIG_HOSTNAME,
								active_metrics[i].key,
								"ZBX_NOTSUPPORTED",
								&active_metrics[i].lastlogsize,
								NULL,
								NULL,
								NULL
							);
				}

			}while(0); /* simple try realization */
		}
		/* Special processing for eventlog */
		else if(strncmp(active_metrics[i].key,"eventlog[",9) == 0)
		{
			do{ /* simple try realization */
				if (parse_command(active_metrics[i].key, NULL, 0, params, MAX_STRING_LEN) != 2)
					break;
				
				if (num_param(params) > 2)
					break;

				if (get_param(params, 1, filename, sizeof(filename)) != 0)
					break;

				if (get_param(params, 2, pattern, sizeof(pattern)) != 0)
					*pattern = '\0';

				s_count = 0;
				p_count = 0;
				lastlogsize = active_metrics[i].lastlogsize;
				while (SUCCEED == (ret = process_eventlog(filename, &lastlogsize,
					&timestamp, &source, &severity, &value)))
				{
					if (!value) /* EOF */
						break;

					if (!pattern || NULL != zbx_regexp_match(value, pattern, NULL)) {
						send_err = process_value(
									server,
									port,
									CONFIG_HOSTNAME,
									active_metrics[i].key,
									value,
									&lastlogsize,
									&timestamp,
									source,
									&severity
								);
						s_count++;
					}
					p_count++;

					zbx_free(source);
					zbx_free(value);

					if (SUCCEED == send_err)
						active_metrics[i].lastlogsize = lastlogsize;
					else
						lastlogsize = active_metrics[i].lastlogsize;

					/* Do not flood ZABBIX server if file grows too fast */
					if(s_count >= (MAX_LINES_PER_SECOND * active_metrics[i].refresh))	break;

					/* Do not flood local system if file grows too fast */
					if(p_count >= (4 * MAX_LINES_PER_SECOND * active_metrics[i].refresh))	break;
				}

				if( FAIL == ret )
				{
					active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.",
						active_metrics[i].key);

					send_err = process_value(
								server,
								port,
								CONFIG_HOSTNAME,
								active_metrics[i].key,
								"ZBX_NOTSUPPORTED",
								&active_metrics[i].lastlogsize,
								NULL,
								NULL,
								NULL
							);
				}
			}while(0); /* simple try realization NOTE: never loop */
		}
		else
		{
			
			process(active_metrics[i].key, 0, &result);

			if( NULL == (pvalue = GET_TEXT_RESULT(&result)) )
				pvalue = GET_MSG_RESULT(&result);

			if(pvalue)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "For key [%s] received value [%s]", active_metrics[i].key, *pvalue);

				send_err = process_value(
						server,
						port,
						CONFIG_HOSTNAME,
						active_metrics[i].key,
						*pvalue,
						NULL,
						NULL,
						NULL,
						NULL
					);
				
				if( 0 == strcmp(*pvalue,"ZBX_NOTSUPPORTED") )
				{
					active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", active_metrics[i].key);
				}
			}

			free_result(&result);
		}
		active_metrics[i].nextcheck = (int)time(NULL)+active_metrics[i].refresh;
	}
}

static void	refresh_metrics(char *server, unsigned short port)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In refresh_metrics('%s',%u)",server, port);

	while(get_active_checks(server, port) != SUCCEED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Getting list of active checks failed. Will retry after 60 seconds");

		zbx_setproctitle("poller [sleeping for %d seconds]", 60);

		zbx_sleep(60);
	}
}

ZBX_THREAD_ENTRY(active_checks_thread, args)
{
	ZBX_THREAD_ACTIVECHK_ARGS activechk_args;

#if defined(ZABBIX_DAEMON)
	struct	sigaction phan;
#endif /* ZABBIX_DAEMON */
	int	sleeptime, nextcheck;
	int	nextrefresh;
	char	*p = NULL;

#if defined(ZABBIX_DAEMON)
	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);
#endif /* ZABBIX_DAEMON */

	activechk_args.host = strdup(((ZBX_THREAD_ACTIVECHK_ARGS *)args)->host);
	activechk_args.port = ((ZBX_THREAD_ACTIVECHK_ARGS *)args)->port;

	assert(activechk_args.host);
	
	p = strchr(activechk_args.host,',');
	if(p) *p = '\0';

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd active check started [%s:%u]", activechk_args.host, activechk_args.port);

	zbx_setproctitle("getting list of active checks");

	init_active_metrics();

	refresh_metrics(activechk_args.host, activechk_args.port);
	nextrefresh = (int)time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;

	while(ZBX_IS_RUNNING)
	{
		zbx_setproctitle("processing active checks");

		process_active_checks(activechk_args.host, activechk_args.port);

		nextcheck = get_min_nextcheck();
		if (FAIL == nextcheck)
			sleeptime = 60;
		else {
			sleeptime = nextcheck - (int)time(NULL);
			sleeptime = MAX(sleeptime, 0);
		}

		if (sleeptime > 0) {
			sleeptime = MIN(sleeptime, 60);

			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds", sleeptime );

			zbx_setproctitle("poller [sleeping for %d seconds]", sleeptime);

			zbx_sleep( sleeptime );
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "No sleeping" );

		if(time(NULL) >= nextrefresh)
		{
			refresh_metrics(activechk_args.host, activechk_args.port);
			nextrefresh = (int)time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
		}
	}

	zbx_free(activechk_args.host);
	free_active_metrics();

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd active check stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);

}

