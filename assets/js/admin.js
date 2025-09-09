let userIndex = 0;

document.addEventListener("DOMContentLoaded", function () {
  initWizardProgress();
  initSecurityToggles();
  initCopyButtons();
  initTestConnectionButton();
  initUserManagement();
  initApiFormValidation();

  if (new URLSearchParams(window.location.search).has("updated")) {
    showNotification(wpsl_admin.strings.saved, "success");
  }
});

function initWizardProgress() {
  const completedSteps = document.querySelectorAll(
    ".wpsl-step.completed"
  ).length;
  const totalSteps = document.querySelectorAll(".wpsl-step").length;
  const progressBar = document.querySelector(".wpsl-progress-fill");
  if (progressBar) {
    progressBar.style.width = `${(completedSteps / totalSteps) * 100}%`;
  }
}

function initSecurityToggles() {
  document
    .querySelectorAll(".wpsl-security-card .wpsl-security-toggle")
    .forEach((checkbox) => {
      checkbox.addEventListener("change", function () {
        this.closest(".wpsl-security-card").classList.toggle(
          "enabled",
          this.checked
        );
      });
    });
}

function initCopyButtons() {
  document.querySelectorAll(".wpsl-copy-btn").forEach((button) => {
    button.addEventListener("click", () => copyToClipboard(button));
  });
}

function initTestConnectionButton() {
  const testButton = document.getElementById("wpsl-test-connection-btn");
  if (testButton) {
    testButton.addEventListener("click", testGoogleConnection);
  }
}

function initUserManagement() {
  const userList = document.getElementById("user-list");
  if (!userList) return;

  userIndex = wpsl_admin.initial_user_count;

  const addUserBtn = document.getElementById("wpsl-add-user-btn");
  if (addUserBtn) {
    addUserBtn.addEventListener("click", addUser);
  }

  userList.addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("wpsl-remove-user")) {
      removeUser(e.target);
    }
  });
}

function initApiFormValidation() {
  const form = document.getElementById("wpsl-google-api-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      if (
        !document.getElementById("client_id").value ||
        !document.getElementById("client_secret").value
      ) {
        e.preventDefault();
        alert(wpsl_admin.strings.fill_both_fields);
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
    showNotification(wpsl_admin.strings.fill_both_fields, "error");
    return;
  }

  button.innerHTML = `<span class="wpsl-loading"></span> ${wpsl_admin.strings.testing}`;
  button.disabled = true;

  fetch(wpsl_admin.ajax_url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      action: "wpsl_test_connection",
      nonce: wpsl_admin.nonce,
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
          `${wpsl_admin.strings.connection_failed}: ${data.data}`,
          "error"
        );
      }
    })
    .catch(() =>
      showNotification(wpsl_admin.strings.connection_failed, "error")
    )
    .finally(() => {
      button.textContent = originalText;
      button.disabled = false;
    });
}

function addUser() {
  const userList = document.getElementById("user-list");
  const newUser = document.createElement("div");
  newUser.className = "wpsl-user-item";
  newUser.innerHTML = wpsl_admin.user_template.replace(/__INDEX__/g, userIndex);
  userList.appendChild(newUser);
  userIndex++;
}

function removeUser(button) {
  if (confirm(wpsl_admin.strings.confirm_remove_user)) {
    button.closest(".wpsl-user-item").remove();
  }
}

function copyToClipboard(button) {
  const input = button.previousElementSibling;
  navigator.clipboard.writeText(input.value).then(
    () => showCopySuccess(button),
    () => showNotification(wpsl_admin.strings.copy_failed, "error")
  );
}

function showCopySuccess(button) {
  const originalText = button.textContent;
  button.textContent = wpsl_admin.strings.copied;
  button.style.backgroundColor = "#28a745";
  setTimeout(() => {
    button.textContent = originalText;
    button.style.backgroundColor = "";
  }, 2000);
}

function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `wpsl-notification wpsl-notification-${type}`;
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
