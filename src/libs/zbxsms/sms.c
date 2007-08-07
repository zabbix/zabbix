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

	zabbix_log(LOG_LEVEL_DEBUG, "Write [%s]", str);

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

static int read_gsm(int fd, const char *expect, char *error, int max_error_len)
{
	static char	buffer[0xFF];
	static char	*ebuf = buffer;
	static char	*sbuf = buffer;

	fd_set		fdset;

	int	i, nbytes;
	int	ret = SUCCEED;

	/* wait for response from modem */
	FD_ZERO(&fdset);	FD_SET(fd, &fdset);
	do {
		i = select(fd + 1, &fdset, NULL, NULL, NULL);
		if ( -1 == i )
		{
			if ( EINTR == errno )	continue;
		
			zabbix_log(LOG_LEVEL_DEBUG, "Error select() for GSM modem. [%s]", strerror(errno));
			if ( error ) zbx_snprintf(error,max_error_len, "Error select() for GSM modem. [%s]", strerror(errno));

			return (ret = FAIL);
		}
		else if ( 1 != i )
		{
			/* Timeout exceeded */
			zabbix_log(LOG_LEVEL_DEBUG, "Error during wait for GSM modem.");
			if ( error ) zbx_snprintf(error,max_error_len, "Error during wait for GSM modem.");

			return (ret = FAIL);
		}
		else
		{
			break;
		}
	} while ( 1 );

	/* read characters into our string buffer */
	while ((nbytes = read(fd, ebuf, buffer + sizeof(buffer) - 1 - ebuf)) > 0)
	{
		ebuf += nbytes;
	}
	/* nul terminate the string and see if we got an OK response */
	*ebuf = '\0';

	if( '\0' == *expect ) /* empty */
	{
		sbuf = ebuf = buffer;
		*ebuf = '\0';
		return ret;
	}

	zbx_ltrim(sbuf, "\r\n");

	for(i = 0 ; i < (ebuf - sbuf) && (sbuf[i] != '\n' && sbuf[i] != '\r'); i++); /* find first '\r' & '\n' */

	if(i < ebuf - sbuf)	sbuf[i++] = '\0';

#if defined(DEBUG)
	for ( i = 0; i < strlen(buffer); i++ )	zabbix_log(LOG_LEVEL_DEBUG, "%3i[0x%x]", i, buffer[i]);
#endif /* DEBUG */

	if (strstr(sbuf, expect) == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Read something unexpected from GSM modem. Expected [%s] instead [%s]", expect, sbuf);
		if ( error ) zbx_snprintf(error,max_error_len, "Read something unexpected from GSM modem. Expected [%s]", expect);
		ret = FAIL;
	}
	
	sbuf += i;

	if(sbuf != buffer)
	{
		memmove(buffer, sbuf, ebuf - sbuf + 1); /* +1 for '\0' */
		ebuf -= sbuf - buffer;
		sbuf = buffer;
	}

	return ret;
}

typedef struct {
	char 		*message;
	const char 	*result;
} zbx_sms_scenario;

int	send_sms(char *device,char *number,char *message, char *error, int max_error_len)
{
#define	ZBX_AT_ESC	"\x1B"
#define ZBX_AT_CTRL_Z	"\x1A"

	zbx_sms_scenario scenario[] = {
/*  0  */	{"\r" ZBX_AT_ESC	, ""		},	/* Send <ESC> */
/*  1  */	{"ATE0\r"		, "OK"		},	/* Turn off echo */
/*  2  */	{"AT\r"			, "OK"		},	/* Init modem */
/*  3  */	{"AT+CMGF=1\r"		, "OK"		},	/* Switch to text mode */
/*  4  */	{"AT+CMGS=\""		, NULL		},	/* Set phone number */
/*  5  */	{number			, NULL		},	/* Write phone number */
/*  6  */	{"\"\r"			, ">"		},	/* Set phone number */
/*  7  */	{message		, NULL		},	/* Write message */
/*  8  */	{ZBX_AT_CTRL_Z		, "+CMGS: "	},	/* Send message */
/*  9  */	{NULL			, "OK"		},	/* ^Z */
/* EOS */	{NULL			, NULL		}
		};

	zbx_sms_scenario *step = NULL;

	struct termios
		options,
		old_options;

	int	f,
		ret = SUCCEED;


	if ( -1 == (f = open(device,O_RDWR | O_NOCTTY | O_NDELAY)) )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error open(%s) [%s]", device, strerror(errno));
		if ( error ) zbx_snprintf(error,max_error_len, "Error open(%s) [%s]", device, strerror(errno));
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
	options.c_cc[VTIME] = 20;

	tcsetattr(f, TCSANOW, &options);

	for(step = scenario; step->message || step->result; step++)
	{
		if(step->message)
		{
			if(FAIL == (ret = write_gsm(f, step->message, error, max_error_len))) break;
		}
		if(step->result)
		{
			if(FAIL == (ret = read_gsm(f, step->result, error, max_error_len))) break;
		}
	}

	if ( FAIL == ret )
	{
		write_gsm(f, "\r" ZBX_AT_ESC ZBX_AT_CTRL_Z, NULL, 0); /* cancel all */
		read_gsm(f, "", NULL, 0); /* clear buffer */
	}

	tcsetattr(f, TCSANOW, &old_options);
	close(f);

	return ret;
}
