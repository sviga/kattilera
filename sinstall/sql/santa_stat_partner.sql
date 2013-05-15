DROP TABLE IF EXISTS `%PREFIX%_stat_partner`;
CREATE TABLE `%PREFIX%_stat_partner` (
  `IDPartner` int(10) unsigned NOT NULL auto_increment,
  `partner` varchar(64) NOT NULL default '',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDPartner`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='Статистика - статистика партнёров';
INSERT INTO `%PREFIX%_stat_partner` VALUES ('1','Яндекс','1204119440');
INSERT INTO `%PREFIX%_stat_partner` VALUES ('2','Рамблер','1204119564');
INSERT INTO `%PREFIX%_stat_partner` VALUES ('3','Google','1204119584');
