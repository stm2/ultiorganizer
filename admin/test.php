<?php
ensureSuperAdmin();
showPage("Test", "<textarea cols='70' rows='10' style='width:100%'>_POST:" . print_r($_POST, true) .  "</textarea></p>\n");
