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
  if($authenticator->get_authenticated_user_type() != UserType::Docente) {
    header("location: /dashboard/index.php");
    die();
  }

  // controllo presenza parametro get
  if(!isset($_GET["id"])) {
    header("location: /dashboard/docenti/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // ottenimento dell'appello e dell'insegnamento
  // ok quando esiste l'appello, esiste l'insegnamento ed è di proprietà dell'utente loggato
  $query_string = "select * from informazioni_complete_appelli where id = $1 and email_docente = $2";
  $query_params = array($_GET["id"], $authenticator->get_authenticated_user()["email"]);
  $result = $database->execute_query("get_appello", $query_string, $query_params);
  // se l'appello non esiste, l'utente viene reindirizzato alla index della dashboard
  if($result->row_count() == 0) {
    header("location: /dashboard/docenti/index.php");
    $database->close_conn();
    die();
  }
  $appello = $result->row();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione richiesta di eliminazione dell'appello, posto dopo SELECT in quanto così ho l'insegnamento al quale
  // fare redirect in caso in cui l'eliminazione avvenga con successo
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // se arrivati a questo punto parametro get per forza presente, no necessità controllo
    // già verificato che appello esiste e di proprietà del docente
    // tentativo di eliminazione dell'appello
    $query_string = "delete from appelli where id = $1";
    $query_params = array($_GET["id"]);
    try {
      $database->execute_query("delete_appello", $query_string, $query_params);
      // successo, redirect alla pagina degli appelli dell'insegnamento
      header("location: /dashboard/docenti/appelli.php?cdl=" . $appello["corso_laurea"] . "&insegnamento=" . $appello["codice"]);
      die();
    }
    catch(QueryError $e) {
      $error_msg = $e->getMessage();
    }
  }

  // ottenimento studenti iscritti all'appello
  $query_string = "select * from get_iscrizioni_appello($1)";
  $query_params = array($appello["id"]);
  $result = $database->execute_query("get_iscrizioni_appello", $query_string, $query_params);
  $studenti = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Iscrizioni Appello"); ?>
  <body>

    <!-- navbar -->
    <?php 
      $user = $authenticator->get_authenticated_user();
      navbar($user["nome"] . " " . $user["cognome"]);
    ?>
    
    <!-- content -->
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">
          <div class="columns mx-2 is-multiline">

            <!-- titolo pagina e sottotitolo -->
            <?php section_title("Gestione iscrizioni appello", "Gestisci le iscrizioni per l'appello in data " . date_format(date_create($appello["data"]), "d/m/Y") . " di " . $appello["nome"]); ?>

            <!-- breadcrumb -->
            <div class="column is-12">
              <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                  <li><a href="/dashboard/docenti/index.php">Home</a></li>
                  <li><a href="/dashboard/docenti/appelli.php?insegnamento=<?php echo $appello["codice_insegnamento"]; ?>&cdl=<?php echo $appello["codice_corso_laurea"]; ?>">Appelli di "<?php echo $appello["nome_insegnamento"]; ?>"</a></li>
                  <li class="is-active">
                    <a href="#" aria-current="page">Appello in data <?php echo date_format(date_create($appello["data"]), "d/m/Y"); ?></a>
                  </li>
                </ul>
              </nav>
            </div>

            <!-- form per l'eliminazione dell'appello (visualizzato se appello è nel futuro o passato ma con zero iscritti) -->
            <?php 
              $now = date_create();
              $data_appello = date_create($appello["data"]);
              if($data_appello > $now || ($data_appello <= $now && sizeof($studenti) == 0)) {
            ?>
                <div class="column is-12">
                  <form class="box" method="post">
                    <div class="columns is-multiline">
                      <div class="column is-10">
                        <p class="mr-2">L'appello può essere eliminato, se si procede con l'eliminazione, tutti gli studenti iscritti verranno automaticamente disiscritti. L'operazione è irreversibile.</p>
                      </div>
                      <div class="column is-2">
                        <div class="field">
                          <div class="control">
                            <button class="button is-fullwidth is-danger is-outlined">
                              <ion-icon name="trash"></ion-icon>
                            </button>
                          </div>
                        </div>
                      </div>
                      <!-- error message -->
                      <?php error_message($error_msg); ?>
                    </div>
                  </form>
                </div>
            <?php } ?>

            <!-- scritta nel caso in cui non ci siano appelli -->
            <?php if(sizeof($studenti) == 0) { ?>
              <div class="column is-12">
                <p class="is-italic">Non ci sono iscritti all'appello.</p>
              </div>
            <?php } ?>

            <!-- card iscritti -->
            <?php for($i = 0; $i < sizeof($studenti); $i++) { ?>
              <div class="column is-12">
                <div class="box">
                  <div class="columns">
                    <div class="column is-8 is-flex is-align-items-center">
                      <p>
                        <?php echo $studenti[$i]["nome_studente"] . " " . $studenti[$i]["cognome_studente"] . ", Matricola: " . $studenti[$i]["matricola_studente"]; ?>
                      </p>
                    </div>
                    <div class="column is-2 is-flex is-justify-content-end is-align-items-center">
                        <div class="control">
                          <input 
                            id="valutazione-s-<?php echo $studenti[$i]["matricola_studente"]; ?>-a-<?php echo $appello["id"]; ?>"
                            name="valutazione"
                            min="0"
                            max="30"
                            minlength="6"
                            class="input"
                            type="number"
                            placeholder="Valutazione"
                            <?php
                              if($studenti[$i]["valutazione_esame"] != NULL)
                                echo "value=\"" . $studenti[$i]["valutazione_esame"] . "\"";
                            ?>
                          />
                        </div>
                    </div>
                    <div class="column is-2 is-flex is-justify-content-end">
                      <button class="button is-link is-outlined is-fullwidth" onclick="<?php echo "salvaValutazione(" . $appello["id"] . ", " . $studenti[$i]["matricola_studente"] . ")"; ?>">
                        <ion-icon name="save-outline"></ion-icon>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php } ?>
            
          </div>
        </div>
      </div>
    </div>

    <!-- scripts -->

    <!-- axios lib -->
    <?php include_once("./../../../components/axios.php"); ?>
    <!-- icons -->
    <?php include_once("./../../../components/icons.php"); ?>
    <!-- toast -->
    <?php include_once("./../../../components/toasts.php"); ?>
    <!-- funzione salva valutazione -->
    <script type="text/javascript">
      function salvaValutazione(appello, studente) {
        var element = document.getElementById("valutazione-s-" + studente + "-a-" + appello);
        axios.postForm("/api/docenti/save-result.php", { appello, studente, valutazione: element.value })
          .then(({ data }) => showSuccessToast(data.message))
          .catch(({ response: { data }}) => showDangerToast(data.message));
      }
    </script>

  </body>
</html>