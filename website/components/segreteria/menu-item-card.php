<?php 
  /**
   * restituisce un componente HTML corrispondente ad una card del menu della dashboard index per l'utente segreteria
   */
  function menu_item_card(string $title, string $description, string $redirect_to) {
?>
    <div class="column is-6">
      <div class="card mb-5">
        <header class="card-header">
          <p class="card-header-title"><?php echo $title; ?></p>
        </header>
        <div class="card-content">
          <div class="content"><?php echo $description; ?></div>
        </div>
        <footer class="card-footer">
          <a href="<?php echo $redirect_to; ?>" class="card-footer-item">Vai</a>
        </footer>
      </div>
    </div>
<?php } ?>