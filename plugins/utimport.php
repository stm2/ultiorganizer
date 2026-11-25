<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=import
format=api
security=superadmin

[DESCRIPTION]
title = "Import from Ultimate Tournament"
description = "Import or re-sync tournament divisions from Ultimate Tournament API"
-->
<?php
ob_end_clean();

require_once __DIR__ . '/../lib/ut/bootstrap.php';
UtImportPage::handle();
