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

/* Required for SNMP support*/
#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	#include <ucd-snmp/ucd-snmp-config.h>
	#include <ucd-snmp/ucd-snmp-includes.h>
	#include <ucd-snmp/system.h>
#endif

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "functions.h"
#include "expression.h"
#include "log.h"

static	pid_t	*pids=NULL;

static	int	sucker_num=0;

static	int	CONFIG_SUCKERD_FORKS		=SUCKER_FORKS;
static	int	CONFIG_NOTIMEWAIT		=0;
static	int	CONFIG_TIMEOUT			=SUCKER_TIMEOUT;
static	int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
static	int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
static	int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
static	char	*CONFIG_PID_FILE		= NULL;
static	char	*CONFIG_LOG_FILE		= NULL;
static	char	*CONFIG_DBNAME			= NULL;
static	char	*CONFIG_DBUSER			= NULL;
static	char	*CONFIG_DBPASSWORD		= NULL;
static	char	*CONFIG_DBSOCKET		= NULL;

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
	else if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		zabbix_log( LOG_LEVEL_ERR, "Got QUIT or INT or TERM signal. Exiting..." );
		uninit();
		exit( FAIL );
	}
	else if( SIGCHLD == sig )
	{
		zabbix_log( LOG_LEVEL_ERR, "One child died. Exiting ..." );
		uninit();
		exit( FAIL );
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
			fprintf(stderr,"Cannot setgid or setuid to zabbix");
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix");
			exit(FAIL);
		}
#endif

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
		close(i);
	}
}

void	create_pid_file(void)
{
	FILE	*f;

/* Check if PID file already exists */
	f = fopen(CONFIG_PID_FILE, "r");
	if(f != NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists. Is zabbix_suckerd already running ?",
			CONFIG_PID_FILE);
		if(fclose(f) != 0)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
			CONFIG_PID_FILE,strerror(errno));
		}
		exit(-1);
	}

	f = fopen(CONFIG_PID_FILE, "w");

	if( f == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]",
			CONFIG_PID_FILE, strerror(errno));
		uninit();
		exit(-1);
	}

	fprintf(f,"%d",getpid());
	if(fclose(f) != 0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
		CONFIG_PID_FILE, strerror(errno));
	}
}

void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"StartSuckers",&CONFIG_SUCKERD_FORKS,0,TYPE_INT,PARM_OPT,2,255},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
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
}

#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
int	get_value_SNMPv1(double *result,char *result_str,DB_ITEM *item)
{
	struct snmp_session session, *ss;
	struct snmp_pdu *pdu;
	struct snmp_pdu *response;

	oid anOID[MAX_OID_LEN];
	size_t anOID_len = MAX_OID_LEN;

	struct variable_list *vars;
	int status;

	int ret=SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1()");

	snmp_sess_init( &session );
	session.version = SNMP_VERSION_1;
	if(item->useip == 1)
	{
		session.peername = item->ip;
	}
	else
	{
		session.peername = item->host;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Peername [%s]", session.peername);
	session.community = item->snmp_community;
	zabbix_log( LOG_LEVEL_DEBUG, "Community [%s]", session.community);
	zabbix_log( LOG_LEVEL_DEBUG, "OID [%s]", item->snmp_oid);
	session.community_len = strlen(session.community);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 0.1");

	SOCK_STARTUP;
	ss = snmp_open(&session);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 0.2");

	pdu = snmp_pdu_create(SNMP_MSG_GET);
	read_objid(item->snmp_oid, anOID, &anOID_len);

#if OTHER_METHODS
	get_node("sysDescr.0", anOID, &anOID_len);
	read_objid(".1.3.6.1.2.1.1.1.0", anOID, &anOID_len);
	read_objid("system.sysDescr.0", anOID, &anOID_len);
#endif

	snmp_add_null_var(pdu, anOID, anOID_len);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 0.3");
  
	status = snmp_synch_response(ss, pdu, &response);
	zabbix_log( LOG_LEVEL_DEBUG, "Status send [%d]", status);
	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 0.4");

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 1");

	if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
	{

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_SNMPv1() 2");
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
				(vars->type == ASN_GAUGE)
			)
			{
				*result=*vars->val.integer;
				sprintf(result_str,"%d",*vars->val.integer);
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

int	get_value_zabbix(double *result,char *result_str,DB_ITEM *item)
{
	int	s;
	int	i;
	char	c[MAX_STRING_LEN+1];
	char	*e;

	struct hostent *hp;

	struct sockaddr_in myaddr_in;
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
	if(s==0)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot create socket [%s]",
				strerror(errno));
		return	FAIL;
	}
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

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

	sprintf(c,"%s\n",item->key);
	if( sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
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
	i=sizeof(struct sockaddr_in);

	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s]",item->host );
					break;
			case	ECONNRESET:
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
	c[i-1]=0;

	zabbix_log(LOG_LEVEL_DEBUG, "Got string:%10s", c );
	*result=strtod(c,&e);

	if( (*result==0) && (c==e) && (item->value_type==0) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got empty string from [%s]. Parameter [%s]",item->host, item->key);
		zabbix_log( LOG_LEVEL_WARNING, "Assuming that agent dropped connection because of access permissions");
		return	NETWORK_ERROR;
	}
	if( *result<0 )
	{
		if( cmp_double(*result,NOTSUPPORTED) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "NOTSUPPORTED1 [%s]", c );
			return NOTSUPPORTED;
		}
	}

	strncpy(result_str,c,MAX_STRING_LEN);

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
	else if(item->type == ITEM_TYPE_SNMP)
	{
#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
		res=get_value_SNMPv1(result,result_str,item);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was no compiled in");
#endif
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
	char		sql[MAX_STRING_LEN+1];

	DB_RESULT	*result;

	int		res;
	int		count;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */ 
	sprintf(sql,"select count(*),min(nextcheck) from items i,hosts h where i.status=0 and (h.status=0 or (h.status=2 and h.disable_until<%d)) and h.hostid=i.hostid and i.status=0 and i.itemid%%%d=%d",now,CONFIG_SUCKERD_FORKS-1,sucker_num-1);
	result = DBselect(sql);

	if( DBis_empty(result) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		DBfree_result(result);
		return FAIL; 
	}

	count = atoi(DBget_field(result,0,0));

	if( count == 0 )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No records for get_minnextcheck");
		DBfree_result(result);
		return	FAIL;
	}

	res = atoi(DBget_field(result,0,1));

	DBfree_result(result);
	return	res;
}

int get_values(void)
{
	double		value;
	char		value_str[MAX_STRING_LEN+1];
	char		sql[MAX_STRING_LEN+1];
 
	DB_RESULT	*result;

	int		i;
	int		now;
	int		res;
	DB_ITEM		item;
	char		*s;

	int	host_status;

	now = time(NULL);

	sprintf(sql,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type from items i,hosts h where i.nextcheck<=%d and i.status=0 and (h.status=0 or (h.status=2 and h.disable_until<=%d)) and h.hostid=i.hostid and i.itemid%%%d=%d order by i.nextcheck", now, now, CONFIG_SUCKERD_FORKS-1,sucker_num-1);
	result = DBselect(sql);

	if( DBis_empty(result) == SUCCEED)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

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

		res = get_value(&value,value_str,&item);
		zabbix_log( LOG_LEVEL_DEBUG, "GOT VALUE [%s]", value_str );
		
		if(res == SUCCEED )
		{
			process_new_value(&item,value_str);
			if(HOST_STATUS_UNREACHABLE == host_status)
			{
				host_status=HOST_STATUS_MONITORED;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				DBupdate_host_status(item.hostid,HOST_STATUS_MONITORED);

				break;
			}
		}
		else if(res == NOTSUPPORTED)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			DBupdate_item_status_to_unsupported(item.itemid);
			if(HOST_STATUS_UNREACHABLE == host_status)
			{
				host_status=HOST_STATUS_MONITORED;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				DBupdate_host_status(item.hostid,HOST_STATUS_MONITORED);

				break;
			}
		}
		else if(res == NETWORK_ERROR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
			DBupdate_host_status(item.hostid,HOST_STATUS_UNREACHABLE);

			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");
		}
	}

	update_triggers( CONFIG_SUCKERD_FORKS, 0, sucker_num, now );

	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_history(int now)
{
	char		sql[MAX_STRING_LEN+1];
	DB_ITEM		item;

	DB_RESULT	*result;

	int		i;

	sprintf(sql,"select i.itemid,i.lastdelete,i.history from items i where i.lastdelete<=%d", now);
	result = DBselect(sql);

	if(DBis_empty(result) == SUCCEED)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to delete.");
		DBfree_result(result);
		return SUCCEED; 
	}

	for(i=0;i<DBnum_rows(result);i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.lastdelete=atoi(DBget_field(result,i,1));
		item.history=atoi(DBget_field(result,i,2));

/* To be rewritten. Only one delete depending on item.value_type */
		sprintf	(sql,"delete from history where itemid=%d and clock<%d",item.itemid,now-item.history);
		DBexecute(sql);
		sprintf	(sql,"delete from history_str where itemid=%d and clock<%d",item.itemid,now-item.history);
		DBexecute(sql);
	
		sprintf(sql,"update items set lastdelete=%d where itemid=%d",now,item.itemid);
		DBexecute(sql);
	}
	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_alerts(int now)
{
	char		sql[MAX_STRING_LEN+1];
	int		alert_history;
	DB_RESULT	*result;

	sprintf(sql,"select alert_history from config");
	result = DBselect(sql);

	alert_history=atoi(DBget_field(result,0,0));

	sprintf	(sql,"delete from alerts where clock<%d",now-24*3600*alert_history);
	DBexecute(sql);

	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_alarms(int now)
{
	char		sql[MAX_STRING_LEN+1];
	int		alarm_history;
	DB_RESULT	*result;

	sprintf(sql,"select alarm_history from config");
	result = DBselect(sql);

	alarm_history=atoi(DBget_field(result,0,0));

	sprintf	(sql,"delete from alarms where clock<%d",now-24*3600*alarm_history);
	DBexecute(sql);
	
	DBfree_result(result);
	return SUCCEED;
}

int main_housekeeping_loop()
{
	int	now;

	if(CONFIG_DISABLE_HOUSEKEEPING == 1)
	{
		for(;;)
		{
/* Do nothing */
#ifdef HAVE_FUNCTION_SETPROCTITLE
			setproctitle("do nothing");
#endif
			sleep(3600);
		}
	}

	now = time(NULL);

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif
		DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old values]");
#endif

		housekeeping_history(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alarms]");
#endif

		housekeeping_alarms(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alerts]");
#endif

		housekeeping_alerts(now);
		zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d hours", CONFIG_HOUSEKEEPING_FREQUENCY);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [sleeping for %d hour(s)]", CONFIG_HOUSEKEEPING_FREQUENCY);
#endif
		DBclose();
		sleep(3600*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}

int main_sucker_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);
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
	sigaction(SIGCHLD, &phan, NULL);

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

	create_pid_file();

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

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_suckerd #%d started",sucker_num);

#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	init_snmp("zabbix_suckerd");
#endif

	if( sucker_num == 0)
	{
/* First instance of zabbix_suckerd does housekeeping procedures */
		main_housekeeping_loop();
	}
	else
	{
		main_sucker_loop();
	}

	return SUCCEED;
}
