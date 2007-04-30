CREATE TABLE browscap (
  useragent varchar(255) PRIMARY KEY,
  data longblob NOT NULL
);

CREATE TABLE browscap_statistics (
  parent VARCHAR(255) PRIMARY KEY,
  counter integer DEFAULT 0 NOT NULL,
  is_crawler smallint DEFAULT 0 NOT NULL
);

CREATE TABLE cache_browscap (
  cid varchar(255) NOT NULL default '' PRIMARY KEY,
  data longblob,
  expire int(11) NOT NULL default '0' KEY,
  created int(11) NOT NULL default '0',
  headers text
)