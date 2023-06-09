<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/studenti/carriera.php');
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
  if(!isset($_GET["matricola"])) {
    header("location: /dashboard/segreteria/studenti.php");
    die();
  }

  // definizione table di ricerca
  $table_studenti = isset($_GET["storico"]) && $_GET["storico"] == true ? "storico_studenti" : "studenti";
  $table_esami = isset($_GET["storico"]) && $_GET["storico"] == true ? "storico_esami" : "esami";

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di modifica dello studente
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["nome"]) && isset($_POST["cognome"]) && isset($_POST["email"])) {
      // tentativo di aggiornamento dello studente
      $query_string = "update $table_studenti set email = $1, nome = $2, cognome = $3 where matricola = $4";
      $query_params = array($_POST["email"] . "@uniesempio.it", $_POST["nome"], $_POST["cognome"], $_GET["matricola"]);
      try {
        $r = $database->execute_query("update_studente", $query_string, $query_params);
        if($r->affected_rows() == 0)
          $error_msg = "Errore sconosciuto.";
      }
      catch(Exception $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }

  // ottenimento studente
  $query_string = "select matricola, email, nome, cognome from $table_studenti where matricola = $1";
  $query_params = array($_GET["matricola"]);
  $result = $database->execute_query("get_studente", $query_string, $query_params);
  if($result->row_count() == 0) {
    header("location: /dashboard/segreteria/studenti.php");
    die();
  }
  $studente = $result->row();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Docente"); ?>
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
                  <li><a href="/dashboard/segreteria/studenti.php">Gestione studenti</a></li>
                  <li class="is-active"><a href="#" aria-current="page">Gestione studente "<?php echo $studente["nome"] . " " . $studente["cognome"]; ?>"</a></li>
                </ul>
              </nav>
            </div>

            <?php if(isset($_GET["storico"]) && $_GET["storico"] == true) { ?>
              <div class="column is-12">
                <article class="message is-warning">
                  <div class="message-body">
                    Si sta visualizzando il profilo di un utente <strong>eliminato</strong>, tale utente non è più attivo nel sistema.
                  </div>
                </article>
              </div>
            <?php } ?>

            <!-- titolo sezione -->
            <?php section_title("Informazioni"); ?>

            <!-- form modifica studente -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- nome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input value=<?php echo $studente["nome"]; ?> placeholder="es. Mario" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- cognome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Cognome</label>
                      <div class="control">
                        <input value=<?php echo $studente["cognome"]; ?> placeholder="es. Cognome" minlength="2" name="cognome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- email -->
                  <div class="column is-12">
                    <label class="label">E-mail</label>
                    <div class="field is-flex has-addons">
                      <p class="control" style="flex: 1 1 auto;">
                        <input value=<?php echo explode("@", $studente["email"])[0]; ?> class="input" name="email" type="text" placeholder="es. mario.rossi">
                      </p>
                      <p class="control">
                        <a class="button is-static">@uniesempio.it</a>
                      </p>
                    </div>
                  </div>

                  <!-- submit -->
                  <div class="column is-12 is-flex is-justify-content-end">
                    <div class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">
                          Salva modifiche
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <?php error_message($error_msg) ?>

                </div>
              </form>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Carriera"); ?>
            
            <!-- select e tabelle -->
            <?php carriera($database, $studente["matricola"], $_GET["storico"]); ?>

            <!-- eliminazione studente -->
            <?php if(!isset($_GET["storico"]) || $_GET["storico"] != true) { ?>
              <!-- titolo sezione -->
              <?php section_title("Eliminazione", "Rimuovi studente in seguito a laurea o rinuncia agli studi"); ?>

              <!-- form per eliminazione studente -->
              <div class="column is-12">
                <div class="box">
                  <div class="columns is-multiline">
                    <div class="column is-10">L'eliminazione dello studente non comporta la perdita dei dati relativi a quest'ultimo o alla relativa carriera. Lo studente eliminato non sarà più in grado di autenticarsi. L'operazione è irreversibile.</div>
                    <div class="column is-2">
                      <div class="field">
                        <div class="control">
                          <button onclick="handleEliminazioneStudente()" class="button is-fullwidth is-danger is-outlined">
                            <ion-icon name="trash"></ion-icon>
                          </button>
                        </div>
                      </div>
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

    <!-- icone -->
    <?php include_once("./../../../components/icons.php"); ?>
    <!-- toast -->
    <?php include_once("./../../../components/toasts.php"); ?>
    <!-- axios -->
    <?php include_once("./../../../components/axios.php"); ?>
    <!-- mostra toast con esito richiesta iscrizione -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg == NULL) { ?>
          showSuccessToast("Studente modificato con successo.");
        <?php } ?>
      });
    </script>
    <!-- gestisce eliminazione dello studente -->
    <script type="text/javascript">
      function handleEliminazioneStudente() {
        axios.delete("/api/segreteria/delete-studente.php", {
          params: {
            matricola: <?php echo $_GET["matricola"]; ?>
          }
        })
        .then((response) => window.location.replace("/dashboard/segreteria/studente.php?storico=true&matricola=<?php echo $_GET["matricola"]; ?>"))
        .catch(({ response: { data } }) => showDangerToast(data.message));
      }
    </script>

  </body>
</html>
<?php $database->close_conn(); ?>