#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <syslog.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>
#include <syslog.h>

#include "common.h"
#include "db.h"

#include "functions.h"
#include "expression.h"

int	evaluate_LAST(float *last,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[256];
	char		*field;

	sprintf(c,"select lastvalue from items where itemid=%d and lastvalue is not null", itemid );

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}
	*last=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MIN(float *min,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[256];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select min(value) from history where clock>%d-%d and itemid=%d",now,parameter,itemid);
	syslog(LOG_WARNING, "SQL:%s", c );

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		syslog(LOG_NOTICE, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		syslog( LOG_NOTICE, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	*min=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MAX(float *max,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[256];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select max(value) from history where clock>%d-%d and itemid=%d",now,parameter,itemid);

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		syslog(LOG_NOTICE, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		syslog( LOG_NOTICE, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}	
	*max=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_PREV(float *prev,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	sprintf(c,"select prevvalue from items where itemid=%d and prevvalue is not null", itemid );

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		syslog(LOG_NOTICE, "Result for PREV is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		syslog(LOG_NOTICE, "Result for PREV is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	*prev=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_DIFF(float *diff,int itemid,int parameter)
{
	float	prev,last;
	float	tmp;

	if(evaluate_PREV(&prev,itemid,parameter) == FAIL)
	{
		*diff=0;
		return SUCCEED;
	}

	if(evaluate_LAST(&last,itemid,parameter) == FAIL)
	{
		*diff=0;
		return SUCCEED;
	}
	
	tmp=last-prev;

	if((tmp<0.000001)&&(tmp>-0.000001))
	{
		*diff=0;
	}
	else
	{
		*diff=1;
	}

	return SUCCEED;
}

int	evaluate_FUNCTION(float *value,int itemid,char *function,int parameter)
{
	int	ret  = SUCCEED;

	if(strcmp(function,"last")==0)
	{
		ret = evaluate_LAST(value,itemid,parameter);
	}
	else if(strcmp(function,"prev")==0)
	{
		ret = evaluate_PREV(value,itemid,parameter);
	}
	else if(strcmp(function,"min")==0)
	{
		ret = evaluate_MIN(value,itemid,parameter);
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX(value,itemid,parameter);
	}
	else if(strcmp(function,"diff")==0)
	{
		ret = evaluate_DIFF(value,itemid,parameter);
	}
	else
	{
		syslog( LOG_WARNING, "Unknown function:%s\n",function);
		ret = FAIL;
	}
	return ret;
}

int	update_functions( int itemid )
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		c[1024];
	float		value;
	int		ret=SUCCEED;
	int		i,rows;

	sprintf(c,"select function,parameter from functions where itemid=%d group by 1,2 order by 1,2",itemid );

	result = DBselect(c);
	rows=DBnum_rows(result);

	if((result==NULL)||(rows==0))
	{
		syslog( LOG_NOTICE, "No functions to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

	for(i=0;i<rows;i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=atoi(DBget_field(result,i,1));
		syslog( LOG_DEBUG, "ItemId:%d Evaluating %s(%d)\n",itemid,function.function,function.parameter);

		ret = evaluate_FUNCTION(&value,itemid,function.function,function.parameter);
		if( FAIL == ret)	
		{
			syslog( LOG_WARNING, "Evaluation failed for function:%s\n",function.function);
			DBfree_result(result);
			return FAIL;
		}
		syslog( LOG_DEBUG, "Result:%f\n",value);
		if (ret == SUCCEED)
		{
			sprintf(c,"update functions set lastvalue=%f where itemid=%d and function='%s' and parameter=%d", value, itemid, function.function, function.parameter );
//			printf("%s\n",c);
			DBexecute(c);
		}
	}

	DBfree_result(result);
	return ret;
}


int	send_mail(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody)
{
	int	s;
	int	i,e;
	char	*c;
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	syslog( LOG_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	if(hp==NULL)
	{
		syslog(LOG_ERR, "Cannot get IP for mailserver.");
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		syslog(LOG_ERR, "Socket error.");
		return FAIL;
	}
	
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog(LOG_ERR, "Connect error.");
		return FAIL;
	}
		
	c=(char *)malloc(1024);
	if(c == NULL)
	{
		syslog(LOG_ERR, "Malloc error.");
		close(s);
		return FAIL;
	}

	sprintf(c,"HELO %s\n",smtp_helo);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending HELO to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
			
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on HELO request.");
		close(s);
		free(c);
		return FAIL;
	}
			
	sprintf(c,"MAIL FROM: %s\n",smtp_email);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending MAIL FROM to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on MAIL FROM request.");
		close(s);
		free(c);
		return FAIL;
	}
			
	sprintf(c,"RCPT TO: <%s>\n",mailto);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending RCPT TO to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on RCPT TO request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"DATA\nSubject: %s\n",mailsubject);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending DATA to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on DATA request.");
		close(s);
		free(c);
		return FAIL;
	}
	sprintf(c,"%s\n",mailbody);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending mail body to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	sprintf(c,".\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending . to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on . request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e ==- 1)
	{
		syslog(LOG_ERR, "Error sending \\n to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on \\n request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"QUIT\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending QUIT to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}

	close(s);
	free(c); 
	
	return SUCCEED;
}

void	send_to_user(int actionid,int userid,char *smtp_server,char *smtp_helo,char *smtp_email,char *subject,char *message)
{
	DB_MEDIA media;
	char c[1024];
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(c,"select type,sendto,active from media where active=0 and userid=%d",userid);
	result = DBselect(c);

	rows=DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		media.active=atoi(DBget_field(result,i,2));
		media.type=DBget_field(result,i,0);
		media.sendto=DBget_field(result,i,1);

		if(strcmp(media.type,"EMAIL")==0)
		{
			syslog( LOG_DEBUG, "Email sending to %s %s Subject:%s Message:%s to %d\n", media.type, media.sendto, subject, message, userid );
			if( FAIL == send_mail(smtp_server,smtp_helo,smtp_email,media.sendto,subject,message))
			{
				syslog( LOG_ERR, "Error sending email to '%s' Subject:'%s' to userid:%d\n", media.sendto, subject, userid );
			}
			now = time(NULL);
			sprintf(c,"insert into alerts (alertid,actionid,clock,type,sendto,subject,message) values (NULL,%d,%d,'%s','%s','%s','%s');",actionid,now,media.type,media.sendto,subject,message);
			DBexecute(c);
		} 
		else
		{
			syslog( LOG_WARNING, "Type %s is not supported yet", media.type );
		}
	}
	DBfree_result(result);
}

void	apply_actions(int triggerid,int good)
{
	DB_RESULT *result;
	
	DB_ACTION action;

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

		substitute_macros(&*action.message);
		substitute_macros(&*action.subject); 

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
	DB_TRIGGER trigger;
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

int	get_lastvalue(float *Result,char *host,char *key,char *function,char *parameter)
{
	DB_RESULT *result;

        char	c[128];
        int	rows;
	int	parm;
	int	itemid;

	sprintf( c, "select i.itemid from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect(c);
        rows = DBnum_rows(result);

	if((result == NULL)||(rows==0))
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed" );
		return FAIL;	
	}

        itemid=atoi(DBget_field(result,0,0));
	syslog(LOG_DEBUG, "Itemid:%d", itemid );
        DBfree_result(result);

	parm=atoi(parameter);
	evaluate_FUNCTION(Result,itemid,function,parm);

        return SUCCEED;
}
