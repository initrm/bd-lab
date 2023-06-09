<?php
  include_once(dirname(__FILE__) . "/utype.php");

  class Database {

    // variabile che contiene la connessione utilizzata per l'esecuzione delle query
    private $connection = NULL;
    // variabile che contiene le credenziali dell'utente da utilizzare per effettuare la connessione al database
    private $database_user = NULL;

    function __construct(UserType $user_type) {
      switch($user_type) {
        case UserType::Studente:
          $this->database_user = array("username" => "studente", "password" => "studente");
          break;
        case UserType::Docente:
          $this->database_user = array("username" => "docente", "password" => "docente");
          break;
        case UserType::Segreteria:
          $this->database_user = array("username" => "segreteria", "password" => "segreteria");
          break;
      }
    }

    /**
     * apre una nuova connessione verso il database e setta il search path automaticamento sullo schema del progetto
     * solleva un'eccezione se la connessione è già aperta
     */
    function open_conn() {
      if($this->connection != NULL)
        throw new Exception("Connessione non aperta.");
      $this->connection = pg_connect("host=pgsql user=" . $this->database_user["username"] . " password=" . $this->database_user["password"] . " dbname=progetto_esame");
      $this->execute_query("set_search_path", "set search_path to progetto_esame", array());
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
     * solleva un'eccezione di tipo QueryError se la query fallisce
     */
    function execute_single_query(string $query, array $query_params) {
      // se non è ancora stata aperta la connessione viene aperta
      if($this->connection == NULL)
        $this->open_conn();
      
      // esegue la query fornita come argomento
      $result = $this->execute_query("single_query", $query, $query_params);

      // chiusura della connessione
      pg_close($this->connection);

      return $result;
    }

    /**
     * esegue la query fornita come argomento, identificata dal tag fornito, con i parametri forniti
     * solleva un'eccezione se non è ancora stata aperta una connessione al database
     * solleva un'eccezione di tipo QueryError se la query fallisce
     */
    function execute_query(string $tag, string $query, array $query_params) {
      // se non è ancora stata aperta la connessione viene solleva un'eccezione
      if($this->connection == NULL)
        throw new Exception('Connessione non aperta.');

      // esegue la query fornita come argomento
      pg_prepare($this->connection, $tag, $query);
      $result = pg_execute($this->connection, $tag, $query_params);

      if($result == false)
        throw new QueryError(pg_last_error($this->connection));

      return new QueryResult($result);
    }
    
  }

  /**
   * eccezione custom che viene sollevata dalla classe Database nel momento in cui un query fallisce
   * e che riporta al suo interno il messaggio relativo a tale fallimento
   */
  class QueryError extends Exception {
    public function __construct($message, $code = 0, Throwable $previous = null) {
      parent::__construct(trim(explode("CONTEXT:", $message)[0]), $code, $previous);
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