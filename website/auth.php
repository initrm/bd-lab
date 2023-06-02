<?php
  include_once(dirname(__FILE__) . '/utype.php');

  class Authenticator {

    private const SESSION_AUTHENTICATION_KEY = "AUTHENTICATED";
    private const SESSION_AUTHENTICATED_USER_KEY = "USER";
    private const SESSION_AUTHENTICATED_USER_TYPE_KEY = "USER_TYPE";

    /**
     * inizializza una nuova istanza della classe this andando a far iniziare una sessione nel
     * caso in cui questa non sia già partita
     */
    public function __construct() {
      if (session_status() == PHP_SESSION_NONE) session_start();
    }

    /**
     * autentica nella sessione l'utente fornito
     */
    public function authenticate(array $user, UserType $user_type) {
      $_SESSION[Authenticator::SESSION_AUTHENTICATION_KEY] = true;
      $_SESSION[Authenticator::SESSION_AUTHENTICATED_USER_KEY] = $user;
      $_SESSION[Authenticator::SESSION_AUTHENTICATED_USER_TYPE_KEY] = $user_type;
    }

    /**
     * restituisce true se nella sessione corrente l'utente è autenticato,
     * false in caso contrario
     */
    public function is_authenticated() {
      return isset($_SESSION[Authenticator::SESSION_AUTHENTICATION_KEY]) && $_SESSION[Authenticator::SESSION_AUTHENTICATION_KEY];
    }
    
    /**
     * restituisce l'utente autenticato 
     * solleva un'eccezione se nella sessione non vi è autenticato alcun utente
     */
    public function get_authenticated_user() {
      if(!$this->is_authenticated())
        throw new Error("Nessun utente autenticato nella sessione corrente.");
      return $_SESSION[Authenticator::SESSION_AUTHENTICATED_USER_KEY];
    }

    /**
     * restituisce il tipo di utente authenticato 
     * solleva un'eccezione se nella sessione non vi è autenticato alcun utente
     */
    public function get_authenticated_user_type() {
      if(!$this->is_authenticated())
        throw new Error("Nessun utente autenticato nella sessione corrente.");
      return $_SESSION[Authenticator::SESSION_AUTHENTICATED_USER_TYPE_KEY];
    }

    /**
     * de-autentica l'utente e distrugge la sessione
     * solleva un'eccezione se nella sessione non vi è autenticato alcun utente
     */
    public function unauthenticate() {
      if(!$this->is_authenticated())
        throw new Error("Nessun utente autenticato nella sessione corrente.");
      session_unset();
      session_destroy();
    }

  }

?>