let userIndex = 0;

document.addEventListener("DOMContentLoaded", function () {
  initWizardProgress();
  initSecurityToggles();
  initCopyButtons();
  initTestConnectionButton();
  initUserManagement();
  initApiFormValidation();
  initSimpleAnimations();

  if (new URLSearchParams(window.location.search).has("updated")) {
    showNotification(wpsl_admin.strings.saved, "success");
  }
});

function initWizardProgress() {
  const completedSteps = document.querySelectorAll(
    ".wpsl-step-nav.completed"
  ).length;
  const totalSteps = document.querySelectorAll(".wpsl-step-nav").length;
  const progressBar = document.querySelector(".wpsl-progress-fill");
  if (progressBar) {
    const progress = (completedSteps / totalSteps) * 100;
    setTimeout(() => {
      progressBar.style.width = `${progress}%`;
    }, 500);
  }
}

function initSecurityToggles() {
  document
    .querySelectorAll(".wpsl-security-card .wpsl-toggle-input")
    .forEach((checkbox) => {
      checkbox.addEventListener("change", function () {
        const card = this.closest(".wpsl-security-card");
        card.classList.toggle("enabled", this.checked);
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
    if (
      e.target &&
      (e.target.classList.contains("wpsl-remove-user") ||
        e.target.closest(".wpsl-remove-user"))
    ) {
      const button = e.target.classList.contains("wpsl-remove-user")
        ? e.target
        : e.target.closest(".wpsl-remove-user");
      removeUser(button);
    }
  });

  initSimpleSignupManagement();
}

function initSimpleSignupManagement() {
  const allowSignupsToggle = document.getElementById("wpsl-allow-signups");
  const roleSection = document.getElementById("wpsl-signup-role-section");

  if (allowSignupsToggle && roleSection) {
    allowSignupsToggle.addEventListener("change", function () {
      roleSection.style.display = this.checked ? "block" : "none";
    });
  }
}

function initApiFormValidation() {
  const form = document.getElementById("wpsl-google-api-form");
  if (form) {
    const clientIdInput = document.getElementById("client_id");
    const clientSecretInput = document.getElementById("client_secret");

    if (clientIdInput && clientSecretInput) {
      [clientIdInput, clientSecretInput].forEach((input) => {
        input.addEventListener("blur", validateField);
      });
    }

    form.addEventListener("submit", function (e) {
      if (!clientIdInput?.value || !clientSecretInput?.value) {
        e.preventDefault();
        showNotification(wpsl_admin.strings.fill_both_fields, "error");
      }
    });
  }
}

function initSimpleAnimations() {
  // Simple fade-in for main content
  const stepContent = document.querySelector(".wpsl-step-content");
  if (stepContent) {
    stepContent.style.opacity = "0";
    setTimeout(() => {
      stepContent.style.transition = "opacity 0.5s ease";
      stepContent.style.opacity = "1";
    }, 100);
  }
}

function validateField(event) {
  const input = event.target;
  const isClientId = input.id === "client_id";
  const isClientSecret = input.id === "client_secret";

  input.classList.remove("error", "success");

  if (isClientId && input.value) {
    if (input.value.includes(".apps.googleusercontent.com")) {
      input.classList.add("success");
    } else if (input.value.length > 10) {
      input.classList.add("error");
    }
  }

  if (isClientSecret && input.value) {
    if (input.value.length > 20) {
      input.classList.add("success");
    } else if (input.value.length > 5) {
      input.classList.add("error");
    }
  }
}

function testGoogleConnection(event) {
  const button = event.target;
  const originalContent = button.innerHTML;
  const clientId = document.getElementById("client_id")?.value;
  const clientSecret = document.getElementById("client_secret")?.value;

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
    .catch(() => {
      showNotification(wpsl_admin.strings.connection_failed, "error");
    })
    .finally(() => {
      setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
      }, 1000);
    });
}

function addUser() {
  const userList = document.getElementById("user-list");

  const newUser = document.createElement("div");
  newUser.className = "wpsl-user-item";
  newUser.innerHTML = wpsl_admin.user_template.replace(/__INDEX__/g, userIndex);

  userList.appendChild(newUser);
  userIndex++;

  // Focus the email input
  const emailInput = newUser.querySelector('input[type="email"]');
  if (emailInput) {
    emailInput.focus();
  }
}

function removeUser(button) {
  const userItem = button.closest(".wpsl-user-item");
  const userList = document.getElementById("user-list");

  if (confirm(wpsl_admin.strings.confirm_remove_user)) {
    userItem.remove();

    // Add a new empty user if no users left
    const remainingUsers = userList.querySelectorAll(".wpsl-user-item");
    if (remainingUsers.length === 0) {
      addUser();
    }
  }
}

function copyToClipboard(button) {
  const input = button.previousElementSibling;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(input.value).then(
      () => showCopySuccess(button),
      () => fallbackCopy(input, button)
    );
  } else {
    fallbackCopy(input, button);
  }
}

function fallbackCopy(input, button) {
  try {
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand("copy");
    showCopySuccess(button);
  } catch (err) {
    showNotification(wpsl_admin.strings.copy_failed, "error");
  }
}

function showCopySuccess(button) {
  const originalText = button.textContent;
  button.textContent = wpsl_admin.strings.copied;
  button.classList.add("success");

  setTimeout(() => {
    button.textContent = originalText;
    button.classList.remove("success");
  }, 2000);
}

function showNotification(message, type = "info") {
  // Remove any existing notifications
  document.querySelectorAll(".wpsl-notification").forEach((n) => n.remove());

  const notification = document.createElement("div");
  notification.className = `wpsl-notification wpsl-notification-${type}`;

  const iconMap = {
    success: "✓",
    error: "✗",
    warning: "⚠",
    info: "ℹ",
  };

  notification.innerHTML = `
    <div class="wpsl-notification-content">
      <span class="wpsl-notification-icon">${iconMap[type] || iconMap.info}</span>
      <span class="wpsl-notification-message">${message}</span>
      <button class="wpsl-notification-close">×</button>
    </div>
  `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.classList.add("show");
  }, 10);

  // Auto-remove
  const timeoutId = setTimeout(
    () => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    },
    type === "error" ? 8000 : 5000
  );

  // Manual close
  notification
    .querySelector(".wpsl-notification-close")
    .addEventListener("click", () => {
      clearTimeout(timeoutId);
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    });
}
