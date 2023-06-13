<?php
  error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/select-docenti.php');
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
  if(!isset($_GET["codice"])) {
    header("location: /dashboard/segreteria/corsi-laurea.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di modifica cdl
  if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "update-cdl") {
    // verifica presenza parametri
    if(isset($_POST["nome"]) && isset($_POST["tipo"])) {
      // tentativo di aggiornamento del cdl
      $query_string = "update corsi_laurea set nome = $1, tipo = $2 where codice = $3";
      $query_params = array($_POST["nome"], $_POST["tipo"], $_GET["codice"]);
      try {
        $result = $database->execute_query("update_cdl", $query_string, $query_params);
        if($result->affected_rows() != 0) { // se == 0, ovvero corso non esiste, subito dopo si viene reinidirizzati
          header("location: /dashboard/segreteria/corso-laurea.php?codice=" . $_GET["codice"]);
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

  // ottenimento corso laurea
  $query_string = "select * from corsi_laurea where codice = $1";
  $query_params = array($_GET["codice"]);
  $result = $database->execute_query("get_corso_laurea", $query_string, $query_params);
  if($result->row_count() == 0) {
    header("location: /dashboard/segreteria/corsi-laurea.php");
    die();
  }
  $corso_laurea = $result->row();

  // gestione della eventuale richiesta di aggiunta insegnamento
  if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "add-insegnamento") {
    // verifica presenza parametri
    if(isset($_POST["codice"]) && isset($_POST["nome"]) && isset($_POST["descrizione"]) && isset($_POST["anno"]) && isset($_POST["docente"])) {
      // tentativo di creazione dell'insegnamento
      $query_string = "insert into insegnamenti(corso_laurea, codice, nome, descrizione, anno, docente) values ($1, $2, $3, $4, $5, $6)";
      $query_params = array($corso_laurea["codice"], $_POST["codice"], $_POST["nome"], $_POST["descrizione"], $_POST["anno"], $_POST["docente"]);
      try {
        $database->execute_query("insert_insegnamento", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }

  // ottenimento insegnamenti del corso di laurea
  $query_string = "select * from produci_informazioni_corso_laurea($1)";
  $query_params = array($corso_laurea["codice"]);
  $result = $database->execute_query("get_informazioni_corso_laurea", $query_string, $query_params);
  $insegnamenti = $result->all_rows();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Corso di Laurea"); ?>
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
                  <li><a href="/dashboard/segreteria/corsi-laurea.php">Gestione corsi di laurea</a></li>
                  <li class="is-active"><a href="#" aria-current="page">Gestione corso di laurea "(<?php echo $corso_laurea["codice"]; ?>) <?php echo $corso_laurea["nome"]; ?>"</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Modifica corso"); ?>

            <!-- form modifica cdl -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- azione -->
                  <input type="hidden" value="update-cdl" name="action" />

                  <!-- codice -->
                  <div class="column is-5">
                    <div class="field">
                      <label class="label">Codice</label>
                      <div class="control">
                        <input disabled="true" value="<?php echo $corso_laurea["codice"]; ?>" minlength="2" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- nome -->
                  <div class="column is-5">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input value="<?php echo $corso_laurea["nome"]; ?>" minlength="2" name="nome" class="input" type="text" required="true" />
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
                            <option <?php if($corso_laurea["tipo"] == "T") { ?> selected="selected" <?php } ?> value="T">Triennale</option>
                            <option <?php if($corso_laurea["tipo"] == "M") { ?> selected="selected" <?php } ?> value="M">Magistrale</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- submit -->
                  <div class="column is-12 is-flex is-justify-content-end">
                    <div class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">Salva modifiche</button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <?php error_message($error_msg); ?>

                </div>
              </form>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Aggiungi insegnamento al corso di laurea"); ?>

            <!-- form inserimento insegnamento -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- azione -->
                  <input type="hidden" value="add-insegnamento" name="action" />

                  <!-- codice -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Codice</label>
                      <div class="control">
                        <input placeholder="es. ABC" minlength="1" name="codice" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- nome -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input placeholder="es. Biologia" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- descrizione -->
                  <div class="column is-12">
                    <div class="field">
                      <label class="label">Descrizione</label>
                      <div class="control">
                        <textarea minlength="12" name="descrizione" class="textarea" placeholder="es. L'insegnamento si pone come obiettivo quello di..."></textarea>
                      </div>
                    </div>
                  </div>

                  <!-- anno -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">Anno</label>
                      <div class="control">
                        <div class="select is-fullwidth">
                          <select name="anno">
                            <option value="1">Primo</option>
                            <option value="2">Secondo</option>
                            <?php if($corso_laurea["tipo"] == "T") { ?><option value="3">Terzo</option><?php } ?>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- docente -->
                  <div class="column is-6">
                    <?php select_docenti($database); ?>
                  </div>

                  <!-- submit -->
                  <div class="column is-12 is-flex is-justify-content-end">
                    <div class="field">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">
                          Aggiungi insegnamento
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- error message -->
                  <?php error_message($error_msg); ?>

                </div>
              </form>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Insegnamenti del corso di laurea"); ?>
            
            <!-- card insegnamenti -->
            <?php foreach($insegnamenti as $insegnamento) { ?>
              <div class="column is-12">
                <div class="card mb-5">
                  <header class="card-header">
                    <p class="card-header-title"><?php echo "(" . $insegnamento["codice"] . ") " . $insegnamento["nome"]; ?></p>
                  </header>
                  <div class="card-content">
                    <div class="content">
                      <?php echo $insegnamento["descrizione"]; ?>
                    </div>
                    <span class="tag is-link">ANNO: <?php echo $insegnamento["anno"]?></span>
                    <span class="tag is-info">DOCENTE: <?php echo $insegnamento["nome_docente"] . " " . $insegnamento["cognome_docente"]?></span>
                  </div>
                  <footer class="card-footer">
                    <a href="/dashboard/segreteria/docente.php?email=<?php echo $insegnamento["email_docente"]; ?>" class="card-footer-item">Vai al docente</a>
                    <a href="/dashboard/segreteria/insegnamento.php?cdl=<?php echo $corso_laurea["codice"]; ?>&codice=<?php echo $insegnamento["codice"]; ?>" class="card-footer-item">Vai all'insegnamento</a>
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
    <!-- mostra toast con esito richiesta aggiunta insegnamento -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', () => {
        <?php if($_SERVER["REQUEST_METHOD"] == "POST" && $error_msg == NULL) { ?>
          showSuccessToast("Insegnamento aggiunto con successo.");
        <?php } ?>
      });
    </script>

  </body>
</html>
<?php $database->close_conn(); ?>