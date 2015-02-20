/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "base64.h"

static char base64_set [] =
  "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

static char char_base64_encode(unsigned char uc);
static unsigned char char_base64_decode(char c);
static int is_base64 (char c);

/*------------------------------------------------------------------------
 *
 * Function	:  is_base64
 *
 * Purpose	:  Is the character passed in a base64 character ?
 *
 * Parameters	:
 *
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
static int is_base64 (char c)
{
	if ( (c >= '0' && c <= '9')
	  || (c >= 'a' && c <= 'z')
	  || (c >= 'A' && c <= 'Z')
	  || c == '/'
	  || c == '+'
	  || c == '='		)

	{
		return 1;
	}

	return 0;
}
/*------------------------------------------------------------------------
 *
 * Function	:  char_base64_encode
 *
 * Purpose	:  Encode a byte into a base64 character
 *
 * Parameters	:
 *
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
static char char_base64_encode(unsigned char uc)
{
	return base64_set[uc];
}

/*------------------------------------------------------------------------
 *
 * Function	:  char_base64_decode
 *
 * Purpose	:  Decode a base64 character into a byte
 *
 * Parameters	:
 *
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
static unsigned char char_base64_decode(char c)
{
	if (c >= 'A' && c <= 'Z')
	{
		return c - 'A';
	}

	if (c >= 'a' && c <= 'z')
	{
		return c - 'a' + 26;
	}

	if (c >= '0' && c <= '9')
	{
		return c - '0' + 52;
	}

	if (c == '+')
	{
		return 62;
	}

	return 63;
}
/*------------------------------------------------------------------------
 *
 * Function	:  str_base64_encode
 *
 * Purpose	:  Encode a string into a base64 string
 *
 * Parameters	:  p_str (in)		- the string to encode
 *		   p_b64str (out)	- the encoded str to return
 *		   in_size (in)		- size (length) of input str
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
void str_base64_encode(const char *p_str, char *p_b64str, int in_size)
{
	int		i;
	unsigned char	from1=0,from2=0,from3=0;
	unsigned char	to1=0,to2=0,to3=0,to4=0;
	char		*p;

	if ( 0 == in_size )
	{
		return;
	};

	assert(p_str);
	assert(p_b64str);

	p = p_b64str;

	for ( i = 0; i < in_size ; i += 3 )
	{
		if (p - p_b64str > ZBX_MAX_B64_LEN - 5)
			break;

		from1 = from2 = from3 = 0;
		from1 = p_str[i];
		if (i+1 < in_size)
		{
			from2 = p_str[i+1];
		}
		if (i+2 < in_size)
		{
			from3 = p_str[i+2];
		}

		to1 = to2 = to3 = to4 = 0;
		to1 = (from1>>2) & 0x3f;
		to2 = ((from1&0x3)<<4)|(from2>>4);
		to3 = ((from2&0xf)<<2)|(from3>>6);
		to4 = from3&0x3f;

		*p++ = char_base64_encode(to1);
		*p++ = char_base64_encode(to2);

		if (i+1 < in_size)
		{
			*p++ = char_base64_encode(to3);
		}
		else
		{
			*p++ = '=';	/* Padding */
		}
		if (i+2 < in_size)
		{
			*p++ = char_base64_encode(to4);
		}
		else
		{
			*p++ = '=';	/* Padding */
		};

/*		if ( i % (76/4*3) == 0)
		{
			*(p_b64str++) = '\r';
			*(p_b64str++) = '\n';
		}*/
	};

	*p = '\0';
	return;
}
/*------------------------------------------------------------------------
 *
 * Function	:  str_base64_encode_dyn
 *
 * Purpose	:  Encode a string into a base64 string
 *                 with dynamical memory allocation
 *
 * Parameters	:  p_str (in)		- the string to encode
 *		   p_b64str (out)	- the pointer to encoded str
 *                                        to return
 *		   in_size (in)		- size (length) of input str
 * Returns	:
 *
 * Comments	:  allocates memory!
 *
 *----------------------------------------------------------------------*/
 void	str_base64_encode_dyn(const char *p_str, char **p_b64str, int in_size)
 {
	const char 	*pc;
	char		*pc_r;
	int		c_per_block = 0;	/* number of bytes which can be encoded to place in the buffer per time */
	int		b_per_block = 0;	/* bytes in the buffer to store 'c_per_block' encoded bytes */
	int		full_block_num = 0;
	int		bytes_left = 0;		/* less then 'c_per_block' bytes left */
	int		bytes_for_left = 0;	/* bytes in the buffer to store 'bytes_left' encoded bytes */

	assert(p_str);
	assert(p_b64str);
	assert(!*p_b64str);	/* expect a pointer will NULL value, do not know whether allowed to free that memory */

	*p_b64str = zbx_malloc(*p_b64str, in_size / 3 * 4 + (in_size % 3 ? 4 + 1 : 1));
	c_per_block = (ZBX_MAX_B64_LEN - 1) / 4 * 3;
	b_per_block = c_per_block / 3 * 4;
	full_block_num = in_size / c_per_block;
	bytes_left = in_size % c_per_block;
	bytes_for_left = bytes_left / 3 * 4 + (bytes_left % 3 ? 4 : 0);

	for (pc = p_str, pc_r = *p_b64str; full_block_num; pc += c_per_block, pc_r += b_per_block, --full_block_num)
		str_base64_encode(pc, pc_r, c_per_block);
	if (bytes_left)
	{
		str_base64_encode(pc, pc_r, bytes_left);
		pc_r += bytes_for_left;
	}

	*pc_r = '\0';
 }
/*------------------------------------------------------------------------
 *
 * Function	:  str_base64_decode
 *
 * Purpose	:  Decode a base64 string into a string
 *
 * Parameters	:  p_b64str (in)	- the base64 string to decode
 *		   p_str (out)		- the decoded str to return
 *		   p_str_maxsize (in)	- the size of p_str buffer
 *		   p_out_size (out)	- the size (len) of the str decoded
 *
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
void	str_base64_decode(const char *p_b64str, char *p_str, int maxsize, int *p_out_size)
{
	const char	*p;
	char		from[4];
	unsigned char	to[4];
	int		i = 0, j = 0;
	int		lasti = -1;	/* index of the last filled-in element of from[] */
	int		finished = 0;

	assert(p_b64str);
	assert(p_str);
	assert(p_out_size);
	assert(maxsize > 0);

	*p_out_size = 0;
	p = p_b64str;

	while (1)
	{
		if ('\0' != *p)
		{
			/* skip non-base64 characters */
			if (0 == is_base64(*p))
			{
				p++;
				continue;
			}

			/* collect up to 4 characters */
			from[i] = *p++;
			lasti = i;
			if (i < 3)
			{
				i++;
				continue;
			}
			else
				i = 0;
		}
		else	/* no more data to read */
		{
			finished = 1;
			for (j = lasti + 1; j < 4; j++)
				from[j] = 'A';
		}

		if (-1 != lasti)
		{
			/* decode a 4-character block */
			for (j = 0; j < 4; j++)
				to[j] = char_base64_decode(from[j]);

			if (1 <= lasti)	/* from[0], from[1] available */
			{
				*p_str++ = ((to[0] << 2) | (to[1] >> 4));
				if (++(*p_out_size) == maxsize)
					break;
			}

			if (2 <= lasti && '=' != from[2])	/* from[2] available */
			{
				*p_str++ = (((to[1] & 0xf) << 4) | (to[2] >> 2));
				if (++(*p_out_size) == maxsize)
					break;
			}

			if (3 == lasti && '=' != from[3])	/* from[3] available */
			{
				*p_str++ = (((to[2] & 0x3) << 6) | to[3]);
				if (++(*p_out_size) == maxsize)
					break;
			}
			lasti = -1;
		}

		if (1 == finished)
			break;
	}
}
