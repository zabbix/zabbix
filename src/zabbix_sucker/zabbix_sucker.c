#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include <syslog.h>

#include "common.h"
#include "db.h"
#include "functions.h"
#include "expression.h"

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
		syslog( LOG_WARNING, "Timeout while executing operation." );
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		syslog( LOG_ERR, "\nGot QUIT or INT or TERM signal. Exiting..." );
		exit( FAIL );
	}
 
	return;
}

void	send_mail(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody)
{
	int s;
	int i,e;
	char *c;
	struct hostent *hp;
//	struct servent *sp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	syslog( LOG_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	if(hp==NULL)
	{
		perror("Cannot get IP for mailserver.");
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(25);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0) perror("socket");

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if(connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in))==-1) perror("Connect");

	c=(char *)malloc(1024);
	if(c==NULL) perror("Cannot allocate memory.");
	sprintf(c,"HELO %s\n",smtp_helo);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending HELO to mailserver.");
	
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving data answer on HELO reqest.");
	
	sprintf(c,"MAIL FROM: %s\n",smtp_email);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending MAIL FROM to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on MAIL FROM request.");
	
	sprintf(c,"RCPT TO: <%s>\n",mailto);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending RCPT TO to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on RCPT TO request.");
	
	sprintf(c,"DATA\nSubject: %s\n",mailsubject);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending DATA to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on DATA request.");
	sprintf(c,"%s\n",mailbody);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending MailBody to mailserver.");
	sprintf(c,".\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending . to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	
	sprintf(c,"\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending \\n to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on \\n request.");
	
	sprintf(c,"QUIT\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending QUIT to mailserver.");
	
	close(s);
	free(c); 
}

void	send_to_user(int actionid,int userid,char *smtp_server,char *smtp_helo,char *smtp_email,char *subject,char *message)
{
	MEDIA media;
	char c[1024];
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(c,"select type,sendto,active from media where userid=%d",userid);
	result = DBselect(c);

	rows=DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		media.active=atoi(DBget_field(result,i,2));
		syslog( LOG_DEBUG, "ACTIVE=%d or %s\n", media.active, DBget_field(result,i,2) );
		if(media.active!=1) // If media is enabled (active)
		{
			media.type=DBget_field(result,i,0);
			media.sendto=DBget_field(result,i,1);

			if(strcmp(media.type,"EMAIL")==0)
			{
				syslog( LOG_DEBUG, "Email sending to %s %s Subject:%s Message:%s to %d\n", media.type, media.sendto, subject, message, userid );
				send_mail(smtp_server,smtp_helo,smtp_email,media.sendto,subject,message);
				now = time(NULL);
				sprintf(c,"insert into alerts (alertid,actionid,clock,type,sendto,subject,message) values (NULL,%d,%d,'%s','%s','%s','%s');",actionid,now,media.type,media.sendto,subject,message);
				DBexecute(c);
			} 
			else
			{
				syslog( LOG_WARNING, "Type %s is not supported yet", media.type );
			}
		}
	}
	DBfree_result(result);
}

void	apply_actions(int triggerid,int good)
{
	DB_RESULT *result;
	
	ACTION action;

	char c[1024];

	char	smtp_server[256],
		smtp_helo[256],
		smtp_email[256];

	int	i,rows;
	int	now;

	syslog( LOG_DEBUG, "Applying actions");

	/* Get smtp_server and smtp_helo from config */
	sprintf(c,"select smtp_server,smtp_helo,smtp_email from config");
	result = DBselect(c);

	strcpy(smtp_server,DBget_field(result,0,0));
	strcpy(smtp_helo,DBget_field(result,0,1));
	strcpy(smtp_email,DBget_field(result,0,2));

	DBfree_result(result);

	now = time(NULL);

	sprintf(c,"select actionid,userid,delay,subject,message from actions where triggerid=%d and good=%d and nextcheck<=%d",triggerid,good,now);
	result = DBselect(c);

	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		syslog( LOG_DEBUG, "Fetched:%s %s %s %s %s\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2),DBget_field(result,i,3),DBget_field(result,i,4));

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		action.subject=DBget_field(result,i,3);
		action.message=DBget_field(result,i,4);

//		substitute_functions(&*action.message);
//		substitute_functions(&*action.subject); 

		send_to_user(action.actionid,action.userid,smtp_server,smtp_helo,smtp_email,action.subject,action.message);
		now = time(NULL);
		sprintf(c,"update actions set nextcheck=%d+%d where actionid=%d",now,action.delay,action.actionid);
		DBexecute(c);
	}
	syslog( LOG_DEBUG, "Actions applied for trigger %d %d\n", triggerid, good );
	DBfree_result(result);
}

void	update_triggers(int itemid)
{
	char c[1024];
	char exp[8192];
	int b;
	TRIGGER trigger;
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(c,"select t.triggerid,t.expression,t.istrue from triggers t,functions f where t.istrue!=2 and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.istrue",itemid);

	result = DBselect(c);

	rows = DBnum_rows(result);

	if(rows == 0)
	{
		syslog( LOG_DEBUG, "Zero, so returning..." );

		DBfree_result(result);
		return;
	}
	for(i=0;i<rows;i++)
	{
		syslog( LOG_DEBUG, "Fetched: TrId[%s] Exp[%s] IsTrue[%s]\n", DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2));
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.istrue=atoi(DBget_field(result,i,2));
		strcpy(exp, trigger.expression);
		if( evaluate_expression(&b, exp) != 0 )
		{
			syslog( LOG_WARNING, "Expression %s - SUX.",trigger.expression);
			continue;
		}

		now = time(NULL);
		sprintf(c,"update triggers set lastcheck=%d where triggerid=%d",now,trigger.triggerid);
		DBexecute(c);

		if((b==1)&&(trigger.istrue!=1))
		{
			now = time(NULL);
			sprintf(c,"update triggers set IsTrue=1, lastchange=%d where triggerid=%d",now,trigger.triggerid);
			DBexecute(c);

			now = time(NULL);
			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,%d,1)",trigger.triggerid,now);
			DBexecute(c);

			apply_actions(trigger.triggerid,1);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
			DBexecute(c);
		}

		if((b==0)&&(trigger.istrue!=0))
		{
			now = time(NULL);
			sprintf(c,"update triggers set IsTrue=0, lastchange=%d where triggerid=%d",now,trigger.triggerid);
			DBexecute(c);

			now = time(NULL);
			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,%d,0)",trigger.triggerid,now);
			DBexecute(c);

			apply_actions(trigger.triggerid,0);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
			DBexecute(c);
		}
	}
	DBfree_result(result);
}

void	daemon_init(void)
{
	int	i;
	pid_t	pid;

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

int	get_value(double *result,char *key,char *host,int port)
{
	int	s;
	int	i;
	char	c[1024];
	char	*e;
	void	*sigfunc;

	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	syslog( LOG_DEBUG, "%10s%25s", host, key );

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(host);

	if(hp==NULL)
	{
		syslog( LOG_WARNING, "Problem with gethostbyname" );
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		syslog( LOG_WARNING, "Problem with socket" );
		return	FAIL;
	}
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	sigfunc = signal( SIGALRM, signal_handler );

	alarm(SUCKER_TIMEOUT);

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog( LOG_WARNING, "Problem with connect" );
		close(s);
		return	FAIL;
	}
	alarm(0);
	signal( SIGALRM, sigfunc );

	sprintf(c,"%s\n",key);
	if( sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog(LOG_WARNING, "Problem with sendto" );
		close(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);

	sigfunc = signal( SIGALRM, signal_handler );
	alarm(SUCKER_TIMEOUT);

	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1)
	{
		syslog( LOG_WARNING, "Problem with recvfrom [%d]",errno );
		close(s);
		return	FAIL;
	}
	alarm(0);
	signal( SIGALRM, sigfunc );
 
	if( close(s)!=0 )
	{
		syslog(LOG_WARNING, "Problem with close" );
	}
	c[i-1]=0;

	syslog(LOG_DEBUG, "Got string:%10s", c );
	*result=strtod(c,&e);

	if( (*result==0) && (c==e) )
	{
		return	FAIL;
	}
	if( *result<0 )
	{
		if( *result == NOTSUPPORTED)
		{
			return SUCCEED;
		}
		else
		{
			return	FAIL;
		}
	}
	return SUCCEED;
}

int get_minnextcheck(void)
{
	char		c[1024];

	DB_RESULT	*result;
	char		*field;

	int		res;

	sprintf(c,"select min(nextcheck) from items i,hosts h where i.status=0 and h.status=0 and h.hostid=i.hostid and i.status=0");
	result = DBselect(c);

	if(result==NULL)
	{
		syslog(LOG_DEBUG, "No items to update for minnextcheck.");
		DBfree_result(result);
		return FAIL; 
	}
	if(DBnum_rows(result)==0)
	{
		syslog( LOG_DEBUG, "No items to update for minnextcheck.");
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}

	res=atoi(field);
	DBfree_result(result);

	return	res;
}

int get_values(void)
{
	double		value;
	char		c[1024];
	ITEM		item;
 
	DB_RESULT	*result;

	int		i,rows;
	int		now;

	now = time(NULL);

	sprintf(c,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.history,i.lastdelete from items i,hosts h where i.nextcheck<=%d and i.status=0 and h.status=0 and h.hostid=i.hostid order by i.nextcheck", now);
	result = DBselect(c);

	if(result==NULL)
	{
		syslog( LOG_DEBUG, "No items to update.");
		DBfree_result(result);
		return SUCCEED; 
	}
	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.key=DBget_field(result,i,1);
		item.host=DBget_field(result,i,2);
		item.port=atoi(DBget_field(result,i,3));
		item.delay=atoi(DBget_field(result,i,4));
		item.description=DBget_field(result,i,5);
		item.history=atoi(DBget_field(result,i,6));
		item.lastdelete=atoi(DBget_field(result,i,7));

		if( get_value(&value,item.key,item.host,item.port) == SUCCEED )
		{
			if( value == NOTSUPPORTED)
			{
				sprintf(c,"update items set status=3 where itemid=%d",item.itemid);
				DBexecute(c);
			}
			else
			{
				now = time(NULL);
				sprintf(c,"insert into history (itemid,clock,value) values (%d,%d,%g)",item.itemid,now,value);
				DBexecute(c);

				sprintf(c,"update items set NextCheck=%d+%d,PrevValue=LastValue,LastValue=%f,LastClock=%d where ItemId=%d",now,item.delay,value,now,item.itemid);
				DBexecute(c);

				if( update_functions( item.itemid ) == FAIL)
				{
					syslog( LOG_WARNING, "Updating simple functions failed" );
				}

				update_triggers( item.itemid );
			}
		}
		else
		{
			syslog( LOG_WARNING, "Wrong value from host [HOST:%s KEY:%s VALUE:%f]", item.host, item.key, value );
			syslog( LOG_WARNING, "The value is not stored in database.");
		}

		if(item.lastdelete+3600<time(NULL))
		{
			now = time(NULL);
			sprintf	(c,"delete from history where ItemId=%d and Clock<%d-%d",item.itemid,now,item.history);
			DBexecute(c);
	
			now = time(NULL);
			sprintf(c,"update items set LastDelete=%d where ItemId=%d",now,item.itemid);
			DBexecute(c);
		}
	}
	DBfree_result(result);
	return SUCCEED;
}

int main_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	for(;;)
	{
		now=time(NULL);
		get_values();

		syslog( LOG_WARNING, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck();
		syslog( LOG_DEBUG, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

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
			syslog( LOG_DEBUG, "Sleeping for %d seconds", sleeptime );
			sleep( sleeptime );
		}
		else
		{
			syslog( LOG_DEBUG, "No sleeping" );
		}
	}
}

int main(int argc, char **argv)
{
	int 	ret;

	daemon_init();

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );


	openlog("zabbix_sucker",LOG_PID,LOG_USER);
//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	syslog( LOG_WARNING, "zabbix_sucker started");

	DBconnect();

	main_loop();

	return SUCCEED;
}
