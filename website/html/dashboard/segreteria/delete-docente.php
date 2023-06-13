<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/error.php');

  $authenticator = new Authenticator();

  // se l'utente non è loggato viene reindirizzato alla pagina per effettuare l'accesso
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // se l'utente non ha i permessi per accedere a questa pagina viene reindirizzato alla sua dashboard
  if($authenticator->get_authenticated_user_type() != UserType::Segreteria) {
    header("location: /dashboard/index.php");
    die();
  }

  // controllo presenza parametro get
  if(!isset($_GET["email"])) {
    header("location: /dashboard/segreteria/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // ottenimento docente
  $query_string = "select email, nome, cognome from docenti where email = $1";
  $query_params = array($_GET["email"]);
  $result = $database->execute_query("get_docente", $query_string, $query_params);
  if($result->row_count() == 0) {
    header("location: /dashboard/segreteria/docenti.php");
    die();
  }
  $docente = $result->row();

  // ottenimento corsi del docente
  $query_string = "select * from insegnamenti where docente = $1";
  $query_params = array($docente["email"]);
  $result = $database->execute_query("get_insegnamenti_docente", $query_string, $query_params);
  $insegnamenti = $result->all_rows();

  // gestione della eventuale richiesta di eliminazione del docente
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    try {
      $cdl_arr = array();
      $ins_arr = array();
      $doc_arr = array();
      foreach($insegnamenti as $i) {
        if(!isset($_POST[$i["corso_laurea"] . $i["codice"]]))
          throw new Exception();
        else {
          array_push($cdl_arr, $i["corso_laurea"]);
          array_push($ins_arr, $i["codice"]);
          array_push($doc_arr, $_POST[$i["corso_laurea"] . $i["codice"]]);
        }
      }
      $placeholders = array_fill(0, 3, array());
      for($i = 0; $i < 3; $i++) {
        for($c = 1; $c <= sizeof($insegnamenti); $c++) {
          array_push($placeholders[$i], "$" . ($i * sizeof($insegnamenti)) + $c + 1);
        }
      }

      // eliminazione
      $query_string = "call delete_docente($1, ARRAY[" . implode(",", $placeholders[0]) . "], ARRAY[" . implode(",", $placeholders[1]) . "], ARRAY[" . implode(",", $placeholders[2]) . "])";
      $query_params = array($docente["email"], ...$cdl_arr, ...$ins_arr, ...$doc_arr);
      $results = $database->execute_query("delete_docente", $query_string, $query_params);

      header("location: /dashboard/segreteria/docenti.php");
      die();
    }
    catch(QueryError $qe) {
      $error_msg = $qe->getMessage();
    }
    catch(Exception $ex) {
      $error_msg = "Parametri mancanti.";
    }
  }

  // ottenimento docenti diversi da quello corrente
  $query_string = "select email, nome, cognome from docenti where email <> $1";
  $query_params = array($_GET["email"]);
  $result = $database->execute_query("get_docenti", $query_string, $query_params);
  if($result->row_count() == 0) {
    header("location: /dashboard/segreteria/docenti.php");
    die();
  }
  $docenti = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Eliminazione Docente"); ?>
  <body>

    <!-- navbar -->
    <?php navbar($authenticator->get_authenticated_user()["email"]); ?>

    <!-- content -->
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">
          <div class="columns mx-2 is-multiline">

            <!-- breadcrumb -->
            <div class="column is-12">
              <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                  <li><a href="/dashboard/segreteria/index.php">Home</a></li>
                  <li><a href="/dashboard/segreteria/docenti.php">Gestione docenti</a></li>
                  <li>
                    <a href="/dashboard/segreteria/docente.php?email=<?php echo $docente["email"]; ?>">
                      Gestione docente "<?php echo $docente["nome"] . " " . $docente["cognome"]; ?>"
                    </a>
                  </li>
                  <li class="is-active"><a href="#" aria-current="page">Eliminazione</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Eliminazione docente", "Riassegna i corsi del docente"); ?>

            <!-- form eliminazione docente -->
            <div class="column is-12">
              <form method="post">
                <div class="columns is-multiline">

                  <?php foreach($insegnamenti as $insegnamento) { ?>
                    <div class="column is-12 box">
                      <div class="columns">
                        <div class="column is-8 is-flex is-align-items-center">
                          <p><?php echo $insegnamento["nome"]; ?></p>
                        </div>
                        <div class="column is-4">
                          <div class="field">
                            <div class="control">
                              <div class="select is-fullwidth">
                                <select name="<?php echo $insegnamento["corso_laurea"] . $insegnamento["codice"]; ?>">
                                  <?php foreach($docenti as $d) { ?>
                                    <option value="<?php echo $d["email"]; ?>">
                                      <?php echo $d["nome"] . " " . $d["cognome"]; ?>
                                    </option>
                                  <?php } ?>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php } ?>

                  <!-- submit -->
                  <div class="column is-12">
                    <div class="field">
                      <div class="control">
                        <button class="button is-danger is-outlined is-fullwidth">Elimina</button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <?php error_message($error_msg); ?>

                </div>
              </form>
            </div>
            
          </div>
        </div>
      </div>
    </div>
  </body>
</html>