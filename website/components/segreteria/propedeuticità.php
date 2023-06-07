<?php
  include_once(dirname(__FILE__) . "/../../database.php");

  /**
   * restituisce un componente HTML corrispondente ad una tabella contenente i corsi propedeutici al corso
   * identificato da $cdl e $insegnamento con la possibilità di raggiungere la pagina relativa a ciascun corso,
   * l'eliminazione di tale propedeuticità ed un form per l'aggiunta di una nuova propedeuticità propedeuticità
   * 
   * $cdl deve essere una stringa contenente il codice di un corso di laurea
   * $insegnamento deve essere una stringa contenente il codice di un insegnamento contenuto nel corso di laurea $cdl
   */
  function propedeuticità(Database $database, string $cdl, string $insegnamento) {

    // ottenimento propedeuticità per l'insegnamento corrente
    $query_string = "select * from get_insegnamenti_propedeutici($1, $2)";
    $query_params = array($cdl, $insegnamento);
    $result = $database->execute_query("get_propedeuticità", $query_string, $query_params);
    $propedeuticità = $result->all_rows();

    // ottenimento insegnamenti che possono essere aggiunti come propedeuticità
    $query_string = "select * from get_insegnamenti_aggiungibili_come_propedeutici($1, $2)";
    $query_params = array($cdl, $insegnamento);
    $result = $database->execute_query("get_opzioni_propedeuticità", $query_string, $query_params);
    $options = $result->all_rows();

?>
    <div class="column is-12">
      <div class="box">
        <div class="table-container">
          <table id="table-propedeuticità" class="table is-striped is-bordered is-fullwidth">
            <thead>
              <tr>
                <th>Codice</th>
                <th>Insegnamento</th>
                <th><abbr title="Anno Previsto Insegnamento">API</abbr></th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($propedeuticità as $p) { ?>
                <tr id="table-propedeuticità-insegnamento-<?php echo $p["codice"]; ?>">
                  <td><?php echo $p["codice"]; ?></td>
                  <td><?php echo $p["nome"]; ?></td>
                  <td><?php echo $p["anno"]; ?></td>
                  <td class="is-flex is-justify-content-space-evenly">
                    <a href="/dashboard/segreteria/insegnamento.php?codice=<?php echo $p["codice"]; ?>&cdl=<?php echo $p["corso_laurea"]; ?>" class="button mr-1 is-small is-link is-outlined">
                      <ion-icon name="arrow-forward-outline"></ion-icon>
                    </a>
                    <button insegnamento-target="<?php echo $p["codice"]; ?>" onclick="handleRimuoviPropedeuticità(event)" class="button is-small is-danger is-outlined">
                      <ion-icon name="trash"></ion-icon>
                    </button>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <div class="columns">
          <div class="column is-8">
            <div class="field">
              <label class="label">Insegnamento</label>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="select-insegnamento-propedeuticità">
                    <?php foreach($options as $o) { ?>
                      <option value="<?php echo $o["codice"]; ?>"><?php echo $o["nome"]; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="column is-4 is-flex is-justify-content-end is-align-items-end">
            <button onclick="handleAggiungiPropedeuticità()" style="flex: 1 1 auto;" class="button is-link is-outlined">
              Aggiungi propedeuticità
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- axios lib -->
    <?php include_once(dirname(__FILE__) . "/../axios.php"); ?>
    <!-- toast -->
    <?php include_once(dirname(__FILE__) . "/../toasts.php"); ?>
    <!-- gestione della rimozione di una propedeuticità -->
    <script type="text/javascript">
      function handleRimuoviPropedeuticità(e) {
        let insegnamento = e.target.getAttribute("insegnamento-target");

        // fixa bug in caso in cui si clicca precisamente su icona cestino e non riesce prendere contenuto dell'attributo insegnamento-target
        if(!insegnamento) insegnamento = e.target.parentNode.getAttribute("insegnamento-target");

        axios.postForm("/api/segreteria/delete-propedeuticità.php", { 
          "cdl": "<?php echo $cdl ?>", 
          "insegnamento-propedeuticità": "<?php echo $insegnamento; ?>",
          "insegnamento-propedeutico": insegnamento,
        })
        .then(({ data }) => { 
          // feedback
          showSuccessToast(data.message);
          // aggiunto corso a selezione
          const tableRow = document.getElementById("table-propedeuticità-insegnamento-" + insegnamento);
          const select = document.getElementById("select-insegnamento-propedeuticità");
          const option = document.createElement("option");
          option.value = insegnamento;
          option.innerHTML = tableRow.getElementsByTagName("td")[1].innerHTML;
          select.appendChild(option);
          // rimozione corso da tabella
          tableRow.remove();
        })
        .catch(({ response: { data } }) => showDangerToast(data.message));
      }
    </script>
    <!-- gestione della creazione di una nuova propedeuticità -->
    <script type="text/javascript">
      function handleAggiungiPropedeuticità() {
        const insegnamento = document.getElementById("select-insegnamento-propedeuticità").value;
        axios.postForm("/api/segreteria/add-propedeuticità.php", { 
          "cdl": "<?php echo $cdl ?>", 
          "insegnamento-propedeuticità": "<?php echo $insegnamento; ?>",
          "insegnamento-propedeutico": insegnamento,
        })
        .then(({ data }) => { 
          // feedback
          showSuccessToast(data.message);
          // aggiunta propedeuticità alla tabella
          const selected_option = document.querySelector("#select-insegnamento-propedeuticità option[value='" + insegnamento + "']");
          const tbody = document.getElementById("table-propedeuticità").getElementsByTagName("tbody")[0];
          tbody.appendChild(createTableRow(insegnamento, selected_option.innerHTML, 1)); 
          // rimozione corso dalla select di aggiunta propedeuticità
          selected_option.remove();
        })
        .catch(({ response: { data } }) => showDangerToast(data.message));
      }

      function createActionsCell(codiceInsegnamento) {
        const cell = document.createElement("td");
        cell.classList.add("is-flex", "is-justify-content-space-evenly");

        const link = document.createElement("a");
        const arrowIcon = document.createElement("ion-icon");
        arrowIcon.setAttribute("name", "arrow-forward-outline");
        link.classList.add("button", "is-small", "is-link", "is-outlined", "mr-1");
        link.href = "/dashboard/segreteria/insegnamento.php?cdl=<?php echo $cdl; ?>&codice=" + codiceInsegnamento;
        link.appendChild(arrowIcon);

        const button = document.createElement("button");
        const trashIcon = document.createElement("ion-icon");
        trashIcon.setAttribute("name", "trash");
        button.setAttribute("insegnamento-target", codiceInsegnamento);
        button.classList.add("button", "is-small", "is-danger", "is-outlined");
        button.onclick = handleRimuoviPropedeuticità;
        button.appendChild(trashIcon);

        cell.appendChild(link);
        cell.appendChild(button);

        return cell;
      }

      function createRowCell(content) {
        const cell = document.createElement("td");
        cell.innerHTML = content;
        return cell;
      }

      function createTableRow(codiceInsegnamento, nomeInsegnamento, annoInsegnamento) {
        const row = document.createElement("tr");
        row.setAttribute("id", "table-propedeuticità-insegnamento-" + codiceInsegnamento);
        row.appendChild(createRowCell(codiceInsegnamento));
        row.appendChild(createRowCell(nomeInsegnamento));
        row.appendChild(createRowCell(annoInsegnamento));
        row.appendChild(createActionsCell(codiceInsegnamento));
        return row;
      }
    </script>
<?php } ?>