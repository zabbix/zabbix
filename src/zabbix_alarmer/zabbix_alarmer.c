#include <stdio.h>
#include <stdlib.h>

#include <unistd.h>

#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>
#include <signal.h>
#include <time.h>

#include "common.h"
#include "debug.h"
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

void	SendMail(char *smtp_server,char *smtp_helo,char *smtp_email,char *MailTo,char *MailSubject,char *MailBody)
{
	int s;
	int i,e;
	char *c;
	struct hostent *hp;
//	struct servent *sp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	dbg_write( dbg_proginfo, "SENDING MAIL");

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
	
	sprintf(c,"RCPT TO: <%s>\n",MailTo);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending RCPT TO to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on RCPT TO request.");
	
	sprintf(c,"DATA\nSubject: %s\n",MailSubject);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e==-1) perror("Error sending DATA to mailserver.");
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1) perror("Error receiving answer on DATA request.");
	sprintf(c,"%s\n",MailBody);
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

void	SendToUser(int UserId,char *smtp_server,char *smtp_helo,char *smtp_email,char *Subject,char *Message)
{
	MEDIA Media;
	char c[1024];
	DB_RESULT *result;

	DB_ROW row;

	sprintf(c,"select type,sendto,active from media where userid=%d",UserId);
	DBexecute(c);

	result = DBget_result();

	while ( (row = DBfetch_row(result)) )
	{
		Media.Active=atoi(row[2]);
		dbg_write( dbg_proginfo, "ACTIVE=%d or %s\n", Media.Active, row[2] );
		if(Media.Active!=1) // If media is enabled (active)
		{
			Media.Type=row[0];
			Media.SendTo=row[1]; 

			if(strcmp(Media.Type,"EMAIL")==0)
			{
				dbg_write( dbg_proginfo, "Email sending to %s %s Subject:%s Message:%s to %d\n", Media.Type, Media.SendTo, Subject, Message, UserId );
				SendMail(smtp_server,smtp_helo,smtp_email,Media.SendTo,Subject,Message);
				sprintf(c,"insert into alerts (alertid,clock,type,sendto,subject,message) values (NULL,unix_timestamp(),'%s','%s','%s','%s');",Media.Type,Media.SendTo,Subject,Message);
				DBexecute(c);
			} 
			else
			{
				dbg_write( dbg_syserr, "Type %s is not supported yet", Media.Type );
			}
		}
	}
	DBfree_result(result);
}

void	ApplyActions(int TriggerId,int Good)
{
	DB_RESULT *result;
	
	DB_ROW row;

	ACTION Action;

	char c[1024];

	char	smtp_server[256],
		smtp_helo[256],
		smtp_email[256];

	dbg_write( dbg_syswarn, "Applying actions");

	/* Get smtp_server and smtp_helo from config */
	sprintf(c,"select smtp_server,smtp_helo,smtp_email from config");
	DBexecute(c);
	result = DBget_result();

	row = DBfetch_row(result);

	strcpy(smtp_server,row[0]);
	strcpy(smtp_helo,row[1]);
	strcpy(smtp_email,row[2]);

	DBfree_result(result);

	sprintf(c,"select actionid,userid,delay,subject,message from actions where triggerid=%d and good=%d and nextcheck<=unix_timestamp()",TriggerId,Good);
	DBexecute(c);

	result = DBget_result();

	while ( (row = DBfetch_row(result)) )
	{
		dbg_write( dbg_proginfo, "Fetched:%s %s %s %s %s\n",row[0],row[1],row[2],row[3],row[4]);

		Action.ActionId=atoi(row[0]);
		Action.UserId=atoi(row[1]);
		Action.Delay=atoi(row[2]);
		Action.Subject=row[3];
		Action.Message=row[4];

		SubstituteFunctions(&*Action.Message);
		SubstituteFunctions(&*Action.Subject); 

		SendToUser(Action.UserId,smtp_server,smtp_helo,smtp_email,Action.Subject,Action.Message);
		sprintf(c,"update actions set nextcheck=unix_timestamp()+%d where actionid=%d",Action.Delay,Action.ActionId);
		DBexecute(c);
	}
	dbg_write( dbg_proginfo, "Actions applied for trigger %d %d\n", TriggerId, Good );
	DBfree_result(result);
}

void	update_triggers(void)
{
	char c[1024];
	char exp[8192];
	int b;
	TRIGGER Trigger;
	DB_RESULT *result;
	DB_ROW row;

	sprintf(c,"select triggerid,expression,istrue from triggers where istrue!=2 order by triggerid");

	DBexecute(c);

	result = DBget_result();

	if(DBnum_rows(result)==0)
	{
		dbg_write( dbg_proginfo, "Zero, so returning..." );
		return;
	} 
	while ( (row = DBfetch_row(result)) )
	{
		dbg_write( dbg_proginfo, "Fetched: TrId[%s] Exp[%s] IsTrue[%s]\n", row[0], row[1], row[2] );
		Trigger.TriggerId=atoi(row[0]);
		Trigger.Expression=row[1];
		Trigger.IsTrue=atoi(row[2]);
		strcpy(exp, Trigger.Expression);
		if( EvaluateExpression(&b, exp) != 0 )
		{
			dbg_write( dbg_syswarn, "Expression %s - SUX.",Trigger.Expression);
			continue;
		}

		sprintf(c,"update triggers set lastcheck=unix_timestamp() where triggerid=%d",Trigger.TriggerId);

		DBexecute(c);


		if((b==1)&&(Trigger.IsTrue!=1))
		{
			sprintf(c,"update triggers set IsTrue=1, lastchange=unix_timestamp() where triggerid=%d",Trigger.TriggerId);
			DBexecute(c);

			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,unix_timestamp(),1)",Trigger.TriggerId);
			DBexecute(c);

			ApplyActions(Trigger.TriggerId,1);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=0",Trigger.TriggerId);
			DBexecute(c);
		}

		if((b==0)&&(Trigger.IsTrue!=0))
		{
			sprintf(c,"update triggers set IsTrue=0, lastchange=unix_timestamp() where triggerid=%d",Trigger.TriggerId);
			DBexecute(c);

			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,unix_timestamp(),0)",Trigger.TriggerId);
			DBexecute(c);

			ApplyActions(Trigger.TriggerId,0);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=1",Trigger.TriggerId);
			DBexecute(c);
		}
	}
	DBfree_result(result);
}

int	main()
{
	time_t now,diff;

	daemon_init();

	dbg_init( dbg_syswarn, "/var/tmp/zabbix_alarmer.log" );
//	dbg_init( dbg_proginfo, "/var/tmp/zabbix_alarmer.log" );

	DBconnect();

	for(;;)
	{
		now=time(NULL);
		update_triggers();
		diff=time(NULL)-now;
		dbg_write( dbg_proginfo, "Spent %d seconds while updating triggers", diff );
		if( diff<ALARMER_DELAY )
		{
			if( diff>=0 )
			{
				dbg_write( dbg_proginfo, "Sleeping for %d seconds", ALARMER_DELAY-diff );
				sleep(ALARMER_DELAY-diff);
			}
		}
	}
}
