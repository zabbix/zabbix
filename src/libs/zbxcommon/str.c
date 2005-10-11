#include "common.h"

#include <string.h>

/* Has to be rewritten to avoi malloc */
char *string_replace(char *str, const char *sub_str1, const char *sub_str2)
{
        char *new_str;
        const char *p;
        const char *q;
        const char *r;
        char *t;
        signed long len;
        signed long diff;
        unsigned long count = 0;

        if ( (p=strstr(str, sub_str1)) == NULL )
                return str;
        ++count;

        len = strlen(sub_str1);

        /* count the number of occurances of sub_str1 */
        for ( p+=len; (p=strstr(p, sub_str1)) != NULL; p+=len )
                ++count;

        diff = strlen(sub_str2) - len;

        /* allocate new memory */
        if ( (new_str=(char *)malloc((strlen(str) + count*diff)*sizeof(char)))
                        == NULL )
                return NULL;

        q = str;
        t = new_str;
        for (p=strstr(str, sub_str1); p!=NULL; p=strstr(p, sub_str1))
        {
                /* copy until next occurance of sub_str1 */
                for ( ; q < p; *t++ = *q++)
                        ;
                q += len;
                p = q;
                for ( r = sub_str2; *t++ = *r++; )
                        ;
                --t;
        }
        /* copy the tail of str */
        while ( *t++ = *q++ )
                ;
        return new_str;

}

/******************************************************************************
 *                                                                            *
 * Function: del_zeroes                                                       *
 *                                                                            *
 * Purpose: delete all right '0' and '.' for the string                       *
 *                                                                            *
 * Parameters: s - string to trim '0'                                         *
 *                                                                            *
 * Return value: string without right '0'                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  10.0100 => 10.01, 10. => 10                                                                 *
 *                                                                            *
 ******************************************************************************/
void del_zeroes(char *s)
{
	int     i;

	if(strchr(s,'.')!=NULL)
	{
		for(i=strlen(s)-1;;i--)
		{
			if(s[i]=='0')
			{
				s[i]=0;
			}
			else if(s[i]=='.')
			{
				s[i]=0;
				break;
			}
			else
			{
				break;
			}
		}
	}
}

int	get_param(const char *param, int num, char *buf, int maxlen)
{
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	ret = 1;
	int	i=0;

	strscpy(tmp,param);
	s=(char *)strtok(tmp,",");
	while(s!=NULL)
	{
		i++;
		if(i == num)
		{
			strncpy(buf,s,maxlen);
			ret = 0;
			break;
		}
		s=(char *)strtok(NULL,";");
	}

	return ret;
}

int	num_param(const char *param)
{
	int	i;
	int	ret = 1;

	for(i=0;param[i]!=0;i++)
	{
		if(param[i]==',')	ret++;
	}

	return ret;
}
