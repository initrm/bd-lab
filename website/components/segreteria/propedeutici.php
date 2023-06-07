<?php
  include_once(dirname(__FILE__) . "/../../database.php");

  /**
   * restituisce un componente HTML corrispondente ad una tabella contenente i corsi che hanno il corso
   * identificato da $cdl e $insegnamento come propedeutico con la possibilità di raggiungere la pagina relativa
   * a ciascun corso
   * 
   * $cdl deve essere una stringa contenente il codice di un corso di laurea
   * $insegnamento deve essere una stringa contenente il codice di un insegnamento contenuto nel corso di laurea $cdl
   */
  function propedeutici(Database $database, string $cdl, string $insegnamento) {

    // ottenimento insegnamenti che hanno l'insegnamento corrente come propedeuticità
    $query_string = "select * from get_insegnamenti_a_cui_propedeutico($1, $2)";
    $query_params = array($cdl, $insegnamento);
    $result = $database->execute_query("get_propedeutici", $query_string, $query_params);
    $propedeutici = $result->all_rows();

?>
    <div class="column is-12">
      <div class="box">
        <div class="table-container">
          <table class="table is-striped is-bordered is-fullwidth">
            <thead>
              <tr>
                <th>Codice</th>
                <th>Insegnamento</th>
                <th><abbr title="Anno Previsto Insegnamento">API</abbr></th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($propedeutici as $p) { ?>
                <tr>
                  <td><?php echo $p["codice"]; ?></td>
                  <td><?php echo $p["nome"]; ?></td>
                  <td><?php echo $p["anno"]; ?></td>
                  <td class="is-flex is-justify-content-space-evenly">
                    <a href="/dashboard/segreteria/insegnamento.php?codice=<?php echo $p["codice"]; ?>&cdl=<?php echo $p["corso_laurea"]; ?>" class="button is-small is-link is-outlined">
                      <ion-icon name="arrow-forward-outline"></ion-icon>
                    </a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
<?php } ?>