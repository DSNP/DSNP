<?php
global $USER_NAME;
?>

<form method="post" action="login/submit">
<table>
<tbody>
<tr><td>Login:</td> <td><input type="text" name="user" value="<?php echo $USER_NAME?>"></td></tr>
<tr><td>Pass:</td> <td><input type="password" name="pass"></td></tr>
</tbody>
</table>
<input type="submit">
</form>

