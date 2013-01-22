CREATE TABLE timeperiods (
      timeperiodid            bigint unsigned         DEFAULT '0'     NOT NULL,
      timeperiod_type         integer         DEFAULT '0'     NOT NULL,
      every           integer         DEFAULT '0'     NOT NULL,
      month           integer         DEFAULT '0'     NOT NULL,
      dayofweek               integer         DEFAULT '0'     NOT NULL,
      day             integer         DEFAULT '0'     NOT NULL,
      start_time              integer         DEFAULT '0'     NOT NULL,
      period          integer         DEFAULT '0'     NOT NULL,
      start_date              integer         DEFAULT '0'     NOT NULL,
      PRIMARY KEY (timeperiodid)
) ENGINE=InnoDB;
