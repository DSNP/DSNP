<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>
		<?php 
			echo $CFG['SITE_NAME'];
			echo ": {$USER['NAME']}";
		?>
	</title>
	<link href="<?echo $this->siteLoc( '/css/choicesocial.css' ); ?>"
		rel="stylesheet" type="text/css"/>
</head>
<body>
	<div id="header">
	<table><tr>
		<td id="header_left"><div class="header_content">
		<h1>
				<?php echo $this->siteLink( $CFG['SITE_NAME'], '/' ); ?>
		</h1>
		</div></td>

		<td id="header_middle"><div class="header_content">
		<h1>
		<?php
			echo $this->absLink( $USER['NAME'], $USER['IDURL'] );
		?>
		</h1>
		</div></td>

		<td id="header_right"><div class="header_content">
		<h1>
		<?php 
			echo $this->absLink( $USER['NAME'], $USER['IDURL'] );
			echo " - ";
			echo $this->userLink( 'logout', "/cred/logout" );
		?>
		</h1>
		</div></td>

	</tr></table>
	</div>

	<div id="page_body">
