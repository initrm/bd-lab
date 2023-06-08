<?php
  error_reporting(E_ERROR | E_PARSE);
  
  include_once("./../../../auth.php");
  include_once("./../../../database.php");

  // se la richiesta non è post viene restituito bad request
  if($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(400);
    echo json_encode(array("message" => "Metodo non supportato."));
    die();
  }

  // controllo campi post, se mancanti viene restituito bad request
  if(!isset($_POST["cdl"]) || !isset($_POST["insegnamento-propedeuticità"]) || !isset($_POST["insegnamento-propedeutico"])) {
    http_response_code(400);
    echo json_encode(array("message" => "Parametri POST mancanti."));
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

  // tentativo di inserimento della propedeuticità
  $query_string = "insert into propedeuticità values ($1, $2, $3, $4)";
  $query_params = array($_POST["insegnamento-propedeutico"], $_POST["cdl"], $_POST["insegnamento-propedeuticità"], $_POST["cdl"]);
  try {
    $results = $database->execute_query("insert_propedeuticità", $query_string, $query_params);
    http_response_code(200);
    echo json_encode(array("message" => "Propedeuticità aggiunta con successo."));
  }
  catch(QueryError $e) {
    http_response_code(400);
    echo json_encode(array("message" => $e->getMessage()));
  }

  // chiusura connessione
  $database->close_conn();
?>