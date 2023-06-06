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
  if($authenticator->get_authenticated_user_type() != UserType::Segreteria) {
    header("location: /dashboard/index.php");
    die();
  }
?>
<!DOCTYPE html>
<html>
  <?php head("Dashboard Segreteria"); ?>
  <body>
    <?php 
      $user = $authenticator->get_authenticated_user();
      $display_name = $user["email"];
      navbar($display_name);
    ?>
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">

          <div class="columns mx-2 is-multiline">

            <!-- titolo pagina e sottotitolo -->
            <div class="column is-12">
              <h1 class="title is-1">Dashboard</h1>
              <h2 class="subtitle">Gestisci studenti, docenti, cdl ed insegnamenti</h2>
            </div>

            <!-- gestione studenti -->
            <div class="column is-6">
              <div class="card mb-5">
                <header class="card-header">
                  <p class="card-header-title">Gestione studenti</p>
                </header>
                <div class="card-content">
                  <div class="content">Visualizza, aggiungi e rimuovi studenti.</div>
                </div>
                <footer class="card-footer">
                  <a href="/dashboard/segreteria/studenti.php" class="card-footer-item">Vai</a>
                </footer>
              </div>
            </div>

            <!-- gestione studenti -->
            <div class="column is-6">
              <div class="card mb-5">
                <header class="card-header">
                  <p class="card-header-title">Gestione docenti</p>
                </header>
                <div class="card-content">
                  <div class="content">Visualizza, aggiungi e rimuovi docenti.</div>
                </div>
                <footer class="card-footer">
                  <a href="/dashboard/segreteria/docenti.php" class="card-footer-item">Vai</a>
                </footer>
              </div>
            </div>

            <!-- gestione cdl -->
            <div class="column is-6">
              <div class="card mb-5">
                <header class="card-header">
                  <p class="card-header-title">Gestione corsi di laurea</p>
                </header>
                <div class="card-content">
                  <div class="content">Visualizza, aggiungi e rimuovi corsi di laurea.</div>
                </div>
                <footer class="card-footer">
                  <a href="/dashboard/segreteria/corsi-laurea.php" class="card-footer-item">Vai</a>
                </footer>
              </div>
            </div>

          </div>

        </div>
      </div>
    </div>
  </body>
</html>