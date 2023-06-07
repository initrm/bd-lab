<?php function section_title(?string $title, ?string $subtitle = NULL) { ?>
  <div class="column is-12">
    <?php if($title != NULL) { ?>
      <h1 class="title is-1"><?php echo $title; ?></h1>
    <?php } ?>
    <?php if($subtitle != NULL) { ?>
      <h2 class="subtitle"><?php echo $subtitle; ?></h2>
    <?php } ?>
  </div>
<?php } ?>