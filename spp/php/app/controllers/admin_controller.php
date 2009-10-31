<?php

class AdminController extends AppController
{
	var $name = 'Admin';

    // var $uses = null; works too
    var $uses = null;

	function index()
	{
		# Load the user's sent friend requests
		$this->loadModel('User');
		$users = $this->User->find('all');
		$this->set( 'users', $users );
	}

	function newuser()
	{

	}

	function snewuser()
	{
		global $CFG_URI;
		global $CFG_COMM_KEY;
		global $CFG_PORT;
		global $CFG_PHOTO_DIR;

		$user = $this->params['form']['user'];
		$pass1 = $this->params['form']['pass1'];
		$pass2 = $this->params['form']['pass2'];

		if ( $pass1 != $pass2 )
			die("password mismatch");

		$fp = fsockopen( 'localhost', $CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $CFG_URI\r\n" . 
			"comm_key $CFG_COMM_KEY\r\n" .
			"new_user $user $pass1 unused\r\n";
		fwrite($fp, $send);

		$res = fgets($fp);
		if ( !ereg("^OK", $res) )
			die( "FAILURE *** New user creation failed with: <br> $res" );
   	
		$this->loadModel('User');
		$this->User->save( array( 'user' => $user ));

		$photoDirCmd =  "umask 0002; mkdir $CFG_PHOTO_DIR/$user";
		system( $photoDirCmd );

		$this->redirect( "/" );
	}
}