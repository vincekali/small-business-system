<?php

require_once "auth.php";

destroySession();

header("Location: login.php");
exit;

?>