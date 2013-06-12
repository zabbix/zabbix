ALTER TABLE media_type MODIFY mediatypeid DEFAULT NULL;
ALTER TABLE media_type ADD status number(10) DEFAULT '0' NOT NULL;
