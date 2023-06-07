<?php
  include_once(dirname(__FILE__) . '/../database.php');

  /**
   * restituisce un componente html che permette di selezionare i docenti tra quelli disponibili, che 
   * come attributo name ha "docente" e che come value di ciascun opzione ha l'id (email) di ciascun docente
   * 
   * $default_value deve essere una stringa contenente l'email del docente che deve essere selezionato di default
   */
  function select_docenti(Database $database, ?string $default_value = NULL) {

    // ottenimento docenti
    $query_string = "select email, nome, cognome from docenti";
    $results = $database->execute_query("select_docenti_component_get_docenti", $query_string, array());
    $docenti = $results->all_rows();
?>
    <div class="field">
      <label class="label">Docente</label>
      <div class="control">
        <div class="select is-fullwidth">
          <select name="docente">
            <?php foreach($docenti as $docente) { ?>
              <option <?php if($default_value != NULL && $docente["email"] == $default_value) { ?>selected="selected"<?php } ?>value="<?php echo $docente["email"]; ?>">
                <?php echo $docente["nome"] . " " . $docente["cognome"]; ?>
              </option>
            <?php } ?>
          </select>
        </div>
      </div>
    </div>
<?php } ?>