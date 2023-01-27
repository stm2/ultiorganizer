<?php
include_once 'menufunctions.php';

$title = _("Database restore");
setBackurl("?view=admin/dbrestore");

class RestoreHandler {

  public string $error = '';
}

class RestoreChecker extends RestoreHandler {

  protected $tname;

  protected $tinserts;

  protected $tothers;

  protected $tdropped;

  protected $tables;

  protected int $start;

  function handle_start($filename) {
    $this->tname = null;
    $this->tinserts = 0;
    $this->tothers = 0;
    $this->tables = 0;
    $this->tdropped = false;
    $this->start = 0;

    echo "<form method='post' enctype='multipart/form-data' action='?view=admin/dbrestoring'>\n";

    echo "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/>";
    echo "<input type='hidden' name='restorefilename' value='" . utf8entities($filename) . "'/>";

    echo "<table class='infotable'>";
    echo "<tr><th>" . checkAllCheckbox('tables') . "</th>";
    echo "<th>" . _("Name") . "</th>";
    echo "<th>" . _("INSERT statements") . "</th>";
    echo "<th>" . _("Other statements") . "</th>";
    echo "<th>" . _("DROP statement") . "</th>";
    echo "</tr>\n";
    $this->flush();
  }

  function flush() {
    ob_flush();
    flush();
  }

  function log_table() {
    if ($this->tname != null) {
      ++$this->tables;
      $tname = $this->tname;
      $tinserts = $this->tinserts;
      $tothers = $this->tothers;
      if ($this->tdropped) {
        $tdropped = "&#x2713;";
      } else {
        $tdropped = "";
      }
      echo "<tr>";
      echo "<td class='center'><input type='checkbox' checked='true' name='tables[]' value='" . utf8entities($tname) .
        "' /></td>";
      echo "<td>" . utf8entities($tname) . "</td>";
      echo "<td>$tinserts</td><td>$tothers</td><td>$tdropped</td></tr>\n";
      $this->flush();
    }
  }

  function handle_table($tname) {
    if ($this->tname != $tname) {
      $this->log_table();
      $this->tname = $tname;
      $this->tinserts = 0;
      $this->tothers = 0;
      $this->tdropped = false;
    }
  }

  function handle_create($tname, $line) {}

  function handle_drop($tname, $line) {
    $this->tdropped = true;
  }

  function handle_insert($tname, $line) {
    ++$this->tinserts;
  }

  function handle_other($line) {
    ++$this->tothers;
  }

  function handle_end() {
    if ($this->tname != null) {
      $this->log_table();
    }
    echo "</table>\n";

    $total = $this->tothers + $this->tinserts + $this->tdropped + $this->tables;
    echo "<p>" . sprintf(_("Found %d tables and %d statements"), $this->tables, $total) . "</p>\n";

    echo "<p>" . _("Testing performance ...");

    $start = time();
    $limit = 300;
    set_time_limit($limit);

    if ($total < 1000)
      $total = 1000;
    echo "<br />" . sprintf(_("Creating test table and inserting %d rows ..."), $total);
    $this->flush();

    $result = mysql_adapt_query("DROP TABLE if exists uo_dbrestore_test");
    if ($result) {
      $result = mysql_adapt_query(
        "CREATE TABLE `uo_dbrestore_test` ( 
           `id` int(10) NOT NULL AUTO_INCREMENT,
           `name` varchar(50) DEFAULT NULL,
           PRIMARY KEY (`id`)
         ) AUTO_INCREMENT=121 DEFAULT CHARSET=utf8;");
    }
    if ($result) {
      $split = time();
      for ($i = 0; $i < $total; ++$i) {
        if ($result) {
          $result = mysql_adapt_query("INSERT INTO `uo_dbrestore_test` (`name`) VALUES('test$i');");
        }
        if (time() - $split > 1) {
          echo "<br />" . sprintf(_("Created %d rows ..."), $i + 1);
          $this->flush();
          $split = time();
        }
      }
    }
    if ($result) {
      echo "<br />" .
        sprintf(_("Successfully created table with %d INSERTs in %ss. Time limit is %ss."), $total, (time() - $start),
          $limit) . "</p>\n";
    } else {
      echo '<p>' . _('Test was <em>not</em> successful!') . ":<br />\n" . mysql_adapt_error() . "</p>";
    }
    $result = mysql_adapt_query("DROP TABLE if exists uo_dbrestore_test");

    echo "<br /><p>" .
      _(
        "This is a risky operation! Should it fail, for example due to a timeout, it may result in a corrupted database!") .
      "</p>";
    echo "<p><input class='button' type='submit' name='restore' value='" . _("Restore") . "'/>";
    echo "</form>";
    $this->flush();
  }
}

class RestoreFilter extends RestoreHandler {

  protected $tname;

  protected $tinserts;

  protected $tothers;

  protected $tdropped;

  protected $tables;

  protected $imported;

  protected $checked;

  protected $paragraph;

  function __construct($post) {
    $this->checked = array();
    $this->paragraph = true;
    foreach ($post['tables'] as $tname) {
      $this->checked[$tname] = true;
    }
  }

  function accept($tname) {
    return isset($this->checked[$tname]);
  }

  function runQuery($context, $line, $log = true) {
    if (!empty($this->error))
      return;

    if ($context == null || $this->accept($context)) {
      $result = mysql_adapt_query($line);
      if (!$result) {
        $this->error .= '<p>' . sprintf(_('Invalid query: "%s"'), $line) . "<br />\n" . mysql_adapt_error() . "</p>";
      }
      if ($log) {
        debug_to_apache("execute: $line");
      }
    } else {
      if ($log) {
        debug_to_apache("reject '$context': $line");
      }
    }
    if ((time() - $this->start) > 1) {
      if ($this->paragraph) {
        echo "<br />running ...";
        $this->paragraph = false;
      } else
        echo ".";
      $this->flush();
      $this->start = time();
    }
  }

  function handle_start($filename) {
    $this->tname = null;
    $this->tinserts = 0;
    $this->tothers = 0;
    $this->tables = 0;
    $this->imported = 0;
    $this->tdropped = false;

    $this->flush();
    $this->start = time();
    echo "<p>";
  }

  function flush() {
    ob_flush();
    flush();
  }

  function log_table() {
    if ($this->tname != null) {
      ++$this->tables;
      $tname = $this->tname;
      $tinserts = $this->tinserts;
      $tothers = $this->tothers;

      $checked = '';
      if ($this->accept($this->tname)) {
        $checked = " checked='true'";
      }
      echo "<br /><input type='checkbox'$checked disabled='true' name='tables[]' value='" . utf8entities($tname) . "' />";
      if ($this->tdropped) {
        echo sprintf(_("%s: %d inserts, %d others, dropped"), utf8entities($tname), $tinserts, $tothers);
      } else {
        echo sprintf(_("%s: %d inserts, %d others"), utf8entities($tname), $tinserts, $tothers);
      }
      echo "\n";
      $this->paragraph = true;
      $this->flush();
      $this->start = time();
    }
  }

  function handle_table($tname) {
    if ($this->accept($tname))
      ++$this->imported;

    if ($this->tname != $tname) {
      $this->log_table();
      $this->tname = $tname;
      $this->tinserts = 0;
      $this->tothers = 0;
      $this->tdropped = false;
    }
  }

  function handle_create($tname, $line) {
    $this->runQuery($tname, $line);
  }

  function handle_drop($tname, $line) {
    $this->tdropped = true;
    $this->runQuery($tname, $line);
  }

  function handle_insert($tname, $line) {
    ++$this->tinserts;
    $this->runQuery($tname, $line, false);
  }

  function handle_other($line) {
    ++$this->tothers;
    $this->runQuery(null, $line);
  }

  function handle_end() {
    if ($this->tname != null) {
      $this->log_table();
    }

    echo "</p>\n";
    echo "<p>" . sprintf(_("Imported %d / %d tables."), $this->imported, $this->tables) . "</p>\n";

    if (empty($this->error)) {
      // disable facebook and twitter updates after restore to avoid false postings
      // (f.ex. if restored database is used for testing purpose)
      $setting = array();
      $setting['name'] = "FacebookEnabled";
      $setting['value'] = "false";
      $settings[] = $setting;
      $setting['name'] = "TwitterEnabled";
      $setting['value'] = "false";
      $settings[] = $setting;

      SetServerConf($settings);

      echo "<p>" . _("Disabled Facebook and Twitter to avoid false postings.") . "</p>\n";

      echo "<p>" . _("Done!") . "</p>";
    } else {
      echo "<p>" . _("Error!") . "</p>";
    }

    $this->flush();
  }
}

class RestoreReader {

  protected $filename;

  protected RestoreHandler $handler;

  protected $tname;

  function __construct(string $filename, RestoreHandler $handler) {
    $this->filename = $filename;
    $this->handler = $handler;
  }

  function handle_start($filename) {
    $this->handler->handle_start($filename);
  }

  function handle_name($new_name) {
    if ($new_name != null && $this->tname != $new_name) {
      $this->handle_table($new_name);
    }
    $this->tname = $new_name;
  }

  function handle_table($tname) {
    $this->handler->handle_table($tname);
  }

  function handle_create($tname, $line) {
    $this->handle_name($tname);
    $this->handler->handle_create($tname, $line);
  }

  function handle_drop($tname, $line) {
    $this->handle_name($tname);
    $this->handler->handle_drop($tname, $line);
  }

  function handle_insert($tname, $line) {
    $this->handle_name($tname);
    $this->handler->handle_insert($tname, $line);
  }

  function handle_other($line) {
    $this->handler->handle_other($line);
  }

  function handle_end() {
    $this->handler->handle_end();
  }

  function read() {
    $error = null;
    $restorefilename = $this->filename;
    echo "<p>" . _("Reading file (this may take a while) ...") . "</p>";

    $ext = explode('.', $restorefilename);
    $ext = end($ext);
    if ("gz" == $ext) {
      $lines = gzfile($restorefilename);
    } elseif ("sql" == $ext) {
      $lines = file($restorefilename);
    } else {
      $error = "<p>" . sprintf(_("Unknown extension '%s' of '%s'"), $ext, utf8entities($restorefilename)) . "</p>";
    }

    if (isset($lines)) {
      $templine = '';

      $line_count = 0;

      $this->handle_start($restorefilename);

      foreach ($lines as $line) {
        // Skip it if it's a comment
        if (substr($line, 0, 2) == '--' || $line == '')
          continue;

        $templine .= $line;
        if (substr(trim($line), -1, 1) == ';') {
          $templine = trim($templine);

          $matches = array();

          $nameexp = "(`[^`]*`|[^ ;]+)";
          if (preg_match("/create  *table  *$nameexp.*/is", $templine, $matches)) {
            $this->handle_create(trim($matches[1], '`'), $templine);
          } else if (preg_match("/insert into  *$nameexp.*/is", $templine, $matches)) {
            $this->handle_insert($matches[1], $templine);
          } else if (preg_match("/drop table  *(?:IF EXISTS  *){0,1}$nameexp.*/is", $templine, $matches)) {
            $this->handle_drop($matches[1], $templine);
          } else {
            $this->handle_other($templine);
          }

          if (!empty($this->handler->error)) {
            $error = "<p>" . sprintf(_("Handler error on line %d."), $line_count) . "</p>\n";
            $error .= $this->handler->error;
            break;
          }

          $templine = '';
          ++$line_count;
          // sleep(1);
        }
      }
      $this->handle_end();
    }

    return $error;
  }
}

if (!defined('ENABLE_ADMIN_DB_ACCESS') || ENABLE_ADMIN_DB_ACCESS != "enabled") {
  $html = "<p>" .
    _(
      "Direct database access is disabled. To enable it, define('ENABLE_ADMIN_DB_ACCESS','enabled') in the config.inc.php file.") .
    "</p>";

  showPage($title, $html);
} else if (!isset($_POST['check']) && !isset($_POST['restore'])) {
  session_write_close();
  header("location:?view=admin/dbrestore");
} else {
  pageTop($title);
  leftMenu();
  contentStart();

  echo "<h2>" . _("Restoring") . "</h2>\n";

  $error = null;
  if (isset($_POST['check'])) {
    if (!isSuperAdmin()) {
      $error = _("Insufficient rights");
    } else {
      $restorefilename = $_FILES['restorefile']['name'];
      $restorefiletempname = $_FILES['restorefile']['tmp_name'];
      $error = '';

      if (!is_uploaded_file($restorefiletempname)) {
        $error = _("Uploaded file not found.");
      } else {
        // FIXME sanitize user input
        $filename = "" . UPLOAD_DIR . "tmp/$restorefilename";
        if (!move_uploaded_file($restorefiletempname, $filename)) {
          $error = _("Could not copy restore file.");
        } else {
          unlink($restorefiletempname);

          $reader = new RestoreReader($filename, new RestoreChecker());
          $error = $reader->read();
        }
      }
    }
  } else if (isset($_POST['restore'])) {
    $error = '';
    if (!isSuperAdmin()) {
      $error = _("Insufficient rights");
    } else {

      $filename = $_POST['restorefilename'];

      if (!($fp = fopen($filename, "r"))) {
        $error = sprintf(_("Could not open file '%s'."), $filename);
      } else {
        $reader = new RestoreReader($filename, new RestoreFilter($_POST));
        $error = $reader->read();
        unset($_SESSION['dbversion']);
      }
      unlink($filename);
    }
  }

  if ($error != null) {
    echo "<br /><p class='warning'>" . _("Error") . ":</p>\n$error";
  }

  echo "<br /><p><a href='?view=admin/dbadmin'>" . _("Return") . "</a></p>\n";

  contentEnd();
  pageEnd();
}

?>