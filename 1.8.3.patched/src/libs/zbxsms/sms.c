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
#include <fcntl.h>

#include <string.h>

#include <errno.h>

#include <termios.h>

#include "common.h"
#include "log.h"
#include "zlog.h"

#include "sms.h"

static int write_gsm(int fd, char *str, char *error, int max_error_len)
{
	int	i = 0,
		wlen = 0,
		len = 0;

	int	ret = SUCCEED;

	len = strlen(str);

	zabbix_log(LOG_LEVEL_DEBUG, "Write to GSM modem [%s]", str);

	for ( wlen = 0; wlen < len; wlen += i )
	{
		if ( -1 == ( i = write(fd, str + wlen, len - wlen)) )
		{
			i = 0;

			if ( EAGAIN == errno ) continue;

			zabbix_log(LOG_LEVEL_DEBUG, "Error writing to GSM modem [%s]", strerror(errno));
			if ( error ) zbx_snprintf(error,max_error_len, "Error writing to GSM modem [%s]", strerror(errno));

			ret = FAIL;
			break;
		}
	}

	return ret;
}

static int	check_modem_result(char *buffer, char **ebuf, char **sbuf, const char *expect, char *error, int max_error_len)
{
	const char	*__function_name = "check_modem_result";
	char		rcv[0xff];
	int		i, len, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	strcpy(rcv, *sbuf);

	do
	{
		len = *ebuf - *sbuf;
		for (i = 0; i < len && (*sbuf)[i] != '\n' && (*sbuf)[i] != '\r'; i++)
			; /* find first '\r' & '\n' */

		if (i < len)
			(*sbuf)[i++] = '\0';

		ret = (NULL == strstr(*sbuf, expect)) ? FAIL : SUCCEED;

		*sbuf += i;

		if (*sbuf != buffer)
		{
			memmove(buffer, *sbuf, *ebuf - *sbuf + 1); /* +1 for '\0' */
			*ebuf -= *sbuf - buffer;
			*sbuf = buffer;
		}
	} while (*sbuf < *ebuf && ret == FAIL);

	if (ret == FAIL && error)
	{
		zbx_snprintf(error, max_error_len, "Expected [%s] received [%s]",
				expect,
				rcv);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int read_gsm(int fd, const char *expect, char *error, int max_error_len, int timeout_sec)
{
	const char	*__function_name = "read_gsm";
	static char	buffer[0xff];
	static char	*ebuf = buffer;
	static char	*sbuf = buffer;
	fd_set		fdset;
	struct timeval  tv;
	int		i, nbytes, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s] [%s] [%s] [%s]", __function_name, expect, ebuf != buffer ? buffer : "NULL", ebuf != buffer ? ebuf : "NULL", ebuf != buffer ? sbuf : "NULL");

/*	if (timeout_sec == 0)
	{
		goto check_result;
	}*/
	if ('\0' != *expect && ebuf != buffer &&
			SUCCEED == check_modem_result(buffer, &ebuf, &sbuf, expect, error, max_error_len))
		goto out;

	tv.tv_sec  = timeout_sec;
	tv.tv_usec = 0;

/* wait for response from modem */
	FD_ZERO(&fdset);	FD_SET(fd, &fdset);
	do {
		i = select(fd + 1, &fdset, NULL, NULL, &tv);
		if ( i == -1 )
		{
			if ( EINTR == errno )	continue;

			zabbix_log(LOG_LEVEL_DEBUG, "Error select() for GSM modem. [%s]", strerror(errno));
			if (error)
				zbx_snprintf(error,max_error_len, "Error select() for GSM modem. [%s]", strerror(errno));
			ret = FAIL;
			goto out;
		}
		else if ( i == 0 ) /*( 1 != i )*/
		{
			/* Timeout exceeded */
			zabbix_log(LOG_LEVEL_DEBUG, "Error during wait for GSM modem.");
			if ( error ) zbx_snprintf(error,max_error_len, "Error during wait for GSM modem.");

			goto check_result;
		}
		else
		{
			break;
		}
	} while ( 1 );

	/* read characters into our string buffer */
	while ((nbytes = read(fd, ebuf, buffer + sizeof(buffer) - 1 - ebuf)) > 0)
		ebuf += nbytes;
	/* nul terminate the string and see if we got an OK response */
check_result:
	*ebuf = '\0';

	zabbix_log(LOG_LEVEL_DEBUG, "Read from GSM modem [%s]", sbuf);

	if ('\0' == *expect) /* empty */
	{
		sbuf = ebuf = buffer;
		*ebuf = '\0';
		goto out;
	}

	ret = check_modem_result(buffer, &ebuf, &sbuf, expect, error, max_error_len);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

typedef struct {
	char		*message;
	const char	*result;
	int		timeout_sec;
} zbx_sms_scenario;

int	send_sms(char *device,char *number,char *message, char *error, int max_error_len)
{
	const char	*__function_name = "send_sms";
#define	ZBX_AT_ESC	"\x1B"
#define ZBX_AT_CTRL_Z	"\x1A"

	zbx_sms_scenario scenario[] = {
/*  0  */	{ZBX_AT_ESC	, NULL		, 0	},	/* Send <ESC> */
/*  1  */	{"AT+CMEE=2\r"	, ""/*"OK"*/	, 5	},	/* verbose error values */
/*  1  */	{"ATE0\r"	, "OK"		, 5	},	/* Turn off echo */
/*  2  */	{"AT\r"		, "OK"		, 5	},	/* Init modem */
/*  3  */	{"AT+CMGF=1\r"	, "OK"		, 5	},	/* Switch to text mode */
/*  4  */	{"AT+CMGS=\""	, NULL		, 0	},	/* Set phone number */
/*  5  */	{number		, NULL		, 0	},	/* Write phone number */
/*  6  */	{"\"\r"		, "> "		, 5	},	/* Set phone number */
/*  7  */	{message	, NULL		, 0	},	/* Write message */
/*  8  */	{ZBX_AT_CTRL_Z	, "+CMGS: "	, 40	},	/* Send message */
/*  9  */	{NULL		, "OK"		, 1	},	/* ^Z */
/* EOS */	{NULL		, NULL		, 0	}
		};

	zbx_sms_scenario *step = NULL;

	struct termios
		options,
		old_options;

	int	f,
		ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if ( -1 == (f = open(device, O_RDWR | O_NOCTTY | O_NDELAY)) )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error open(%s) [%s]",
			device,
			strerror(errno));
		if ( error )
			zbx_snprintf(error,max_error_len, "Error open(%s) [%s]",
				device,
				strerror(errno));
		return FAIL;
	}
	fcntl(f, F_SETFL,0); /* Set the status flag to 0 */

	/* Set ta parameters */
	tcgetattr(f, &old_options);

	memset(&options, 0, sizeof(struct termios));

	options.c_iflag     = IGNCR | INLCR | ICRNL;

#ifdef ONOCR
	options.c_oflag     = ONOCR;
#endif /* ONOCR */

	options.c_cflag     = old_options.c_cflag | CRTSCTS | CS8 | CLOCAL | CREAD;
	options.c_lflag     &= ~(ICANON | ECHO | ECHOE | ISIG);
	options.c_cc[VMIN]  = 0;
	options.c_cc[VTIME] = 1;

	tcsetattr(f, TCSANOW, &options);

	for(step = scenario; step->message || step->result; step++)
	{
		if(step->message)
		{
			if ( FAIL == (ret = write_gsm(f, step->message, error, max_error_len)) ) break;
		}
		if(step->result)
		{
			if( FAIL == (ret = read_gsm(f, step->result, error, max_error_len, step->timeout_sec)) ) break;
		}
	}

	if ( FAIL == ret )
	{
		write_gsm(f, "\r" ZBX_AT_ESC ZBX_AT_CTRL_Z, NULL, 0); /* cancel all */
		read_gsm(f, "", NULL, 0, 0); /* clear buffer */
	}

	tcsetattr(f, TCSANOW, &old_options);
	close(f);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
