<?php
  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../database.php');
  include_once('./../../../components/navbar.php');

  $authenticator = new Authenticator();

  // se l'utente non è loggato viene reindirizzato alla pagina per effettuare l'accesso
  if(!$authenticator->is_authenticated()) {
    header("location: /login.php");
    die();
  }

  // se l'utente non ha i permessi per accedere a questa pagina viene reindirizzato alla sua dashboard
  if($authenticator->get_authenticated_user_type() != UserType::Docente) {
    header("location: /dashboard/index.php");
    die();
  }
?>
<!DOCTYPE html>
<html>
  <?php head("Dashboard Docenti"); ?>
  <body>
    <?php 
      $user = $authenticator->get_authenticated_user();
      $display_name = $user["nome"] . " " . $user["cognome"];
      navbar($display_name);
    ?>
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">

          <div class="columns is-multiline">
            <div class="column">

              <!-- titolo pagina e sottotitolo -->
              <div class="column is-12">
                <h1 class="title is-1">I tuoi insegnamenti</h1>
                <h2 class="subtitle">Gestisci i calendari d'esame dei tuoi insegnamenti</h2>
              </div>
            
            </div>
            <div class="column is-12 is-multiline">

              <!-- card insegnamenti -->
              <?php
                $database = new Database();
                $query_string = "select * from progetto_esame.insegnamenti where docente = $1";
                $query_params = array($authenticator->get_authenticated_user()["email"]);
                $result = $database->execute_single_query($query_string, $query_params);
                $rows = $result->all_rows();
                for($i = 0; $i < sizeof($rows); $i++) {
              ?>
                  <div class="card mb-5 mx-2">
                    <header class="card-header">
                      <p class="card-header-title">
                        <?php echo $rows[$i]["nome"] ?>
                      </p>
                    </header>
                    <div class="card-content">
                      <div class="content">
                        <?php echo $rows[$i]["descrizione"] ?>
                      </div>
                    </div>
                    <footer class="card-footer">
                      <a href="/dashboard/docenti/appelli.php?insegnamento=<?php echo $rows[$i]["codice"] ?>&cdl=<?php echo $rows[$i]["corso_laurea"] ?>" class="card-footer-item">Visualizza appelli</a>
                    </footer>
                  </div>
              <?php
                }
              ?>

            </div>
          </div>

        </div>
      </div>
    </div>
  </body>
</html>