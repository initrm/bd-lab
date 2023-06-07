<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');

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
  $database = new Database();
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di modifica del docente
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["nome"]) && isset($_POST["cognome"]) && isset($_POST["email"]) && isset($_POST["email-originale"])) {
      // tentativo di creazione del docente
      $query_string = "update docenti set email = $1, nome = $2, cognome = $3 where email = $4";
      $query_params = array($_POST["email"] . "@uniesempio.it", $_POST["nome"], $_POST["cognome"], $_POST["email-originale"]);
      try {
        $r = $database->execute_query("update_docente", $query_string, $query_params);
        if($r->affected_rows() == 0)
          $error_msg = "Errore sconosciuto.";
        else {
          header("location: /dashboard/segreteria/docente.php?edited=true&email=". $_POST["email"] . "@uniesempio.it");
          die();
        }
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }

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

  // chiusura connessione
  $database->close_conn();

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
                  <li><a href="/dashboard/segreteria/docenti.php">Gestione docenti</a></li>
                  <li class="is-active"><a href="#" aria-current="page">Gestione docente "<?php echo $docente["nome"] . " " . $docente["cognome"]; ?>"</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Informazioni docente"); ?>

            <!-- form modifica docente -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- nome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input value=<?php echo $docente["nome"]; ?> placeholder="es. Mario" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- cognome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Cognome</label>
                      <div class="control">
                        <input value=<?php echo $docente["cognome"]; ?> placeholder="es. Cognome" minlength="2" name="cognome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- email -->
                  <div class="column is-12">
                    <label class="label">E-mail</label>
                    <div class="field is-flex has-addons">
                      <p class="control" style="flex: 1 1 auto;">
                        <input value=<?php echo explode("@", $docente["email"])[0]; ?> class="input" name="email" type="text" placeholder="es. mario.rossi">
                      </p>
                      <p class="control">
                        <a class="button is-static">@uniesempio.it</a>
                      </p>
                    </div>
                  </div>

                  <!-- email originale -->
                  <input value="<?php echo $docente["email"]; ?>" type="hidden" name="email-originale" />

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

            <!-- titolo sezione -->
            <?php section_title("Insegnamenti del docente"); ?>
            
            <!-- card docenti -->
            <?php foreach($insegnamenti as $insegnamento) { ?>
              <div class="column is-12">
                <div class="card mb-5">
                  <header class="card-header">
                    <p class="card-header-title"><?php echo "(" . $insegnamento["corso_laurea"] . ", " . $insegnamento["codice"] . ") " . $insegnamento["nome"]; ?></p>
                  </header>
                  <div class="card-content">
                    <div class="content"><?php echo $insegnamento["descrizione"]; ?></div>
                  </div>
                  <footer class="card-footer">
                    <a href="/dashboard/segreteria/corso-laurea.php?codice=<?php echo $insegnamento["corso_laurea"]; ?>" class="card-footer-item">Vai al corso di laurea</a>
                    <a href="/dashboard/segreteria/insegnamento.php?cdl=<?php echo $insegnamento["corso_laurea"]; ?>&codice=<?php echo $insegnamento["codice"]; ?>" class="card-footer-item">Vai all'insegnamento</a>
                  </footer>
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
    <!-- mostra toast con esito richiesta iscrizione -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["edited"]) && $_GET["edited"] == true) { ?>
          showSuccessToast("Utente modificato con successo.");
        <?php } ?>
      });
    </script>

  </body>
</html>