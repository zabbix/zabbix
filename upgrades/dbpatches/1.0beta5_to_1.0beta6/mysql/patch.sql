alter table functions modify lastvalue varchar(255);
alter table functions modify parameter varchar(255) default '0' not null;


--
-- Table structure for table 'problems'
--

CREATE TABLE problems (
  problemid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  triggerid		int(4),
  lastupdate		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  categoryid		int(4),
  priority		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (problemid),
  KEY (status),
  KEY (categoryid),
  KEY (priority)
);

--
-- Table structure for table 'categories'
--

CREATE TABLE categories (
  categoryid		int(4)		NOT NULL auto_increment,
  descripion		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (categoryid)
);

--
-- Table structure for table 'problems_comments'
--

CREATE TABLE problems_comments (
  commentid		int(4)		NOT NULL auto_increment,
  problemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4),
  status_before		int(1)		DEFAULT '0' NOT NULL,
  status_after		int(1)		DEFAULT '0' NOT NULL,
  comment		blob,
  PRIMARY KEY (commentid),
  KEY (problemid,clock)
);

--
-- Table structure for table 'service_alarms'
--

CREATE TABLE service_alarms (
  serviceid             int(4)          NOT NULL auto_increment,
  clock                 int(4)          DEFAULT '0' NOT NULL,
  value                 int(4)          DEFAULT '0' NOT NULL,
  PRIMARY KEY (serviceid),
  KEY (serviceid,clock),
  KEY (clock)
);

