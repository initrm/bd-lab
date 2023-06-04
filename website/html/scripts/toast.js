function showToast(message, bgColor) {
  Toastify({
    text: message,
    duration: 5000,
    close: true,
    gravity: "bottom",
    position: "center",
    stopOnFocus: true,
    style: {
      background: bgColor,
    }
  }).showToast();
}

function showDangerToast(message) {
  showToast(message, "linear-gradient(to right, #c70000, #ebc147)");
}

function showSuccessToast(message) {
  showToast(message, "linear-gradient(to right, #00b09b, #96c93d)");
}