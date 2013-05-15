DROP TABLE IF EXISTS `%PREFIX%_backup_ignoredexts`;
CREATE TABLE `%PREFIX%_backup_ignoredexts` (
  `ruleid` int(5) unsigned default NULL,
  `ext` varchar(255) NOT NULL,
  UNIQUE KEY `ext` (`ext`,`ruleid`),
  KEY `ruleid` (`ruleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_backup_ignoredexts` VALUES ('2','avi');
INSERT INTO `%PREFIX%_backup_ignoredexts` VALUES ('2','swf');
