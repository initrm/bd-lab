<?php
  include_once('./../database.php');
  include_once('./../auth.php');
  include_once('./../components/head.php');
  include_once('./../utype.php');

  $authenticator = new Authenticator();

  // se l'utente è già loggato viene reindirizzato alla dashboard
  if($authenticator->is_authenticated()) {
    header("location: /dashboard/index.php");
    die();
  }

  // eventuale messaggio di errore
  $error_msg = NULL;

  // se la richiesta è di tipo post, e quindi, si suppone conseguente all'invio del form per effettuare l'accesso,
  // viene effettuato un tentativo di autenticazione
  if($_SERVER["REQUEST_METHOD"] == "POST") {

    // controllo presenza e correttezza dei parametri
    if(isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["tipo_utente"]) 
    && in_array($_POST["tipo_utente"], array("studente", "docente", "segreteria"))) {

      // costruzione della query
      $query_vars = NULL;
      $user_type = NULL;
      switch($_POST["tipo_utente"]) {
        case "studente":
          $user_type = UserType::Studente;
          $query_vars = array("table" => "studenti", "cols" => "matricola, nome, cognome");
          break;
        case "docente":
          $user_type = UserType::Docente;
          $query_vars = array("table" => "docenti", "cols" => "nome, cognome");
          break;
        case "segreteria":
          $user_type = UserType::Segreteria;
          $query_vars = array("table" => "segreteria", "cols" => "");
          break;
      }
      $query_string = 
        " select email, " . $query_vars["cols"] .
        " from " . $query_vars["table"] . 
        " where email = $1 and password = $2";
      $query_params = array($_POST["email"], $_POST["password"]);

      // esecuzione della query
      $query_result = (new Database())->execute_single_query($query_string, $query_params);
      
      // accesso fallito
      if($query_result->row_count() == 0)
        $error_msg = "Credenziali errate.";
      // accesso avvenuto con successo, autenticazione utente e redirect
      else {
        $authenticator->authenticate($query_result->row(), $user_type);
        header("location: /dashboard/index.php");
        die();
      }
    }
    else 
      $error_msg = "Parametri nella richiesta mancanti o non corretti.";

  }
?>
<!DOCTYPE html>
<html>
  <?php head("Accesso"); ?>
  <body>
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-half-desktop">

          <!-- form di accesso -->
          <form method="post" class="box my-3 mx-3">

            <!-- indirizzo e-mail -->
            <div class="field">
              <label class="label">E-mail</label>
              <div class="control">
                <input 
                  value="<?php if(isset($_POST["email"])) echo $_POST["email"]; ?>" 
                  name="email" 
                  class="input" 
                  type="email" 
                  placeholder="es. mario@rossi.it"
                  required="true"
                />
              </div>
            </div>

            <!-- password -->
            <div class="field">
              <label class="label">Password</label>
              <div class="control">
                <input name="password" minlength="6" class="input" type="password" placeholder="********" required="true">
              </div>
            </div>

            <div class="columns">
              <div class="column">

                <!-- tipo di account -->
                <div class="field">
                  <label class="label">Tipo di account</label>
                  <div class="control">
                    <div class="select">
                      <select name="tipo_utente">
                        <option value="studente">Studente</option>
                        <option value="docente">Docente</option>
                        <option value="segreteria">Segreteria</option>
                      </select>
                    </div>
                  </div>
                </div>

              </div>
              <div class="column is-flex is-justify-content-end is-align-items-end">

                <!-- tasto submit -->
                <div class="field">
                  <div class="control">
                    <button class="button is-link is-outlined">Accedi</button>
                  </div>
                </div>

              </div>
            </div>

            <!-- error message -->
            <?php if($error_msg != NULL) { ?>
              <p class="help is-danger">
                <?php echo $error_msg; ?>
              </p>
            <?php } ?>

          </form>

        </div>
      </div>
    </div>
  </body>
</html>