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

# Requires config.php to have beein included.

session_name("SPPSESSID");

# Only resume sessions or start a new one when *a* session variable is provided
# Note that it might not be the right one.
if ( isset( $_COOKIE[session_name()] ) )
	session_start();

function requireFriend()
{
	if ( !isset( $_SESSION['auth'] ) || $_SESSION['auth'] != 'friend' )
		exit("You do not have permission to access this page\n");
}

function requireOwner()
{
	if ( !isset( $_SESSION['auth'] ) || $_SESSION['auth'] != 'owner' )
		exit("You do not have permission to access this page\n");
}

function requireFriendOrOwner()
{
	if ( !isset( $_SESSION['auth'] ) || ( $_SESSION['auth'] != 'friend' && $_SESSION['auth'] != 'owner' ) )
		exit("You do not have permission to access this page\n");
}

function requireFriendOrOwnerLogin()
{
	global $USER_URI;
	if ( !isset( $_SESSION['auth'] ) || 
			( $_SESSION['auth'] != 'friend' && $_SESSION['auth'] != 'owner' ) )
	{
		if ( isset($_GET['h']) ) {
			$dest_uri = $_SERVER['REQUEST_URI'];
			$dest = urlencode( substr( $dest_uri, 0, strpos($dest_uri, '?') ) );
			header ( "Location: ${USER_URI}sflogin.php?h=" . $_GET['h'] . "&d=" . $dest );
			exit;
		}
		else {
			exit("You do not have permission to access this page\n");
		}
	}
}

