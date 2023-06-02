<?php

  class Database {

    // variabile che contiene la connessione utilizzata per l'esecuzione delle query
    private $connection = NULL;

    /**
     * apre una nuova connessione verso il database
     * solleva un'eccezione se la connessione è già aperta
     */
    function open_conn() {
      if($this->connection != NULL)
        throw new Exception("Connessione non aperta.");
      $this->connection = pg_connect("host=pgsql user=progetto password=progetto dbname=progetto_esame");
    }

    /**
     * chiude la connessione verso il database
     * solleva un'eccezione se la connessione non è aperta
     */
    function close_conn() {
      if($this->connection == NULL)
        throw new Exception("Connessione già aperta.");
      pg_close($this->connection);
    }

    /**
     * chiude la connessione attuale verso il database e ne apre una nuova
     * solleva un'eccezione se la connessione non è aperta
     */
    function reset_conn() {
      $this->close_conn();
      $this->open_conn();
    }
    
    /**
     * apre una connessione con il database, esegue una singola query e termina la connessione
     * se era già stata aperta una connessione tramite il metodo open_conn() viene utilizzata quella connessione,
     * si noti che al termine dell'esecuzione della funzione la connessione verrà terminata e quindi bisognerà
     * aprirne un'altra (tramite l'invocazione di open_conn()) prima di chiamare altri metodi
     */
    function execute_single_query(string $query, array $query_params) {
      // se non è ancora stata aperta la connessione viene aperta
      if($this->connection == NULL)
        $this->open_conn();
      
      // esegue la query fornita come argomento
      $tag = "single_query";
      pg_prepare($this->connection, $tag, $query);
      $result = pg_execute($this->connection, $tag, $query_params);

      // chiusura della connessione
      pg_close($this->connection);

      return new QueryResult($result);
    }

    /**
     * esegue la query fornita come argomento, identificata dal tag fornito, con i parametri forniti
     * solleva un'eccezione se non è ancora stata aperta una connessione al database
     */
    function execute_query(string $tag, string $query, array $query_params) {
      // se non è ancora stata aperta la connessione viene solleva un'eccezione
      if($this->connection == NULL)
        throw new Exception('Connessione non aperta.');

      // esegue la query fornita come argomento
      pg_prepare($this->connection, $tag, $query);
      $result = pg_execute($this->connection, $tag, $query_params);

      return new QueryResult($result);
    }
    
  }

  class QueryResult {

    // variabile che contiene i risultati della query
    private $results;

    /**
     * inizializza una nuova istanza della classe this andando a "wrappare" il
     * risultato fornito come parametro
     */
    function __construct($results) {
      $this->results = $results;
    }

    /**
     * restituisce il numero ri record restituiti dalla query
     */
    function row_count() {
      return pg_num_rows($this->results);
    }

    /**
     * restituisce la riga ritornata dalla query sotto forma di array associativo
     */
    function row() {
      return pg_fetch_row($this->results, NULL, PGSQL_ASSOC);
    }

    /**
     * restituisce tutte le righe ritornate dalal query sotto forma di array di array associativi
     */
    function all_rows() {
      return pg_fetch_all($this->results, PGSQL_ASSOC);
    }

    /**
     * restituisce il numero di righe ritornate dalla query
     */
    function affected_rows() {
      return pg_affected_rows($this->results);
    }

  }
  
?>