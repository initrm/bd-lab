<?php
  include_once('./../auth.php');

  $authenticator = new Authenticator();

  if($authenticator->is_authenticated())
    $authenticator->unauthenticate();

  header("location: /login.php");
?>