-- phpMyAdmin SQL Dump
-- version 3.2.0.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generato il: 19 apr, 2017 at 06:05 PM
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
-- Struttura della tabella `odt_reports`
--

DROP TABLE IF EXISTS `odt_reports`;
CREATE TABLE IF NOT EXISTS `odt_reports` (
  `ID` int(11) NOT NULL auto_increment COMMENT 'index auto',
  `page` char(60) NOT NULL COMMENT 'the destination page ',
  `position` int(11) unsigned NOT NULL COMMENT 'order index',
  `templateID` char(60) NOT NULL COMMENT 'the file name (.odt)',
  `show` varchar(500) default NULL COMMENT 'php: returns true|false',
  `outmode` enum('send','save','send_save') NOT NULL default 'send' COMMENT 'Document destination',
  `outfilepath` varchar(200) default NULL COMMENT 'php: return file name',
  `shortName` char(250) default NULL COMMENT 'php: return short name',
  `description` varchar(500) default NULL COMMENT 'php: returns description (HTML)',
  `key1type` enum('hidden','HTML','list','radio','date','foreach') default NULL COMMENT 'the key type',
  `key1name` char(60) default NULL COMMENT 'the key name (HTML)',
  `key1value` varchar(2500) default NULL COMMENT 'php|SELECT query',
  `key2type` enum('hidden','HTML','list','radio','date','foreach') default NULL COMMENT 'the key type',
  `key2name` char(60) default NULL COMMENT 'the key name (HTML)',
  `key2value` varchar(2500) default NULL COMMENT 'php|SELECT query',
  `key3type` enum('hidden','HTML','list','radio','date') default NULL COMMENT 'the key type',
  `key3name` char(60) default NULL COMMENT 'the key name (HTML)',
  `key3value` varchar(2500) default NULL COMMENT 'php|SELECT query',
  `key4type` enum('hidden','HTML','list','radio','date') default NULL COMMENT 'the key type',
  `key4name` char(60) default NULL COMMENT 'the key name (HTML)',
  `key4value` varchar(2500) default NULL COMMENT 'php|SELECT query',
  `key5type` enum('hidden','HTML','list','radio','date') default NULL COMMENT 'the key type',
  `key5name` char(60) default NULL COMMENT 'php|SELECT query',
  `key5value` varchar(2500) default NULL,
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `position` (`position`),
  KEY `page` (`page`,`position`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Definizione reports';
