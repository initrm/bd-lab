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
  showToast(message, "#c70000");
}

function showSuccessToast(message) {
  showToast(message, "#00b09b");
}