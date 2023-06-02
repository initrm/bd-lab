<?php
  include_once('./../../auth.php');
  include_once('./../../utype.php');

  $authenticator = new Authenticator();

  // se l'utente non è autenticato viene reindirizzato alla login
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // l'utente viene reindirizzato verso la dashboard di competenza
  $url = NULL;
  switch($authenticator->get_authenticated_user_type()) {
    case UserType::Docente:
      $url = "/dashboard/docenti.php";
      break;
    case UserType::Studente:
      $url = "/dashboard/studenti.php"; 
      break;
    case UserType::Segreteria:
      $url = "/dashboard/segreteria.php";
      break;
  }

  header("location: " . $url);
?>