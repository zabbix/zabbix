#ifndef MON_FUNCTIONS_H
#define MON_FUNCTIONS_H

int	update_functions(int id, int flag);
void    update_triggers (int id, int flag);
int	get_lastvalue(float *Result,char *host,char *key,char *function,char *parameter);

#endif
