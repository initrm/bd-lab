<?php function navbar($display_name) { ?>
  <nav class="navbar is-transparent" style="background-color: #e9e9e9;" role="navigation" aria-label="main navigation">
    <div class="navbar-menu">
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
<?php } ?>