// Show / hide password (eye button)
function tp(b) {
  var i = b.previousElementSibling,
    s = i.type == 'password';
  i.type = s ? 'text' : 'password';
  b.classList.toggle('on', s);
  b.setAttribute('aria-label', s ? 'Hide password' : 'Show password');
}
