#ifndef MON_FUNCTIONS_H
#define MON_FUNCTIONS_H

int	update_functions( int itemid );
void    update_triggers(int itemid);
int	get_lastvalue(float *Result,char *host,char *key,char *function,char *parameter);

#endif
