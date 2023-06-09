<?php 
  /**
   * restituisce un componente HTML che contenente il messaggio di errore $message se quest'ultimo è diverso da NULL,
   * altrimenti non restituisce nulla 
   */
  function error_message(?string $message = NULL) { 
    if($message != NULL) {
?>
      <div class="column is-12">
          <p class="help is-danger">
            <?php echo $message; ?>
          </p>
      </div>
<?php 
    } 
  }
?>