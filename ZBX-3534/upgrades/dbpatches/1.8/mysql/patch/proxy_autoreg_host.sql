CREATE TABLE proxy_autoreg_host (
      id              bigint unsigned                 NOT NULL        auto_increment unique,
      clock           integer         DEFAULT '0'     NOT NULL,
      host            varchar(64)             DEFAULT ''      NOT NULL,
      PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX proxy_autoreg_host_1 on proxy_autoreg_host (clock);
