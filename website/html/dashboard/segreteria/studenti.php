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
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;

  // gestione della eventuale richiesta di creazione di un nuovo studente
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    // verifica presenza parametri
    if(isset($_POST["nome"]) && isset($_POST["cognome"]) && isset($_POST["email"]) && isset($_POST["corso-laurea"])) {
      // tentativo di creazione dello studente
      $query_string = "insert into studenti(email, nome, cognome, password, corso_laurea) values ($1, $2, $3, $4, $5)";
      $query_params = array($_POST["email"] . "@uniesempio.it", $_POST["nome"], $_POST["cognome"], $_POST["nome"] . "." . $_POST["cognome"], $_POST["corso-laurea"]);
      try {
        $database->execute_query("insert_studente", $query_string, $query_params);
      }
      catch(QueryError $e) {
        $error_msg = $e->getMessage();
      }
    }
    else 
      $error_msg = "Parametri mancanti.";
  }
  
  // ottenimento studenti attivi
  $query_string = "select matricola, email, nome, cognome, corso_laurea from studenti order by matricola asc";
  $result = $database->execute_query("get_studenti", $query_string, array());
  $studenti = $result->all_rows();

  // ottenimento storico studenti
  $query_string = "select * from storico_studenti order by matricola asc";
  $result = $database->execute_query("get_storico_studenti", $query_string, array());
  $storico = $result->all_rows();

  // ottenimento corsi laurea
  $query_string = "select * from corsi_laurea";
  $result = $database->execute_query("get_corsi_laurea", $query_string, array());
  $corsi_laurea = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

?>
<!DOCTYPE html>
<html>
  <?php head("Gestione Studenti"); ?>
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
                  <li class="is-active"><a href="#" aria-current="page">Gestione Studenti</a></li>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione -->
            <?php section_title("Crea studente"); ?>

            <!-- form creazione nuovo studente -->
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
                        <input placeholder="es. Rossi" minlength="2" name="cognome" class="input" type="text" required="true" />
                      </div>
                    </div>
                  </div>

                  <!-- email -->
                  <div class="column is-6">
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

                  <!-- corso di laurea -->
                  <div class="column is-6">
                    <div class="field">
                      <label class="label">CdL</label>
                      <div class="control">
                        <div class="select is-fullwidth">
                          <select name="corso-laurea">
                            <?php foreach($corsi_laurea as $corso) { ?>
                              <option value="<?php echo $corso["codice"]; ?>">
                                <?php echo "(" . $corso["codice"] . ") " . $corso["nome"]; ?>
                              </option>
                            <?php } ?>
                          </select>
                        </div>
                      </div>
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
                        <button class="button is-link is-outlined is-fullwidth">Crea studente</button>
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
            <?php section_title("Lista studenti"); ?>

            <!-- selezione tipo di studenti da visualizzare -->
            <div class="column is-12">
              <div class="select">
                <select id="tipo-studenti-select" onchange="handleTipoStudentiSelect()">
                  <option value="attivi">Attivi</option>
                  <option value="storico">Storico</option>
                </select>
              </div>
            </div>
            
            <!-- card studenti -->
            <div id="lista-attivi" class="column is-12">
              <div class="columns is-multiline">
                <?php foreach($studenti as $studente) { ?>
                  <div  class="column is-12">
                    <div class="box">
                      <div class="columns">
                        <div class="column is-8 is-flex is-align-items-center">
                          <p><strong><?php echo "[" . $studente["matricola"] . "]"; ?></strong> <?php echo $studente["nome"] . " " . $studente["cognome"] . " (" . $studente["email"] . ")"; ?></p>
                        </div>
                        <div class="column is-4 is-flex is-justify-content-end">
                          <a href="/dashboard/segreteria/studente.php?matricola=<?php echo $studente["matricola"] ?>" class="button is-link is-outlined">
                            <ion-icon name="arrow-forward-outline"></ion-icon>
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php } ?>
              </div>
            </div>

            <!-- card studenti storico -->
            <div id="lista-storico" class="column is-12 is-hidden">
              <div class="columns">
                <?php foreach($storico as $studente) { ?>
                  <div class="column is-12">
                    <div class="box">
                      <div class="columns">
                        <div class="column is-8 is-flex is-align-items-center">
                          <p><strong><?php echo "[" . $studente["matricola"] . "]"; ?></strong> <?php echo $studente["nome"] . " " . $studente["cognome"] . " (" . $studente["email"] . ")"; ?></p>
                        </div>
                        <div class="column is-4 is-flex is-justify-content-end">
                          <a href="/dashboard/segreteria/studente.php?storico=true&matricola=<?php echo $studente["matricola"] ?>" class="button is-link is-outlined">
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
          showSuccessToast("Studente creato con successo.");
        <?php } ?>
      });
    </script>
    <!-- gestisce tipo di studenti da visualizzare -->
    <script type="text/javascript">
      function handleTipoStudentiSelect() {
        let val = document.getElementById("tipo-studenti-select").value;
        let containerAttivi = document.getElementById("lista-attivi");
        let containerStorico = document.getElementById("lista-storico");
        if(val === 'storico') {
          containerAttivi.classList.add("is-hidden");
          containerStorico.classList.remove("is-hidden");
        }
        else {
          containerStorico.classList.add("is-hidden");
          containerAttivi.classList.remove("is-hidden");
        }
      }
    </script>

  </body>
</html>