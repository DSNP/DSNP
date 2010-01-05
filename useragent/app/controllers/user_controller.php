<?php

class UserController extends AppController
{
	var $name = 'User';
	var $helpers = array("html", "text");

	function beforeFilter()
	{
		$this->checkUser();
		$this->maybeActivateSession();
		$this->checkRole();
	}

	function indexOwner()
	{
		$this->set( 'ROLE', 'owner' );
		$this->privName();

		if ( isset( $_GET['start'] ) )
			$start = $_GET['start'];
		else
			$start = 0;

		# Load the user's sent friend requests
		$this->loadModel('SentFriendRequest');
		$sentFriendRequests = $this->SentFriendRequest->find('all', 
				array( 'conditions' => array( 'from_user' => $this->USER_NAME )));
		$this->set( 'sentFriendRequests', $sentFriendRequests );

		# Load the user's friend requests. 
		$this->loadModel('FriendRequest');
		$friendRequests = $this->FriendRequest->find( 'all',
				array( 'conditions' => array( 'for_user' => $this->USER_NAME )));
		$this->set( 'friendRequests', $friendRequests );

		$networkId = $this->findNetworkId( $this->NETWORK );

		# Load the friend list.
		$this->loadModel( 'FriendClaim' );
		$friendClaims = $this->FriendClaim->find('all', array( 
				'conditions' => array( 'user_id' => $this->USER_ID ),
				'joins' => array (
					array( 
						'table' => 'network_member',
						'alias' => 'NetworkMember',
						'type' => 'inner',
						'foreignKey' => false,
						'conditions'=> array(
							'FriendClaim.id = NetworkMember.friend_claim_id',
							'NetworkMember.network_id' => $networkId
						) 
					)
				)
			));

		$this->set( 'friendClaims', $friendClaims );

		# Load the user's images.
		$this->loadModel('Image');
		$images = $this->Image->find('all', array(
			'conditions' => 
				array( 'user' => $this->USER_NAME ),
			'order' => array( 'Image.seq_num DESC' ),
			'limit' => 30
		));
		$this->set( 'images', $images );

		$this->loadModel('Activity');
		$activity = $this->Activity->find( 'all', array( 
				'conditions' => array( 
					'Activity.user_id' => $this->USER_ID
				),
				'order' => 'time_published DESC',
				'limit' => $start + Configure::read('activity_size')
			));
		$this->set( 'start', $start );
		$this->set( 'activity', $activity );

		$this->render( 'owner' );
	}

	function indexFriend()
	{
		$this->set( 'ROLE', 'friend' );
		$this->privName();

		if ( isset( $_GET['start'] ) )
			$start = $_GET['start'];
		else
			$start = 0;

		$BROWSER = $this->Session->read('BROWSER');
		$this->set( 'BROWSER', $BROWSER );

		$networkId = $this->findNetworkId( $this->NETWORK );

		# Load the friend list.
		$this->loadModel( 'FriendClaim' );
		$friendClaims = $this->FriendClaim->find('all', 
				array( 
					'fields' => array(
						'FriendClaim.*',
						'NetworkMember.*',
						'FriendLink.*'
					),
					'conditions' => array( 'user_id' => $this->USER_ID ),
					'joins' => array (
						array(
							'table' => 'network_member',
							'alias' => 'NetworkMember',
							'type' => 'inner',
							'foreignKey' => false,
							'conditions' => array(
								'NetworkMember.network_id' => $networkId,
								'FriendClaim.id = NetworkMember.friend_claim_id'
							)
						),
						array( 
							'table' => 'friend_link',
							'alias' => 'FriendLink',
							'type' => 'left outer',
							'foreignKey' => false,
							'conditions'=> array(
								'FriendClaim.id = FriendLink.from_fc_id', 
								'FriendLink.network_id' => $networkId,
								'FriendLink.to_fc_id' => $BROWSER['id']
							) 
						)
					)
				)
			);
		$this->set( 'friendClaims', $friendClaims );

		# Load the user's images.
		$this->loadModel('Image');
		$images = $this->Image->find('all', array( 
			'conditions' => 
				array( 'user' => $this->USER_NAME ),
			'order' => array( 'Image.seq_num DESC' ),
			'limit' => 30
		));
		$this->set( 'images', $images );


		$this->loadModel('Activity');
		$activity = $this->Activity->find( 'all', array( 
				'conditions' => array( 
					'Activity.user_id' => $this->USER_ID,
					'Activity.network_id' => $networkId,
					'Activity.published' => 'true'
				),
				'order' => 'time_published DESC',
				'limit' => $start + Configure::read('activity_size')
			));
		$this->set( 'start', $start );
		$this->set( 'activity', $activity );

		$this->render( 'friend' );
	}

	function indexPublic()
	{
		$this->set( 'ROLE', 'public' );
		$this->render( 'public' );
	}

	function ssOwner()
	{
		$this->set( 'ROLE', 'owner' );
		$this->privName();

		# Load the user's sent friend requests
		$this->loadModel('SentFriendRequest');
		$sentFriendRequests = $this->SentFriendRequest->find('all', 
				array( 'conditions' => array( 'from_user' => $this->USER_NAME )));
		$this->set( 'sentFriendRequests', $sentFriendRequests );

		# Load the user's friend requests. 
		$this->loadModel('FriendRequest');
		$friendRequests = $this->FriendRequest->find( 'all',
				array( 'conditions' => array( 'for_user' => $this->USER_NAME )));
		$this->set( 'friendRequests', $friendRequests );

		# Load the friend list.
		$this->loadModel( 'FriendClaim' );
		$friendClaims = $this->FriendClaim->find('all', 
				array( 'conditions' => array( 'user_id' => $this->USER_ID )));
		$this->set( 'friendClaims', $friendClaims );

		# Load the user's images.
		$this->loadModel('Image');
		$images = $this->Image->find('all', array(
			'conditions' => 
				array( 'user' => $this->USER_NAME ),
			'order' => array( 'Image.seq_num DESC' ),
			'limit' => 30
		));
		$this->set( 'images', $images );

		$this->loadModel('Activity');
		$activity = $this->Activity->find( 'all', array( 
				'conditions' => array( 
					'Activity.user_id' => $this->USER_ID
				),
				'order' => 'time_published DESC',
				'limit' => 30
			));
		$this->set( 'activity', $activity );

		$this->render( 'ss_owner' );
	}

	function ssFriend()
	{
		$this->set( 'ROLE', 'friend' );
		$this->privName();

		$BROWSER = $this->Session->read('BROWSER');
		$this->set( 'BROWSER', $BROWSER );

		# Load the friend list.
		$this->loadModel( 'FriendClaim' );
		$friendClaims = $this->FriendClaim->find('all', 
				array( 'conditions' => array( 'user_id' => $this->USER_ID )));
		$this->set( 'friendClaims', $friendClaims );

		# Load the user's images.
		$this->loadModel('Image');
		$images = $this->Image->find('all', array( 
			'conditions' => 
				array( 'user' => $this->USER_NAME ),
			'order' => array( 'Image.seq_num DESC' ),
			'limit' => 30
		));
		$this->set( 'images', $images );

		$this->loadModel('Activity');
		$activity = $this->Activity->find( 'all', array( 
				'conditions' => array( 
					'Activity.user_id' => $this->USER_ID,
					'published' => 'true'
				),
				'order' => 'time_published DESC',
				'limit' => 30
			));
		$this->set( 'activity', $activity );

		$this->loadModel('SecretSanta');
		$givesTo = $this->SecretSanta->find('first', array( 
			'conditions' => 
				array( 'friend_id' => $BROWSER['id'] )
		));
		$givesTo = $this->FriendClaim->find('first', array( 
			'conditions' => 
				array( 'id' => $givesTo['SecretSanta']['gives_to_id'] )
		));
		$this->set( 'givesTo', $givesTo );

		$this->render( 'ss_friend' );
	}

	function ssPublic()
	{
		$this->set( 'ROLE', 'public' );
		$this->render( 'ss_public' );
	}

	function index()
	{
		switch ( $this->USER['type'] ) {

			case 0: {
				/* Regular user. */
				if ( $this->Session->valid() ) {
					if ( $this->Session->read('ROLE') === 'owner' )
						$this->indexOwner();
					else if ( $this->Session->read('ROLE') === 'friend' )
						$this->indexFriend();
					else
						$this->indexPublic();
				}
				else
					$this->indexPublic();
				break;
			}

			case 1: {
				if ( $this->Session->valid() ) {
					if ( $this->Session->read('ROLE') === 'owner' )
						$this->ssOwner();
					else if ( $this->Session->read('ROLE') === 'friend' )
						$this->ssFriend();
					else
						$this->ssPublic();
				}
				else
					$this->ssPublic();
				break;
			}
		}
	}

	function broadcast()
	{
		$this->requireOwner();

		/* User message */
		$headers = 
			"Content-Type: text/plain\r\n" .
			"Type: broadcast\r\n" .
			"\r\n";
		$message = trim( $_POST['message'] );
		$len = strlen( $headers ) + strlen( $message );

		$this->loadModel('Published');
		$this->Published->save( array( 
			"user"  => $this->USER_NAME,
			"author_id" => $this->USER_ID,
			"type" => "MSG",
			"message" => $message,
		));

		$this->loadModel('Activity');
		$this->Activity->save( array( 
			"user_id"  => $this->USER_ID,
			'published' => 'true',
			"type" => "MSG",
			"message" => $message,
		));

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"submit_broadcast $this->USER_NAME social $len\r\n";

		fwrite( $fp, $send );
		fwrite( $fp, $headers, strlen($headers) );
		fwrite( $fp, $message, strlen($message) );
		fwrite( $fp, "\r\n", 2 );

		$res = fgets($fp);

		if ( ereg("^OK", $res, $regs) )
			$this->redirect( "/$this->USER_NAME/" );
		else
			echo $res;
	}

	function findNetworkId( $networkName )
	{
		$this->loadModel( 'Network' );
		$this->Network->bindModel( array(
			'belongsTo' => array( 'NetworkName' )
		));
		$networks = $this->Network->find( 'first', array( 
			'conditions' => array( 
				'Network.user_id' => $this->USER_ID,
				'NetworkName.name' => $networkName ),
			'order' => 'NetworkName.id' 
		));

		return $networks['Network']['id'];
	}

	function board()
	{
		$this->requireFriend();
		$BROWSER = $this->Session->read('BROWSER');

		/* User message. */
		$headers = 
			"Content-Type: text/plain\r\n" .
			"Type: board-post\r\n" .
			"\r\n";
		$message = trim( $_POST['message'] );
		$len = strlen( $headers ) + strlen( $message );

		$this->loadModel('Published');
		$this->Published->save( array( 
			'user' => $this->USER_NAME,
			'author_id' => $BROWSER['identity'],
			'subject_id' => $this->CFG_URI . $this->USER_NAME . "/",
			'type' => 'BRD',
			'message' => $message,
		));

		$networkId = $this->findNetworkId( $this->NETWORK );

		$this->loadModel('Activity');
		$this->Activity->save( array( 
			'user_id'  => $this->USER_ID,
			'network_id' => $networkId,
			'author_id' => $BROWSER['id'],
			'published' => 'true',
			'type' => 'BRD',
			'message' => $message,
		));

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$identity = $BROWSER['identity'];
		$hash = $_SESSION['hash'];
		$token = $_SESSION['token'];

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"remote_broadcast_request $this->USER_NAME $identity $hash $token family $len\r\n";

		fwrite( $fp, $send );
		fwrite( $fp, $headers, strlen($headers) );
		fwrite( $fp, $message, strlen($message) );
		fwrite( $fp, "\r\n", 2 );

		$res = fgets($fp);

		if ( !ereg("^OK ([-A-Za-z0-9_]+)", $res, $regs) )
			die( $res );
		$reqid = $regs[1];

		$this->redirect( "${identity}user/flush?reqid=$reqid&backto=" . 
			urlencode( $this->USER['identity'] )  );
	}

	function flush()
	{
		$this->requireOwner();
		$reqid = $_REQUEST['reqid'];
		$backto = $_REQUEST['backto'];

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"remote_broadcast_response $this->USER_NAME $reqid\r\n";

		fwrite( $fp, $send );
		$res = fgets($fp);

		if ( !ereg("^OK ([-A-Za-z0-9_]+)", $res, $regs) )
			die( "remote_broadcast_response failed with $res ");
		$reqid = $regs[1];

		$this->redirect( "${backto}user/finish?reqid=$reqid" );
	}

	function finish()
	{
		$this->requireFriend();
		$reqid = $_REQUEST['reqid'];

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"remote_broadcast_final $this->USER_NAME $reqid\r\n";

		fwrite( $fp, $send );
		$res = fgets($fp);

		if ( !ereg("^OK", $res, $regs) )
			die( "remote_broadcast_final failed with $res" );

		$this->redirect( "/$this->USER_NAME/" );
	}

	function edit()
	{
		$this->requireOwner();
		$this->data = $this->User->find('first', 
				array( 'conditions' => array( 'user' => $this->USER_NAME )));
	}

	function sedit()
	{
		$this->requireOwner();

		if ( $this->data['User']['id'] == $this->USER_ID ) {
			if ( preg_match( '/^[ \t\n]*$/', $this->data['User']['name'] ) )
				$this->data['User']['name'] = null;
			if ( preg_match( '/^[ \t\n]*$/', $this->data['User']['email'] ) )
				$this->data['User']['email'] = null;
				
			$this->User->save( $this->data, true, array('name', 'email') );
		}

		/* User message */
		$headers = 
			"Content-Type: text/plain\r\n" .
			"Type: name-change\r\n" .
			"\r\n";
		$message = $this->data['User']['name'];
		$len = strlen( $headers ) + strlen( $message );

		$fp = fsockopen( 'localhost', $this->CFG_PORT );
		if ( !$fp )
			exit(1);

		$send = 
			"SPP/0.1 $this->CFG_URI\r\n" . 
			"comm_key $this->CFG_COMM_KEY\r\n" .
			"submit_broadcast $this->USER_NAME social $len\r\n";

		fwrite( $fp, $send );
		fwrite( $fp, $headers, strlen($headers) );
		fwrite( $fp, $message, strlen($message) );
		fwrite( $fp, "\r\n", 2 );

		$res = fgets($fp);

		if ( ereg("^OK", $res, $regs) )
			$this->redirect( "/$this->USER_NAME/" );
		else
			echo $res;

		header( "Location: " . Router::url( "/$this->USER_NAME/" ) );
	}

	function cnet()
	{
		$this->requireOwner();

		$this->loadModel( 'Network' );
		$this->Network->bindModel( array(
			'belongsTo' => array( 'NetworkName' )
		));
		$networks = $this->Network->find( 'all', array( 
			'conditions' => array( 
				'Network.user_id' => $this->USER_ID ),
			'order' => 'NetworkName.id' 
		));

		$this->set( 'networks', $networks );	
	}
}
?>
