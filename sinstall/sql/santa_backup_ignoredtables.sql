DROP TABLE IF EXISTS `%PREFIX%_backup_ignoredtables`;
CREATE TABLE `%PREFIX%_backup_ignoredtables` (
  `ruleid` int(5) unsigned default NULL,
  `tablename` varchar(255) NOT NULL,
  UNIQUE KEY `tablename` (`tablename`,`ruleid`),
  KEY `ruleid` (`ruleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_backup');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_backup_ignoredexts');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_backup_ignoredpaths');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_backup_ignoredtables');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_backup_rules');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_search1_docs');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_search1_ignored');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_search1_index');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_search1_words');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_domain');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_host');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_index');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_partner');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_partner_eregs');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_referer');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_robot');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_search');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_uri');
INSERT INTO `%PREFIX%_backup_ignoredtables` VALUES ('2','santa_stat_word');
