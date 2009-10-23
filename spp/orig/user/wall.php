<?php

/* 
 * Copyright (c) 2009, Adrian Thurston <thurston@complang.org>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

include('../config.php');
include('lib/session.php');

requireFriend();
$BROWSER_ID = $_SESSION['identity'];

/* User message. */
$message = $_POST['message'];
$len = strlen( $message );

# Connect to the database.
$conn = mysql_connect($CFG_DB_HOST, $CFG_DB_USER, $CFG_ADMIN_PASS) or die 
	('Could not connect to database');
mysql_select_db($CFG_DB_DATABASE) or die
	('Could not select database ' . $CFG_DB_DATABASE);

$subjectId = "$CFG_URI$USER_NAME/";
$query = sprintf(
	"INSERT INTO published ( user, author_id, subject_id, type, message ) " .
	"VALUES ( '%s', '%s', '%s', '%s', '%s' );",
	mysql_real_escape_string($USER_NAME),
	mysql_real_escape_string($BROWSER_ID),
	mysql_real_escape_string($subjectId),
	mysql_real_escape_string("MSG"),
    mysql_real_escape_string($message)
);

mysql_query( $query ) or die('Query failed: ' . mysql_error());

$fp = fsockopen( 'localhost', $CFG_PORT );
if ( !$fp )
	exit(1);

$token = $_SESSION['token'];
$hash = $_SESSION['hash'];

$send = 
	"SPP/0.1 $CFG_URI\r\n" . 
	"comm_key $CFG_COMM_KEY\r\n" .
	"submit_remote_broadcast $USER_NAME $BROWSER_ID $hash $token BRD $len\r\n";

fwrite( $fp, $send );
fwrite( $fp, $message, $len );
fwrite( $fp, "\r\n", 2 );

$res = fgets($fp);

if ( ereg("^OK", $res, $regs) )
	header("Location: ${USER_URI}" );
else
	echo $res;