<?php 
  /**
   * 
   */
  function exam_table($records, $con_valutazioni = true) { 
?>
    <div class="table-container">
      <table class="table is-striped is-bordered is-fullwidth">
        <thead>
          <tr>
            <th><abbr title="Codice Univoco Appello">CUA</abbr></th>
            <th>Data</th>
            <th><abbr title="Corso di Laurea">CDL</abbr></th>
            <th><abbr title="Codice Insegnamento">CI</abbr></th>
            <th>Insegnamento</th>
            <th><abbr title="Anno Previsto Insegnamento">API</abbr></th>
            <th>Docente</th>
            <?php if($con_valutazioni) { ?><th>Valutazione</th><?php } ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach($records as $esame) { ?>
            <tr>
              <td><?php echo $esame["appello"]; ?></td>
              <td><?php echo date_format(date_create($esame["data_appello"]), "d/m/Y"); ?></td>
              <td><?php echo $esame["cdl"]; ?></td>
              <td><?php echo $esame["codice_insegnamento"]; ?></td>
              <td><?php echo $esame["nome_insegnamento"]; ?></td>
              <td><?php echo $esame["anno_insegnamento"]; ?></td>
              <td><?php echo $esame["nome_docente"] . " " . $esame["cognome_docente"]; ?></td>
              <?php if($con_valutazioni) { ?>
                <td <?php if($esame["valutazione"] != NULL) { ?> class="has-background-<?php echo $esame["valutazione"] >= 18 ? "success" : "danger"; ?>-light" <?php } ?>>
                  <?php echo $esame["valutazione"]; ?>
                </td>
              <?php } ?>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
<?php } ?>