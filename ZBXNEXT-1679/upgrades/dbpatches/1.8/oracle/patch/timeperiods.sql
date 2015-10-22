CREATE TABLE timeperiods (
        timeperiodid            number(20)              DEFAULT '0'     NOT NULL,
        timeperiod_type         number(10)              DEFAULT '0'     NOT NULL,
        every           number(10)              DEFAULT '0'     NOT NULL,
        month           number(10)              DEFAULT '0'     NOT NULL,
        dayofweek               number(10)              DEFAULT '0'     NOT NULL,
        day             number(10)              DEFAULT '0'     NOT NULL,
        start_time              number(10)              DEFAULT '0'     NOT NULL,
        period          number(10)              DEFAULT '0'     NOT NULL,
        start_date              number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (timeperiodid)
);
