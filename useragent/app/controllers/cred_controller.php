<?php

class CredController extends AppController
{
	var $name = 'Cred';
	var $uses = array();

	var $NETWORK_NAME = null;
	var $NETWORK_ID = null;

	function beforeFilter()
	{
		$this->checkUserMaybeActivateSession();
	}

	function index()
	{
		$this->render( 'login' );
	}

	function login()
	{
		if ( isset( $_REQUEST['d'] ) )	
			$this->set('dest', $_REQUEST['d'] );
	}

	function ownerNetwork()
	{
		/* Load the most recent login state. */
		$this->loadModel('LoginState');
		$loginState = $this->LoginState->find('first', array(
			'conditions' => array ( 'user_id' => $this->USER_ID ) ) );

		if ( isset( $loginState['LoginState'] ) > 0 ) {
			/* Use the stored login state. */
			$networkName = $loginState['LoginState']['network_name'];
			$networkId = $this->findNetworkId( $networkName );
		}
		else {
			/* No previous login state. Find a network. */
			$this->loadModel('Network');
			$first = $this->Network->find('first', array(
				'conditions' => array ( 'user_id' => $this->USER_ID ) ));

			if ( isset( $first['Network'] ) ) {
				$networkName = $first['NetworkName']['name'];
				$networkId = $first['Network']['id'];
			}
			else {
				/* No network. */
				$networkName = null;
				$networkId = null;
			}
		}

		$this->NETWORK_NAME = $networkName;
		$this->NETWORK_ID = $networkId;
	}

	function slogin()
	{
		$this->activateSession();

		$pass = $this->data['User']['pass'];

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"login $this->USER_NAME $pass\r\n";
		fwrite($fp, $send);

		$res = fgets($fp);
		if ( !ereg("^OK ([-A-Za-z0-9_]+) ([-A-Za-z0-9_]+) ([0-9]+)", $res, $regs) ) {
			$this->userError('generic', array(
				'message' => 'Login failed.',
				'details' => 'Please press the back button to try again.'
			));
		}

		$this->ownerNetwork();

		# Login successful.
		$this->Session->write( 'ROLE', 'owner' );
		$this->Session->write( 'NETWORK_NAME', $this->NETWORK_NAME );
		$this->Session->write( 'NETWORK_ID', $this->NETWORK_ID );
		$this->Session->write( 'hash', $regs[1] );
		$this->Session->write( 'token', $regs[2] );

		if ( isset( $_POST['d'] ) )
			$this->redirect( urldecode($_POST['d']) );
		else
			$this->redirect( "/$this->USER_NAME/" );
	}

	function friendNetwork( $networkName, $hash )
	{
		if ( !isset($networkName) || count($networkName) == 0 )
			$networkName = '-';
			
		/* See if we have this network. */
		$this->loadModel('Network');

		$first = $this->Network->find('first', array(
			'conditions' => array ( 
				'user_id' => $this->USER_ID,
				'NetworkName.name' => $networkName )));

		if ( !isset( $first['NetworkName'] ) )
			$networkName = '-';
		else {
			$networkName = $first['NetworkName']['name'];

			/* Is the user a member of the network. */
			$this->loadModel( 'FriendClaim' );
			$friendClaim = $this->FriendClaim->find('first', array( 
					'conditions' => array( 
						'user_id' => $this->USER_ID,
						'friend_hash' => $hash
					),
					'joins' => array (
						array( 
							'table' => 'network_member',
							'alias' => 'NetworkMember',
							'type' => 'inner',
							'foreignKey' => false,
							'conditions'=> array(
								'FriendClaim.id = NetworkMember.friend_claim_id',
								'NetworkMember.network_id' => $first['Network']['id']
							) 
						)
					)
				));

			if ( !isset( $friendClaim['FriendClaim'] ) )
				$networkName = '-';
		}

		return $networkName;
	}

	function sflogin()
	{
		$hash = $_REQUEST['h'];
		$networkName = $_REQUEST['n'];

		if ( !$hash )
			die('no hash given');

		/* Maybe we are already logged in as this friend. */
		if ( isset( $this->ROLE ) && $this->ROLE === 'friend' && 
				isset( $this->NETWORK_NAME ) && $this->NETWORK_NAME === $networkName &&
				isset( $_SESSION['hash'] ) && $_SESSION['hash'] === $hash )
		{
			header( "Location: " . Router::url( "/$this->USER_NAME/" ) );
		}
		else {
			$networkName = $this->friendNetwork( $networkName, $hash );

			/* Not logged in as the hashed user. */
			$fp = fsockopen( 'localhost', $this->CFG_PORT );
			if ( !$fp )
				exit(1);

			$send = 
				"SPP/0.1 " . $this->CFG_URI . "\r\n" . 
				"comm_key " . $this->CFG_COMM_KEY . "\r\n" .
				"ftoken_request " . $this->USER_NAME . " $networkName $hash\r\n";
			fwrite($fp, $send);

			$res = fgets($fp);

			if ( ereg("^OK ([-A-Za-z0-9_]+) ([^ \t\n\r]+) ([-A-Za-z0-9_]+)", $res, $regs) ) {
				$arg_h = 'h=' . urlencode( $regs[3] );
				$arg_reqid = 'reqid=' . urlencode( $regs[1] );
				$friend_id = $regs[2];
				$dest = "";
				if ( isset( $_GET['d'] ) )
					$dest = "&d=" . urlencode($_GET['d']);
					
				header( "Location: ${friend_id}cred/retftok?${arg_h}&${arg_reqid}" . $dest );
			}
			else {
				header( "Location: " . Router::url( "/$this->USER_NAME/" ) );
			}
		}
	}
	
	function retftok()
	{
		if ( !$this->isOwner() )
			$this->userError('noAuthRetftok');

		$hash = $_REQUEST['h'];
		$reqid = $_GET['reqid'];

		if ( !$hash )
			die('no hash given');

		if ( !$reqid )
			die('no reqid given');

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 " . $this->CFG_URI . "\r\n" . 
			"comm_key " . $this->CFG_COMM_KEY . "\r\n" .
			"ftoken_response " . $this->USER_NAME . " $hash $reqid\r\n";
		fwrite($fp, $send);

		$res = fgets($fp);

		if ( ereg("^OK ([-A-Za-z0-9_]+) ([^ \t\r\n]*)", $res, $regs) ) {
			$arg_ftoken = 'ftoken=' . urlencode( $regs[1] );
			$friend_id = $regs[2];
			$dest = "";
			if ( isset( $_GET['d'] ) )
				$dest = "&d=" . urlencode($_GET['d']);
			header("Location: ${friend_id}cred/sftoken?${arg_ftoken}" . $dest );
		}
		else {
			echo $res;
		}
	}
	
	function sftoken()
	{
		$this->activateSession();

		# No session yet. Maybe going to set it up.

		$ftoken = $_GET['ftoken'];

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 " . $this->CFG_URI . "\r\n" . 
			"comm_key " . $this->CFG_COMM_KEY . "\r\n" .
			"submit_ftoken $ftoken\r\n";
		fwrite($fp, $send);

		$res = fgets($fp);

		# If there is a result then the login is successful. 
		if ( ereg("^OK ([-A-Za-z]+) ([-A-Za-z0-9_]+) ([0-9a-f]+) ([^ \t\r\n]*)", $res, $regs) ) {
			# Login successful.
			$this->Session->write( 'ROLE', 'friend' );
			$this->Session->write( 'NETWORK_NAME', $regs[1] );
			$this->Session->write( 'token', $ftoken );
			$this->Session->write( 'hash', $regs[2] );

			/* Find the friend claim data and store in the session. */
			$this->loadModel('FriendClaim');
			$BROWSER = $this->FriendClaim->find('first', array(
				'conditions' => array (
					'user_id' => $this->USER_ID,
					'identity' => $regs[4]
				)
			));

			$this->Session->write( 'BROWSER', $BROWSER['FriendClaim'] );

			if ( isset( $_GET['d'] ) )
				$this->redirect( $_GET['d'] );
			else
				$this->redirect( "/" . $this->USER_NAME . "/" );
		}
		else {
			echo "<center>\n";
			echo "FRIEND LOGIN FAILED<br> $res\n";
			echo "</center>\n";
			exit;
		}
	}

	function logout()
	{
		$this->Session->destroy();
		$this->redirect( "/" . $this->USER_NAME . "/" );
	}
	
	function nnet()
	{
		$this->requireOwnerOrFriend();

		if ( $this->ROLE == 'owner' ) {
			$this->loadModel('LoginState');
			$loginState = $this->LoginState->find('first', array(
				'conditions' => array ( 'user_id' => $this->USER_ID ) ) );

			$newNetwork = $this->data['User']['network'];
			$loginState['LoginState']['network_name'] = $newNetwork;
			$loginState = $this->LoginState->save( $loginState );
			$this->Session->write('NETWORK_NAME', $newNetwork );
		}
		else {
			$newNetwork = $this->data['User']['network'];
			$networkId = $this->findNetworkId( $newNetwork );
			$BROWSER = $this->Session->read('BROWSER');
			$this->loadModel('NetworkMember');
			$networks = $this->NetworkMember->find( 'first', array( 
				'conditions' => array( 
					'network_id' => $networkId,
					'friend_claim_id' => $BROWSER['id']
				)
			));

			if ( !isset( $networks['NetworkMember'] ) )
				die('bad network');

			$this->Session->write('NETWORK_NAME', $newNetwork );
		}

		$this->redirect( "/" . $this->USER_NAME . "/" );
	}
}
?>
