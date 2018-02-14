<?php
if(IsRegistered($_SESSION['uid'])){
  header("location:?view=mobile/respgames");
}

header("location:?view=login_failed");
?>