ALTER TABLE ONLY expressions ALTER expressionid DROP DEFAULT,
			     ALTER regexpid DROP DEFAULT;
DELETE FROM expressions WHERE NOT EXISTS (SELECT 1 FROM regexps WHERE regexps.regexpid = expressions.regexpid);
ALTER TABLE ONLY expressions ADD CONSTRAINT c_expressions_1 FOREIGN KEY (regexpid) REFERENCES regexps (regexpid) ON DELETE CASCADE;
