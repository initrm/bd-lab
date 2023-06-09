<?php
  // error_reporting(E_ERROR | E_PARSE);

  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/segreteria/propedeutici.php');
  include_once('./../../../components/segreteria/propedeuticità.php');
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

  // controllo presenza parametri get
  if(!isset($_GET["codice"]) || !isset($_GET["cdl"])) {
    header("location: /dashboard/segreteria/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di modifica dell'insegnamento
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["codice"]) && isset($_POST["nome"]) && isset($_POST["descrizione"]) && isset($_POST["anno"]) && isset($_POST["docente"]) && isset($_POST["codice-originale"])) {
      // tentativo di aggiornamento dell'insegnamento
      $query_string = "update insegnamenti set codice = $1, nome = $2, descrizione = $3, anno = $4, docente = $5 where codice = $6 and corso_laurea = $7";
      $query_params = array($_POST["codice"], $_POST["nome"], $_POST["descrizione"], $_POST["anno"], $_POST["docente"], $_POST["codice-originale"], $_POST["corso-laurea"]);
      try {
        $r = $database->execute_query("update_insegnamento", $query_string, $query_params);
        if($r->affected_rows() == 0)
          $error_msg = "Errore sconosciuto.";
        else {
          header("location: /dashboard/segreteria/insegnamento.php?edited=true&codice=" . $_POST["codice"] . "&cdl=". $_POST["corso-laurea"]);
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

  // ottenimento insegnamento
  $query_string = "select * from informazioni_complete_insegnamenti where corso_laurea = $1 and codice = $2";
  $query_params = array($_GET["cdl"], $_GET["codice"]);
  $result = $database->execute_query("get_insegnamento", $query_string, $query_params);
  if($result->row_count() == 0) {
    header("location: /dashboard/segreteria/index.php");
    die();
  }
  $insegnamento = $result->row();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Insegnamento"); ?>
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
                  <li><a href="/dashboard/segreteria/corso-laurea.php?codice=<?php echo $insegnamento["corso_laurea"]; ?>">Gestione corso di laurea "(<?php echo $insegnamento["corso_laurea"]; ?>) <?php echo $insegnamento["nome_corso_laurea"]; ?>"</a></li>
                  <li class="is-active"><a href="#" aria-current="page">Gestione insegnamento "(<?php echo $insegnamento["codice"]; ?>) <?php echo $insegnamento["nome"]; ?>"</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Informazioni insegnamento"); ?>

            <!-- form modifica insegnamento -->
            <div class="column is-12">
              <form method="post" class="box my-3">

                <div class="columns is-multiline">

                  <!-- nome -->
                  <div class="column is-2">
                    <div class="field">
                      <label class="label">Codice</label>
                      <div class="control">
                        <input value="<?php echo $insegnamento["codice"]; ?>" placeholder="es. BC" minlength="1" name="codice" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- nome -->
                  <div class="column is-10">
                    <div class="field">
                      <label class="label">Nome</label>
                      <div class="control">
                        <input value="<?php echo $insegnamento["nome"]; ?>" placeholder="es. Beni culturali" minlength="2" name="nome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- descrizione -->
                  <div class="column is-12">
                    <div class="field">
                      <label class="label">Descrizione</label>
                      <div class="control">
                        <textarea minlength="12" rows="4" name="descrizione" class="textarea" placeholder="es. L'insegnamento si pone come obiettivo quello di..."><?php echo $insegnamento["descrizione"]; ?></textarea>
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
                            <option <?php if($insegnamento["anno"] == 1) { echo "selected=\"selected\""; } ?> value="1">Primo</option>
                            <option <?php if($insegnamento["anno"] == 2) { echo "selected=\"selected\""; } ?> value="2">Secondo</option>
                            <?php if($insegnamento["tipo_corso_laurea"] == "T") { ?><option <?php if($insegnamento["anno"] == 3) { echo "selected=\"selected\""; } ?> value="3">Terzo</option><?php } ?>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- docente -->
                  <div class="column is-6">
                    <?php select_docenti($database, $insegnamento["email_docente"]); ?>
                  </div>

                  <!-- codice originale -->
                  <input value="<?php echo $insegnamento["codice"]; ?>" type="hidden" name="codice-originale" />
                  <!-- corso di laurea -->
                  <input value="<?php echo $insegnamento["corso_laurea"]; ?>" type="hidden" name="corso-laurea" />

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
                  <?php error_message($error_msg); ?>

                </div>
              </form>
            </div>

            <!-- titolo e sottotitolo sezione -->
            <?php section_title("Propedeuticità", "Insegnamenti prepedeutici all'insegnamento"); ?>

            <!-- tabella propedeuticità -->
            <?php propedeuticità($database, $insegnamento["corso_laurea"], $insegnamento["codice"]); ?>

            <!-- sottotitolo sezione -->
            <?php section_title(NULL, "Insegnamenti che hanno l'insegnamento come propedeuticità"); ?>

            <!-- tabella propedeutici -->
            <?php propedeutici($database, $insegnamento["corso_laurea"], $insegnamento["codice"]); ?>
            
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
          showSuccessToast("Insegnamento modificato con successo.");
        <?php } ?>
      });
    </script>

  </body>
</html>
<?php $database->close_conn(); ?>