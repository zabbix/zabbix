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

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

static ZBX_ACTIVE_METRIC *active_metrics = NULL;

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

	zabbix_log( LOG_LEVEL_DEBUG, "In add_check('%s', %i, %li)", key, refresh, lastlogsize);

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
	char	*p, *pstrend, *refresh, *lastlogsize;

	zabbix_log(LOG_LEVEL_DEBUG, "In parse_list_of_checks() [%s]",
		str);

	disable_all_metrics();

	while (NULL != str) {
		if (NULL != (pstrend = strchr(str,'\n')))
			*pstrend = '\0'; /* prepare line */

		zabbix_log(LOG_LEVEL_DEBUG, "Parsed [%s]", str);

		if (0 == strcmp(str, "ZBX_EOF"))
			break;

		refresh = NULL; 
		lastlogsize = NULL;

		/* parse string from end of line */
		/* line format "key:refresh:lastlogsize" */

		p = (NULL != pstrend) ? pstrend : str + strlen(str);

		/* Lastlogsize */
		for (; p != str; p--) {
			if (*p == ':') {
				*p = '\0';

				lastlogsize = p + 1;
				break;
			}
		}

		/* Refresh */
		for (; p != str; p--) {
			if (*p == ':') {
				*p = '\0';

				refresh = p + 1;
				break;
			}
		}

		if (str && refresh && lastlogsize)
			add_check(str, atoi(refresh), atoi(lastlogsize));

		if (pstrend == NULL)
			break;

		str = pstrend + 1;
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
 * Author: Eugene Grigorjev                                                   *
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

	char
		*buf,
		packet[MAX_BUF_LEN];

	int	ret;

	zabbix_log( LOG_LEVEL_DEBUG, "get_active_checks('%s',%u)", host, port);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, CONFIG_TIMEOUT))) {
		zbx_snprintf(packet, sizeof(packet), "%s\n%s\n","ZBX_GET_ACTIVE_CHECKS", CONFIG_HOSTNAME);
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", packet);

		if( SUCCEED == (ret = zbx_tcp_send(&s, packet)) )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Before read");

			if( SUCCEED == (ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE)) )
			{
				parse_list_of_checks(buf);
			}
		}
	}
	zbx_tcp_close(&s);

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Get active checks error: %s", zbx_tcp_strerror());
	}	
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
			}
		}
	}
	zbx_tcp_close(&s);

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Send value error: %s", zbx_tcp_strerror());
	}
	return ret;
}

static int	process_active_checks(char *server, unsigned short port)
{
	register int	i, s_count, p_count;

	char	**pvalue;

	int		now, send_err = SUCCEED, ret = SUCCEED;

	unsigned long	timestamp;
	char		*source = NULL;
	char		*value = NULL;
	unsigned short	severity;

	char	params[MAX_STRING_LEN];
	char	*filename;
	char	*pattern;

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
				if(parse_command(active_metrics[i].key, NULL, 0, params, MAX_STRING_LEN) != 2)
					break;
				
				if(num_param(params) > 2)
					break;

				filename = params;

				if( (pattern = strchr(params, ',')) ) /* TODO: rewrite for get_param */
				{
					*pattern = '\0';
					pattern++;
				}

				s_count = 0;
				p_count = 0;
				while( SUCCEED == (ret = process_log(filename, &active_metrics[i].lastlogsize, &value)) )
				{
					if( !value ) /* EOF */	break;

					if( !pattern || NULL != zbx_regexp_match(value, pattern, NULL) )
					{
						send_err = send_value(
									server,
									port,
									CONFIG_HOSTNAME,
									active_metrics[i].key,
									value,
									&active_metrics[i].lastlogsize,
									NULL,
									NULL,
									NULL
								);

						s_count++;
					}
					p_count++;

					zbx_free(value);

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
				if(parse_command(active_metrics[i].key, NULL, 0, params, MAX_STRING_LEN) != 2)
					break;
				
				if(num_param(params) > 2)
					break;

				filename = params;

				if( (pattern = strchr(params, ',')) )
				{
					*pattern = '\0';
					pattern++;
				}

				s_count = 0;
				p_count = 0;
				while( SUCCEED == (ret = process_eventlog(filename,&active_metrics[i].lastlogsize, &timestamp, &source, &severity, &value)) )
				{
					if( value  && (!pattern || NULL != zbx_regexp_match(value, pattern, NULL)) )
					{
						send_err = send_value(
									server,
									port,
									CONFIG_HOSTNAME,
									active_metrics[i].key,
									value,
									&active_metrics[i].lastlogsize,
									&timestamp,
									source,
									&severity
								);

						s_count++;
					}
					p_count++;

					zbx_free(source);
					zbx_free(value);

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
	return ret;
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

		if(process_active_checks(activechk_args.host, activechk_args.port) == FAIL)
		{
			zbx_sleep(60);
			continue;
		}

		nextcheck = get_min_nextcheck();
		if(FAIL == nextcheck)
		{
			sleeptime = 60;
		}
		else
		{
			sleeptime = nextcheck - (int)time(NULL);

			sleeptime = MAX(sleeptime, 0);
		}

		if(sleeptime > 0)
		{
			sleeptime = MIN(sleeptime, 60);

			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds", sleeptime );

			zbx_setproctitle("poller [sleeping for %d seconds]", sleeptime);

			zbx_sleep( sleeptime );
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No sleeping" );
		}

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

