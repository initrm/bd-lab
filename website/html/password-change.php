<?php
  include_once('./../auth.php');
  include_once('./../components/head.php');
  include_once('./../database.php');

  $authenticator = new Authenticator();

  // se l'utente non è loggato viene reindirizzato alla pagina per effettuare l'accesso
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // variabile che contiene l'eventuale messaggio di errore
  $error_msg = NULL;

  // se la richiesta è di tipo post, e quindi, si suppone conseguente all'invio del form per la modifica
  // della password, viene effettuato un tentativo di autenticazione
  if($_SERVER["REQUEST_METHOD"] == "POST") {

    // controllo presenza dei parametri
    if(isset($_POST["vecchia_password"]) && isset($_POST["nuova_password"]) && isset($_POST["conferma_nuova_password"])) {

      // verifica della nuova password
      if($_POST["nuova_password"] == $_POST["conferma_nuova_password"]) {

        // verifica correttezza
        if(strlen($_POST["nuova_password"]) > 6 && strlen($_POST["nuova_password"]) <= 128) {
          $database = new Database();
          $database->open_conn();

          $table = NULL;
          switch($authenticator->get_authenticated_user_type()) {
            case UserType::Docente:
              $table = "docenti";
              break;
            case UserType::Segreteria:
              $table = "segreteria";
              break;
            case UserType::Studente:
              $table = "studenti";
              break;
          }

          // verifica della correttezza della vecchia password
          $query_string = "select email from progetto_esame." . $table . " where email = $1 and password = $2";
          $query_params = array($authenticator->get_authenticated_user()["email"], $_POST["vecchia_password"]);
          $results = $database->execute_query("old_psw_check", $query_string, $query_params);
          if($results->row_count() == 0)
            $error_msg = "La password vecchia non è corretta.";
          else {

            // cambio della password
            $query_string = "update progetto_esame." . $table . " set password = $1 WHERE email = $2 and password = $3;";
            $query_params = array($_POST["nuova_password"], $authenticator->get_authenticated_user()["email"], $_POST["vecchia_password"]);
            $results = $database->execute_query("password_update", $query_string, $query_params);
            if($results->affected_rows() == 0)
              $error_msg = "Qualcosa è andato storto.";
            else 
              header("location: /dashboard/index.php");
          }
        }
        else
          $error_msg = "La lunghezza della password deve essere compresa tra 6 e 128 caratteri.";
      }
      else
        $error_msg = "Le password non coincidono.";
    }
    else
      $error_msg = "Parametri nella richiesta mancanti o non corretti.";
  }
?>
<!DOCTYPE html>
<html>
  <?php head("Cambio Password"); ?>
  <body>
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-half-desktop">

          <!-- form di modifica della password -->
          <form method="post" class="box my-3 mx-3">

            <!-- vecchia password -->
            <div class="field">
              <label class="label">Vecchia password</label>
              <div class="control">
                <input name="vecchia_password" minlength="6" class="input" type="password" placeholder="********" required="true">
              </div>
            </div>

            <!-- nuova password -->
            <div class="field">
              <label class="label">Nuova password</label>
              <div class="control">
                <input name="nuova_password" minlength="6" class="input" type="password" placeholder="********" required="true">
              </div>
            </div>

            <!-- conferma nuova password -->
            <div class="field">
              <label class="label">Conferma nuova password</label>
              <div class="control">
                <input name="conferma_nuova_password" minlength="6" class="input" type="password" placeholder="********" required="true">
              </div>
            </div>

            <div class="columns">
              <div class="column is-flex is-justify-content-end is-align-items-end">

                <!-- tasto submit -->
                <div class="field">
                  <div class="control">
                    <button class="button is-link is-outlined">Modifica password</button>
                  </div>
                </div>

              </div>
            </div>

            <!-- error message -->
            <?php if(isset($GLOBALS["error_msg"])) { ?>
              <p class="help is-danger">
                <?php echo $GLOBALS["error_msg"]; ?>
              </p>
            <?php } ?>

          </form>

        </div>
      </div>
    </div>
  </body>
</html>