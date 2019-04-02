CREATE TABLE test.`t1`
(
  `id`    int(11)        NOT NULL AUTO_INCREMENT,
  `name`  varchar(128)   NOT NULL,
  `desc`  varchar(255) DEFAULT NULL,
  `value` decimal(10, 2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8;