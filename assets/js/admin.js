let userIndex = 0;

document.addEventListener("DOMContentLoaded", function () {
  initWizardProgress();
  initSecurityToggles();
  initCopyButtons();
  initTestConnectionButton();
  initUserManagement();
  initApiFormValidation();
  initTooltips();
  initAnimations();

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

        // Add a subtle animation
        card.style.transform = "scale(1.02)";
        setTimeout(() => {
          card.style.transform = "";
        }, 200);
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
}

function initApiFormValidation() {
  const form = document.getElementById("wpsl-google-api-form");
  if (form) {
    const clientIdInput = document.getElementById("client_id");
    const clientSecretInput = document.getElementById("client_secret");

    if (clientIdInput && clientSecretInput) {
      [clientIdInput, clientSecretInput].forEach((input) => {
        input.addEventListener("input", validateField);
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

function initTooltips() {
  // Simple tooltip implementation for copy buttons
  document.querySelectorAll(".wpsl-copy-btn").forEach((btn) => {
    btn.addEventListener("mouseenter", function () {
      if (!this.dataset.tooltip) {
        this.dataset.tooltip = "true";
        this.setAttribute("title", "Click to copy");
      }
    });
  });
}

function initAnimations() {
  // Animate cards on scroll
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = "1";
        entry.target.style.transform = "translateY(0)";
      }
    });
  }, observerOptions);

  document
    .querySelectorAll(".wpsl-feature-card, .wpsl-security-card")
    .forEach((card) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(20px)";
      card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
      observer.observe(card);
    });
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
  button.classList.add("loading");

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
        button.classList.add("success");
        setTimeout(() => button.classList.remove("success"), 3000);
      } else {
        showNotification(
          `${wpsl_admin.strings.connection_failed}: ${data.data}`,
          "error"
        );
        button.classList.add("error");
        setTimeout(() => button.classList.remove("error"), 3000);
      }
    })
    .catch(() => {
      showNotification(wpsl_admin.strings.connection_failed, "error");
      button.classList.add("error");
      setTimeout(() => button.classList.remove("error"), 3000);
    })
    .finally(() => {
      setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        button.classList.remove("loading");
      }, 1000);
    });
}

function addUser() {
  const userList = document.getElementById("user-list");
  const emptyState = userList.querySelector(".wpsl-empty-state");

  if (emptyState) {
    emptyState.remove();
  }

  const newUser = document.createElement("div");
  newUser.className = "wpsl-user-item";
  newUser.style.opacity = "0";
  newUser.style.transform = "translateY(-20px)";
  newUser.innerHTML = wpsl_admin.user_template.replace(/__INDEX__/g, userIndex);

  userList.appendChild(newUser);

  // Animate in
  setTimeout(() => {
    newUser.style.transition = "opacity 0.3s ease, transform 0.3s ease";
    newUser.style.opacity = "1";
    newUser.style.transform = "translateY(0)";
  }, 10);

  userIndex++;

  // Focus the email input
  const emailInput = newUser.querySelector('input[type="email"]');
  if (emailInput) {
    setTimeout(() => emailInput.focus(), 300);
  }
}

function removeUser(button) {
  const userItem = button.closest(".wpsl-user-item");
  const userList = document.getElementById("user-list");

  if (confirm(wpsl_admin.strings.confirm_remove_user)) {
    // Animate out
    userItem.style.transition = "opacity 0.3s ease, transform 0.3s ease";
    userItem.style.opacity = "0";
    userItem.style.transform = "translateX(100%)";

    setTimeout(() => {
      userItem.remove();

      // Show empty state if no users left
      const remainingUsers = userList.querySelectorAll(".wpsl-user-item");
      if (remainingUsers.length === 0) {
        const emptyState = document.createElement("div");
        emptyState.className = "wpsl-empty-state";
        emptyState.innerHTML = `
          <div class="wpsl-empty-icon">
            <span class="dashicons dashicons-groups"></span>
          </div>
          <h4>No users configured yet</h4>
          <p>Add email addresses of users who should be able to access your site.</p>
        `;
        userList.appendChild(emptyState);
      }
    }, 300);
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
    input.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand("copy");
    showCopySuccess(button);
  } catch (err) {
    showNotification(wpsl_admin.strings.copy_failed, "error");
  }
}

function showCopySuccess(button) {
  const originalText = button.textContent;
  const originalBg = button.style.backgroundColor;

  button.textContent = wpsl_admin.strings.copied;
  button.style.backgroundColor = "#10b981";
  button.style.transform = "scale(1.05)";

  setTimeout(() => {
    button.textContent = originalText;
    button.style.backgroundColor = originalBg;
    button.style.transform = "";
  }, 2000);
}

function showNotification(message, type = "info") {
  // Remove any existing notifications to prevent stacking
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
      <button class="wpsl-notification-close" aria-label="Close">×</button>
    </div>
  `;

  document.body.appendChild(notification);

  // Trigger the transition by adding the 'show' class after a brief moment
  setTimeout(() => {
    notification.classList.add("show");
  }, 10);

  // Auto-remove after a delay
  const autoRemoveDelay = type === "error" ? 8000 : 5000;
  const timeoutId = setTimeout(() => {
    notification.classList.remove("show");
  }, autoRemoveDelay);

  // Function to remove the element from the DOM
  const removeElement = () => {
    notification.remove();
    clearTimeout(timeoutId); // Clear the auto-remove timeout if closed manually
  };

  // Remove the element after the fade-out transition is complete
  notification.addEventListener("transitionend", () => {
    if (!notification.classList.contains("show")) {
      removeElement();
    }
  });

  // Handle manual closing
  notification
    .querySelector(".wpsl-notification-close")
    .addEventListener("click", () => {
      notification.classList.remove("show");
    });
}
