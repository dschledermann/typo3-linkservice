
#
# Table structure for table 'tx_linkservice_linkcache'
#
CREATE TABLE tx_linkservice_linkcache (
	expires int(11) unsigned DEFAULT '0' NOT NULL,
	url mediumtext NOT NULL,
	http_status int(11) unsigned DEFAULT '200' NOT NULL,
	location mediumtext DEFAULT '' NOT NULL,
	PRIMARY KEY url (url(256))
);

#
# Table structure for table 'tx_linkservice_field_status'
#
CREATE TABLE tx_linkservice_field_status (
	lastcheck int(11) unsigned DEFAULT '0' NOT NULL,
	field_name varchar(255) NOT NULL,
	table_name varchar(255) NOT NULL,
	record_uid int(11) DEFAULT '0' NOT NULL,
	KEY lastcheck
);
