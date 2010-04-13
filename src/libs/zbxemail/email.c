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
#include "base64.h"

#include "email.h"

/*
 * smtp_readln reads a until a 0x0a
 */
ssize_t smtp_readln(int fd, char *buf, int buf_len)
{
	ssize_t	nbytes, read_bytes = 0;

	buf_len --;	/* '\0' */
	do
	{
		if (-1 == (nbytes = read(fd, &((char*)buf)[read_bytes], 1)))
			return nbytes;				/* Error */

		read_bytes += nbytes;
	}
	while (nbytes > 0 &&					/* End Of File (socket closed) */
			read_bytes < buf_len &&			/* End of Buffer */
			((char*)buf)[read_bytes - 1] != '\n' );	/* new line */

	buf[read_bytes] = '\0';

	return read_bytes;
}

/*
 * Send email
 */
int	send_email(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody, char *error, int max_error_len)
{
	int		ret = FAIL;
	zbx_sock_t	s;
	int		e;
	char		c[MAX_STRING_LEN], *cp = NULL, *pc_base64 = NULL;

	char		str_time[MAX_STRING_LEN];
	struct		tm *local_time = NULL;
	time_t		email_time;

	char		*OK_220 = "220";
	char		*OK_250 = "250";
	char		*OK_251 = "251";
	char		*OK_354 = "354";

	zabbix_log(LOG_LEVEL_DEBUG, "In send_email[smtp_server:%s]", smtp_server);

	*error = '\0';

	if(FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, smtp_server, 25, 0))
	{
		zbx_snprintf(error,max_error_len,"Cannot connect to SMTP server [%s] [%s]", smtp_server, zbx_tcp_strerror());
		goto out;
	}

	if (-1 == smtp_readln(s.socket, c, sizeof(c)))
	{
		zbx_snprintf(error,max_error_len,"Error receiving initial string from SMTP server [%s]", strerror(errno));
		goto out;
	}
	if (0 != strncmp(OK_220, c, strlen(OK_220)))
	{
		zbx_snprintf(error,max_error_len,"No welcome message 220* from SMTP server [%s]", c);
		goto out;
	}

	if (0 != strlen(smtp_helo))
	{
		memset(c,0,MAX_STRING_LEN);
		zbx_snprintf(c,sizeof(c),"HELO %s\r\n",smtp_helo);
		e=write(s.socket,c,strlen(c));
		if(e == -1)
		{
			zbx_snprintf(error,max_error_len,"Error sending HELO to mailserver [%s]", strerror(errno));
			goto out;
		}

		if (-1 == smtp_readln(s.socket, c, sizeof(c)))
		{
			zbx_snprintf(error,max_error_len,"Error receiving answer on HELO request [%s]", strerror(errno));
			goto out;
		}
		if (0 != strncmp(OK_250, c, strlen(OK_250)))
		{
			zbx_snprintf(error,max_error_len,"Wrong answer on HELO [%s]", c);
			goto out;
		}
	}

	memset(c,0,MAX_STRING_LEN);

	zbx_snprintf(c,sizeof(c),"MAIL FROM: <%s>\r\n",smtp_email);

	e=write(s.socket,c,strlen(c));
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending MAIL FROM to mailserver [%s]", strerror(errno));
		goto out;
	}

	if (-1 == smtp_readln(s.socket, c, sizeof(c)))
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on MAIL FROM request [%s]", strerror(errno));
		goto out;
	}
	if (0 != strncmp(OK_250, c, strlen(OK_250)))
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on MAIL FROM [%s]", c);
		goto out;
	}

	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"RCPT TO: <%s>\r\n",mailto);
	e=write(s.socket,c,strlen(c));
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending RCPT TO to mailserver [%s]", strerror(errno));
		goto out;
	}
	if (-1 == smtp_readln(s.socket, c, sizeof(c)))
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on RCPT TO request [%s]", strerror(errno));
		goto out;
	}
	/* May return 251 as well: User not local; will forward to <forward-path>. See RFC825 */
	if( strncmp(OK_250,c,strlen(OK_250)) != 0 && strncmp(OK_251,c,strlen(OK_251)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on RCPT TO [%s]", c);
		goto out;
	}

	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"DATA\r\n");
	e=write(s.socket,c,strlen(c));
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending DATA to mailserver [%s]", strerror(errno));
		goto out;
	}
	if (-1 == smtp_readln(s.socket, c, sizeof(c)))
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on DATA request [%s]", strerror(errno));
		goto out;
	}
	if(strncmp(OK_354,c,strlen(OK_354)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on DATA [%s]", c);
		goto out;
	}

	cp = string_replace(mailsubject, "\r\n", "\n");
	mailsubject = string_replace(cp, "\n", "\r\n");
	zbx_free(cp);
	str_base64_encode_dyn((const char *)mailsubject, &pc_base64, strlen(mailsubject));
	zbx_free(mailsubject);
	mailsubject = pc_base64;
	pc_base64 = NULL;

	cp = string_replace(mailbody, "\r\n", "\n");
	mailbody = string_replace(cp, "\n", "\r\n");
	zbx_free(cp);
	str_base64_encode_dyn((const char *)mailbody, &pc_base64, strlen(mailbody));
	zbx_free(mailbody);
	mailbody = pc_base64;
	pc_base64 = NULL;

	memset(c,0,MAX_STRING_LEN);
	time(&email_time);
	local_time = localtime(&email_time);
	strftime( str_time, MAX_STRING_LEN, "%a, %d %b %Y %H:%M:%S %z", local_time );
	/* e-mail header and content format is based on MIME standard since UTF-8 is used both in mailsubject and mailbody */
	/* =?charset?encoding?encoded text?= format must be used for subject field */
	/* e-mails are sent in 'SMTP/MIME e-mail' format, this should be documented */
	cp = zbx_dsprintf(cp, "From:<%s>\r\nTo:<%s>\r\nDate: %s\r\n"
			"Subject: =?UTF-8?B?%s?=\r\n"
			"MIME-Version: 1.0\r\n"
			"Content-Type: text/plain; charset=\"UTF-8\"\r\n"
			"Content-Transfer-Encoding: base64\r\n"
			"\r\n%s",
			smtp_email, mailto, str_time, mailsubject, mailbody);
	e=write(s.socket,cp,strlen(cp));
	zbx_free(cp);
	zbx_free(mailsubject);
	zbx_free(mailbody);
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending mail subject and body to mailserver [%s]", strerror(errno));
		goto out;
	}

	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"\r\n.\r\n");
	e=write(s.socket,c,strlen(c));
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending . to mailserver [%s]", strerror(errno));
		goto out;
	}
	if (-1 == smtp_readln(s.socket, c, sizeof(c)))
	{
		zbx_snprintf(error,max_error_len,"Error receiving answer on . request [%s]", strerror(errno));
		goto out;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zbx_snprintf(error,max_error_len,"Wrong answer on end of data [%s]", c);
		goto out;
	}

	memset(c,0,MAX_STRING_LEN);
	zbx_snprintf(c,sizeof(c),"QUIT\r\n");
	e=write(s.socket,c,strlen(c));
	if(e == -1)
	{
		zbx_snprintf(error,max_error_len,"Error sending QUIT to mailserver [%s]", strerror(errno));
		goto out;
	}
	ret = SUCCEED;
out:
	zbx_tcp_close(&s);

	if ('\0' != *error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		zabbix_syslog("%s", error);
	}

	return ret;
}
