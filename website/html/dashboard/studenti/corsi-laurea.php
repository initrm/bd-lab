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
  if($authenticator->get_authenticated_user_type() != UserType::Studente) {
    header("location: /dashboard/index.php");
    die();
  }

  // apertura connessione con il database
  $database = new Database($authenticator->get_authenticated_user_type());
  $database->open_conn();

  // eventuale messaggio di errore
  $error_msg = NULL;
  // variabili contenenti l'esito della richiesta post
  $cdl = NULL;
  $insegnamenti = NULL;

  // gestione eventuale richiesta di ottenimento di un corso specifico
  if(isset($_GET["cdl"])) {
    // ottenimento corso di laurea
    $query_string = "select * from corsi_laurea where codice = $1";
    $query_params = array($_GET["cdl"]);
    try {
      $result = $database->execute_query("select_corso_laurea", $query_string, $query_params);
      if($result->row_count() != 0) {
        $cdl = $result->row();
        // ottenimento informazioni
        $query_string = "select * from produci_informazioni_corso_laurea($1)";
        $query_params = array($cdl["codice"]);
        $insegnamenti = $database->execute_query("get_informazioni_cdl", $query_string, $query_params)->all_rows();
      }
      else 
        $error_msg = "Corso di laurea non trovato.";
    }
    catch(QueryError $e) {
      $error_msg = $e->getMessage();
    }
  }

  // ottenimento appelli a cui l'utente è attualmente iscritto che sono da svolgere nel futuro
  $query_string = "select * from corsi_laurea";
  $query_params = array();
  $result = $database->execute_query("get_iscrizioni", $query_string, $query_params);
  $corsi_laurea = $result->all_rows();

  // chiusura connessione
  $database->close_conn();

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

            <!-- breadcrumb -->
            <div class="column is-12">
              <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                  <li><a href="/dashboard/studenti/index.php">Home</a></li>
                  <?php if($cdl != NULL) { ?>
                    <li><a href="/dashboard/studenti/corsi-laurea.php">Corsi di laurea</a></li>
                    <li class="is-active">
                      <a href="#" aria-current="page">
                        <?php echo "(" . $cdl["codice"] . ") " . $cdl["nome"]; ?>
                      </a>
                    </li>
                  <?php } else { ?>
                    <li class="is-active"><a href="#" aria-current="page">Corsi di laurea</a></li>
                  <?php } ?>
                </ul>
              </nav>
            </div>

            <!-- titolo sezione e sottotitolo -->
            <?php section_title("Corsi di laurea", "Visualizza le informazioni relative ai corsi di laurea"); ?>

            <!-- selezione corso di laurea -->
            <div class="column is-12">
              <form class="box" method="get">
                <div class="columns is-multiline">
                  <div class="column is-10">
                    <div class="field">
                      <label class="label">Corso di laurea</label>
                      <div class="control">
                        <div class="select is-fullwidth">
                          <select name="cdl">
                            <?php foreach($corsi_laurea as $corso) { ?>
                              <option <?php if($cdl != NULL && $corso["codice"] == $cdl["codice"]) { ?> selected="selected" <?php } ?>  value="<?php echo $corso["codice"] ?>">
                                <?php echo "(" . $corso["codice"] . ") " . $corso["nome"]; ?>
                              </option>
                            <? } ?>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="column is-2 is-flex is-align-items-end">
                    <div class="field" style="flex: 1 1 auto;">
                      <div class="control">
                        <button class="button is-link is-outlined is-fullwidth">
                          <ion-icon name="arrow-forward-outline"></ion-icon>
                        </button>
                      </div>
                    </div>
                  </div>
                  <!-- error message -->
                  <?php error_message($error_msg); ?>
                </div>
              </form>
            </div>

            <?php if($cdl != NULL) { ?>

              <!-- titolo sezione -->
              <?php section_title("Informazioni"); ?>

              <!-- informazioni corso -->
              <div class="column is-12">
                <div class="box">
                  <p>Codice: <strong><?php echo $cdl["codice"]; ?></strong></p>
                  <p>Nome: <strong><?php echo $cdl["nome"]; ?></strong></p>
                  <p>Tipo: <strong><?php if($cdl["tipo"] == "T") { echo "Triennale"; } else { echo "Magistrale"; } ?></strong></p>
                </div>
              </div>

              <!-- titolo sezione -->
              <?php section_title("Insegnamenti"); ?>

              <!-- insegnamenti corso -->
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
                  </div>
                </div>
              <?php } ?>

            <?php } ?>

          </div>
        </div>
      </div>
    </div>

    <!-- scripts -->

    <!-- icone -->
    <?php include_once("./../../../components/icons.php"); ?>

  </body>
</html>