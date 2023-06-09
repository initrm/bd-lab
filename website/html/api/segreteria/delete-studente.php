<?php
  error_reporting(E_ERROR | E_PARSE);
  
  include_once("./../../../auth.php");
  include_once("./../../../database.php");

  // se la richiesta non è delete viene restituito bad request
  if($_SERVER["REQUEST_METHOD"] != "DELETE") {
    http_response_code(400);
    echo json_encode(array("message" => "Metodo non supportato."));
    die();
  }

  // controllo campi get, se mancanti viene restituito bad request
  if(!isset($_GET["matricola"])) {
    http_response_code(400);
    echo json_encode(array("message" => "Parametri GET mancanti."));
    die();
  }

  $authenticator = new Authenticator();
  
  // se l'utente non è loggato viene restituito unauthorized
  if(!$authenticator->is_authenticated()) {
    http_response_code(401);
    echo json_encode(array("message" => "Utente non autenticato."));
    die();
  }

  // se l'utente non ha i permessi per utilizzare questa pagina viene restituito unauthorized
  if($authenticator->get_authenticated_user_type() != UserType::Segreteria) {
    http_response_code(401);
    echo json_encode(array("message" => "Utente non autorizzato."));
    die();
  }
  
  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // tentativo di eliminazione dello studente
  $query_string = "delete from studenti where matricola = $1";
  $query_params = array($_GET["matricola"]);
  try {
    $results = $database->execute_query("delete_studente", $query_string, $query_params);
    if($results->affected_rows() == 0) {
      http_response_code(400);
      echo json_encode(array("message" => "Lo studente non esiste."));
    } else {
      http_response_code(200);
      echo json_encode(array("message" => "Propedeuticità rimossa con successo."));
    }
  }
  catch(QueryError $e) {
    http_response_code(400);
    echo json_encode(array("message" => $e->getMessage()));
  }

  // chiusura connessione
  $database->close_conn();
?>