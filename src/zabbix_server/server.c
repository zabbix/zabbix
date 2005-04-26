/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>
/* getopt() */
#include <unistd.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#ifdef ZABBIX_THREADS
	#include <pthread.h>
#endif

#include "common.h"
#include "functions.h"
#include "expression.h"

#include "alerter.h"
#include "pinger.h"
#include "housekeeper.h"
#include "housekeeper.h"

#include "checks_agent.h"
#include "checks_internal.h"
#include "checks_simple.h"
#include "checks_snmp.h"

#define       LISTENQ 1024

#ifdef ZABBIX_THREADS
struct poller_answer {
        int	status; /* 0 - not received 1 - in processing 2 - received */
        int	itemid;
        int	ret;
	double	value;
	char	value_str[100];
};

struct poller_answer requests[10000];
int answer_count = 0;
int request_count = 0;

pthread_mutex_t poller_mutex;

pthread_mutex_t result_mutex;
pthread_cond_t result_cv;
int	state = 0; /* 0 - select data from DB, 1 - poll values from agents, 2 - update database */
DB_RESULT	*shared_result;
#endif

static	pid_t	*pids=NULL;

static	int	sucker_num=0;

int	CONFIG_SUCKERD_FORKS		=SUCKER_FORKS;
/* For trapper */
int	CONFIG_TRAPPERD_FORKS		= TRAPPERD_FORKS;
int	CONFIG_LISTEN_PORT		= 10051;
int	CONFIG_TRAPPER_TIMEOUT		= TRAPPER_TIMEOUT;
/**/
int	CONFIG_NOTIMEWAIT		=0;
int	CONFIG_TIMEOUT			=SUCKER_TIMEOUT;
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_PINGER_FREQUENCY		= 60;
int	CONFIG_DISABLE_PINGER		= 0;
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
char	*CONFIG_FILE			= NULL;
char	*CONFIG_PID_FILE		= NULL;
char	*CONFIG_LOG_FILE		= NULL;
char	*CONFIG_ALERT_SCRIPTS_PATH	= NULL;
char	*CONFIG_FPING_LOCATION		= NULL;
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;

void	uninit(void)
{
	int i;

	if(sucker_num == 0)
	{
		if(pids != NULL)
		{
			for(i=0;i<CONFIG_SUCKERD_FORKS+CONFIG_TRAPPERD_FORKS-1;i++)
			{
				if(kill(pids[i],SIGTERM) !=0 )
				{
					zabbix_log( LOG_LEVEL_WARNING, "Cannot kill process. PID=[%d] [%s]", pids[i], strerror(errno));
				}
			}
		}

		if(unlink(CONFIG_PID_FILE) != 0)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot remove PID file [%s] [%s]",
				CONFIG_PID_FILE, strerror(errno));
		}
	}
}

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
		zabbix_log( LOG_LEVEL_DEBUG, "Timeout while executing operation." );
	}
	else if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig || SIGPIPE == sig )
	{
		zabbix_log( LOG_LEVEL_ERR, "Got QUIT or INT or TERM or PIPE signal. Exiting..." );
		uninit();
		exit( FAIL );
	}
        else if( (SIGCHLD == sig) && (sucker_num == 0) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "One child process died. Exiting ...");
		uninit();
		exit( FAIL );
	}
/*	else if( SIGCHLD == sig )
	{
		zabbix_log( LOG_LEVEL_ERR, "One child died. Exiting ..." );
		uninit();
		exit( FAIL );
	}*/
	else if( SIGPIPE == sig)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got SIGPIPE. Where it came from???");
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got signal [%d]. Ignoring ...", sig);
	}
}

void	daemon_init(void)
{
	int		i;
	pid_t		pid;
	struct passwd	*pwd;

	/* running as root ?*/
	if((getuid()==0) || (getgid()==0))
	{
		pwd = getpwnam("zabbix");
		if ( pwd == NULL )
		{
			fprintf(stderr,"User zabbix does not exist.\n");
			fprintf(stderr, "Cannot run as root !\n");
			exit(FAIL);
		}
		if( (setgid(pwd->pw_gid) ==-1) || (setuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setgid or setuid to zabbix [%s]", strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( setegid(pwd->pw_gid) ==-1)
		{
			fprintf(stderr,"Cannot setegid to zabbix [%s]\n", strerror(errno));
			exit(FAIL);
		}
		if( seteuid(pwd->pw_uid) ==-1)
		{
			fprintf(stderr,"Cannot seteuid to zabbix [%s]\n", strerror(errno));
			exit(FAIL);
		}
#endif
/*		fprintf(stderr,"UID [%d] GID [%d] EUID[%d] GUID[%d]\n", getuid(), getgid(), geteuid(), getegid());*/

	}

	if( (pid = fork()) != 0 )
	{
		exit( 0 );
	}
	setsid();

	signal( SIGHUP, SIG_IGN );

	if( (pid = fork()) !=0 )
	{
		exit( 0 );
	}

	chdir("/");

	umask(0);

	for(i=0;i<MAXFD;i++)
	{
		if(i != fileno(stderr)) close(i);
	}
}

void usage(char *prog)
{
	printf("zabbix_server - ZABBIX server process v1.1alpha7\n");
	printf("Usage: %s [-h] [-c <file>]\n", prog);
	printf("\nOptions:\n");
	printf("  -c <file>   Specify configuration file. Default is /etc/zabbix/zabbix_server.conf\n");
	printf("  -h          Help\n");
	exit(-1);
}

void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
#ifdef ZABBIX_THREADS
		{"StartSuckers",&CONFIG_SUCKERD_FORKS,0,TYPE_INT,PARM_OPT,6,255},
#else
		{"StartSuckers",&CONFIG_SUCKERD_FORKS,0,TYPE_INT,PARM_OPT,5,255},
#endif
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"PingerFrequency",&CONFIG_PINGER_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},
		{"FpingLocation",&CONFIG_FPING_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"StartTrappers",&CONFIG_TRAPPERD_FORKS,0,TYPE_INT,PARM_OPT,2,255},
		{"TrapperTimeout",&CONFIG_TRAPPER_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"ListenPort",&CONFIG_LISTEN_PORT,0,TYPE_INT,PARM_OPT,1024,32768},
		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},
		{"DisablePinger",&CONFIG_DISABLE_PINGER,0,TYPE_INT,PARM_OPT,0,1},
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{0}
	};

	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE=strdup("/etc/zabbix/zabbix_server.conf");
	}

	parse_cfg_file(CONFIG_FILE,cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_server.pid");
	}
	if(CONFIG_ALERT_SCRIPTS_PATH == NULL)
	{
		CONFIG_ALERT_SCRIPTS_PATH=strdup("/home/zabbix/bin");
	}
	if(CONFIG_FPING_LOCATION == NULL)
	{
		CONFIG_FPING_LOCATION=strdup("/usr/sbin/fping");
	}
}

int	get_value(double *result,char *result_str,DB_ITEM *item, char *error, int max_error_len)
{
	int res=FAIL;

	struct	sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(CONFIG_TIMEOUT);

	if(item->type == ITEM_TYPE_ZABBIX)
	{
		res=get_value_agent(result,result_str,item,error,max_error_len);
	}
	else if( (item->type == ITEM_TYPE_SNMPv1) || (item->type == ITEM_TYPE_SNMPv2c))
	{
#ifdef HAVE_SNMP
		res=get_value_snmp(result,result_str,item,error, max_error_len);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was no compiled in");
		zabbix_syslog("Support of SNMP parameters was no compiled in. Cannot process [%s:%s]", item->host, item->key);
		res=NOTSUPPORTED;
#endif
	}
	else if(item->type == ITEM_TYPE_SIMPLE)
	{
		res=get_value_simple(result,result_str,item,error,max_error_len);
	}
	else if(item->type == ITEM_TYPE_INTERNAL)
	{
		res=get_value_internal(result,result_str,item,error,max_error_len);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Not supported item type:%d",item->type);
		zabbix_syslog("Not supported item type:%d",item->type);
		res=NOTSUPPORTED;
	}
	alarm(0);
	return res;
}

#ifdef ZABBIX_THREADS
int get_minnextcheck_thread(MYSQL *database, int now)
{
	char		sql[MAX_STRING_LEN];

	DB_RESULT	*result;

	int		res;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */ 
	snprintf(sql,sizeof(sql)-1,"select count(*),min(nextcheck) from items i,hosts h where ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<%d)) and h.hostid=i.hostid and i.status=%d and i.type not in (%d) and i.key_ not in ('%s','%s','%s','%s')", HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE,HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY, SERVER_ZABBIXLOG_KEY);
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif

	if( DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if( atoi(DBget_field(result,0,0)) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(DBget_field(result,0,1));
		}
	}
	DBfree_result(result);

	return	res;
}
#endif

int get_minnextcheck(int now)
{
	char		sql[MAX_STRING_LEN];

	DB_RESULT	*result;

	int		res;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */ 
	snprintf(sql,sizeof(sql)-1,"select count(*),min(nextcheck) from items i,hosts h where ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<%d)) and h.hostid=i.hostid and i.status=%d and i.type not in (%d) and i.itemid%%%d=%d and i.key_ not in ('%s','%s','%s','%s')", HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE,HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, CONFIG_SUCKERD_FORKS-4,sucker_num-4,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
	result = DBselect(sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if( atoi(DBget_field(result,0,0)) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(DBget_field(result,0,1));
		}
	}
	DBfree_result(result);

	return	res;
}

#ifdef ZABBIX_THREADS
/* Update special host's item - "status" */
void update_key_status_thread(MYSQL *database, int hostid,int host_status)
{
	char		sql[MAX_STRING_LEN];
	char		value_str[MAX_STRING_LEN];
	char		*s;

	DB_ITEM		item;
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_key_status()");

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where h.hostid=i.hostid and h.hostid=%d and i.key_='%s'", hostid,SERVER_STATUS_KEY);
	result = DBselect_thread(database, sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
	}
	else
	{
		DBget_item_from_db(&item,result,0);
	
		snprintf(value_str,sizeof(value_str)-1,"%d",host_status);

		process_new_value_thread(database,&item,value_str);
		update_triggers_thread(database,item.itemid);
	}

	DBfree_result(result);
}
#endif

/* Update special host's item - "status" */
void update_key_status(int hostid,int host_status)
{
	char		sql[MAX_STRING_LEN];
	char		value_str[MAX_STRING_LEN];

	DB_ITEM		item;
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_key_status()");

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where h.hostid=i.hostid and h.hostid=%d and i.key_='%s'", hostid,SERVER_STATUS_KEY);
	result = DBselect(sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
	}
	else
	{
		DBget_item_from_db(&item,result,0);
	
		snprintf(value_str,sizeof(value_str)-1,"%d",host_status);

		process_new_value(&item,value_str);
		update_triggers(item.itemid);
	}

	DBfree_result(result);
}

void	trend(void)
{
	char		sql[MAX_STRING_LEN];
 
	DB_RESULT	*result,*result2;

	int		i,j;

	snprintf(sql,sizeof(sql)-1,"select itemid from items");
	result2 = DBselect(sql);
	for(i=0;i<DBnum_rows(result2);i++)
	{
		snprintf(sql,sizeof(sql)-1,"select clock-clock%%3600, count(*),min(value),avg(value),max(value) from history where itemid=%d group by 1",atoi(DBget_field(result2,i,0)));
		result = DBselect(sql);
	
		for(j=0;j<DBnum_rows(result);j++)
		{
			snprintf(sql,sizeof(sql)-1,"insert into trends (itemid, clock, num, value_min, value_avg, value_max) values (%d,%d,%d,%f,%f,%f)",atoi(DBget_field(result2,i,0)), atoi(DBget_field(result,j,0)),atoi(DBget_field(result,j,1)),atof(DBget_field(result,j,2)),atof(DBget_field(result,j,3)),atof(DBget_field(result,j,4)));
			DBexecute(sql);
		}
		DBfree_result(result);
	}
	DBfree_result(result2);
}

#ifdef ZABBIX_THREADS
int get_values(MYSQL *database)
#else
int get_values(void)
#endif
{
	double		value;
	char		value_str[MAX_STRING_LEN];
	char		sql[MAX_STRING_LEN];

	char		error[MAX_STRING_LEN];
 
	DB_RESULT	*result;

	int		i;
	int		now;
	int		res;
	DB_ITEM		item;

	error[0]=0;

	now = time(NULL);

#ifdef ZABBIX_THREADS
	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where i.nextcheck<=%d and i.status=%d and i.type not in (%d) and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<=%d)) and h.hostid=i.hostid and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE,HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
#else
	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where i.nextcheck<=%d and i.status=%d and i.type not in (%d) and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<=%d)) and h.hostid=i.hostid and i.itemid%%%d=%d and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, CONFIG_SUCKERD_FORKS-4,sucker_num-4,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
#endif
#ifdef ZABBIX_THREADS
	pthread_mutex_lock (&result_mutex);
//	zabbix_log( LOG_LEVEL_WARNING, "\nSUCKER: waiting for state 0 [select data from DB] state [%d]", state);
	while (state != 0)
	{
		pthread_cond_wait(&result_cv, &result_mutex);
	}
	pthread_mutex_unlock(&result_mutex);

	result = DBselect_thread(database, sql);
	shared_result = result;
	answer_count = 0;
	request_count = DBnum_rows(result);
	for(i=0;i<request_count;i++)
	{
		requests[i].status = 0;
		requests[i].itemid=atoi(DBget_field(result,i,0));
	}


	pthread_mutex_lock (&result_mutex);
	state  = 1;
//	pthread_cond_signal(&result_cv);
	zabbix_log( LOG_LEVEL_WARNING, "sucker: broadcast state [%d]", state);
	pthread_cond_broadcast(&result_cv);
	pthread_mutex_unlock(&result_mutex);
	
	pthread_mutex_lock (&result_mutex);
	zabbix_log( LOG_LEVEL_WARNING, "sucker: waiting for request_count [%d] == answer_count [%d] [update data in DB] state [%d]", request_count, answer_count, state);
//	while ( (state != 2) && (answer_count != request_count))
//	while (state != CONFIG_SUCKERD_FORKS-5+request_count)
	while (answer_count!=request_count)
	{
		pthread_cond_wait(&result_cv, &result_mutex);
/*		zabbix_log( LOG_LEVEL_WARNING, "sucker: YOPT %d %d", request_count, answer_count);
		zabbix_log( LOG_LEVEL_WARNING, "sucker: before printing result");
		for(i=0;i<request_count;i++)
		{
			zabbix_log( LOG_LEVEL_WARNING, "sucker: before doing actual DB update: itemid [%d] status [%d]", atoi(DBget_field(shared_result,i,0)), requests[i].status );
		}*/
//		pthread_mutex_unlock (&poller_mutex);
	}
	pthread_mutex_unlock(&result_mutex);

	pthread_mutex_lock (&result_mutex);
	state  = 0;
//	pthread_cond_signal(&result_cv);
	zabbix_log( LOG_LEVEL_WARNING, "sucker: broadcast state [%d]", state);
	pthread_cond_broadcast(&result_cv);
	pthread_mutex_unlock(&result_mutex);
#else
	result = DBselect(sql);
#endif

	for(i=0;i<DBnum_rows(result);i++)
	{
		DBget_item_from_db(&item,result, i);

#ifdef ZABBIX_THREADS
		res = requests[i].ret;
		value = requests[i].value;
		strscpy(value_str,requests[i].value_str);
		if(requests[i].status != 2)
		{
			zabbix_log( LOG_LEVEL_WARNING, "sucker: ERROR status [%d] expected [2] host [%s] key [%s]", requests[i].status, item.host, item.key );
		}
/*		res = get_value(&value,value_str,&item);*/
#else
		res = get_value(&value,value_str,&item,error,sizeof(error));
#endif
		zabbix_log( LOG_LEVEL_DEBUG, "GOT VALUE [%s]", value_str );
		
		if(res == SUCCEED )
		{
#ifdef ZABBIX_THREADS
			process_new_value_thread(database, &item,value_str);
#else
			process_new_value(&item,value_str);
#endif

			if(item.host_network_errors>0)
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=0,error='' where hostid=%d and network_errors>0", item.hostid);
#ifdef ZABBIX_THREADS
				DBexecute_thread(database, sql);
#else
				DBexecute(sql);
#endif
			}

/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
			if(HOST_AVAILABLE_TRUE != item.host_available)
			{
				item.host_available=HOST_AVAILABLE_TRUE;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				zabbix_syslog("Enabling host [%s]", item.host );
#ifdef ZABBIX_THREADS
				DBupdate_host_availability_thread(database, item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status_thread(database, item.hostid,HOST_STATUS_MONITORED);
#else
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);
#endif

/* Why this break??? Trigger needs to be updated anyway!
				break;*/
			}
#ifdef ZABBIX_THREADS
		        update_triggers_thread(database, item.itemid);
#else
		        update_triggers(item.itemid);
#endif
		}
		else if(res == NOTSUPPORTED)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			zabbix_syslog("Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
#ifdef ZABBIX_THREADS
			DBupdate_item_status_to_notsupported_thread(database, item.itemid, error);
#else
			DBupdate_item_status_to_notsupported(item.itemid, error);
#endif
/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
			if(HOST_AVAILABLE_TRUE != item.host_available)
			{
				item.host_available=HOST_AVAILABLE_TRUE;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				zabbix_syslog("Enabling host [%s]", item.host );
#ifdef ZABBIX_THREADS
				DBupdate_host_availability_thread(database, item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status_thread(database, item.hostid,HOST_STATUS_MONITORED);	
#else
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);	
#endif

				break;
			}
		}
		else if(res == NETWORK_ERROR)
		{
			item.host_network_errors++;
			if(item.host_network_errors>=3)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
				zabbix_syslog("Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
#ifdef ZABBIX_THREADS
				DBupdate_host_availability_thread(database, item.hostid,HOST_AVAILABLE_FALSE,now,error);
				update_key_status_thread(database,item.hostid,HOST_AVAILABLE_FALSE);	
#else
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_FALSE,now,error);
				update_key_status(item.hostid,HOST_AVAILABLE_FALSE);	
#endif

				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=3 where hostid=%d", item.hostid);
#ifdef ZABBIX_THREADS
				DBexecute_thread(database, sql);
#else
				DBexecute(sql);
#endif
			}
			else
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=%d where hostid=%d", item.host_network_errors, item.hostid);
#ifdef ZABBIX_THREADS
				DBexecute_thread(database, sql);
#else
				DBexecute(sql);
#endif
			}

			break;
		}
/* Possibly, other logic required? */
		else if(res == AGENT_ERROR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed (ZBX_ERROR)", item.key, item.host );
			zabbix_syslog("Getting value of [%s] from host [%s] failed (ZBX_ERROR)", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");

			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_syslog("Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");
		}
	}

	DBfree_result(result);
	return SUCCEED;
}

#ifdef ZABBIX_THREADS
void *main_nodata_loop()
#else
int main_nodata_loop()
#endif
{
	char	sql[MAX_STRING_LEN];
	int	i,now;

	int	itemid,functionid;
	char	*parameter;

#ifdef ZABBIX_THREADS
	DB_HANDLE	database;
#endif
	DB_RESULT	*result;

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("updating nodata() functions");
#endif

#ifdef ZABBIX_THREADS
		DBconnect_thread(&database);
#else
		DBconnect();
#endif

		now=time(NULL);
#ifdef HAVE_PGSQL
		snprintf(sql,sizeof(sql)-1,"select distinct f.itemid,f.functionid,f.parameter from functions f, items i,hosts h where h.hostid=i.hostid and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<%d)) and i.itemid=f.itemid and f.function='nodata' and i.lastclock+f.parameter::text::integer<=%d and i.status=%d and i.type=%d and (f.lastvalue<>1 or f.lastvalue is NULL)", HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER);
#else
		snprintf(sql,sizeof(sql)-1,"select distinct f.itemid,f.functionid,f.parameter from functions f, items i,hosts h where h.hostid=i.hostid and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<%d)) and i.itemid=f.itemid and f.function='nodata' and i.lastclock+f.parameter<=%d and i.status=%d and i.type=%d and (f.lastvalue<>1 or f.lastvalue is NULL)", HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER);
#endif

#ifdef ZABBIX_THREADS
		result = DBselect_thread(&database, sql);
#else
		result = DBselect(sql);
#endif

		for(i=0;i<DBnum_rows(result);i++)
		{
			itemid=atoi(DBget_field(result,i,0));
			functionid=atoi(DBget_field(result,i,1));
			parameter=DBget_field(result,i,2);

			snprintf(sql,sizeof(sql)-1,"update functions set lastvalue='1' where itemid=%d and function='nodata' and parameter='%s'" , itemid, parameter );
#ifdef ZABBIX_THREADS
			DBexecute_thread(&database, sql);
#else
			DBexecute(sql);
#endif

#ifdef ZABBIX_THREADS
			update_triggers_thread(&database, itemid);
#else
			update_triggers(itemid);
#endif
		}

		DBfree_result(result);
#ifdef ZABBIX_THREADS
		DBclose_thread(&database);
#else
		DBclose();
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sleeping for 30 sec");
#endif
		sleep(30);
	}
}

#ifdef ZABBIX_THREADS
void *main_poller_loop()
{
	int i, num, ret, end, unprocessed_flag, processed;
	int itemid=0;

	DB_ITEM item;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_poller_loop()");
	for(;;)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "poller: waiting for state 1..%d [poll values from agents] State [%d]", (CONFIG_SUCKERD_FORKS-4)-1, state);
		zabbix_log( LOG_LEVEL_WARNING, "poller: waiting for state 1 [poll values from agents] State [%d]", state);
		pthread_mutex_lock (&result_mutex);
		while (state != 1)
//		while ( (state != 1) && (answer_count == request_count ))
//		while ( (state < 1) || (state>(CONFIG_SUCKERD_FORKS-4)) )
		{
			pthread_cond_wait(&result_cv, &result_mutex);
		}
		pthread_mutex_unlock (&result_mutex);
// Do work till there are unprocessed requests
		for(;;)
		{
			pthread_mutex_lock (&poller_mutex);
			unprocessed_flag=0;
			processed=0;
			for(i=0;i<request_count;i++)
			{
//				if(requests[i].status == 1)
//					zabbix_log( LOG_LEVEL_WARNING, "poller: looking for status [0] host [%s] key [%s] status [%d]", DBget_field(shared_result,i,2), DBget_field(shared_result,i,1), requests[i].status);
				num = i;
				if(requests[i].status == 0)
				{
//					zabbix_log( LOG_LEVEL_WARNING, "poller: itemid [%d]", atoi(DBget_field(shared_result,i,0)) );
					if(requests[i].status!=0)
						zabbix_log( LOG_LEVEL_WARNING, "poller: ERROR status [%d] must be [0] host [%s] key  [%s]", requests[num].status, item.host, item.key);
					requests[i].status = 1;
					/* we should retrieve info from shared_result here */
					itemid=atoi(DBget_field(shared_result,i,0));
					unprocessed_flag=1;
					break;
				}
				else
				{
					processed++;
				}
			}
			pthread_mutex_unlock (&poller_mutex);

/*			zabbix_log( LOG_LEVEL_WARNING, "poller: number of already retrieved items [%d] total [%d]", i, request_count);*/

			/* 0 5 6 ? */
//			zabbix_log( LOG_LEVEL_WARNING, "poller: num [%d] answer count [%d] request count [%d]", num, answer_count, request_count);
//			if(processed < request_count-1)
			if( (num < request_count) && (unprocessed_flag == 1))
			{
				/* do logic */
//				zabbix_log( LOG_LEVEL_WARNING, "poller: processing host [%s] key [%s] status [%d]", DBget_field(shared_result,num,2), DBget_field(shared_result,num,1), requests[num].status);

				item.key=DBget_field(shared_result,num,1);
				item.host=DBget_field(shared_result,num,2);
				item.port=atoi(DBget_field(shared_result,num,3));
				item.type=atoi(DBget_field(shared_result,num,7));
				item.snmp_community=DBget_field(shared_result,num,8);
				item.snmp_oid=DBget_field(shared_result,num,9);
				item.useip=atoi(DBget_field(shared_result,num,10));
				item.ip=DBget_field(shared_result,num,11);
				item.value_type=atoi(DBget_field(shared_result,num,17));
				item.snmp_port=atoi(DBget_field(shared_result,num,19));

				if(requests[num].status!=1)
					zabbix_log( LOG_LEVEL_WARNING, "poller: ERROR status [%d] must be [1] host [%s] key  [%s]", requests[num].status, item.host, item.key);
				ret = get_value(&requests[num].value,requests[num].value_str,&item);
				/* end of do logic */

				pthread_mutex_lock (&poller_mutex);
				requests[num].ret = ret;
				if(requests[num].status!=1)
				{
					zabbix_log( LOG_LEVEL_WARNING, "poller: ERROR2 status [%d] must be [1] host [%s] key [%s] num [%d] processed [%d] request_count [%d]", requests[num].status, item.host, item.key, num, processed, request_count);
					zabbix_log( LOG_LEVEL_WARNING, "poller: ERROR2 host2 [%s] item.host [%s]", DBget_field(shared_result,num,2), item.host);
				}
				requests[num].status = 2;
				answer_count++;
//				zabbix_log( LOG_LEVEL_WARNING, "poller: answer count [%d] request count [%d]", answer_count, request_count);
				pthread_mutex_unlock (&poller_mutex);

//				zabbix_log( LOG_LEVEL_WARNING, "poller: processed [%d] request count [%d]", processed, request_count);
			}

//			if(num == request_count-1)
			pthread_mutex_lock (&poller_mutex);
//			zabbix_log( LOG_LEVEL_WARNING, "poller:      num [%d] answer count [%d] request count [%d] processed [%d]", num, answer_count, request_count, processed);
			if(processed == request_count)		end = 1;
			else					end = 0;
			pthread_mutex_unlock (&poller_mutex);

			if(end == 1)
			{
				break;
			}

		}

		pthread_mutex_lock (&result_mutex);
//		if( (state >= 1) && (state< CONFIG_SUCKERD_FORKS-4) )

/*		if(state == 1)
		{
			state = 2;
			zabbix_log( LOG_LEVEL_WARNING, "poller: broadcast state [%d]", state);
			pthread_cond_broadcast(&result_cv);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "poller: do not broadcast state [2]");
		}
*/
		state = 2;
		zabbix_log( LOG_LEVEL_WARNING, "poller: broadcast state [%d]", state);
		pthread_cond_broadcast(&result_cv);
		pthread_mutex_unlock (&result_mutex);

/*		pthread_mutex_lock (&result_mutex);
//		if( (answer_count == request_count) && (state == 1))
		if( answer_count == request_count)
		{
			state = 2;
			zabbix_log( LOG_LEVEL_WARNING, "poller: broadcast state [%d]", state);
			pthread_cond_signal(&result_cv);
//		pthread_cond_broadcast(&result_cv);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "poller: do not broadcast state [2]");
		}
		pthread_mutex_unlock (&result_mutex);*/
	}
	sleep(100000);
}
#endif

#ifdef ZABBIX_THREADS
void *main_sucker_loop()
#else
int main_sucker_loop()
#endif
{
	int	now;
	int	nextcheck,sleeptime;

#ifdef ZABBIX_THREADS
	DB_HANDLE	database;
#endif

#ifdef ZABBIX_THREADS
	my_thread_init();
#endif

#ifdef ZABBIX_THREADS
	DBconnect_thread(&database);
#else
	DBconnect();
#endif

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sucker [getting values]");
#endif
		now=time(NULL);
#ifdef ZABBIX_THREADS
		get_values(&database);
#else
		get_values();
#endif

		zabbix_log( LOG_LEVEL_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

#ifdef ZABBIX_THREADS
		nextcheck=get_minnextcheck_thread(&database,now);
#else
		nextcheck=get_minnextcheck(now);
#endif
		zabbix_log( LOG_LEVEL_DEBUG, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

		if( FAIL == nextcheck)
		{
			sleeptime=SUCKER_DELAY;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > SUCKER_DELAY)
			{
				sleeptime = SUCKER_DELAY;
			}
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime );
#ifdef HAVE_FUNCTION_SETPROCTITLE
			setproctitle("sucker [sleeping for %d seconds]", 
					sleeptime);
#endif
			sleep( sleeptime );
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}
	}
}

#ifdef ZABBIX_THREADS

#define NUM_THREADS	100
void	init_threads(void)
{
	pthread_t threads[NUM_THREADS];
	int rc, i;
	for(i=0;i < CONFIG_SUCKERD_FORKS;i++){
		zabbix_log( LOG_LEVEL_WARNING, "Creating thread %d", i);
		if(i==0)
		{
			rc = pthread_create(&threads[i], NULL, main_housekeeper_loop, (void *)i);
		}
		else if(i==1)
		{
			rc = pthread_create(&threads[i], NULL, main_alerter_loop, (void *)i);
		}
		else if(i==2)
		{
			rc = pthread_create(&threads[i], NULL, main_nodata_loop, (void *)i);
//			rc = pthread_create(&threads[i], NULL, main_housekeeper_loop, (void *)i);
		}
		else if(i==3)
		{
			rc = pthread_create(&threads[i], NULL, main_pinger_loop, (void *)i);
//			rc = pthread_create(&threads[i], NULL, main_housekeeper_loop, (void *)i);
		}
		else if(i==4)
		{
#ifdef HAVE_SNMP
			init_snmp("zabbix_server");
#endif
			rc = pthread_create(&threads[i], NULL, main_sucker_loop, (void *)i);
		}
		else
		{
			rc = pthread_create(&threads[i], NULL, main_poller_loop, (void *)i);
		}

		if (rc){
			zabbix_log( LOG_LEVEL_ERR, "ERROR from pthread_create() [%m]");
			exit(-1);
		}
	}
	pthread_exit(NULL);
}
#endif


int	process_trap(int sockfd,char *s)
{
	char	*p;
	char	*server,*key,*value_string;

	int	ret=SUCCEED;

	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;

	server=(char *)strtok(s,":");
	if(NULL == server)
	{
		return FAIL;
	}

	key=(char *)strtok(NULL,":");
	if(NULL == key)
	{
		return FAIL;
	}

	value_string=(char *)strtok(NULL,":");
	if(NULL == value_string)
	{
		return FAIL;
	}

	ret=process_data(sockfd,server,key,value_string);

	return ret;
}

void	process_trapper_child(int sockfd)
{
	ssize_t	nread;
	char	line[MAX_STRING_LEN];
	char	result[MAX_STRING_LEN];
	static struct  sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(CONFIG_TIMEOUT);

	zabbix_log( LOG_LEVEL_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, MAX_STRING_LEN)) < 0)
	{
		if(errno == EINTR)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Read timeout");
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "read() failed");
		}
		zabbix_log( LOG_LEVEL_DEBUG, "After read() 1");
		alarm(0);
		return;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "After read() 2 [%d]",nread);

	if(nread>0)
	{
		line[nread-1]=0;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Got line:%s", line);
	if( SUCCEED == process_trap(sockfd,line) )
	{
		snprintf(result,sizeof(result)-1,"OK\n");
	}
	else
	{
		snprintf(result,sizeof(result)-1,"NOT OK\n");
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Sending back [%s]", result);
	zabbix_log( LOG_LEVEL_DEBUG, "Length [%d]", strlen(result));
	zabbix_log( LOG_LEVEL_DEBUG, "Sockfd [%d]", sockfd);
	if( write(sockfd,result,strlen(result)) == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error sending result back [%s]",strerror(errno));
		zabbix_syslog("Trapper: error sending result back [%s]",strerror(errno));
	}
	zabbix_log( LOG_LEVEL_DEBUG, "After write()");
	alarm(0);
}

int	tcp_listen(const char *host, int port, socklen_t *addrlenp)
{
	int	sockfd;
	struct	sockaddr_in      serv_addr;
	struct linger ling;

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) == -1)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create socket");
		exit(1);
	}

	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
		ling.l_linger=0;
		if(setsockopt(sockfd,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}

	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	serv_addr.sin_port        = htons(port);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot bind to port %d. Another zabbix_trapperd running ?", port);
		exit(1);
	}
	
	if(listen(sockfd, LISTENQ) !=0 )
	{
		zabbix_log( LOG_LEVEL_CRIT, "listen() failed");
		exit(1);
	}

	*addrlenp = sizeof(serv_addr);

	return  sockfd;
}

void	child_trapper_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr cliaddr;

	zabbix_log( LOG_LEVEL_DEBUG, "In child_main()");

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_trapperd %ld started",(long)getpid());*/
	zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Trapper]", i);

	zabbix_log( LOG_LEVEL_DEBUG, "Before DBconnect()");
	DBconnect();
	zabbix_log( LOG_LEVEL_DEBUG, "After DBconnect()");

	for(;;)
	{
		clilen = addrlen;
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("waiting for connection");
#endif
		zabbix_log( LOG_LEVEL_DEBUG, "Before accept()");
		connfd=accept(listenfd,&cliaddr, &clilen);
		zabbix_log( LOG_LEVEL_DEBUG, "After accept()");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing data");
#endif

		process_trapper_child(connfd);

		close(connfd);
	}
	DBclose();
}

pid_t	child_trapper_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
		return (pid);
	}
	else
	{
//		sucker_num=i;
	}

	/* never returns */
	child_trapper_main(i, listenfd, addrlen);

	/* avoid compilator warning */
	return 0;
}

int main(int argc, char **argv)
{
	int	ch;
	int	i;
	pid_t	pid;

	struct	sigaction phan;

	int		listenfd;
	socklen_t	addrlen;

	char		host[128];


/* Parse the command-line. */
	while ((ch = getopt(argc, argv, "c:h")) != EOF)
	switch ((char) ch) {
		case 'c':
			CONFIG_FILE = optarg;
			break;
		case 'h':
			usage(argv[0]);
			break;
		default:
			usage(argv[0]);
			break;
        }

	init_metrics();

	init_config();

	daemon_init();

	phan.sa_handler = &signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);
	sigaction(SIGPIPE, &phan, NULL);

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

	if( FAIL == create_pid_file(CONFIG_PID_FILE))
	{
		return -1;
	}

	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_server...");

/* Need to set trigger status to UNKNOWN since last run */
	DBconnect();
	DBupdate_triggers_status_after_restart();

/*#define CALC_TREND*/

#ifdef CALC_TREND
	trend();
	return 0;
#endif
	DBclose();
	pids=calloc(CONFIG_SUCKERD_FORKS+CONFIG_TRAPPERD_FORKS-1,sizeof(pid_t));

#ifdef ZABBIX_THREADS
	my_init();
	pthread_mutex_init(&poller_mutex, NULL);
	pthread_mutex_init(&result_mutex, NULL);
	pthread_cond_init(&result_cv, NULL);
	init_threads();
	for(;;) sleep(100000);
	return 0;
#endif

	for(i=1;i<CONFIG_SUCKERD_FORKS;i++)
	{
		if((pid = fork()) == 0)
		{
			sucker_num=i;
			break;
		}
		else
		{
			pids[i-1]=pid;
		}
	}

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started",sucker_num);*/



	if( sucker_num == 0)
	{
		sigaction(SIGCHLD, &phan, NULL);
/* Run trapper processes then do housekeeping */
		if(gethostname(host,127) != 0)
		{
			zabbix_log( LOG_LEVEL_CRIT, "gethostname() failed");
			exit(FAIL);
		}

		listenfd = tcp_listen(host,CONFIG_LISTEN_PORT,&addrlen);

		for(i = CONFIG_SUCKERD_FORKS; i< CONFIG_SUCKERD_FORKS+CONFIG_TRAPPERD_FORKS; i++)
		{
			pids[i-i] = child_trapper_make(i, listenfd, addrlen);
		}

/* First instance of zabbix_suckerd performs housekeeping procedures */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Housekeeper]",sucker_num);
		main_housekeeper_loop();
	}
	else if(sucker_num == 1)
	{
/* Second instance of zabbix_suckerd sends alerts to users */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Alerter]",sucker_num);
		main_alerter_loop();
	}
	else if(sucker_num == 2)
	{
/* Third instance of zabbix_suckerd periodically re-calculates 'nodata' functions */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [nodata() calculator]",sucker_num);
		main_nodata_loop();
	}
	else if(sucker_num == 3)
	{
/* Fourth instance of zabbix_suckerd periodically pings hosts */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [ICMP pinger]",sucker_num);
		main_pinger_loop();
	}
	else
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Sucker. SNMP:ON]",sucker_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Sucker. SNMP:OFF]",sucker_num);
#endif

		main_sucker_loop();
	}

	return SUCCEED;
}
