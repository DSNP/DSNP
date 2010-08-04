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

#include "dsnp.h"
#include "encrypt.h"
#include "string.h"
#include "disttree.h"

#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <mysql.h>
#include <string.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>
#include <openssl/sha.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <sys/wait.h>


void setConfigByUri( const char *uri )
{
	c = config_first;
	while ( c != 0 && strcmp( c->CFG_URI, uri ) != 0 )
		c = c->next;

	if ( c == 0 ) {
		fatal( "bad site\n" );
		exit(1);
	}
}

void setConfigByName( const char *name )
{
	c = config_first;
	while ( c != 0 && strcmp( c->name, name ) != 0 )
		c = c->next;

	if ( c == 0 ) {
		fatal( "bad site\n" );
		exit(1);
	}
}

char *strend( char *s )
{
	return s + strlen(s);
}

char *get_site( const char *identity )
{
	char *res = strdup( identity );
	char *last = res + strlen(res) - 1;
	while ( last[-1] != '/' )
		last--;
	*last = 0;
	return res;
}

char *bin2hex( unsigned char *data, long len )
{
	char *res = (char*)malloc( len*2 + 1 );
	for ( int i = 0; i < len; i++ ) {
		unsigned char l = data[i] & 0xf;
		if ( l < 10 )
			res[i*2+1] = '0' + l;
		else
			res[i*2+1] = 'a' + (l-10);

		unsigned char h = data[i] >> 4;
		if ( h < 10 )
			res[i*2] = '0' + h;
		else
			res[i*2] = 'a' + (h-10);
	}
	res[len*2] = 0;
	return res;
}

long hex2bin( unsigned char *dest, long len, const char *src )
{
	long slen = strlen( src ) / 2;
	if ( len < slen )
		return 0;
	
	for ( int i = 0; i < slen; i++ ) {
		char l = src[i*2+1];
		char h = src[i*2];

		if ( '0' <= l && l <= '9' )
			dest[i] = l - '0';
		else
			dest[i] = 10 + (l - 'a');
			
		if ( '0' <= h && h <= '9' )
			dest[i] |= (h - '0') << 4;
		else
			dest[i] |= (10 + (h - 'a')) << 4;
	}
	return slen;
}

AllocString bnToBase64( const BIGNUM *n )
{
	long len = BN_num_bytes(n);
	u_char *bin = new u_char[len];
	BN_bn2bin( n, bin );
	AllocString b64 = binToBase64( bin, len );
	delete[] bin;
	return b64;
}

BIGNUM *base64ToBn( const char *base64 )
{
	u_char *bin = new u_char[strlen(base64)];
	long len = base64ToBin( bin, base64, strlen(base64) );
	BIGNUM *bn = BN_bin2bn( bin, len, 0 );
	delete[] bin;
	return bn;
}

AllocString passHash( const u_char *salt, const char *pass )
{
	unsigned char hash[SHA_DIGEST_LENGTH];
	u_char *pass_comb = new u_char[SALT_SIZE + strlen(pass)];
	memcpy( pass_comb, salt, SALT_SIZE );
	memcpy( pass_comb + SALT_SIZE, pass, strlen(pass) );
	SHA1( pass_comb, SALT_SIZE+strlen(pass), hash );
	return binToBase64( hash, SHA_DIGEST_LENGTH );
}


CurrentPutKey::CurrentPutKey( MYSQL *mysql, const char *user, const char *group )
{
	DbQuery query( mysql, 
		"SELECT network.id, "
		"	network.key_gen, "
		"	network.tree_gen_low, "
		"	network.tree_gen_high, "
		"	put_broadcast_key.broadcast_key "
		"FROM user "
		"JOIN network "
		"ON user.id = network.user_id "
		"JOIN put_broadcast_key "
		"ON network.id = put_broadcast_key.network_id "
		"WHERE user.user = %e AND "
		"	network.name = %e AND "
		"	network.key_gen = put_broadcast_key.generation ", 
		user, group );
	
	if ( query.rows() == 0 )
		fatal( "failed to get current put broadcast key for user %s and group %s\n", user, group );
	
	MYSQL_ROW row = query.fetchRow();
	networkId = strtoll( row[0], 0, 10 );
	keyGen = strtoll( row[1], 0, 10 );
	treeGenLow = strtoll( row[2], 0, 10 );
	treeGenHigh = strtoll( row[3], 0, 10 );
	broadcastKey.set( row[4] );
}

void newBroadcastKey( MYSQL *mysql, long long networkId, long long generation )
{
	/* Generate the relationship and request ids. */
	unsigned char broadcast_key[RELID_SIZE];
	RAND_bytes( broadcast_key, RELID_SIZE );
	const char *bk = binToBase64( broadcast_key, RELID_SIZE );

	DbQuery( mysql, 
		"INSERT INTO put_broadcast_key "
		"( network_id, generation, broadcast_key ) "
		"VALUES ( %L, %L, %e ) ",
		networkId, generation, bk );
}

void publicKey( MYSQL *mysql, const char *user )
{
	MYSQL_RES *result;
	MYSQL_ROW row;

	/* Query the user. */
	exec_query( mysql, "SELECT rsa_n, rsa_e FROM user WHERE user = %e", user );

	/* Check for a result. */
	result = mysql_store_result( mysql );
	row = mysql_fetch_row( result );
	if ( !row ) {
		BIO_printf( bioOut, "ERROR user not found\r\n" );
		goto free_result;
	}

	/* Everythings okay. */
	BIO_printf( bioOut, "OK %s %s\n", row[0], row[1] );

free_result:
	mysql_free_result( result );
}

long open_inet_connection( const char *hostname, unsigned short port )
{
	sockaddr_in servername;
	hostent *hostinfo;
	long socketFd, connectRes;

	/* Create the socket. */
	socketFd = socket( PF_INET, SOCK_STREAM, 0 );
	if ( socketFd < 0 )
		return ERR_SOCKET_ALLOC;

	/* Lookup the host. */
	servername.sin_family = AF_INET;
	servername.sin_port = htons(port);
	hostinfo = gethostbyname (hostname);
	if ( hostinfo == NULL ) {
		::close( socketFd );
		return ERR_RESOLVING_NAME;
	}

	servername.sin_addr = *(in_addr*)hostinfo->h_addr;

	/* Connect to the listener. */
	connectRes = connect( socketFd, (sockaddr*)&servername, sizeof(servername) );
	if ( connectRes < 0 ) {
		::close( socketFd );
		return ERR_CONNECTING;
	}

	return socketFd;
}

long fetch_public_key_db( PublicKey &pub, MYSQL *mysql, const char *identity )
{
	long result = 0;
	long query_res;
	MYSQL_RES *select_res;
	MYSQL_ROW row;

	query_res = exec_query( mysql, 
		"SELECT rsa_n, rsa_e FROM public_key WHERE identity = %e", identity );
	if ( query_res != 0 ) {
		result = ERR_QUERY_ERROR;
		goto query_fail;
	}

	/* Check for a result. */
	select_res= mysql_store_result( mysql );
	row = mysql_fetch_row( select_res );
	if ( row ) {
		pub.n = strdup( row[0] );
		pub.e = strdup( row[1] );
		result = 1;
	}

	/* Done. */
	mysql_free_result( select_res );

query_fail:
	return result;
}

long store_public_key( MYSQL *mysql, const char *identity, PublicKey &pub )
{
	long result = 0, query_res;

	query_res = exec_query( mysql,
		"INSERT INTO public_key ( identity, rsa_n, rsa_e ) "
		"VALUES ( %e, %e, %e ) ", identity, pub.n, pub.e );

	if ( query_res != 0 ) {
		result = ERR_QUERY_ERROR;
		goto query_fail;
	}

query_fail:
	return result;
}

RSA *fetchPublicKey( MYSQL *mysql, const char *identity )
{
	PublicKey pub;
	RSA *rsa;

	Identity id( identity );
	id.parse();

	/* First try to fetch the public key from the database. */
	long result = fetch_public_key_db( pub, mysql, identity );
	if ( result < 0 )
		return 0;

	/* If the db fetch failed, get the public key off the net. */
	if ( result == 0 ) {
		char *site = get_site( identity );
		result = fetch_public_key_net( pub, site, id.host, id.user );
		if ( result < 0 )
			return 0;

		/* Store it in the db. */
		result = store_public_key( mysql, identity, pub );
		if ( result < 0 )
			return 0;
	}

	rsa = RSA_new();
	rsa->n = base64ToBn( pub.n );
	rsa->e = base64ToBn( pub.e );

	return rsa;
}


RSA *load_key( MYSQL *mysql, const char *user )
{
	long query_res;
	MYSQL_RES *result;
	MYSQL_ROW row;
	RSA *rsa;

	query_res = exec_query( mysql,
		"SELECT rsa_n, rsa_e, rsa_d, rsa_p, rsa_q, rsa_dmp1, rsa_dmq1, rsa_iqmp "
		"FROM user WHERE user = %e", user );

	if ( query_res != 0 )
		goto query_fail;

	/* Check for a result. */
	result = mysql_store_result( mysql );
	row = mysql_fetch_row( result );
	if ( !row ) {
		goto free_result;
	}

	/* Everythings okay. */
	rsa = RSA_new();
	rsa->n =    base64ToBn( row[0] );
	rsa->e =    base64ToBn( row[1] );
	rsa->d =    base64ToBn( row[2] );
	rsa->p =    base64ToBn( row[3] );
	rsa->q =    base64ToBn( row[4] );
	rsa->dmp1 = base64ToBn( row[5] );
	rsa->dmq1 = base64ToBn( row[6] );
	rsa->iqmp = base64ToBn( row[7] );

free_result:
	mysql_free_result( result );
query_fail:
	return rsa;
}

long sendMessageNow( MYSQL *mysql, bool prefriend, const char *from_user,
		const char *to_identity, const char *put_relid,
		const char *msg, char **result_msg )
{
	RSA *id_pub, *user_priv;
	Encrypt encrypt;
	int encrypt_res;

	id_pub = fetchPublicKey( mysql, to_identity );
	user_priv = load_key( mysql, from_user );

	encrypt.load( id_pub, user_priv );

	/* Include the null in the message. */
	encrypt_res = encrypt.signEncrypt( (u_char*)msg, strlen(msg) );

	message( "send_message_now sending to: %s\n", to_identity );
	return sendMessageNet( mysql, prefriend, from_user, to_identity, put_relid, encrypt.sym,
			strlen(encrypt.sym), result_msg );
}

AllocString make_id_hash( const char *salt, const char *identity )
{
	/* Make a hash for the identity. */
	long len = strlen(salt) + strlen(identity) + 1;
	char *total = new char[len];
	sprintf( total, "%s%s", salt, identity );
	unsigned char friend_hash[SHA_DIGEST_LENGTH];
	SHA1( (unsigned char*)total, len, friend_hash );
	return binToBase64( friend_hash, SHA_DIGEST_LENGTH );
}

void login( MYSQL *mysql, const char *user, const char *pass )
{
	const long lasts = LOGIN_TOKEN_LASTS;

	DbQuery login( mysql, 
		"SELECT user, pass_salt, pass, id_salt FROM user WHERE user = %e", user );

	if ( login.rows() == 0 ) {
		message( "login of %s failed, user not found\n", user );
		BIO_printf( bioOut, "ERROR\r\n" );
		return;
	}

	MYSQL_ROW row = login.fetchRow();
	char *salt_str = row[1];
	char *pass_str = row[2];
	char *id_salt_str = row[3];

	/* Hash the password using the sale found in the DB. */
	u_char pass_salt[SALT_SIZE];
	base64ToBin( pass_salt, salt_str, strlen(salt_str) );
	String pass_hashed = passHash( pass_salt, pass );

	/* Check the login. */
	if ( strcmp( pass_hashed, pass_str ) != 0 ) {
		message("login of %s failed, pass hashes do not match\n", user );
		BIO_printf( bioOut, "ERROR\r\n" );
		return;
	}

	/* Login successful. Make a token. */
	u_char token[TOKEN_SIZE];
	RAND_bytes( token, TOKEN_SIZE );
	String token_str = binToBase64( token, TOKEN_SIZE );

	/* Record the token. */
	DbQuery loginToken( mysql, 
		"INSERT INTO login_token ( user, login_token, expires ) "
		"VALUES ( %e, %e, date_add( now(), interval %l second ) )", 
		user, token_str.data, lasts );

	String identity( "%s%s/", c->CFG_URI, user );
	String id_hash_str = make_id_hash( id_salt_str, identity );

	BIO_printf( bioOut, "OK %s %s %ld\r\n", id_hash_str.data, token_str.data, lasts );

	message("login of %s successful\n", user );
}

char *decrypt_result( MYSQL *mysql, const char *from_user, 
		const char *to_identity, const char *user_message )
{
	RSA *id_pub, *user_priv;
	Encrypt encrypt;
	int decrypt_res;

	::message( "decrypting result %s %s %s\n", from_user, to_identity, user_message );

	user_priv = load_key( mysql, from_user );
	id_pub = fetchPublicKey( mysql, to_identity );

	encrypt.load( id_pub, user_priv );
	message( "about to\n");
	decrypt_res = encrypt.decryptVerify( user_message );

	if ( decrypt_res < 0 ) {
		message( "decrypt_verify failed\n");
		return 0;
	}

	message( "decrypt_result: %s\n", encrypt.decrypted );

	return strdup((char*)encrypt.decrypted);
}

