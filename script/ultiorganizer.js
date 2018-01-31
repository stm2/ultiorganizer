function checkAll(name) {
  var elements = document.getElementsByName(name);
  if (elements.length == 0)
    elements = document.getElementsByName(name + "[]");

  for (var i = 0; i < elements.length; i++) {
    if (elements[i].nodeName != "form")
      elements[i].checked = !elements[i].checked;
  }
}

function setId(id) {
  var input = document.getElementById("hiddenDeleteId");
  input.value = id;
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
