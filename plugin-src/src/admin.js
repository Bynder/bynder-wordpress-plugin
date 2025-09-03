document.querySelector("#edit-client-secret").addEventListener("click", (event) => {
    showClientSecretInput();
    event.preventDefault();
}, false);

document.querySelector("#cancel-client-secret").addEventListener("click", (event) => {
    cancelClientSecretInput();
    event.preventDefault();
}, false);

function showClientSecretInput() {
    toggleElement("edit-client-secret");
    var i = document.createElement("input");
    i.setAttribute("type", "text");
    i.setAttribute("name", "bynder_settings[client_secret]");
    i.setAttribute("class", "regular-text");
    var container = document.getElementById("client-secret-container");
    container.appendChild(i);
    toggleElement("cancel-client-secret");
}

function cancelClientSecretInput() {
    var container = document.getElementById("client-secret-container");
    container.innerHTML = "";
    toggleElement("edit-client-secret");
    toggleElement("cancel-client-secret");
}

function toggleElement(id) {
    var x = document.getElementById(id);
    if (x.style.display === "none") {
      x.style.display = "block";
    } else {
      x.style.display = "none";
    }
}