let userIndex = 0;

document.addEventListener("DOMContentLoaded", function () {
  initWizardProgress();
  initSecurityToggles();
  initCopyButtons();
  initTestConnectionButton();
  initUserManagement();
  initApiFormValidation();

  if (new URLSearchParams(window.location.search).has("updated")) {
    showNotification(glo_admin.strings.saved, "success");
  }
});

function initWizardProgress() {
  const completedSteps = document.querySelectorAll(
    ".glo-step.completed"
  ).length;
  const totalSteps = document.querySelectorAll(".glo-step").length;
  const progressBar = document.querySelector(".glo-progress-fill");
  if (progressBar) {
    progressBar.style.width = `${(completedSteps / totalSteps) * 100}%`;
  }
}

function initSecurityToggles() {
  document
    .querySelectorAll(".glo-security-card .glo-security-toggle")
    .forEach((checkbox) => {
      checkbox.addEventListener("change", function () {
        this.closest(".glo-security-card").classList.toggle(
          "enabled",
          this.checked
        );
      });
    });
}

function initCopyButtons() {
  document.querySelectorAll(".glo-copy-btn").forEach((button) => {
    button.addEventListener("click", () => copyToClipboard(button));
  });
}

function initTestConnectionButton() {
  const testButton = document.getElementById("glo-test-connection-btn");
  if (testButton) {
    testButton.addEventListener("click", testGoogleConnection);
  }
}

function initUserManagement() {
  const userList = document.getElementById("user-list");
  if (!userList) return;

  userIndex = glo_admin.initial_user_count;

  const addUserBtn = document.getElementById("glo-add-user-btn");
  if (addUserBtn) {
    addUserBtn.addEventListener("click", addUser);
  }

  userList.addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("glo-remove-user")) {
      removeUser(e.target);
    }
  });
}

function initApiFormValidation() {
  const form = document.getElementById("google-api-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      if (
        !document.getElementById("client_id").value ||
        !document.getElementById("client_secret").value
      ) {
        e.preventDefault();
        alert(glo_admin.strings.fill_both_fields);
      }
    });
  }
}

function testGoogleConnection(event) {
  const button = event.target;
  const originalText = button.textContent;
  const clientId = document.getElementById("client_id").value;
  const clientSecret = document.getElementById("client_secret").value;

  if (!clientId || !clientSecret) {
    showNotification(glo_admin.strings.fill_both_fields, "error");
    return;
  }

  button.innerHTML = `<span class="glo-loading"></span> ${glo_admin.strings.testing}`;
  button.disabled = true;

  fetch(glo_admin.ajax_url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      action: "glo_test_connection",
      nonce: glo_admin.nonce,
      client_id: clientId,
      client_secret: clientSecret,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showNotification(data.data.message, "success");
      } else {
        showNotification(
          `${glo_admin.strings.connection_failed}: ${data.data}`,
          "error"
        );
      }
    })
    .catch(() => showNotification(glo_admin.strings.connection_failed, "error"))
    .finally(() => {
      button.textContent = originalText;
      button.disabled = false;
    });
}

function addUser() {
  const userList = document.getElementById("user-list");
  const newUser = document.createElement("div");
  newUser.className = "glo-user-item";
  newUser.innerHTML = glo_admin.user_template.replace(/__INDEX__/g, userIndex);
  userList.appendChild(newUser);
  userIndex++;
}

function removeUser(button) {
  if (confirm(glo_admin.strings.confirm_remove_user)) {
    button.closest(".glo-user-item").remove();
  }
}

function copyToClipboard(button) {
  const input = button.previousElementSibling;
  navigator.clipboard.writeText(input.value).then(
    () => showCopySuccess(button),
    () => showNotification("Failed to copy.", "error")
  );
}

function showCopySuccess(button) {
  const originalText = button.textContent;
  button.textContent = "Copied!";
  button.style.backgroundColor = "#28a745";
  setTimeout(() => {
    button.textContent = originalText;
    button.style.backgroundColor = "";
  }, 2000);
}

function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `glo-notification glo-notification-${type}`;
  notification.textContent = message;
  notification.style.cssText = `
        position: fixed; top: 40px; right: 20px; z-index: 9999;
        background: ${type === "success" ? "#d4edda" : "#f8d7da"};
        color: ${type === "success" ? "#155724" : "#721c24"};
        padding: 15px 20px; border-radius: 8px;
        border-left: 5px solid ${type === "success" ? "#28a745" : "#dc3545"};
        box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0;
        transform: translateX(100%); transition: all 0.4s ease;
    `;
  document.body.appendChild(notification);
  setTimeout(() => {
    notification.style.opacity = "1";
    notification.style.transform = "translateX(0)";
  }, 10);
  setTimeout(() => {
    notification.style.opacity = "0";
    notification.style.transform = "translateX(100%)";
    setTimeout(() => notification.remove(), 400);
  }, 5000);
}
