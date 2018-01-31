<script type="text/javascript">
<!--

function toggleField(checkbox, fieldid) {
  var input = document.getElementById(fieldid);
  input.disabled = !checkbox.checked;
}

function checkAll(name) {
  var elements = document.getElementsByName(name);
  if (elements.length == 0)
    elements = document.getElementsByName(name + "[]");
  
  for (var i = 0; i < elements.length; i++) {
    if (elements[i].nodeName != "form")
      elements[i].checked = !elements[i].checked;
  }
}


function clearAll(field) {
  var form = document.getElementById(field);

  for (var i = 1; i < form.elements.length; i++) {
    form.elements[i].checked = false;
  }
}

function setId(value) {
  setId1('hiddenDeleteId', value);
}

function setId1(id, value) {
  var input = document.getElementById(id);
  input.value = value;
}

function setId2(id1, id2, v1, v2) 
{
  var input = document.getElementById(id1);
  input.value = v1;
  input = document.getElementById(id2);
  input.value = v2;
}

function changeseason(id) {
  var url = location.href;

  var param = "selseason";
  var re = new RegExp("([?|&])" + param + "=.*?(&|$)", "i");
  if (url.match(re)) {
    url = url.replace(re, '$1' + param + "=" + id + '$2');
  } else {

    if (location.href.search("view=") != -1) {
      url = url + '&' + param + "=" + id;
    } else {
      url = url.substring(0, url.lastIndexOf('/'));
      url = url + "/index.php?" + param + "=" + id;
    }
  }
  location.href = url;
}

function confirmLeave(element, confirm, text) {
  if (element) {
    if (!element.className.contains("highlight"))
      element.className += "highlight";
  }
  if (confirm)
    window.onbeforeunload = function() {
      return text == null ? "" : text;
    }
  else
    window.onbeforeunload = null;
};

//-->
</script>