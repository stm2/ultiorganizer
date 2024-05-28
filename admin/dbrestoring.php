<?php
include_once 'menufunctions.php';

$title = _("Database restore");
setBackurl("?view=admin/dbrestore");

function get_error($error) {
  $phpFileUploadErrors = array(
    0 => 'There is no error, the file uploaded with success',
    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
    3 => 'The uploaded file was only partially uploaded', 4 => 'No file was uploaded', 6 => 'Missing a temporary folder',
    7 => 'Failed to write file to disk.', 8 => 'A PHP extension stopped the file upload.');
  return $phpFileUploadErrors[$error] ?? '';
}

class RestoreHandler {

  public string $error = '';
}

class RestoreChecker extends RestoreHandler {

  protected $tname;

  protected $tinserts;

  protected $tothers;

  protected $tdropped;
  
  protected $total;

  protected $tables;

  protected int $start;
  
  protected $checked;
  
  protected bool $checkAll;
  
  protected int $limit;

  function __construct($post) {
    $this->checked = array();
    if (isset($post['tables'])) {
      foreach ($post['tables'] as $tname) {
        $this->checked[$tname] = true;
      }
      $this->checkAll = false;
    } else {
      $this->checkAll = true;
    }
    $this->limit = min(12 * 60 * 60, max(1, intval($post['tlimit'] ?? 10)));
    set_time_limit($this->limit);
  }
  
  function accept($tname) {
    return $this->checkAll || isset($this->checked[$tname]);
  }
  
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
      if ($this->accept($tname))
        ++$this->total;
        
      $tinserts = $this->tinserts;
      $tothers = $this->tothers;
      if ($this->tdropped) {
        $tdropped = "&#x2713;";
      } else {
        $tdropped = "";
      }
      
      $checked = $this->accept($tname) ? "checked='true'" : "";   
      
      echo "<tr>";
      echo "<td class='center'><input type='checkbox' $checked name='tables[]' value='" . utf8entities($tname) .
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
    if ($this->accept($tname))
      ++$this->total;
  }

  function handle_insert($tname, $line) {
    ++$this->tinserts;
    if ($this->accept($tname))
      ++$this->total;
  }

  function handle_other($line) {
    ++$this->tothers;
    ++$this->total;
  }

  function handle_end() {
    if ($this->tname != null) {
      $this->log_table();
    }
    echo "</table>\n";

    $total = $this->total;
    echo "<p>" . sprintf(_("Found %d tables and %d statements"), $this->tables, $total) . "</p>\n";

    $limit = $this->limit;
    echo "<p>" . sprintf(_("Testing performance. Time limit is %ds. If this takes too long or does not finish, you may have to reduce the file size..."),
      $limit);

    $start = time();
    set_time_limit(intval($limit * 11 / 10));

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
           `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (`id`)
         ) AUTO_INCREMENT=121 DEFAULT CHARSET=utf8;");
    }
    DBTransaction();
    
    if ($result) {
      $split = time();
      for ($i = 0; $i < $total; ++$i) {
        if ($result) {
          $result = mysql_adapt_query("INSERT INTO `uo_dbrestore_test` (`name`) VALUES('test$i');");
        }
        $elapsed = time() - $start;
        if (time() - $split >= 2) {
          echo "<br />" . sprintf(_("Created %d rows in %ds / %ds..."), $i + 1, $elapsed, $limit);
          $this->flush();
          $split = time();
        }
        if ($elapsed > $limit) {
          $result = null;
          break;
        }
        if ($elapsed >= 10 && $elapsed / $limit > 2 * $i / $total || $elapsed > .8 * $limit) {
          echo sprintf(_("<br />Estimated total time of %ds. Too slow ..."), intval($elapsed * $total / $i));
          $result = null;
          break;
        }
      }
    }
    
    if ($result) {
      echo "<br />" . _("Committing...");
      DBCommit();
      $elapsed = time() - $start;
      if ($elapsed > $limit) {
        $result = null;
      } 
    } else {
      DBRollback();
    }
    
    if ($result) {
      echo "<br />" .
        sprintf(_("Successfully created table with %d INSERTs in %ds. Time limit is %ds."), $total, (time() - $start),
          $limit) . "</p>\n";
    } else {
      echo '<p>' . _('Test was <em>not</em> successful!') . ":<br />\n" . mysql_adapt_error() . "</p>";
    }
    $result = mysql_adapt_query("DROP TABLE if exists uo_dbrestore_test");
    
    echo "Time limit: <input class='input' size='10' maxlength='10' name='tlimit' value='$limit'/>s\n";

    echo "<br /><p>" .
      _(
        "This is a risky operation! Should it fail, for example due to a timeout, it may result in a corrupted database!") .
      "</p>";
      echo "<p><input class='button' type='submit' name='check' value='" . _("Test again...") . "'/>";
      echo "<p><input class='button' type='submit' name='restore' value='" . _("Restore") . "'/>";
    echo "</form>";
    $this->flush();
  }
  
  function handle_abort() {
    // NOP
  }

  function abort() {
    return false;
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
  
  protected int $limit;
  
  protected bool $abort = false;
  
  protected int $split;
  
  protected int $split2;

  function __construct($post) {
    $this->checked = array();
    $this->paragraph = true;
    foreach ($post['tables'] as $tname) {
      $this->checked[$tname] = true;
    }
    $this->limit = min(12*60*60, max(1, intval($post['tlimit'])) ?? 120);
    set_time_limit(intval($this->limit *11 / 10));
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
    $split = time() - $this->split;
    if ($split >= 2) {
      if ($this->paragraph) {
        echo "<br />" . sprintf(_("running (time limit %ds / %ds)..."), time() - $this->start, $this->limit);
        $this->paragraph = false;
        $this->split2 = time();
      } else if (time() - $this->start > $this->limit) {
        echo "<br />" . _("Time limit exceeded. Aborting ...");
        $this->abort = true;
      } else if (time() - $this->split2 >= 20) {
        echo "<br />" . sprintf(_("time elapsed: %ds"), time() - $this->start);
        $this->split2 = time();
      } else
        echo ".";
      $this->split = time();
      $this->flush();
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
    $this->split = time();
    $this->split2 = time();
    
    debug_to_apache("Starting transaction...");
    DBTransaction();
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
      debug_to_apache("Committing...");
      DBCommit();
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
      debug_to_apache("Rolling back...");
      DBRollback();
      echo "<p>" . _("Error, rolling back!") . "</p>";
    }

    $this->flush();
  }
  
  function handle_abort() {
    debug_to_apache("Rolling back...");
    DBRollback();
    echo "<p>" . _("Error, rolling back!") . "</p>";
  }
  
  function abort() {
    return $this->abort;
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
  
  function handle_abort() {
    $this->handler->handle_abort();
  }
  
  function abort()  {
    return $this->handler->abort();
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

    if (isset($lines) && $lines !== false) {
      $templine = '';

      $line_count = 0;

      $this->handle_start($restorefilename);

      foreach ($lines as $line) {
        if ($this->abort())
          break;
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
      if ($this->abort()) {
        $this->handle_abort();
        if (empty($error))
          $error = "";
        
        $error .= "<p>" . _("Handler aborted") . "</p>\n";
      } else {
        $this->handle_end();
      }
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
      ini_set("memory_limit", -1);
      
      if (isset($_POST['restorefilename'])) {
        $filename = $_POST['restorefilename'];
      } else {
        $restorefilename = $_FILES['restorefile']['name'];
        $restorefiletempname = $_FILES['restorefile']['tmp_name'];
        $error = '';

        if (!is_uploaded_file($restorefiletempname)) {
          $error = sprintf(_("Uploaded file not found (error code %d: %s)."), $_FILES['restorefile']['error'], get_error($_FILES['restorefile']['error']));
        } else {
          // FIXME sanitize user input
          $filename = "" . UPLOAD_DIR . "tmp/$restorefilename";
          if (!move_uploaded_file($restorefiletempname, $filename)) {
            $error = _("Could not copy restore file.");
          } else {
            unlink($restorefiletempname);
          }
        }
      }
      if (empty($error)) {
          $reader = new RestoreReader($filename, new RestoreChecker($_POST));
          $error = $reader->read();
        }
    }
  } else if (isset($_POST['restore'])) {
    $error = '';
    if (!isSuperAdmin()) {
      $error = _("Insufficient rights");
    } else {
      ini_set("memory_limit", -1);
      
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
