ALTER TABLE expressions MODIFY expressionid DEFAULT NULL;
ALTER TABLE expressions MODIFY regexpid DEFAULT NULL;
DELETE FROM expressions WHERE NOT regexpid IN (SELECT regexpid FROM regexps);
ALTER TABLE expressions ADD CONSTRAINT c_expressions_1 FOREIGN KEY (regexpid) REFERENCES regexps (regexpid) ON DELETE CASCADE;
