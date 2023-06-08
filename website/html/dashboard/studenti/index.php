<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/studenti/carriera.php');

  $authenticator = new Authenticator();

  // se l'utente non è loggato viene reindirizzato alla pagina per effettuare l'accesso
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // se l'utente non ha i permessi per accedere a questa pagina viene reindirizzato alla sua dashboard
  if($authenticator->get_authenticated_user_type() != UserType::Studente) {
    header("location: /dashboard/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database();
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione eventuale richiesta post (iscrizione ad appello d'esame)
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // controllo presenza parametri
    if(isset($_POST["appello"])) {
      $query_string = "insert into esami(appello, studente) values ($1, $2)";
      $query_params = array($_POST["appello"], $authenticator->get_authenticated_user()["matricola"]);
      try {
        $database->execute_query("insert_esame", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else
      $error_msg = "Parametri richiesta mancanti.";
  }

  // ottenimento appelli a cui l'utente è attualmente iscritto che sono da svolgere nel futuro
  $query_string = "select * from get_iscrizioni_attive_appelli_studente($1)";
  $query_params = array($authenticator->get_authenticated_user()["matricola"]);
  $result = $database->execute_query("get_iscrizioni", $query_string, $query_params);
  $iscrizioni = $result->all_rows();

  // ottenimento appelli a cui lo studente può iscriversi
  // ovvero gli appelli a cui non è già iscritto
  $query_string = "select * from get_appelli_studente_non_iscritto_futuri($1)";
  $query_params = array($authenticator->get_authenticated_user()["matricola"]);
  $result = $database->execute_query("get_appelli_disponibili", $query_string, $query_params);
  $appelli = $result->all_rows();

?>
<!DOCTYPE html>
<html>
  <?php head("Dashboard Studenti"); ?>
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

            <!-- gestione appelli -->
            <div class="column is-12">
              <h1 class="title is-1">Gestisci appelli</h1>
              <h2 class="subtitle">Iscriviti agli appelli d'esame</h2>
            </div>

            <!-- appelli ai quali è iscritto -->
            <div class="column is-12">
              <div class="box">
                <p class="mb-1"><strong>Iscrizioni attive agli appelli d'esame</strong></p>
                <?php if(sizeof($iscrizioni) != 0) { exam_table($iscrizioni, false); } else { ?><p class="is-italic">Nessuna iscrizione attiva.</p><?php } ?>
              </div>
            </div>

            <!-- iscrizione appelli -->
            <div class="column is-12">
              <form class="box" method="post">
                <div class="columns">
                  <div class="column is-9">
                    <div class="field">
                      <label class="label">Iscriviti ad un appello d'esame</label>
                      <?php if(sizeof($appelli) > 0) { ?>
                        <div class="control">
                          <div class="select is-fullwidth">
                            <select name="appello">
                              <?php foreach($appelli as $appello) { ?>
                                <option value="<?php echo $appello["appello"] ?>">
                                  <?php echo $appello["nome_insegnamento"] . " del " . date_format(date_create($appello["data_appello"]), "d/m/Y"); ?>
                                </option>
                              <? } ?>
                            </select>
                          </div>
                        </div>
                      <?php } else { ?><p class="is-italic">Nessun appello disponibile.</p><?php } ?>
                    </div>
                  </div>
                  <?php if(sizeof($appelli) > 0) { ?>
                    <div class="column is-3 is-flex is-align-items-end">
                      <div class="field" style="flex: 1 1 auto;">
                        <div class="control">
                          <button class="button is-link is-outlined is-fullwidth">
                            Conferma iscrizione
                          </button>
                        </div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </form>
            </div>

            <!-- carriera studente -->
            <div class="column is-12">
              <h1 class="title is-1">Carriera</h1>
              <h2 class="subtitle">Visualizza la tua carriera</h2>
            </div>

            <!-- select e tabelle carriera -->
            <?php carriera($database, $authenticator->get_authenticated_user()["matricola"]); ?>

          </div>
        </div>
      </div>
    </div>

    <!-- scripts -->

    <!-- toast -->
    <?php include_once("./../../../components/toasts.php"); ?>
    <!-- mostra toast con esito richiesta iscrizione -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg == NULL) { ?>
          showSuccessToast("Iscrizione avvenuta con successo.");
        <?php } else if ($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg != NULL) { ?>
          showDangerToast("<?php echo str_replace("\n", " ", str_replace("\"", "\\\"", $error_msg)); ?>");
        <?php } ?>
      });
    </script>

  </body>
</html>
<?php $database->close_conn(); ?>