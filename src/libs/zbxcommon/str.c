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
