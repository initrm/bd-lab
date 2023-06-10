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

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di creazione di un nuovo docente
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["nome"]) && isset($_POST["cognome"]) && isset($_POST["email"])) {
      // tentativo di creazione del docente
      $query_string = "insert into docenti(email, nome, cognome, password) values ($1, $2, $3, $4)";
      $query_params = array($_POST["email"] . "@uniesempio.it", $_POST["nome"], $_POST["cognome"], strtolower($_POST["nome"] . "." . $_POST["cognome"]));
      try {
        $database->execute_query("insert_docente", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }
  
  // ottenimento docenti
  $query_string = "select email, nome, cognome from docenti";
  $result = $database->execute_query("get_docenti", $query_string, array());
  $docenti = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Docenti"); ?>
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
                  <li class="is-active"><a href="#" aria-current="page">Gestione docenti</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione e sottotitolo -->
            <?php section_title("Crea docente"); ?>

            <!-- form creazione nuovo docente -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- nome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input placeholder="es. Mario" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- cognome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Cognome</label>
                      <div class="control">
                        <input placeholder="es. Cognome" minlength="2" name="cognome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- email -->
                  <div class="column is-12">
                    <label class="label">E-mail</label>
                    <div class="field is-flex has-addons">
                      <p class="control" style="flex: 1 1 auto;">
                        <input class="input" name="email" type="text" placeholder="es. mario.rossi">
                      </p>
                      <p class="control">
                        <a class="button is-static">@uniesempio.it</a>
                      </p>
                    </div>
                  </div>

                  <!-- disclaimer password -->
                  <div class="column is-12">
                    <p class="is-size-7">L'utente viene creato con una password di default che corrisponde a "nome.cognome".</p>
                  </div>

                  <!-- submit -->
                  <div class="column is-12 is-flex is-justify-content-end">
                    <div class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">Crea docente</button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <?php error_message($error_msg); ?>

                </div>
              </form>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Lista docenti"); ?>
            
            <!-- card docenti -->
            <?php foreach($docenti as $docente) { ?>
              <div class="column is-12">
                <div class="box">
                  <div class="columns">
                    <div class="column is-8 is-flex is-align-items-center">
                      <p><?php echo $docente["nome"] . " " . $docente["cognome"] . " (" . $docente["email"] . ")"; ?></p>
                    </div>
                    <div class="column is-4 is-flex is-justify-content-end">
                      <a href="/dashboard/segreteria/docente.php?email=<?php echo $docente["email"] ?>" class="button is-link is-outlined">
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

    <!-- scripts -->

    <!-- icone -->
    <?php include_once("./../../../components/icons.php"); ?>
    <!-- toast -->
    <?php include_once("./../../../components/toasts.php"); ?>
    <!-- mostra toast con esito richiesta iscrizione -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg == NULL) { ?>
          showSuccessToast("Utente creato con successo.");
        <?php } ?>
      });
    </script>
    
  </body>
</html>