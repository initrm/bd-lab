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

  // apertura connessione con il database
  $database = new Database();
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di creazione di un nuovo corso di laurea
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["codice"]) && isset($_POST["nome"]) && isset($_POST["tipo"])) {
      // tentativo di creazione del docente
      $query_string = "insert into corsi_laurea(codice, nome, tipo) values ($1, $2, $3)";
      $query_params = array($_POST["codice"], $_POST["nome"], $_POST["tipo"]);
      try {
        $database->execute_query("insert_corso_laurea", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }
  
  // ottenimento corsi di laurea
  $query_string = "select * from corsi_laurea";
  $result = $database->execute_query("get_corsi_laurea", $query_string, array());
  $corsi_laurea = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Corsi di Laurea"); ?>
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
                  <li class="is-active"><a href="#" aria-current="page">Gestione corsi di laurea</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Crea corso di laurea", NULL); ?>

            <!-- form creazione nuovo corso di laurea -->
            <div class="column is-12">
              <form method="post" class="box my-3">
                <div class="columns is-multiline">

                  <!-- nome -->
                  <div class="column is-5">
                    <div class="field">
                      <label class="label">Codice</label>
                      <div class="control">
                        <input placeholder="es. L-10" minlength="2" name="codice" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- nome -->
                  <div class="column is-5">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input placeholder="es. Informatica" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- tipo -->
                  <div class="column is-2">
                    <div class="field">
                      <label class="label">Tipo</label>
                      <div class="control">
                        <div class="select">
                          <select name="tipo">
                            <option value="T">Triennale</option>
                            <option value="M">Magistrale</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- submit -->
                  <div class="column is-12 is-flex is-justify-content-end">
                    <div class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">Crea corso di laurea</button>
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
            <?php section_title("Lista corsi di laurea", NULL); ?>
            
            <!-- card docenti -->
            <?php foreach($corsi_laurea as $corso) { ?>
              <div class="column is-12">
                <div class="box">
                  <div class="columns">
                    <div class="column is-10 is-flex is-align-items-center">
                      <p><?php echo "<strong>" . $corso["codice"] . "</strong>" . " - " . $corso["nome"] ?></p>
                      <p class="mx-2">
                        <span class="tag is-<?php echo $corso["tipo"] == "T" ? "link" : "info" ?>">
                          <?php echo $corso["tipo"] == "T" ? "TRIENNALE" : "MAGISTRALE" ?>
                        </span>
                      </p>
                    </div>
                    <div class="column is-2 is-flex is-justify-content-end">
                      <a href="/dashboard/segreteria/corso-laurea.php?codice=<?php echo $corso["codice"] ?>" class="button is-link is-outlined">
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
    <?php include_once('./../../../components/toasts.php'); ?>
    <!-- toast -->
    <?php include_once('./../../../components/icons.php'); ?>
    <!-- mostra toast con esito richiesta iscrizione -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg == NULL) { ?>
          showSuccessToast("Corso di laurea creato con successo.");
        <?php } ?>
      });
    </script>

  </body>
</html>