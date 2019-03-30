use alderamin;

CREATE TABLE `report`
(
  `report_id`      bigint(20)   NOT NULL AUTO_INCREMENT,
  `report_title`   varchar(128)          DEFAULT NULL,
  `report_code`    varchar(128) NOT NULL,
  `parameters`     mediumtext   NOT NULL,
  `apply_user`     varchar(128) NOT NULL,
  `status`         varchar(32)  NOT NULL,
  `priority`       int(11)      NOT NULL,
  `apply_time`     datetime     NOT NULL,
  `enqueue_time`   datetime              DEFAULT NULL,
  `execute_time`   datetime              DEFAULT NULL,
  `finish_time`    datetime              DEFAULT NULL,
  `feedback`       mediumtext,
  `pid`            int(11)               DEFAULT NULL,
  `archive_status` varchar(16)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING SENDING DONE ERROR',
  PRIMARY KEY (`report_id`),
  KEY `IND_FETCH_NEW` (`status`, `priority`, `apply_time`),
  KEY `report_code` (`report_code`),
  KEY `apply_user` (`apply_user`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8
;

CREATE TABLE `lock`
(
  `lock_id`             bigint(20)   NOT NULL AUTO_INCREMENT,
  `lock_code`           varchar(128) NOT NULL,
  `lock_by_report_id`   bigint(20)   NOT NULL,
  `lock_by_report_code` varchar(128) NOT NULL,
  `lock_time`           datetime     NOT NULL,
  PRIMARY KEY (`lock_id`),
  UNIQUE KEY `lock_code` (`lock_code`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8
;

CREATE TABLE `report_attribute`
(
  `attr_id`   bigint(20)   NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20)   NOT NULL,
  `key`       varchar(128) NOT NULL,
  `value`     varchar(128) NOT NULL,
  `type`      varchar(32)  NOT NULL,
  PRIMARY KEY (`attr_id`),
  KEY `IDX_RK` (`report_id`, `key`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8
;

CREATE TABLE `kill_request`
(
  `id`           bigint(20)  NOT NULL AUTO_INCREMENT,
  `report_id`    bigint(20)  NOT NULL,
  `request_time` datetime    NOT NULL,
  `status`       varchar(32) NOT NULL COMMENT 'PENDING DONE FAILED',
  `feedback`     varchar(512) DEFAULT NULL,
  `execute_time` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`, `request_time`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8
;

CREATE ALGORITHM = UNDEFINED DEFINER =`alderamin`@`%` SQL SECURITY DEFINER VIEW `alderamin`.`attribute_report_view` AS
select `r`.`report_id`    AS `report_id`,
       `r`.`report_title` AS `report_title`,
       `r`.`report_code`  AS `report_code`,
       `r`.`priority`     AS `priority`,
       `r`.`apply_user`   AS `apply_user`,
       `r`.`status`       AS `status`,
       `a`.`key`          AS `key`,
       `a`.`value`        AS `value`,
       `a`.`type`         AS `attribute_type`
from (`alderamin`.`report` `r`
       left join `alderamin`.`report_attribute` `a` on ((`a`.`report_id` = `r`.`report_id`)))
;