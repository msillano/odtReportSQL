-- phpMyAdmin SQL Dump
-- version 3.2.0.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generato il: 19 apr, 2017 at 06:03 PM
-- Versione MySQL: 5.0.45
-- Versione PHP: 5.3.0

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `odtphp`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `odt_queries`
--

DROP TABLE IF EXISTS `odt_queries`;
CREATE TABLE `odt_queries` (
  `ID` int(11) NOT NULL auto_increment COMMENT 'key auto',
  `templateID` char(80) NOT NULL COMMENT 'template name',
  `block` char(40) default NULL COMMENT 'only if block, name',
  `parent` char(40) default NULL COMMENT 'only if nested block, name',
  `query` varchar(16000) NOT NULL COMMENT 'SQL query',
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `position` (`templateID`,`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='fields and blocks queries definitions';
