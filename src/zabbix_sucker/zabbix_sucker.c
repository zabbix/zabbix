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
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

/* NET-SNMP is used */
#ifdef HAVE_NETSNMP
	#include <net-snmp/net-snmp-config.h>
	#include <net-snmp/net-snmp-includes.h>
#endif

/* Required for SNMP support*/
#ifdef HAVE_UCDSNMP
	#include <ucd-snmp/ucd-snmp-config.h>
	#include <ucd-snmp/ucd-snmp-includes.h>
	#include <ucd-snmp/system.h>
#endif

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"

#include "common.h"
#include "functions.h"
#include "expression.h"
#include "alerter.h"
#include "pinger.h"
#include "housekeeper.h"

#include "../zabbix_agent/sysinfo.h"

static	pid_t	*pids=NULL;

static	int	sucker_num=0;

int	CONFIG_SUCKERD_FORKS		=SUCKER_FORKS;
int	CONFIG_NOTIMEWAIT		=0;
int	CONFIG_TIMEOUT			=SUCKER_TIMEOUT;
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_PINGER_FREQUENCY		= 60;
int	CONFIG_DISABLE_PINGER		= 0;
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
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
			for(i=0;i<CONFIG_SUCKERD_FORKS-1;i++)
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
	if((getuid()==0) || (getuid()==0))
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

void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"StartSuckers",&CONFIG_SUCKERD_FORKS,0,TYPE_INT,PARM_OPT,5,255},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"PingerFrequency",&CONFIG_PINGER_FREQUENCY,0,TYPE_INT,PARM_OPT,30,3600},
		{"FpingLocation",&CONFIG_FPING_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
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

	parse_cfg_file("/etc/zabbix/zabbix_suckerd.conf",cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_suckerd.pid");
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

#ifdef HAVE_SNMP
int	get_value_SNMP(int version,double *result,char *result_str,DB_ITEM *item)
{

	#define NEW_APPROACH

	struct snmp_session session, *ss;
	struct snmp_pdu *pdu;
	struct snmp_pdu *response;

	#ifdef NEW_APPROACH
	char temp[MAX_STRING_LEN];
	#endif

	oid anOID[MAX_OID_LEN];
	size_t anOID_len = MAX_OID_LEN;

	struct variable_list *vars;
	int status;

	unsigned char *ip;

	int ret=SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP()");

	snmp_sess_init( &session );
	session.version = version;
	session.remote_port = item->snmp_port;


	if(item->useip == 1)
	{
	#ifdef NEW_APPROACH
		snprintf(temp,sizeof(temp)-1,"%s:%d", item->ip, item->snmp_port);
		session.peername = temp;
	#else
		session.peername = item->ip;
	#endif
	}
	else
	{
	#ifdef NEW_APPROACH
		snprintf(temp, sizeof(temp)-1, "%s:%d", item->host, item->snmp_port);
		session.peername = temp;
	#else
		session.peername = item->host;
	#endif
	}
	session.community = item->snmp_community;
	session.community_len = strlen(session.community);

	zabbix_log( LOG_LEVEL_DEBUG, "SNMP [%s@%s:%d]",session.community, session.peername, session.remote_port);
	zabbix_log( LOG_LEVEL_DEBUG, "OID [%s]", item->snmp_oid);

	SOCK_STARTUP;
	ss = snmp_open(&session);

	if(ss == NULL)
	{
		SOCK_CLEANUP;
		zabbix_log( LOG_LEVEL_WARNING, "Error: snmp_open()");
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP() 0.2");

	pdu = snmp_pdu_create(SNMP_MSG_GET);
	read_objid(item->snmp_oid, anOID, &anOID_len);

#if OTHER_METHODS
	get_node("sysDescr.0", anOID, &anOID_len);
	read_objid(".1.3.6.1.2.1.1.1.0", anOID, &anOID_len);
	read_objid("system.sysDescr.0", anOID, &anOID_len);
#endif

	snmp_add_null_var(pdu, anOID, anOID_len);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP() 0.3");
  
	status = snmp_synch_response(ss, pdu, &response);
	zabbix_log( LOG_LEVEL_DEBUG, "Status send [%d]", status);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP() 0.4");

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP() 1");

	if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
	{

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMP() 2");
/*		for(vars = response->variables; vars; vars = vars->next_variable)
		{
			print_variable(vars->name, vars->name_length, vars);
		}*/

		for(vars = response->variables; vars; vars = vars->next_variable)
		{
			int count=1;
			zabbix_log( LOG_LEVEL_DEBUG, "AV loop()");

			if(	(vars->type == ASN_INTEGER) ||
				(vars->type == ASN_UINTEGER)||
				(vars->type == ASN_COUNTER) ||
				(vars->type == ASN_TIMETICKS) ||
				(vars->type == ASN_GAUGE)
			)
			{
				*result=(long)*vars->val.integer;
				/*
				 * This solves situation when large numbers are stored as negative values
				 * http://sourceforge.net/tracker/index.php?func=detail&aid=700145&group_id=23494&atid=378683
				 */ 
				/*sprintf(result_str,"%ld",(long)*vars->val.integer);*/
				snprintf(result_str,MAX_STRING_LEN-1,"%lu",(long)*vars->val.integer);
			}
			else if(vars->type == ASN_OCTET_STR)
			{
				memcpy(result_str,vars->val.string,vars->val_len);
				result_str[vars->val_len] = '\0';
				if(item->type == 0)
				{
					ret = NOTSUPPORTED;
				}
			}
			else if(vars->type == ASN_IPADDRESS)
			{
				ip = vars->val.string;
				snprintf(result_str,MAX_STRING_LEN-1,"%d.%d.%d.%d",ip[0],ip[1],ip[2],ip[3]);
				if(item->type == 0)
				{
					ret = NOTSUPPORTED;
				}
			}
			else
			{
				zabbix_log( LOG_LEVEL_WARNING,"value #%d has unknow type", count++);
				ret  = NOTSUPPORTED;
			}
		}
	}
	else
	{
		if (status == STAT_SUCCESS)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error in packet\nReason: %s\n",
				snmp_errstring(response->errstat));
			if(response->errstat == SNMP_ERR_NOSUCHNAME)
			{
				ret=NOTSUPPORTED;
			}
			else
			{
				ret=FAIL;
			}
		}
		else if(status == STAT_TIMEOUT)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Timeout while connecting to [%s]",
					session.peername);
			snmp_sess_perror("snmpget", ss);
			ret = NETWORK_ERROR;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error [%d]",
					status);
			snmp_sess_perror("snmpget", ss);
			ret=FAIL;
		}
	}

	if (response)
	{
		snmp_free_pdu(response);
	}
	snmp_close(ss);

	SOCK_CLEANUP;
	return ret;
}
#endif

int	get_value_SIMPLE(double *result,char *result_str,DB_ITEM *item)
{
	char	*e,*t;
	char	c[MAX_STRING_LEN];
	char	s[MAX_STRING_LEN];
	int	ret = SUCCEED;

	/* The code is ugly. I would rewrite it. Alexei.	*/
	/* Assumption: host name does not contain '_perf'	*/
	if(NULL == strstr(item->key,"_perf"))
	{
		if(item->useip==1)
		{
			snprintf(c,sizeof(c)-1,"check_service[%s,%s]",item->key,item->ip);
		}
		else
		{
			snprintf(c,sizeof(c)-1,"check_service[%s,%s]",item->key,item->host);
		}
	}
	else
	{
		strscpy(s,item->key);
		t=strstr(s,"_perf");
		t[0]=0;
		
		if(item->useip==1)
		{
			snprintf(c,sizeof(c)-1,"check_service_perf[%s,%s]",s,item->ip);
		}
		else
		{
			snprintf(c,sizeof(c)-1,"check_service_perf[%s,%s]",s,item->host);
		}
	}


	process(c,result_str);

	if(strcmp(result_str,"ZBX_NOTSUPPORTED\n") == 0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Simple check [%s] is not supported", c);
		ret = NOTSUPPORTED;
	}
	else
	{
		*result=strtod(result_str,&e);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "SIMPLE [%s] [%s] [%f] RET [%d]", c, result_str, *result, ret);
	return ret;
}

int	get_value_INTERNAL(double *result,char *result_str,DB_ITEM *item)
{
	if(strcmp(item->key,"zabbix[triggers]")==0)
	{
		*result=DBget_triggers_count();
	}
	else if(strcmp(item->key,"zabbix[items]")==0)
	{
		*result=DBget_items_count();
	}
	else if(strcmp(item->key,"zabbix[items_unsupported]")==0)
	{
		*result=DBget_items_unsupported_count();
	}
	else if(strcmp(item->key,"zabbix[history]")==0)
	{
		*result=DBget_history_count();
	}
	else if(strcmp(item->key,"zabbix[trends]")==0)
	{
		*result=DBget_trends_count();
	}
	else if(strcmp(item->key,"zabbix[queue]")==0)
	{
		*result=DBget_queue_count();
	}
	else
	{
		return NOTSUPPORTED;
	}

	snprintf(result_str,MAX_STRING_LEN-1,"%f",*result);

	zabbix_log( LOG_LEVEL_DEBUG, "INTERNAL [%s] [%f]", result_str, *result);
	return SUCCEED;
}

int	get_value_zabbix(double *result,char *result_str,DB_ITEM *item)
{
	int	s;
	int	len;
	static	char	c[MAX_STRING_LEN];
	char	*e;

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	struct linger ling;

	zabbix_log( LOG_LEVEL_DEBUG, "%10s%25s", item->host, item->key );

	servaddr_in.sin_family=AF_INET;
	if(item->useip==1)
	{
		hp=gethostbyname(item->ip);
	}
	else
	{
		hp=gethostbyname(item->host);
	}

	if(hp==NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed" );
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(item->port);

	s=socket(AF_INET,SOCK_STREAM,0);

	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
		ling.l_linger=0;
		if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}
	if(s == -1)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot create socket [%s]",
				strerror(errno));
		return	FAIL;
	}
 
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while connecting to [%s]",item->host );
				break;
			case EHOSTUNREACH:
				zabbix_log( LOG_LEVEL_WARNING, "No route to host [%s]",item->host );
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	NETWORK_ERROR;
	}

	snprintf(c,sizeof(c)-1,"%s\n",item->key);
	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);
	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending data to [%s]",item->host );
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	FAIL;
	} 

	memset(c,0,MAX_STRING_LEN);
	len=read(s,c,MAX_STRING_LEN);
	if(len == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s]",item->host );
					break;
			case	ECONNRESET:
					zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer. Host [%s] Parameter [%s]",item->host, item->key );
					close(s);
					return	NETWORK_ERROR;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	FAIL;
	}

	if( close(s)!=0 )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}
	zabbix_log(LOG_LEVEL_DEBUG, "Got string:[%d] [%s]", len, c);
	if(len>0)
	{
		c[len-1]=0;
	}

	*result=strtod(c,&e);

	/* The section should be improved */
	if( (*result==0) && (c==e) && (item->value_type==0) && (strcmp(c,"ZBX_NOTSUPPORTED") != 0) && (strcmp(c,"ZBX_ERROR") != 0) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got empty string from [%s]. Parameter [%s]", item->host, item->key);
		zabbix_log( LOG_LEVEL_WARNING, "Assuming that agent dropped connection because of access permissions");
		return	NETWORK_ERROR;
	}

	/* Should be deleted in Zabbix 1.0 stable */
	if( cmp_double(*result,NOTSUPPORTED) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "NOTSUPPORTED1 [%s]", c );
		return NOTSUPPORTED;
	}
	if( strcmp(c,"ZBX_NOTSUPPORTED") == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "NOTSUPPORTED2 [%s]", c );
		return NOTSUPPORTED;
	}
	if( strcmp(c,"ZBX_ERROR") == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "AGENT_ERROR [%s]", c );
		return AGENT_ERROR;
	}

	strcpy(result_str,c);

	zabbix_log(LOG_LEVEL_DEBUG, "RESULT_STR [%s]", c );

	return SUCCEED;
}

int	get_value(double *result,char *result_str,DB_ITEM *item)
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
		res=get_value_zabbix(result,result_str,item);
	}
	else if(item->type == ITEM_TYPE_SNMPv1)
	{
#ifdef HAVE_SNMP
		res=get_value_SNMP(SNMP_VERSION_1,result,result_str,item);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was no compiled in");
		res=NOTSUPPORTED;
#endif
	}
	else if(item->type == ITEM_TYPE_SNMPv2c)
	{
#ifdef HAVE_SNMP
		res=get_value_SNMP(SNMP_VERSION_2c,result,result_str,item);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was no compiled in");
		res=NOTSUPPORTED;
#endif
	}
	else if(item->type == ITEM_TYPE_SIMPLE)
	{
		res=get_value_SIMPLE(result,result_str,item);
	}
	else if(item->type == ITEM_TYPE_INTERNAL)
	{
		res=get_value_INTERNAL(result,result_str,item);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Not supported item type:%d",item->type);
		res=NOTSUPPORTED;
	}
	alarm(0);
	return res;
}

int get_minnextcheck(int now)
{
	char		sql[MAX_STRING_LEN];

	DB_RESULT	*result;

	int		res;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */ 
#ifdef TESTTEST
	snprintf(sql,sizeof(sql)-1,"select count(*),min(nextcheck) from items i,hosts h where (h.status=0 or (h.status=2 and h.disable_until<%d)) and h.hostid=i.hostid and i.status=0 and i.type not in (%d) and h.hostid%%%d=%d and i.key_<>'%s'", now, ITEM_TYPE_TRAPPER, CONFIG_SUCKERD_FORKS-4,sucker_num-4,SERVER_STATUS_KEY);
#else
	snprintf(sql,sizeof(sql)-1,"select count(*),min(nextcheck) from items i,hosts h where (h.status=%d or (h.status=%d and h.disable_until<%d)) and h.hostid=i.hostid and i.status=%d and i.type not in (%d) and i.itemid%%%d=%d and i.key_<>'%s' and i.key_<>'%s' and i.key_<>'%s'", HOST_STATUS_MONITORED, HOST_STATUS_UNREACHABLE, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, CONFIG_SUCKERD_FORKS-4,sucker_num-4,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY);
#endif
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

/* Update special host's item - "status" */
void update_key_status(int hostid,int host_status)
{
	char		sql[MAX_STRING_LEN];
	char		value_str[MAX_STRING_LEN];
	char		*s;

	DB_ITEM		item;
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_key_status()");

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,i.status from items i,hosts h where h.hostid=i.hostid and h.hostid=%d and i.key_='%s'", hostid,SERVER_STATUS_KEY);
	result = DBselect(sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
	}
	else
	{
		item.itemid=atoi(DBget_field(result,0,0));
		item.key=DBget_field(result,0,1);
		item.host=DBget_field(result,0,2);
		item.port=atoi(DBget_field(result,0,3));
		item.delay=atoi(DBget_field(result,0,4));
		item.description=DBget_field(result,0,5);
		item.nextcheck=atoi(DBget_field(result,0,6));
		item.type=atoi(DBget_field(result,0,7));
		item.snmp_community=DBget_field(result,0,8);
		item.snmp_oid=DBget_field(result,0,9);
		item.useip=atoi(DBget_field(result,0,10));
		item.ip=DBget_field(result,0,11);
		item.history=atoi(DBget_field(result,0,12));
		s=DBget_field(result,0,13);
		if(s==NULL)
		{
			item.lastvalue_null=1;
		}
		else
		{
			item.lastvalue_null=0;
			item.lastvalue_str=s;
			item.lastvalue=atof(s);
		}
		s=DBget_field(result,0,14);
		if(s==NULL)
		{
			item.prevvalue_null=1;
		}
		else
		{
			item.prevvalue_null=0;
			item.prevvalue_str=s;
			item.prevvalue=atof(s);
		}
		item.hostid=atoi(DBget_field(result,0,15));
		item.value_type=atoi(DBget_field(result,0,17));
		item.delta=atoi(DBget_field(result,0,18));
	
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

int get_values(void)
{
	double		value;
	char		value_str[MAX_STRING_LEN];
	char		sql[MAX_STRING_LEN];
 
	DB_RESULT	*result;

	int		i;
	int		now;
	int		res;
	DB_ITEM		item;
	char		*s;

	int	host_status;
	int	network_errors;

	now = time(NULL);

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock from items i,hosts h where i.nextcheck<=%d and i.status=%d and i.type not in (%d) and (h.status=%d or (h.status=%d and h.disable_until<=%d)) and h.hostid=i.hostid and i.itemid%%%d=%d and i.key_<>'%s' and i.key_<>'%s' and i.key_<>'%s' order by i.nextcheck", now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, HOST_STATUS_UNREACHABLE, now, CONFIG_SUCKERD_FORKS-4,sucker_num-4,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.key=DBget_field(result,i,1);
		item.host=DBget_field(result,i,2);
		item.port=atoi(DBget_field(result,i,3));
		item.delay=atoi(DBget_field(result,i,4));
		item.description=DBget_field(result,i,5);
		item.nextcheck=atoi(DBget_field(result,i,6));
		item.type=atoi(DBget_field(result,i,7));
		item.snmp_community=DBget_field(result,i,8);
		item.snmp_oid=DBget_field(result,i,9);
		item.useip=atoi(DBget_field(result,i,10));
		item.ip=DBget_field(result,i,11);
		item.history=atoi(DBget_field(result,i,12));
		s=DBget_field(result,i,13);
		if(s==NULL)
		{
			item.lastvalue_null=1;
		}
		else
		{
			item.lastvalue_null=0;
			item.lastvalue_str=s;
			item.lastvalue=atof(s);
		}
		s=DBget_field(result,i,14);
		if(s==NULL)
		{
			item.prevvalue_null=1;
		}
		else
		{
			item.prevvalue_null=0;
			item.prevvalue_str=s;
			item.prevvalue=atof(s);
		}
		item.hostid=atoi(DBget_field(result,i,15));
		host_status=atoi(DBget_field(result,i,16));
		item.value_type=atoi(DBget_field(result,i,17));

		network_errors=atoi(DBget_field(result,i,18));
		item.snmp_port=atoi(DBget_field(result,i,19));
		item.delta=atoi(DBget_field(result,i,20));

		s=DBget_field(result,i,21);
		if(s==NULL)
		{
			item.prevorgvalue_null=1;
		}
		else
		{
			item.prevorgvalue_null=0;
			item.prevorgvalue=atof(s);
		}
		s=DBget_field(result,i,22);
		if(s==NULL)
		{
			item.lastclock=0;
		}
		else
		{
			item.lastclock=atoi(s);
		}


		res = get_value(&value,value_str,&item);
		zabbix_log( LOG_LEVEL_DEBUG, "GOT VALUE [%s]", value_str );
		
		if(res == SUCCEED )
		{
			process_new_value(&item,value_str);

			if(network_errors>0)
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=0 where hostid=%d and network_errors>0", item.hostid);
				DBexecute(sql);
			}

			if(HOST_STATUS_UNREACHABLE == host_status)
			{
				host_status=HOST_STATUS_MONITORED;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				DBupdate_host_status(item.hostid,HOST_STATUS_MONITORED,now);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);	

				break;
			}
		}
		else if(res == NOTSUPPORTED)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			DBupdate_item_status_to_notsupported(item.itemid);
			if(HOST_STATUS_UNREACHABLE == host_status)
			{
				host_status=HOST_STATUS_MONITORED;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				DBupdate_host_status(item.hostid,HOST_STATUS_MONITORED,now);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);	

				break;
			}
		}
		else if(res == NETWORK_ERROR)
		{
			network_errors++;
			if(network_errors>=3)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
				DBupdate_host_status(item.hostid,HOST_STATUS_UNREACHABLE,now);
				update_key_status(item.hostid,HOST_STATUS_UNREACHABLE);	

				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=3 where hostid=%d", item.hostid);
				DBexecute(sql);
			}
			else
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=%d where hostid=%d", network_errors, item.hostid);
				DBexecute(sql);
			}

			break;
		}
/* Possibly, other logic required? */
		else if(res == AGENT_ERROR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed (ZBX_ERROR)", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");

			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");
		}

		if(res ==  SUCCEED)
		{
		        update_triggers(item.itemid);
		}

	}

	DBfree_result(result);
	return SUCCEED;
}

int main_nodata_loop()
{
	char	sql[MAX_STRING_LEN];
	int	i,now;

	int	itemid,functionid;
	char	*parameter;

	DB_RESULT	*result;

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("updating nodata() functions");
#endif
		DBconnect();

		now=time(NULL);
#ifdef HAVE_PGSQL
		snprintf(sql,sizeof(sql)-1,"select distinct f.itemid,f.functionid,f.parameter from functions f, items i,hosts h where h.hostid=i.hostid and (h.status=%d or (h.status=%d and h.disable_until<%d)) and i.itemid=f.itemid and f.function='nodata' and i.lastclock+f.parameter::text::integer<=%d and i.status=%d and i.type=%d and f.lastvalue<>1", HOST_STATUS_MONITORED, HOST_STATUS_UNREACHABLE, now, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER);
#else
		snprintf(sql,sizeof(sql)-1,"select distinct f.itemid,f.functionid,f.parameter from functions f, items i,hosts h where h.hostid=i.hostid and (h.status=%d or (h.status=%d and h.disable_until<%d)) and i.itemid=f.itemid and f.function='nodata' and i.lastclock+f.parameter<=%d and i.status=%d and i.type=%d and f.lastvalue<>1", HOST_STATUS_MONITORED, HOST_STATUS_UNREACHABLE, now, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER);
#endif
		result = DBselect(sql);

		for(i=0;i<DBnum_rows(result);i++)
		{
			itemid=atoi(DBget_field(result,i,0));
			functionid=atoi(DBget_field(result,i,1));
			parameter=DBget_field(result,i,2);

			snprintf(sql,sizeof(sql)-1,"update functions set lastvalue='1' where itemid=%d and function='nodata' and parameter='%s'" , itemid, parameter );
			DBexecute(sql);

			update_triggers(itemid);
		}

		DBfree_result(result);
		DBclose();

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sleeping for 30 sec");
#endif
		sleep(30);
	}
}

int main_sucker_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	DBconnect();
	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sucker [getting values]");
#endif
		now=time(NULL);
		get_values();

		zabbix_log( LOG_LEVEL_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck(now);
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

int main(int argc, char **argv)
{
	int	i;
	pid_t	pid;

	struct	sigaction phan;

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

	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_suckerd...");

/* Need to set trigger status to UNKNOWN since last run */
	DBconnect();
	DBupdate_triggers_status_after_restart();

/*#define CALC_TREND*/

#ifdef CALC_TREND
	trend();
	return 0;
#endif
	DBclose();
	pids=calloc(CONFIG_SUCKERD_FORKS-1,sizeof(pid_t));

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

#ifdef HAVE_SNMP
	init_snmp("zabbix_suckerd");
#endif

	if( sucker_num == 0)
	{
		sigaction(SIGCHLD, &phan, NULL);
/* First instance of zabbix_suckerd performs housekeeping procedures */
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [Housekeeper]",sucker_num);
		main_housekeeper_loop();
	}
	else if(sucker_num == 1)
	{
/* Second instance of zabbix_suckerd sends alerts to users */
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [Alerter]",sucker_num);
		main_alerter_loop();
	}
	else if(sucker_num == 2)
	{
/* Third instance of zabbix_suckerd periodically re-calculates 'nodata' functions */
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [nodata() calculator]",sucker_num);
		main_nodata_loop();
	}
	else if(sucker_num == 3)
	{
/* Fourth instance of zabbix_suckerd periodically pings hosts */
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [ICMP pinger]",sucker_num);
		main_pinger_loop();
	}
	else
	{
#ifdef HAVE_SNMP
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [Sucker. SNMP:ON]",sucker_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started [Sucker. SNMP:OFF]",sucker_num);
#endif
		main_sucker_loop();
	}

	return SUCCEED;
}
