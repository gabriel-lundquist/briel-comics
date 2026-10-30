USE breel_comics;

CREATE TABLE tag (
  tagid int unsigned NOT NULL AUTO_INCREMENT,
  name varchar(32) DEFAULT NULL,
  PRIMARY KEY (tagid)
);

CREATE TABLE aspectratio (
    ratio varchar(8) NOT NULL COMMENT 'Ratio description width:height',
    ratioid smallint unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (ratioid)
);

CREATE TABLE blog (
    blogid int unsigned NOT NULL AUTO_INCREMENT,
    blogtext text,
    postdate datetime DEFAULT NULL,
    PRIMARY KEY (blogid),
    FULLTEXT KEY searchblog (blogtext)
);

CREATE TABLE colorstyle (
    colorstyleid int NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    colorstyledesc varchar(127) DEFAULT NULL COMMENT 'Description of style colors',
    path varchar(511) DEFAULT NULL COMMENT 'Path to css file',
    PRIMARY KEY (colorstyleid)
);

CREATE TABLE comicupdate (
    updateid int unsigned NOT NULL AUTO_INCREMENT,
    title varchar(32) DEFAULT NULL,
    postdate datetime DEFAULT NULL,
    updatedesc text,
    PRIMARY KEY (updateid),
    FULLTEXT KEY searchdesc (updatedesc)
);

CREATE TABLE comicupdateorder (
    sourceid int unsigned DEFAULT NULL,
    targetid int unsigned DEFAULT NULL,
    UNIQUE KEY sourceid (sourceid),
    UNIQUE KEY targetid (targetid),
    CONSTRAINT updateorder_targetref FOREIGN KEY (targetid) REFERENCES comicupdate (updateid) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT updateordersourceref FOREIGN KEY (sourceid) REFERENCES comicupdate (updateid) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE spread (
    spreadid int unsigned NOT NULL AUTO_INCREMENT,
    spreadtype varchar(32) DEFAULT NULL,
    PRIMARY KEY (spreadid)
);

CREATE TABLE page (
    pageid int unsigned NOT NULL AUTO_INCREMENT,
    title varchar(32) DEFAULT NULL,
    postdate datetime DEFAULT NULL,
    path varchar(512) DEFAULT NULL,
    imagedesc text,
    spreadid int unsigned NOT NULL DEFAULT '1',
    colorstyleid int NOT NULL DEFAULT '1',
    note text,
    PRIMARY KEY (pageid),
    KEY page_spreadref (spreadid),
    KEY page_colorstyleref (colorstyleid),
    FULLTEXT KEY descsearch (imagedesc),
    CONSTRAINT page_colorstyleref FOREIGN KEY (colorstyleid) REFERENCES colorstyle (colorstyleid),
    CONSTRAINT page_spreadref FOREIGN KEY (spreadid) REFERENCES spread (spreadid)
);

CREATE TABLE comicupdatepage (
    updateid int unsigned NOT NULL,
    pageid int unsigned NOT NULL,
    PRIMARY KEY (updateid, pageid),
    KEY comicupdatepage_pageref (pageid),
    CONSTRAINT comicupdatepage_pageref FOREIGN KEY (pageid) REFERENCES page (pageid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT comicupdatepage_updateref FOREIGN KEY (updateid) REFERENCES comicupdate (updateid) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE contwarning (
    contwarningid int unsigned NOT NULL AUTO_INCREMENT,
    name varchar(32) DEFAULT NULL,
    PRIMARY KEY (contwarningid)
);

CREATE TABLE contwarningpage (
    contwarningid int unsigned NOT NULL,
    pageid int unsigned NOT NULL,
    PRIMARY KEY (contwarningid, pageid),
    KEY contwarningpage_pageref (pageid),
    CONSTRAINT contwarningpage_contwarningref FOREIGN KEY (contwarningid) REFERENCES contwarning (contwarningid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT contwarningpage_pageref FOREIGN KEY (pageid) REFERENCES page (pageid) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE filepurpose (
    purpose varchar(16) NOT NULL,
    purposeid smallint unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (purposeid)
);

CREATE TABLE file (
    fileid int unsigned NOT NULL AUTO_INCREMENT,
    path varchar(512) DEFAULT NULL,
    width smallint unsigned DEFAULT NULL,
    height smallint unsigned DEFAULT NULL,
    alttext text,
    uploaddate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifydate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    filetype varchar(8) DEFAULT NULL,
    filesize int unsigned DEFAULT NULL,
    pageid int unsigned DEFAULT NULL,
    ratioid smallint unsigned DEFAULT NULL,
    purposeid smallint unsigned NOT NULL DEFAULT '3',
    PRIMARY KEY (fileid),
    KEY ratio_ref (ratioid),
    KEY pageindex (pageid),
    KEY purposeid_ref (purposeid),
    FULLTEXT KEY searchalt (alttext),
    CONSTRAINT purposeid_ref FOREIGN KEY (purposeid) REFERENCES filepurpose (purposeid) ON DELETE RESTRICT,
    CONSTRAINT ratio_ref FOREIGN KEY (ratioid) REFERENCES aspectratio (ratioid)
);

CREATE TABLE pageorder (
    sourceid int unsigned DEFAULT NULL,
    targetid int unsigned DEFAULT NULL,
    UNIQUE KEY sourceid (sourceid),
    UNIQUE KEY targetid (targetid),
    CONSTRAINT pageorder_sourceref FOREIGN KEY (sourceid) REFERENCES page (pageid) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT pageorder_targetref FOREIGN KEY (targetid) REFERENCES page (pageid) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE tagpage (
    tagid int unsigned NOT NULL,
    pageid int unsigned NOT NULL,
    PRIMARY KEY (tagid, pageid),
    KEY tagpage_tagref (tagid),
    KEY tagpage_pageref (pageid),
    CONSTRAINT tagpage_pageref FOREIGN KEY (pageid) REFERENCES page (pageid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT tagpage_tagref FOREIGN KEY (tagid) REFERENCES tag (tagid) ON DELETE CASCADE ON UPDATE CASCADE
);

USE breel_user_submitted;

CREATE TABLE searchcache (
    search varchar(256) NOT NULL,
    searchimgdesc varchar(2) NOT NULL DEFAULT '',
    matchexactly varchar(2) NOT NULL DEFAULT '',
    resultpageindex int unsigned NOT NULL DEFAULT '1',
    path varchar(512) DEFAULT NULL,
    PRIMARY KEY (
        search,
        searchimgdesc,
        matchexactly,
        resultpageindex
    )
) COMMENT = 'Cached search pages';

CREATE TABLE searchdata (
    search varchar(256) NOT NULL,
    searchimgdesc varchar(2) NOT NULL DEFAULT '',
    matchexactly varchar(2) NOT NULL DEFAULT '',
    numtimes int unsigned NOT NULL DEFAULT '1',
    firstsearched datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastsearched datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (
        search,
        searchimgdesc,
        matchexactly
    )
)  COMMENT = 'Records data about searches';