<?php function navbar($display_name) { ?>
  <nav class="navbar is-transparent mb-3" style="background-color: #e9e9e9;" role="navigation" aria-label="main navigation">
    
    <!-- burger solo mobile -->
    <a role="button" id="burger-button" class="navbar-burger" data-target="navbar" aria-label="menu" aria-expanded="false">
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </a>

    <!-- elementi navbar -->
    <div class="navbar-menu" id="navbar">
      <div class="navbar-start">
        <p class="navbar-item">Loggato come: <span class="has-text-link"><?php echo $display_name; ?></span></p>
      </div>
      <div class="navbar-end">
        <div class="navbar-item">
          <div class="buttons">
            <a href="/password-change.php" class="button is-link is-outlined">Cambia password</a>
            <a href="/logout.php" class="button is-danger is-outlined">Disconnettiti</a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- script espansione menu mobile -->
  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', () => {
      const burgerButton = document.getElementById('burger-button');
      burgerButton.addEventListener('click', () => {
        // ottenimento del target dall'attributo "data-target"
        const target = burgerButton.dataset.target;
        const $target = document.getElementById(target);
        // attivazione
        burgerButton.classList.toggle('is-active');
        $target.classList.toggle('is-active');
      });
    });
  </script>
<?php } ?>