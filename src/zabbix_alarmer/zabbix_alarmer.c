#include <stdio.h>
#include <stdlib.h>

#include <unistd.h>

#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>
#include <signal.h>
#include <time.h>

#include <syslog.h>

#include "common.h"
#include "expression.h"
#include "db.h"

void    daemon_init(void)
{
	int	i;
	pid_t	pid;
 
	if( (pid = fork()) != 0 )
	{
		exit(0);
	}
	setsid();
 
	signal( SIGHUP, SIG_IGN );
 
	if( (pid = fork()) !=0 )
	{
		exit(0);
	}
 
	chdir("/");
 
	umask(0);
 
	for(i=0;i<MAXFD;i++)
	{
		close(i);
	}
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

void	update_triggers(void)
{
	char c[1024];
	char exp[8192];
	int b;
	TRIGGER trigger;
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(c,"select triggerid,expression,istrue from triggers where istrue!=2 order by triggerid");

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

int	main()
{
	int	now,diff;
	int 	ret;

	daemon_init();

	openlog("zabbix_alarmer",LOG_PID,LOG_USER);
//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	syslog(LOG_WARNING, "zabbix_alarmer started");

	DBconnect();

	for(;;)
	{
		now=time(NULL);
		update_triggers();
		diff=time(NULL)-now;
		syslog( LOG_DEBUG, "Spent %d seconds while updating triggers", diff );
		if( diff<ALARMER_DELAY )
		{
			if( diff>=0 )
			{
				syslog( LOG_DEBUG, "Sleeping for %d seconds", ALARMER_DELAY-diff );
				sleep(ALARMER_DELAY-diff);
			}
		}
	}
}
