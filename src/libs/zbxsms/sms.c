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

#include <string.h>

#include <errno.h>

#include "common.h"
#include "log.h"
#include "zlog.h"

#include "sms.h"

/*
 * Send email
 */ 
int	send_sms(char *device,char *number,char *message, char *error, int max_error_len)
{
	int	s;
	int	i,e;
	char	c[MAX_STRING_LEN];
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

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL2");
	if(hp==NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot get IP for mailserver [%s]",smtp_server);
		zabbix_syslog("Cannot get IP for mailserver [%s]",smtp_server);
		snprintf(error,max_error_len-1,"Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL3");

/*	if(hp==NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);*/

	s=socket(AF_INET,SOCK_STREAM,0);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL4");
	if(s == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot create socket [%s]", strerror(errno));
		zabbix_syslog("Cannot create socket [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Cannot create socket [%s]",strerror(errno));
		return FAIL;
	}
	
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot connect to SMTP server [%s] Error [%s]",smtp_server, strerror(errno));
		zabbix_syslog("Cannot connect to SMTP server [%s] Error [%s]",smtp_server, strerror(errno));
		snprintf(error,max_error_len-1,"Cannot connect to SMTP server [%s] [%s]", smtp_server, strerror(errno));
		close(s);
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL5");

	memset(c,0,MAX_STRING_LEN);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL6");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receiving initial string from SMTP server [%m]");
		zabbix_syslog("Error receiving initial string from SMTP server [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error receiving initial string from SMTP server [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	if(strncmp(OK_220,c,strlen(OK_220)) != 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No welcome message 220* from SMTP server [%s]", c);
		zabbix_syslog("No welcome message 220* from SMTP server [%s]", c);
		snprintf(error,max_error_len-1,"No welcome message 220* from SMTP server [%s]", c);
		close(s);
		return FAIL;
	}

	if(strlen(smtp_helo) != 0)
	{
		memset(c,0,MAX_STRING_LEN);
		snprintf(c,sizeof(c)-1,"HELO %s\r\n",smtp_helo);
/*		e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
		e=write(s,c,strlen(c)); 
		zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL7");
		if(e == -1)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Error sending HELO to mailserver [%s]", strerror(errno));
			zabbix_syslog("Error sending HELO to mailserver [%s]", strerror(errno));
			snprintf(error,max_error_len-1,"Error sending HELO to mailserver [%s]", strerror(errno));
			close(s);
			return FAIL;
		}
				
		memset(c,0,MAX_STRING_LEN);
/*		i=sizeof(struct sockaddr_in);
		i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
		i=read(s,c,MAX_STRING_LEN);
		zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL8");
		if(i == -1)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Error receiving answer on HELO request [%s]", strerror(errno));
			zabbix_syslog("Error receiving answer on HELO request [%s]", strerror(errno));
			snprintf(error,max_error_len-1,"Error receiving answer on HELO request [%s]", strerror(errno));
			close(s);
			return FAIL;
		}
		if(strncmp(OK_250,c,strlen(OK_250)) != 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Wrong answer on HELO [%s]",c);
			zabbix_syslog("Wrong answer on HELO [%s]",c);
			snprintf(error,max_error_len-1,"Wrong answer on HELO [%s]", c);
			close(s);
			return FAIL;
		}
	}
			
	memset(c,0,MAX_STRING_LEN);
/*	sprintf(c,"MAIL FROM: %s\r\n",smtp_email);*/
	snprintf(c,sizeof(c)-1,"MAIL FROM: <%s>\r\n",smtp_email);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL9");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending MAIL FROM to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending MAIL FROM to mailserver [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error sending MAIL FROM to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL10");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receiving answer on MAIL FROM request [%s]", strerror(errno));
		zabbix_syslog("Error receiving answer on MAIL FROM request [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error receiving answer on MAIL FROM request [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Wrong answer on MAIL FROM [%s]", c);
		zabbix_syslog("Wrong answer on MAIL FROM [%s]", c);
		snprintf(error,max_error_len-1,"Wrong answer on MAIL FROM [%s]", c);
		close(s);
		return FAIL;
	}
			
	memset(c,0,MAX_STRING_LEN);
	snprintf(c,sizeof(c)-1,"RCPT TO: <%s>\r\n",mailto);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL11");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending RCPT TO to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending RCPT TO to mailserver [%s]", strerror(errno) );
		snprintf(error,max_error_len-1,"Error sending RCPT TO to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL12");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receiving answer on RCPT TO request [%s]", strerror(errno));
		zabbix_syslog("Error receiving answer on RCPT TO request [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error receiving answer on RCPT TO request [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	/* May return 251 as well: User not local; will forward to <forward-path>. See RFC825 */
	if( strncmp(OK_250,c,strlen(OK_250)) != 0 && strncmp(OK_251,c,strlen(OK_251)) != 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Wrong answer on RCPT TO [%s]", c);
		zabbix_syslog("Wrong answer on RCPT TO [%s]", c);
		snprintf(error,max_error_len-1,"Wrong answer on RCPT TO [%s]", c);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN);
	snprintf(c,sizeof(c)-1,"DATA\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL13");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending DATA to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending DATA to mailserver [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error sending DATA to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL14");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receivng answer on DATA request [%s]", strerror(errno));
		zabbix_syslog("Error receivng answer on DATA request [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error receivng answer on DATA request [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	if(strncmp(OK_354,c,strlen(OK_354)) != 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Wrong answer on DATA [%s]", c);
		zabbix_syslog("Wrong answer on DATA [%s]", c);
		snprintf(error,max_error_len-1,"Wrong answer on DATA [%s]", c);
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
	time(&email_time);
	local_time = localtime(&email_time);
	strftime( str_time, MAX_STRING_LEN, "%a, %d %b %Y %H:%M:%S %z", local_time );
/*	sprintf(c,"Subject: %s\r\n%s",mailsubject, mailbody);*/
/*	snprintf(c,sizeof(c)-1,"From:<%s>\r\nTo:<%s>\r\nSubject: %s\r\n\r\n%s",smtp_email,mailto,mailsubject, mailbody);*/
	snprintf(c,sizeof(c)-1,"From:<%s>\r\nTo:<%s>\r\nDate: %s\r\nSubject: %s\r\n\r\n%s",smtp_email,mailto,str_time,mailsubject, mailbody);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending mail subject and body to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending mail subject and body to mailserver [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error sending mail subject and body to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN);
	snprintf(c,sizeof(c)-1,"\r\n.\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL15");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending . to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending . to mailserver [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error sending . to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL16");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error receivng answer on . request [%s]", strerror(errno));
		zabbix_syslog("Error receivng answer on . request [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error receivng answer on . request [%s]", strerror(errno));
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Wrong answer on end of data [%s]", c);
		zabbix_syslog("Wrong answer on end of data [%s]", c);
		snprintf(error,max_error_len-1,"Wrong answer on end of data [%s]", c);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN);
	snprintf(c,sizeof(c)-1,"QUIT\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL18");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending QUIT to mailserver [%s]", strerror(errno));
		zabbix_syslog("Error sending QUIT to mailserver [%s]", strerror(errno));
		snprintf(error,max_error_len-1,"Error sending QUIT to mailserver [%s]", strerror(errno));
		close(s);
		return FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL19");
	close(s);

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL. END.");
	
	return SUCCEED;
}


#include <stdlib.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include <unistd.h>
#include <stdio.h>
#include <string.h>

#include <termios.h>

/* 0 - OK, -1 - not OK */
int write_gsm(int fd, char *str)
{
	int	len;
	int	ret = 0;

	len = strlen(str);

	if (write(fd, str, len) < len)
	{
		printf("Error writing to modem\n");
		ret = -1;
	}

	return ret;
}

int read_gsm(int fd, char *expect)
{
	char	buffer[255];
	char	*bufptr;
	int	i,nbytes;
	int	ret = -1;

	/* read characters into our string buffer until we get a CR or NL */
	bufptr = buffer;
	while ((nbytes = read(fd, bufptr, buffer + sizeof(buffer) - bufptr - 1)) > 0)
	{
		printf("read %d bytes\n", nbytes);
		bufptr += nbytes;
		if (bufptr[-1] == '\n' || bufptr[-1] == '\r')
			break;
	}
	/* nul terminate the string and see if we got an OK response */
	*bufptr = '\0';
//	printf("Read buffer [%s]\n", buffer);
	for(i=0;i<strlen(buffer);i++)
		printf("[%x]\n",buffer[i]);
	if (strstr(buffer, expect) != NULL)
	{
		printf("Read OK\n");
		ret = 0;
	}
	else
	{
		printf("Read something unexpected\n");
	}
	return ret;
}

int main()
{
	int	f;

	struct termios options, old_options;

	f=open("/dev/ttyS0",O_RDWR | O_NOCTTY | O_NDELAY);
	if(f == -1)
	{
		printf("Error open(): [%s]\n", strerror(errno));
		return -1;
	}
	fcntl(f, F_SETFL,0);
	tcgetattr(f, &old_options);

	memset(&options, 0, sizeof(struct termios));

	options.c_iflag     = IGNCR | INLCR | ICRNL;
	options.c_oflag     = ONOCR;
	options.c_cflag     = B38400 | CRTSCTS | CS8 | CLOCAL | CREAD;
	options.c_lflag     &= ~(ICANON | ECHO | ECHOE | ISIG);
	options.c_cc[VMIN]  = 0;
	options.c_cc[VTIME] = 100;

	tcsetattr(f, TCSANOW, &options);
	
	/* Turn off echo */
	write_gsm(f,"ATE0\r");
	read_gsm(f,"\rOK\r");

	/* Init modem */
	write_gsm(f,"AT\r");
	read_gsm(f,"\rOK\r");

	/* Switch to text mode */
	write_gsm(f,"AT+CMGF=1\r");
	read_gsm(f,"\rOK\r");

	/* Send phone number */
	write_gsm(f,"AT+CMGS=\"9275351\"\r");
	read_gsm(f,"\r> ");

	/* Send message */
	write_gsm(f,"Test message\x01a");
	read_gsm(f,"\r+CMGS: ");
	read_gsm(f,"OK\r");

	tcsetattr(f, TCSANOW, &old_options);
	close(f);
}
