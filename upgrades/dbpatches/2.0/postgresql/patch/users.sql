ALTER TABLE ONLY users ALTER userid DROP DEFAULT;
UPDATE users SET theme='css_ob.css' WHERE theme='default.css';
