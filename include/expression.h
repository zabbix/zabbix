#ifndef MON_EXPRESSION_H
#define MON_EXPRESSION_H

int	cmp_double(double a,double b);
int	find_char(char *str,char c);
int	substitute_functions(char *exp);
int	substitute_macros(char *exp);
int     evaluate_expression (int *result,char *expression);

#endif
