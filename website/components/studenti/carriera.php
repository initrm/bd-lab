<?php
  include_once(dirname(__FILE__) . "/../../database.php");
  include_once(dirname(__FILE__) . "/exam-table.php");

  /**
   * restituisce un componente HTML che corrisponde ad un select che permette di selezionare se visualizzare le
   * tabelle contenenti la carriera completa e la tabella valida dello studente e queste ultime
   * 
   * $matricola deve corrispondere ad un intero che identifica uno studente (matricola)
   * $storico se è true considera lo studente come di storico e non attivo, altrimenti, il contrario
   */
  function carriera(Database $database, int $matricola, ?bool $storico = false) {

    if($storico == NULL) $storico = false;

    $carriera_completa_func = $storico ? "produci_carriera_completa_studente_storico" : "produci_carriera_completa_studente";
    $carriera_valida_func = $storico ? "produci_carriera_valida_studente_storico" : "produci_carriera_valida_studente";

    // ottenimento carriera completa
    $query_string = "select * from $carriera_completa_func($1)";
    $query_params = array($matricola);
    $result = $database->execute_query("get_carriera_completa", $query_string, $query_params);
    $carriera_completa = $result->all_rows();

    // ottenimento carriera valida
    $query_string = "select * from $carriera_valida_func($1)";
    $query_params = array($matricola);
    $result = $database->execute_query("get_carriera_valida", $query_string, $query_params);
    $carriera_valida = $result->all_rows();

?>
    <!-- selezione tipo di carriera da visualizzare -->
    <div class="column is-12">
      <div class="select">
        <select id="tipo-carriera-select" onchange="handleTipoCarrieraSelect()">
          <option value="completa">Carriera completa</option>
          <option value="valida">Carriera valida</option>
        </select>
      </div>
    </div>

    <!-- carriera completa -->
    <div id="carriera-completa" class="column is-12">
      <div class="box">
        <?php exam_table($carriera_completa); ?>
      </div>
    </div>

    <!-- carriera valida -->
    <div id="carriera-valida" class="column is-12 is-hidden">
      <div class="box">
        <?php exam_table($carriera_valida); ?>
      </div>
    </div>

    <!-- gestisce tipo di carriera da visualizzare -->
    <script type="text/javascript">
      function handleTipoCarrieraSelect() {
        let val = document.getElementById("tipo-carriera-select").value;
        let containerCompleta = document.getElementById("carriera-completa");
        let containerValida = document.getElementById("carriera-valida");
        if(val === 'valida') {
          containerCompleta.classList.add("is-hidden");
          containerValida.classList.remove("is-hidden");
        }
        else {
          containerValida.classList.add("is-hidden");
          containerCompleta.classList.remove("is-hidden");
        }
      }
    </script>
<?php } ?>