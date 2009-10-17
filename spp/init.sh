#!/bin/bash
#
# Copyright (c) 2007, 2008, Adrian Thurston <thurston@complang.org>
#
# Permission to use, copy, modify, and/or distribute this software for any
# purpose with or without fee is hereby granted, provided that the above
# copyright notice and this permission notice appear in all copies.
#
# THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
# WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
# MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
# ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
# WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
# ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
# OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.

PHP_CONF=php/config.php

#
# Config for all sites
#

# Make a key for communication from the frontend to backend.
CFG_COMM_KEY=`head -c 24 < /dev/urandom | xxd -p`

# Port for the server.
CFG_PORT=7085

# Backup the conf before wrting it. 
if test -f $PHP_CONF; then
	mv $PHP_CONF ${PHP_CONF}.bak
fi


# start the config files
{ echo '<?php'; echo; } > $PHP_CONF

cat << EOF
Please choose a password to protect the new database users with. Every site you
create during this run will have a new user with this password. The user name
will be derived from the site name.

EOF

while true; do
	read -s -p 'password: ' CFG_ADMIN_PASS; echo
	read -s -p '   again: ' AGAIN; echo

	if [ "$CFG_ADMIN_PASS" != "$AGAIN" ]; then
		echo; echo error: passwords do not match; echo
		continue
	fi

	if [ -z "$CFG_ADMIN_PASS" ]; then
		echo; echo error: password must not be empty; echo 
		continue
	fi
	break;
done

#
# Start reading installations.
#

echo
echo "Thank you. You can now add sites."

# Clear the database init file
rm -f init.sql 
while true; do

echo
echo "Please give a short name for the site. It should contain only letters and"
echo "numbers and should begin with a letter. This name will be used internally to"
echo "identify the installation. It will not be visible to the user. If you are"
echo "finished giving installations just press enter."
echo

while true; do 
	read -p 'installation name: ' NAME

	if [ -z "$NAME" ]; then
		done=yes;
		break;
	fi
	if echo $NAME | grep '^[a-zA-Z][a-zA-Z0-9]*$' >/dev/null; then
		break
	fi
	echo; echo error: name did not validate; echo
done 

[ -n "$done" ] && break;

echo
echo "Please give the Uniform Resource Identifier (URI) of this site. This will be"
echo "the installations public name. It should start with 'https://' and end with '/'."
echo

while true; do 
	read -p 'installation uri: ' URI_IN

	if echo $URI_IN | grep '^https:\/\/.*\/$' >/dev/null; then
		break
	fi
	echo; echo error: uri did not validate; echo
done 

CFG_HOST=`echo $URI_IN | sed 's/^https:\/\///; s/\/.*$//;'`
CFG_URI=$URI_IN;
CFG_PATH=`echo $URI_IN | sed 's/^https:\/\///; s/^[^\/]*//;'`

#
# Init the database.
#

cat >> init.sql << EOF
DROP USER '${NAME}_fe_owner'@'localhost';
CREATE USER '${NAME}_fe_owner'@'localhost' IDENTIFIED BY '$CFG_ADMIN_PASS';

DROP DATABASE ${NAME}_fe;
CREATE DATABASE ${NAME}_fe;
GRANT ALL ON ${NAME}_fe.* TO '${NAME}_fe_owner'@'localhost';
USE ${NAME}_fe;
CREATE TABLE user2 ( 
	user VARCHAR(20), 
	email VARCHAR(50)
);

CREATE TABLE public_key (
	identity TEXT,
	rsa_n TEXT,
	rsa_e TEXT
);

CREATE TABLE relid_request (
	for_user VARCHAR(20),
	from_id TEXT,
	requested_relid VARCHAR(48),
	reqid VARCHAR(48),
	msg_sym TEXT
);

CREATE TABLE relid_response (
	from_id TEXT,
	requested_relid VARCHAR(48),
	returned_relid VARCHAR(48),
	reqid VARCHAR(48),
	msg_sym TEXT
);

CREATE TABLE friend_request2 (
	for_user VARCHAR(20), 
	from_id TEXT,
	reqid VARCHAR(48),
	requested_relid VARCHAR(48),
	returned_relid VARCHAR(48)
);

CREATE TABLE pending_friend_request (
	from_user VARCHAR(20),
	for_id TEXT
);

CREATE TABLE put_broadcast_key (
	user VARCHAR(20), 
	generation BIGINT,
	broadcast_key VARCHAR(48)
);

CREATE TABLE friend_claim (
	user VARCHAR(20), 
	friend_id TEXT,
	friend_salt VARCHAR(48),
	friend_hash VARCHAR(48),
	put_relid VARCHAR(48),
	get_relid VARCHAR(48)
);

CREATE TABLE put_tree (
	user VARCHAR(20),
	friend_id TEXT,
	generation BIGINT,
	root BOOL,
	forward1 TEXT,
	forward2 TEXT
);

CREATE TABLE get_tree (
	user VARCHAR(20),
	friend_id TEXT,
	generation BIGINT,
	broadcast_key VARCHAR(48),
	site1 TEXT,
	site2 TEXT,
	site_ret TEXT,
	relid1 VARCHAR(48),
	relid2 VARCHAR(48),
	relid_ret VARCHAR(48)
);

CREATE TABLE ftoken_request (
	user VARCHAR(20), 
	from_id TEXT,
	token VARCHAR(48),
	reqid VARCHAR(48),
	msg_sym TEXT
);

CREATE TABLE broadcast_queue (
	to_site TEXT,
	relid VARCHAR(48),
	generation BIGINT,
	message TEXT
);

CREATE TABLE unack_forward (
	relid  VARCHAR(48),
	children INT,
	generation BIGINT,
	seq_num BIGINT
);

CREATE TABLE message_queue (
	from_user VARCHAR(20),
	to_id TEXT,
	relid VARCHAR(48),
	message TEXT
);

CREATE TABLE received ( 
	for_user VARCHAR(20),
	author_id TEXT,
	subject_id TEXT,
	seq_num BIGINT,
	time_published TIMESTAMP,
	time_received TIMESTAMP,
	type CHAR(4),
	resource_id BIGINT,
	message BLOB
);

CREATE TABLE published (
	user VARCHAR(20),
	author_id TEXT,
	subject_id TEXT,
	seq_num BIGINT NOT NULL AUTO_INCREMENT,
	time_published TIMESTAMP,
	type CHAR(4),
	resource_id BIGINT,
	message BLOB,
	PRIMARY KEY(user, seq_num)
);

CREATE TABLE remote_published (
	user VARCHAR(20),
	author_id TEXT,
	subject_id TEXT,
	time_published TIMESTAMP,
	type CHAR(4),
	resource_id BIGINT,
	message BLOB
);

CREATE TABLE login_token (
	user VARCHAR(20),
	login_token VARCHAR(48),
	expires TIMESTAMP
);

CREATE TABLE flogin_token (
	user VARCHAR(20),
	identity TEXT,
	login_token VARCHAR(48),
	expires TIMESTAMP
);

CREATE TABLE remote_flogin_token (
	user VARCHAR(20),
	identity TEXT,
	login_token VARCHAR(48)
);

CREATE TABLE image (
	user VARCHAR(20),
	seq_num BIGINT NOT NULL AUTO_INCREMENT,
	rows INT,
	cols INT,
	mime_type VARCHAR(32),

	PRIMARY KEY(user, seq_num)
);

EOF

#
# Add the site to the PHP config file.
#

cat >> $PHP_CONF << EOF
if ( strpos( \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'], '$CFG_HOST$CFG_PATH' ) === 0 ) {
	\$CFG_URI = '$CFG_URI';
	\$CFG_HOST = '$CFG_HOST';
	\$CFG_PATH = '$CFG_PATH';
	\$CFG_DB_HOST = 'localhost';
	\$CFG_DB_USER = '${NAME}_fe_owner';
	\$CFG_DB_DATABASE = '${NAME}_fe';
	\$CFG_ADMIN_PASS = '$CFG_ADMIN_PASS';
	\$CFG_COMM_KEY = '$CFG_COMM_KEY';
	\$CFG_PORT = $CFG_PORT;
	\$CFG_USE_RECAPTCHA = SET_THIS;
	\$CFG_RC_PUBLIC_KEY = SET_THIS;
	\$CFG_RC_PRIVATE_KEY = SET_THIS;
	\$CFG_PHOTO_DIR = SET_THIS;
	\$CFG_IM_CONVERT = SET_THIS;
}

EOF

done

echo
echo "Thank you, now initializing the database. Please login as root@localhost."
echo "Please ignore any \"Can't drop\" messages."
echo

mysql -f -h localhost -u root -p < init.sql
rm init.sql

# Finish the PHP config file.
cat >> $PHP_CONF << EOF
if ( !\$CFG_URI ) {
	die("config.php: could not select installation\n");
}

if ( get_magic_quotes_gpc() ) {
	die("the SPP software assumes PHP magic quotes to be off\n");
}

\$USER_NAME = isset( \$_GET['u'] ) ? \$_GET['u'] : "";
\$USER_PATH = "\${CFG_PATH}\$USER_NAME/";
\$USER_URI = "\${CFG_URI}\$USER_NAME/";

include('error.php');

?>
EOF

