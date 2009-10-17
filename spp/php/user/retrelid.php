<?php

/* 
 * Copyright (c) 2007, Adrian Thurston <thurston@complang.org>
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

// If not the owner we cannot become someone's friend.
if ( $_SESSION['auth'] != 'owner' )
	exit("You must login before you can become someone's friend.");

$identity = $_GET['identity'];
$fr_reqid = $_GET['fr_reqid'];

$fp = fsockopen( 'localhost', $CFG_PORT );
if ( !$fp )
	exit(1);

$send = 
	"SPP/0.1 $CFG_URI\r\n" . 
	"comm_key $CFG_COMM_KEY\r\n" .
	"relid_response $USER_NAME $fr_reqid $identity\r\n";
fwrite($fp, $send);

$res = fgets($fp);

if ( ereg("^OK ([-A-Za-z0-9_]+)", $res, $regs) ) {
	$arg_identity = 'identity=' . urlencode( $USER_URI );
	$arg_reqid = 'reqid=' . urlencode( $regs[1] );

	header("Location: ${identity}frfinal.php?${arg_identity}&${arg_reqid}" );
}
else {
	echo $res;
}

?>
