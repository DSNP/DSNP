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

require( ROOT . DS . 'view' . DS . 'functions.php' );

?>

<table><tr>

<td id="leftcol">

<div id="details">
    <h2><?php print $USER['name'];?></h2>
</div>

<div class="content">

<?php

echo '<h3>Friend List</h3>';

foreach ( $friendClaims as $row ) {
	$name = $row['name'];
	$iduri = $row['iduri'];

	echo "<a href=\"${iduri}\">";
	if ( isset( $name ) )
		echo $name;
	else
		echo $iduri;
	echo "</a> <br>\n";
}

?>

</div>

<div class="content">

<h3>Photo Stream</h3>

<table class="photos">
<?php
$count = 0;
foreach ( $images as $row ) {
	$seq_num = $row['seq_num'];
	if ( $count % 2 == 0 ) {
		echo "<tr div class=\"photorow\">";
		echo "<td class=\"photo0\">";
	}
	else
		echo "<td class=\"photo1\">";

	echo "<a href=\"". $USER_URI . "image/view/img-$seq_num.jpg\">";
	echo "<img src=\"" . $USER_URI . "image/view/thm-$seq_num.jpg\" alt=\"$seq_num\"></a><br>\n";
	echo "</td>";

	if ( $count % 2 == 1 )
		echo "</tr>";
	$count += 1;
}

if ( $count % 2 == 1 )
	echo "</tr>";
?>
</table>
</div>

</td>

<td id="activity">

<div class="content">

<form method="post" action="<?php echo $this->userLoc("/user/board");?>">

Write on <?php print $USER['name'];?>'s message board:
<p>
<!--<input type="text" name="message" size="50">-->
<textarea rows="3" cols="65" name="message" wrap="physical"></textarea>
<p><input value="Submit" type="submit">

</form>
</div>

<div class="content">
<?

$activity_size = ACTIVITY_SIZE;
$limit = $start + $activity_size < count( $activity ) ? 
		$start + $activity_size : count( $activity );
for ( $i = $start; $i < $limit; $i++ ) {
	$row = $activity[$i];

	$author = $row;
	$subject = $row;
	$item = $row;

	echo "<p>\n";
	printMessage( $this, $text, $USER, $BROWSER, $author, $subject, $item );
}

if ( $start > 0 ) 
	echo $html->link( 'newer', "/$USER_NAME/user/index?start=" . ( $start - $activity_size ) ) . "&nbsp;&nbsp;";

if ( count( $activity ) == $start + $activity_size )
	echo $html->link( 'older', "/$USER_NAME/user/index?start=" . ( $start + $activity_size ) );
?>

</div>

</td>
</tr></table>

