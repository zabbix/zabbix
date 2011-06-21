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

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

#include "common.h"
#include "log.h"
#include "zlog.h"
#include "comms.h"

#include "email.h"

/*
 * Send email
 */ 
int	send_email(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody, char *error, int max_error_len)
{
	int	s;
	int	i,e;
	char	c[MAX_STRING_LEN], *cp = NULL;
	struct hostent *hp;

	char	str_time[MAX_STRING_LEN];
	struct	tm *local_time = NULL;
	time_t	email_time;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	char	*OK_220="220";
	char	*OK_250="250";
	char	*OK_251="251";
	char	*OK_354="354";

	zabbix_log( LOG_LEVEL_DEBUG, "In send_email[smtp_server:%s]",
		smtp_server);

	servaddr_in.sin_family=AF_INET;

	if(NULL == (hp = zbx_gethost(smtp_server)))
	{
		zbx_snprintf(error,max_error_len,"Cannot get IP for mailserver [%s]",smtp_server);
		zabbix_log(LOG_LEVEL_DEBUG, "%s",error);
		zabbix_syslog("%s",error);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		zbx_snprintf(error,max_error_len,"Cannot create socket [%s]",strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		return FAIL;
	}
	
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zbx_snprintf(error,max_error_len,"Cannot connect to SMTP server [%s] [%s]", smtp_server, strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s",error);
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
	i=read(s,c,MAX_STRING_LEN);
	if(i == -1)
	{
		zbx_snprintf(error,max_error_len,"Error receiving initial string from SMTP server [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	if(strncmp(OK_220,c,strlen(OK_220)) != 0)
	{
		zbx_snprintf(error,max_error_len,"No welcome message 220* from SMTP server [%s]", c);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}

	if(strlen(smtp_helo) != 0)
	{
		memset(c,0,MAX_STRING_LEN);
		zbx_snprintf(c,sizeof(c),"HELO %s\r\n",smtp_helo);
		e=write(s,c,strlen(c)); 
		if(e == -1)
		{
			zbx_snprintf(error,max_error_len,"Error sending HELO to mailserver [%s]", strerror(errno));
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
			close(s);
			return FAIL;
		}
				
		memset(c,0,MAX_STRING_LEN);
		i=read(s,c,MAX_STRING_LEN);
		if(i == -1)
		{
			zbx_snprintf(error,max_error_len,"Error receiving answer on HELO request [%s]", strerror(errno));
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
			close(s);
			return FAIL;
		}
		if(strncmp(OK_250,c,strlen(OK_250)) != 0)
		{
			zbx_snprintf(error,max_error_len,"Wrong answer on HELO [%s]", c);
			zabbix_log(LOG_LEVEL_DEBUG, "%s",error);
			zabbix_syslog("%s",error);
			close(s);
			return FAIL;
		}
	}
			
	memset(c,0,MAX_STRING_LEN);

	zbx_snprintf(c,sizeof(c),"MAIL FROM: <%s>\r\n",smtp_email);

	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending MAIL FROM to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
	i=read(s,c,MAX_STRING_LEN);
	if(i == -1)
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on MAIL FROM request [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on MAIL FROM [%s]", c);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
			
	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"RCPT TO: <%s>\r\n",mailto);
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending RCPT TO to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
	i=read(s,c,MAX_STRING_LEN);
	if(i == -1)
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on RCPT TO request [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	/* May return 251 as well: User not local; will forward to <forward-path>. See RFC825 */
	if( strncmp(OK_250,c,strlen(OK_250)) != 0 && strncmp(OK_251,c,strlen(OK_251)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on RCPT TO [%s]", c);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"DATA\r\n");
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending DATA to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
	i=read(s,c,MAX_STRING_LEN);
	if(i == -1)
	{
		zbx_snprintf(error,max_error_len,"Error receivng answer on DATA request [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}
	if(strncmp(OK_354,c,strlen(OK_354)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on DATA [%s]", c);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}

	cp = string_replace(mailsubject, "\r\n", "\n");
	mailsubject = string_replace(cp, "\n", "\r\n");
	zbx_free(cp);

	cp = string_replace(mailbody, "\r\n", "\n");
	mailbody = string_replace(cp, "\n", "\r\n");
	zbx_free(cp);

	memset(c,0,MAX_STRING_LEN);
	time(&email_time);
	local_time = localtime(&email_time);
	strftime( str_time, MAX_STRING_LEN, "%a, %d %b %Y %H:%M:%S %z", local_time );
	cp = zbx_dsprintf(cp,"From:<%s>\r\nTo:<%s>\r\nDate: %s\r\nSubject: %s\r\n\r\n%s",smtp_email,mailto,str_time,mailsubject, mailbody);
	e=write(s,cp,strlen(cp)); 
	zbx_free(cp);
	zbx_free(mailsubject);
	zbx_free(mailbody);
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending mail subject and body to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"\r\n.\r\n");
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending . to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s",error);
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
	i=read(s,c,MAX_STRING_LEN);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receivng answer on . request [%s]", strerror(errno));
		zabbix_syslog("Error receivng answer on . request [%s]", strerror(errno));
		zbx_snprintf(error,max_error_len,"Error receivng answer on . request [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on end of data [%s]", c);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s",error);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"QUIT\r\n");
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending QUIT to mailserver [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s",error);
		close(s);
		return FAIL;
	}

	close(s);

	
	return SUCCEED;
}
