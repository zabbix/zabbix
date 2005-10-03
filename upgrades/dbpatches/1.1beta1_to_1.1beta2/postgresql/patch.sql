--
-- Table structure for table 'autoreg'
--

CREATE TABLE autoreg (
  id                    serial,
  priority              int4            DEFAULT '0' NOT NULL,
  pattern               varchar(255)    DEFAULT '' NOT NULL,
  hostid                int4            DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
);
