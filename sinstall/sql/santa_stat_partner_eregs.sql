DROP TABLE IF EXISTS `%PREFIX%_stat_partner_eregs`;
CREATE TABLE `%PREFIX%_stat_partner_eregs` (
  `IDPEregs` int(10) unsigned NOT NULL auto_increment,
  `IDPartner` int(10) unsigned NOT NULL default '0',
  `preg_partner` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`IDPEregs`),
  KEY `IDPartner` (`IDPartner`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='Статистика - настройки партнёров';
INSERT INTO `%PREFIX%_stat_partner_eregs` VALUES ('1','1','yandex');
INSERT INTO `%PREFIX%_stat_partner_eregs` VALUES ('2','2','rambler');
INSERT INTO `%PREFIX%_stat_partner_eregs` VALUES ('3','3','google');
