<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');

  $authenticator = new Authenticator();

  // se l'utente non è loggato viene reindirizzato alla pagina per effettuare l'accesso
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // se l'utente non ha i permessi per accedere a questa pagina viene reindirizzato alla sua dashboard
  if($authenticator->get_authenticated_user_type() != UserType::Docente) {
    header("location: /dashboard/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database();
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;
  
  // ottenimento informazioni insegnamento e verifica correttezza parametri
  // ok quando esiste l'insegnamento ed è di proprietà dell'utente loggato
  $query_string = "select * from progetto_esame.insegnamenti where codice = $1 and corso_laurea = $2 and docente = $3";
  $query_params = array($_GET["insegnamento"], $_GET["cdl"], $authenticator->get_authenticated_user()["email"]);
  $result = $database->execute_query("get_insegnamento", $query_string, $query_params);
  // se l'insegnamento non esiste, ovvero i parametri get sono errati, l'utente viene reindirizzato alla index della dashobard
  if($result->row_count() == 0) {
    header("location: /dashboard/docenti/index.php");
    $database->close_conn();
    die();
  }
  $insegnamento = $result->row();

  // gestione della eventuale richiesta di creazione di un nuovo appello
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza data e che sia una data nel futuro
    if(isset($_POST["data"]) && date("Y-m-d") < $_POST["data"]) {
      // tentativo di creazione dell'appello
      $query_string = "insert into progetto_esame.appelli(insegnamento, corso_laurea, data) values ($1, $2, $3)";
      $query_params = array($insegnamento["codice"], $insegnamento["corso_laurea"], $_POST["data"]);
      try {
        $database->execute_query("insert_appello", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Seleziona una data valida.";
  }

  // ottenimento appelli attualmente fissati per l'insegnamento
  $query_string = "select * from progetto_esame.appelli where insegnamento = $1 and corso_laurea = $2 order by data desc";
  $query_params = array($insegnamento["codice"], $insegnamento["corso_laurea"]);
  $result = $database->execute_query("get_appelli", $query_string, $query_params);
  $appelli = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Appelli"); ?>
  <body>
    <?php 
      $user = $authenticator->get_authenticated_user();
      $display_name = $user["nome"] . " " . $user["cognome"];
      navbar($display_name);
    ?>
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">
          <div class="columns mx-2 is-multiline">

            <!-- titolo pagina e sottotitolo -->
            <div class="column is-12">
              <h1 class="title is-1">Gestione appelli</h1>
              <h2 class="subtitle">Gestisci gli appelli per l'insegnamento "<?php echo $insegnamento["nome"];?>"</h2>
            </div>

            <!-- breadcrumb -->
            <div class="column is-12">
              <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                  <li><a href="/dashboard/docenti/index.php">Home</a></li>
                  <li class="is-active"><a href="#" aria-current="page">Appelli di "<?php echo $insegnamento["nome"]; ?>"</a></li>
                </ul>
              </nav>
            </div>

            <!-- form creazione nuovo appello -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- data -->
                  <div class="column is-9">
                    <div class="field">
                      <label class="label">Data</label>
                      <div class="control">
                        <input min="<?php echo date("Y-m-d"); ?>" name="data" class="input" type="date" required="true">
                      </div>
                    </div>
                  </div>

                  <!-- submit -->
                  <div class="column is-3 is-flex is-align-items-end">
                    <div style="flex: 1 1 auto;" class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">Crea appello</button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <div class="column is-12">
                    <?php if($error_msg != NULL) { ?>
                      <p class="help is-danger">
                        <?php echo $error_msg; ?>
                      </p>
                    <?php } ?>
                  </div>

                </div>
              </form>
            </div>

            <!-- card appelli -->
            <?php for($i = 0; $i < sizeof($appelli); $i++) { ?>
              <div class="column is-12">
                <div class="box">
                  <div class="columns">
                    <div class="column is-8 is-flex is-align-items-center">
                      <p>Appello del <?php echo date_format(date_create($appelli[$i]["data"]), "d/m/Y"); ?></p>
                    </div>
                    <div class="column is-4 is-flex is-justify-content-end">
                      <a href="/dashboard/docenti/appello.php?id=<?php echo $appelli[$i]["id"] ?>" class="button is-link is-outlined">
                        <ion-icon name="arrow-forward-outline"></ion-icon>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            <?php } ?>
            
          </div>
        </div>
      </div>
    </div>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  </body>
</html>