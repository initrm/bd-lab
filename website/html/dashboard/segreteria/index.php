<?php
  include_once('./../../../auth.php');
  include_once('./../../../utype.php');
  include_once('./../../../components/head.php');
  include_once('./../../../components/navbar.php');
  include_once('./../../../components/title.php');
  include_once('./../../../components/segreteria/menu-item-card.php');

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

    <!-- navbar -->
    <?php navbar($authenticator->get_authenticated_user()["email"]); ?>

    <!-- content -->
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-10-desktop">
          <div class="columns mx-2 is-multiline">

            <!-- titolo pagina e sottotitolo -->
            <?php section_title("Dashboard", "Gestisci studenti, docenti, cdl ed insegnamenti"); ?>

            <!-- gestione studenti -->
            <?php menu_item_card("Gestione studenti", "Visualizza, aggiungi e rimuovi studenti.", "/dashboard/segreteria/studenti.php"); ?>

            <!-- gestione studenti -->
            <?php menu_item_card("Gestione docenti", "Visualizza, aggiungi e rimuovi docenti.", "/dashboard/segreteria/docenti.php"); ?>

            <!-- gestione cdl -->
            <?php menu_item_card("Gestione corsi di laurea", "Visualizza, aggiungi e rimuovi corsi di laurea.", "/dashboard/segreteria/corsi-laurea.php"); ?>

          </div>
        </div>
      </div>
    </div>
  </body>
</html>