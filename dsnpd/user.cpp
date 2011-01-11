/*
 * Copyright (c) 2008-2009, Adrian Thurston <thurston@complang.org>
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

#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <mysql/mysql.h>
#include <string.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>
#include <openssl/sha.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <sys/wait.h>

#include "dsnp.h"
#include "encrypt.h"
#include "string.h"
#include "error.h"

User::User( MYSQL *mysql, const char *user )
: 
	mysql(mysql),
	user(user),
	haveId(false),
	_id(-1)
{}


User::User( MYSQL *mysql, long long _id )
: 
	mysql(mysql),
	haveId(true),
	_id(_id)
{
	DbQuery find( mysql,
		"SELECT user FROM user WHERE id = %L", 
		_id );

	if ( find.rows() != 1 )
		throw UserIdInvalid();
	
	MYSQL_ROW row = find.fetchRow();
	user = strdup( row[0] );
}

long long User::id()
{
	if ( !haveId ) {
		DbQuery find( mysql,
			"SELECT id FROM user WHERE user = %e", 
			user() );

		if ( find.rows() > 0 ) {
			_id = strtoll( find.fetchRow()[0], 0, 10 );
			haveId = true;
		}
	}
	return _id;
}

Relationship::Relationship( MYSQL *mysql, User &user, int type, Identity &identity )
:
	mysql(mysql),
	haveId(false)
{
	user_id = user.id();
	this->type = type;
	identity_id = identity.id();
	defaultName.set( identity.user() );
}

long long Relationship::id()
{
	if ( !haveId ) {
		/* Always try to insert. Ignore failures. */
		DbQuery insert( mysql, 
			"INSERT IGNORE INTO relationship "
			"( user_id, identity_id ) "
			"VALUES ( %L, %L )",
			user_id, identity_id
		);

		if ( insert.affectedRows() > 0 ) {
			_id = lastInsertId( mysql );

			DbQuery( mysql, 
				"UPDATE relationship SET type = %l, name = %e WHERE id = %L",
				type, defaultName.data, _id
			);
		}
		else {
			DbQuery find( mysql,
				"SELECT id FROM relationship "
				"WHERE user_id = %L AND identity_id = %L ", 
				user_id, identity_id );
			MYSQL_ROW row = find.fetchRow();
			_id = strtoll( row[0], 0, 10 );
		}

		haveId = true;
	}

	return _id;
}

FriendClaim::FriendClaim( MYSQL *mysql, User &user, Identity &identity )
:
	mysql(mysql)
{
	DbQuery query( mysql,
		"SELECT id, relationship_id, put_relid, get_relid "
		"FROM friend_claim "
		"WHERE user_id = %L AND identity_id = %L",
		user.id(), identity.id() );

	if ( query.rows() == 0 )
		throw FriendClaimNotFound();

	userId = user.id();
	identityId = identity.id();

	MYSQL_ROW row = query.fetchRow();
	id = parseId( row[0] );
	relationshipId = parseId( row[1] );
	putRelid = row[2];
	getRelid = row[3];
}

FriendClaim::FriendClaim( MYSQL *mysql, const char *getRelid )
:
	mysql(mysql)
{
	DbQuery query( mysql,
		"SELECT id, user_id, identity_id, relationship_id, put_relid, get_relid "
		"FROM friend_claim "
		"WHERE get_relid = %e",
		getRelid );
	
	if ( query.rows() == 0 )
		throw InvalidRelid();

	MYSQL_ROW row = query.fetchRow();

	id = parseId( row[0] );
	userId = parseId( row[1] );
	identityId = parseId( row[2] );
	relationshipId = parseId( row[3] );
	putRelid = row[4];
	getRelid = row[5];
}

void Server::newUser( MYSQL *mysql, const char *user, const char *pass )
{
	String iduri( "%s%s/", c->CFG_URI, user );

	/* Generate a new key. Do this first so we don't have to remove the user if
	 * it fails. */
	RSA *rsa = RSA_generate_key( 1024, RSA_F4, 0, 0 );
	if ( rsa == 0 )
		throw RsaKeyGenError( ERR_get_error() );

	u_char passSalt[SALT_SIZE];
	RAND_bytes( passSalt, SALT_SIZE );
	String passSaltStr = binToBase64( passSalt, SALT_SIZE );

	/* Hash the password. */
	String passHashed = passHash( passSalt, pass );

	/*
	 * The User
	 */

	/* First try to make the new user. */
	DbQuery insert( mysql,
			"INSERT IGNORE INTO user ( user, pass_salt, pass ) "
			"VALUES ( %e, %e, %e ) ",
			user, passSaltStr.data, passHashed.data );
	if ( insert.affectedRows() == 0 )
		throw UserExists( user );

	long long userId = lastInsertId( mysql );

	/*
	 * User Keys
	 */

	/* Extract the components to hex strings. */
	String n = bnToBase64( rsa->n );
	String e = bnToBase64( rsa->e );
	String d = bnToBase64( rsa->d );
	String p = bnToBase64( rsa->p );
	String q = bnToBase64( rsa->q );
	String dmp1 = bnToBase64( rsa->dmp1 );
	String dmq1 = bnToBase64( rsa->dmq1 );
	String iqmp = bnToBase64( rsa->iqmp );
	RSA_free( rsa );

	DbQuery( mysql,
			"INSERT INTO user_keys "
			"	( rsa_n, rsa_e, rsa_d, rsa_p, rsa_q, rsa_dmp1, rsa_dmq1, rsa_iqmp ) "
			"VALUES ( %e, %e, %e, %e, %e, %e, %e, %e );",
			n.data, e.data, d.data, p.data, q.data, dmp1.data, dmq1.data, iqmp.data );

	long long userKeysId = lastInsertId( mysql );

	/*
	 * Idenity
	 */
	String hash = makeIduriHash( iduri.data );
	DbQuery( mysql,
			"INSERT IGNORE INTO identity ( iduri, hash ) "
			"VALUES ( %e, %e ) ",
			iduri.data, hash.data );

	DbQuery identityQuery( mysql,
			"SELECT id FROM identity WHERE iduri = %e",
			iduri.data );
	long long identityId = strtoll( identityQuery.fetchRow()[0], 0, 10 );

	/*
	 * Self-Relationship
	 */
	DbQuery( mysql,
			"INSERT INTO relationship ( user_id, type, identity_id, name ) "
			"VALUES ( %L, %l, %L, %e ) ",
			userId, REL_TYPE_SELF, identityId, user );

	long long relationshipId = lastInsertId( mysql );


	/*
	 * Network
	 */
	unsigned char distName[RELID_SIZE];
	RAND_bytes( distName, RELID_SIZE );
	String distNameStr = binToBase64( distName, RELID_SIZE );

	/* Always, try to insert. Ignore failures. FIXME: need to loop on the
	 * random selection here. */
	DbQuery ( mysql, 
			"INSERT IGNORE INTO network "
			"( user_id, type, dist_name, key_gen ) "
			"VALUES ( %L, %l, %e, 1 )",
			userId, NET_TYPE_PRIMARY, distNameStr.data );

	long long networkId = lastInsertId( mysql );
	newBroadcastKey( mysql, networkId, 1 );

	DbQuery( mysql,
			"UPDATE user "
			"SET user_keys_id = %L, identity_id = %L, relationship_id = %L, network_id = %L "
			"WHERE id = %L ",
			userKeysId, identityId, relationshipId, networkId, userId );

	String photoDir( PREFIX "/var/lib/dsnp/%s/data", c->name );

	/* Create the photo directory. */
	String photoDirCmd( "umask 0002; mkdir %s/%s", photoDir.data, user );
	message( "executing photo creating command: %s\n", photoDirCmd.data );
	int res = system( photoDirCmd.data );
	if ( res < 0 )
		error( "photo dir creation failed with %s\n", strerror(errno) );

	bioWrap->printf( "OK\r\n" );
}

