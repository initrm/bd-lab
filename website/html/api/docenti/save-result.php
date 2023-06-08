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
  if(!isset($_POST["appello"]) || !isset($_POST["valutazione"]) || !isset($_POST["studente"])) {
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
  if($authenticator->get_authenticated_user_type() != UserType::Docente) {
    http_response_code(401);
    echo json_encode(array("message" => "Utente non autorizzato."));
    die();
  }
  
  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // controllo se l'appello è di proprietà del docente loggato
  $query_string = "
    select *
    from appelli a
    inner join insegnamenti i on a.corso_laurea = i.corso_laurea and a.insegnamento = i.codice
    where i.docente = $1
  ";
  $query_params = array($authenticator->get_authenticated_user()["email"]);
  $result = $database->execute_query("get_insegnamento", $query_string, $query_params);
  if($result->row_count() == 0) {
    http_response_code(401);
    echo json_encode(array("message" => "Utente non autorizzato."));
    die();
  }

  $query_string = "update esami set valutazione = $1 where appello = $2 and studente = $3";
  $query_params = array($_POST["valutazione"], $_POST["appello"], $_POST["studente"]);
  try {
    $results = $database->execute_query("update_esame", $query_string, $query_params);
    if($results->affected_rows() == 0) {
      http_response_code(503);
      echo json_encode(array("message" => "Errore sconosciuto."));
    }
    else {
      http_response_code(200);
      echo json_encode(array("message" => "Voto aggiornato con successo."));
    }
  }
  catch(QueryError $e) {
    http_response_code(400);
    echo json_encode(array("message" => $e->getMessage()));
  }

  // chiusura connessione
  $database->close_conn();
?>