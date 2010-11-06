<?php
class OwnerFreqController extends Controller
{
	var $function = array(
		'retrelid' => array(),
		'answer' => array(),
	);

	function retrelid()
	{
		$identity = $_GET['identity'];
		$fr_reqid = $_GET['fr_reqid'];

		$connection = new Connection;
		$connection->openLocalPriv();

		$connection->relidResponse( 
			$this->USER[USER], $fr_reqid, $identity );

		if ( ereg("^OK ([-A-Za-z0-9_]+)", $connection->result, $regs) ) {
			$arg_identity = 'identity=' . urlencode( $this->USER[URI]);
			$arg_reqid = 'reqid=' . urlencode( $regs[1] );

			$this->redirect("${identity}freq/frfinal?${arg_identity}&${arg_reqid}" );
		}
		else {
			echo $connection->result;
		}
	}

	function answer()
	{
		$reqid = $_GET['reqid'];

		$connection = new Connection;
		$connection->openLocalPriv();

		$connection->acceptFriend( 
			$this->USER[USER], $reqid );

		if ( !ereg("^OK", $connection->result ) )
			die( "FAILURE *** Friend accept failed with: <br>" . $connection->result );

		$this->userRedirect( "/" );
	}
}
?>
