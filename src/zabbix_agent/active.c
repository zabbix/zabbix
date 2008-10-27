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
static ZBX_REGEXP	*regexps = NULL;
static int		regexps_alloc = 0, regexps_num = 0;

static void	init_active_metrics()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In init_active_metrics()");

	active_metrics = zbx_malloc(active_metrics, sizeof(ZBX_ACTIVE_METRIC));
	active_metrics->key = NULL;
}

static void	disable_all_metrics()
{
	int	i;

 	zabbix_log(LOG_LEVEL_DEBUG, "In disable_all_metrics()");

	for (i = 0; NULL != active_metrics[i].key; i++)
		active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
}

static void	free_active_metrics()
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_active_metrics()");

	for (i = 0; NULL != active_metrics[i].key; i++)
		zbx_free(active_metrics[i].key);

	zbx_free(active_metrics);

	clean_regexps_ex(regexps, &regexps_num);

	zbx_free(regexps);
}

static int	get_min_nextcheck()
{
	int	i, min = -1;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_min_nextcheck()");

	for (i = 0; NULL != active_metrics[i].key; i++)
	{
		if (ITEM_STATUS_ACTIVE != active_metrics[i].status)
			continue;

		if (active_metrics[i].nextcheck < min || (-1) == min)
			min = active_metrics[i].nextcheck;
	}

	if ((-1) == min)
		return FAIL;

	return min;
}

static void	add_check(const char *key, int refresh, long lastlogsize)
{
	int	i;

	zabbix_log( LOG_LEVEL_DEBUG, "In add_check('%s', %i, %li)", key, refresh, lastlogsize);

	for (i = 0; NULL != active_metrics[i].key; i++)
	{
		if (0 != strcmp(active_metrics[i].key, key))
			continue;

		/* replace metric */
		if (active_metrics[i].refresh != refresh)
			active_metrics[i].nextcheck = 0;
		active_metrics[i].refresh = refresh;
		active_metrics[i].status = ITEM_STATUS_ACTIVE;

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
	active_metrics	= zbx_realloc(active_metrics, (i + 1) * sizeof(ZBX_ACTIVE_METRIC));

	/* inicialize last metric */
	active_metrics[i].key = NULL;
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
 * Author: Eugene Grigorjev                                                   *
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
	const char		*p;
	char			name[MAX_STRING_LEN], expression[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
				exp_delimiter;
	int			delay, lastlogsize, expression_type, case_sensitive;
	struct zbx_json_parse	jp;
	struct zbx_json_parse	jp_data, jp_row;

	zabbix_log(LOG_LEVEL_DEBUG, "In parse_list_of_checks()");

	disable_all_metrics();

	if (SUCCEED != zbx_json_open(str, &jp))
		goto json_error;

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, tmp, sizeof(tmp)))
		goto json_error;

	if (0 != strcmp(tmp, ZBX_PROTO_VALUE_SUCCESS))
	{
		zabbix_log(LOG_LEVEL_ERR, "Unsucesfull response received from server");
		return FAIL;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		goto json_error;

 	p = NULL;
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
/* {"data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *          ^------------------------------^
 */ 		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
			goto json_error;

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, name, sizeof(name)) || *name == '\0')
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_KEY);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DELAY, tmp, sizeof(tmp)) || *tmp == '\0')
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_DELAY);
			continue;
		}

		delay = atoi(tmp);

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGLASTSIZE, tmp, sizeof(tmp)) || *tmp == '\0')
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
					ZBX_PROTO_TAG_LOGLASTSIZE);
			continue;
		}

		lastlogsize = atoi(tmp);

		add_check(name, delay, lastlogsize);
	}

	clean_regexps_ex(regexps, &regexps_num);

	if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_REGEXP, &jp_data))
	{
	 	p = NULL;
		while (NULL != (p = zbx_json_next(&jp_data, p)))
		{
/*              {"regexp":[{"name":"regexp1",...,...},{...},...]} 
 *                         ^------------------------^
 */			if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
				goto json_error;

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "name", name, sizeof(name)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
						"name");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression", expression, sizeof(expression)) || *expression == '\0')
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
						"expression");
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "expression_type", tmp, sizeof(tmp)) || *tmp == '\0')
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
						"expression_type");
				continue;
			}

			expression_type = atoi(tmp);

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "exp_delimiter", tmp, sizeof(tmp)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
						"exp_delimiter");
				continue;
			}

			exp_delimiter = tmp[0];

			if (SUCCEED != zbx_json_value_by_name(&jp_row, "case_sensitive", tmp, sizeof(tmp)) || *tmp == '\0')
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unable to retrieve value of tag \"%s\"",
						"case_sensitive");
				continue;
			}

			case_sensitive = atoi(tmp);

			add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
					name, expression, expression_type, exp_delimiter, case_sensitive);
		}
	}

	return SUCCEED;
json_error:
	zabbix_log(LOG_LEVEL_ERR, "Can't parse list of active checks. %s",
			zbx_json_strerror());
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: refresh_active_checks                                            *
 *                                                                            *
 * Purpose: Retrive from ZABBIX server list of active checks                  *
 *                                                                            *
 * Parameters: host - IP or Hostname of ZABBIX server                         *
 *             port - port of ZABBIX server                                   *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	refresh_active_checks(const char *host, unsigned short port)
{
	zbx_sock_t	s;
	char		*buf;
	int		ret;
	struct zbx_json	json;

	zabbix_log( LOG_LEVEL_DEBUG, "refresh_active_checks('%s',%u)", host, port);

	zbx_json_init(&json, 128);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, CONFIG_TIMEOUT))) {
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", json.buffer);

		if (SUCCEED == (ret = zbx_tcp_send(&s, json.buffer)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Before read");

			if (SUCCEED == (ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Got [%s]", buf);
				parse_list_of_checks(buf);
			}
		}
		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Get active checks error: %s", zbx_tcp_strerror());

	zbx_json_free(&json);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_value                                                       *
 *                                                                            *
 * Purpose: Send value of specified key to ZABBIX server                      *
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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	send_value(
		const char		*host,
		unsigned short	port,
		const char		*hostname,
		const char		*key,
		const char		*value,
		long			*lastlogsize,
		unsigned long	*timestamp,
		const char		*source, 
		unsigned short	*severity
	)
{
	zbx_sock_t	s;
	char		*buf = NULL,
			*request = NULL;
	int		ret;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, CONFIG_TIMEOUT))) {
		request = comms_create_request(hostname, key, value, lastlogsize, timestamp, source, severity);

		zabbix_log(LOG_LEVEL_DEBUG, "XML before sending [%s]",request);

		ret = zbx_tcp_send(&s, request);

		zbx_free(request);

		if( SUCCEED == ret )
		{
			if( SUCCEED == (ret = zbx_tcp_recv(&s, &buf)) )
			{
				/* !!! REMOVE '\n' AT THE AND (always must be present) !!! */
				zbx_rtrim(buf, "\r\n\0");
				if(strcmp(buf,"OK") == 0)
				{
					zabbix_log( LOG_LEVEL_DEBUG, "OK");
				}
				else
				{
					zabbix_log( LOG_LEVEL_DEBUG, "NOT OK [%s:%s] [%s]", host, key, buf);
				}
			} else
				zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [recv] %s", zbx_tcp_strerror());
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [send] %s", zbx_tcp_strerror());
		zbx_tcp_close(&s);
	} else
		zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [connect] %s", zbx_tcp_strerror());

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
	char		encoding[16];

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
				
				if (num_param(params) > 3)
					break;

				if (get_param(params, 1, filename, sizeof(filename)) != 0)
					break;

				if (get_param(params, 2, pattern, sizeof(pattern)) != 0)
					*pattern = '\0';

				if (get_param(params, 3, encoding, sizeof(encoding)) != 0)
					*encoding = '\0';

				s_count = 0;
				p_count = 0;
				lastlogsize = active_metrics[i].lastlogsize;
				while (SUCCEED == (ret = process_log(filename, &lastlogsize, &value))) {
					if (!value) /* EOF */
						break;

					if (SUCCEED == regexp_match_ex(regexps, regexps_num, value, pattern, ZBX_CASE_SENSITIVE, encoding)) {
						send_err = send_value(
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

					send_err = send_value(
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
				
				if (num_param(params) > 3)
					break;

				if (get_param(params, 1, filename, sizeof(filename)) != 0)
					break;

				if (get_param(params, 2, pattern, sizeof(pattern)) != 0)
					*pattern = '\0';

				if (get_param(params, 3, encoding, sizeof(encoding)) != 0)
					*encoding = '\0';

				s_count = 0;
				p_count = 0;
				lastlogsize = active_metrics[i].lastlogsize;
				while (SUCCEED == (ret = process_eventlog(filename, &lastlogsize,
						&timestamp, &source, &severity, &value)))
				{
					if (!value) /* EOF */
						break;

					if (SUCCEED == regexp_match_ex(regexps, regexps_num, value, pattern, ZBX_CASE_SENSITIVE, encoding)) {
						send_err = send_value(
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

					send_err = send_value(
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

				send_err = send_value(
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

/*static void	refresh_metrics(char *server, unsigned short port)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In refresh_metrics('%s',%u)",server, port);

	while(get_active_checks(server, port) != SUCCEED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Getting list of active checks failed. Will retry after 60 seconds");

		zbx_setproctitle("poller [sleeping for %d seconds]", 60);

		zbx_sleep(60);
	}
}
*/
ZBX_THREAD_ENTRY(active_checks_thread, args)
{
	ZBX_THREAD_ACTIVECHK_ARGS activechk_args;

#if defined(ZABBIX_DAEMON)
	struct	sigaction phan;
#endif /* ZABBIX_DAEMON */
	int	sleeptime, nextcheck;
	int	nextrefresh = 0;
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

/*	refresh_metrics(activechk_args.host, activechk_args.port);
	nextrefresh = (int)time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
*/
	while(ZBX_IS_RUNNING)
	{
		if(time(NULL) >= nextrefresh)
		{
			zbx_setproctitle("poller [getting list of active checks]");

			if(FAIL == refresh_active_checks(activechk_args.host, activechk_args.port))
			{
				nextrefresh = (int)time(NULL) + 60;
			}
			else
			{
				nextrefresh = (int)time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
			}
		}

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

/*		if(time(NULL) >= nextrefresh)
		{
			refresh_metrics(activechk_args.host, activechk_args.port);
			nextrefresh = (int)time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
		}*/
	}

	zbx_free(activechk_args.host);
	free_active_metrics();

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd active check stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);

}

