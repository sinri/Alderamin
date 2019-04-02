CREATE TABLE test.`t2`
(
  `name`      varchar(128)   NOT NULL,
  `value_sum` decimal(16, 2) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;