<?php
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
  if($authenticator->get_authenticated_user_type() != UserType::Docente) {
    header("location: /dashboard/index.php");
    die();
  }
?>
<!DOCTYPE html>
<html>
  <?php head("Dashboard Docenti"); ?>
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

            <!-- titolo pagina e sottotitolo -->
            <?php section_title("I tuoi insegnamenti", "Gestisci i calendari d'esame dei tuoi insegnamenti"); ?>

            <!-- card insegnamenti -->
            <?php
              $database = new Database();
              $query_string = "select * from insegnamenti where docente = $1";
              $query_params = array($authenticator->get_authenticated_user()["email"]);
              $result = $database->execute_single_query($query_string, $query_params);
              $rows = $result->all_rows();
              for($i = 0; $i < sizeof($rows); $i++) {
            ?>
              <div class="column is-12">
                <div class="card mb-5">
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
              </div>
            <?php
              }
            ?>

          </div>
        </div>
      </div>
    </div>
  </body>
</html>