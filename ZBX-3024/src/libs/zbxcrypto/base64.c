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

#include "common.h"
#include "base64.h"

#define MAX_B64_SIZE 64*1024

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
 *		   p_str (out)		- the encoded str to return
 *		   p_str_maxsize (in)	- the size of p_str buffer
 *		   p_out_size (out)	- the size (len) of the str decoded
 *
 * Returns	:
 *
 * Comments	:
 *
 *----------------------------------------------------------------------*/
void str_base64_decode(const char *p_b64str, char *p_str, int maxsize, int *p_out_size)
{
	const char	*p;
	char		*o, from1, from2, from3, from4;
	unsigned char	to1, to2, to3, to4;
	char		str_clean[MAX_B64_SIZE];/* str_clean is the string after removing
						 * the non-base64 characters
						 */
	assert(p_b64str);
	assert(p_str);
	assert(p_out_size);
	assert(maxsize > 0);

	*p_out_size = 0;

	/* Clean-up input string */
	for (p = p_b64str, o = str_clean; *p != '\0'; p++ )
		if (is_base64(*p))
			*o++ = *p;
	*o = '\0';

	for (o = str_clean; *o != '\0';)
	{
		from1 = *o++;
		from2 = from3 = from4 = 'A';

		if (*o != '\0')
		{
			from2 = *o++;
			if (*o != '\0')
			{
				from3 = *o++;
				if (*o != '\0')
					from4 = *o++;
			}
		}

		to1 = char_base64_decode(from1);
		to2 = char_base64_decode(from2);
		to3 = char_base64_decode(from3);
		to4 = char_base64_decode(from4);

		*p_str++ = ((to1 << 2) | (to2 >> 4));
		if (++(*p_out_size) == maxsize)
			break;

		if (from3 != '=')
		{
			*p_str++ = (((to2 & 0xf) << 4) | (to3 >> 2));
			if (++(*p_out_size) == maxsize)
				break;
		}

		if (from4 != '=')
		{
			*p_str++ =  (((to3 & 0x3) << 6) | to4);
			if (++(*p_out_size) == maxsize)
				break;
		}
	}

	return;
}
