document.addEventListener("DOMContentLoaded", function () {
  // Initialize wizard functionality
  initWizardProgress();
  initSecurityToggles();
  initTestConnection();

  // Show success messages
  if (new URLSearchParams(window.location.search).has("updated")) {
    showNotification(glo_admin.strings.saved, "success");
  }
});

function initWizardProgress() {
  // Update progress bar based on completed steps
  const completedSteps = document.querySelectorAll(
    ".glo-step.completed"
  ).length;
  const totalSteps = document.querySelectorAll(".glo-step").length;
  const progressBar = document.querySelector(".glo-progress-fill");

  if (progressBar) {
    const percentage = (completedSteps / totalSteps) * 100;
    progressBar.style.width = percentage + "%";
  }
}

function initSecurityToggles() {
  const securityCards = document.querySelectorAll(".glo-security-card");

  securityCards.forEach((card) => {
    const checkbox = card.querySelector(".glo-security-toggle");
    if (checkbox) {
      checkbox.addEventListener("change", function () {
        if (this.checked) {
          card.classList.add("enabled");
        } else {
          card.classList.remove("enabled");
        }
      });
    }
  });
}

function initTestConnection() {
  const testButton = document.querySelector('[onclick="testConnection()"]');
  if (testButton) {
    testButton.onclick = function () {
      testGoogleConnection();
    };
  }
}

function testGoogleConnection() {
  const clientId = document.getElementById("client_id").value;
  const clientSecret = document.getElementById("client_secret").value;

  if (!clientId || !clientSecret) {
    showNotification(
      glo_admin.strings.error + ": Both Client ID and Secret are required",
      "error"
    );
    return;
  }

  const button = event.target;
  const originalText = button.textContent;
  button.textContent = glo_admin.strings.testing;
  button.disabled = true;

  fetch(glo_admin.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
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
        showNotification(glo_admin.strings.connection_success, "success");
      } else {
        showNotification(
          glo_admin.strings.connection_failed + ": " + data.data,
          "error"
        );
      }
    })
    .catch((error) => {
      showNotification(glo_admin.strings.connection_failed, "error");
    })
    .finally(() => {
      button.textContent = originalText;
      button.disabled = false;
    });
}

function showNotification(message, type = "info") {
  // Create notification element
  const notification = document.createElement("div");
  notification.className = `glo-notification glo-notification-${type}`;
  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === "success" ? "#d4edda" : type === "error" ? "#f8d7da" : "#d1ecf1"};
        color: ${type === "success" ? "#155724" : type === "error" ? "#721c24" : "#0c5460"};
        padding: 15px 20px;
        border-radius: 8px;
        border: 1px solid ${type === "success" ? "#c3e6cb" : type === "error" ? "#f5c6cb" : "#bee5eb"};
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        max-width: 400px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;

  notification.textContent = message;
  document.body.appendChild(notification);

  // Animate in
  setTimeout(() => {
    notification.style.opacity = "1";
    notification.style.transform = "translateX(0)";
  }, 100);

  // Remove after 5 seconds
  setTimeout(() => {
    notification.style.opacity = "0";
    notification.style.transform = "translateX(100%)";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 5000);
}

// Copy to clipboard functionality
function copyToClipboard(button) {
  const input = button.previousElementSibling;

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard
      .writeText(input.value)
      .then(() => {
        showCopySuccess(button);
      })
      .catch(() => {
        fallbackCopy(input, button);
      });
  } else {
    fallbackCopy(input, button);
  }
}

function fallbackCopy(input, button) {
  input.select();
  input.setSelectionRange(0, 99999);

  try {
    const successful = document.execCommand("copy");
    if (successful) {
      showCopySuccess(button);
    } else {
      showNotification("Failed to copy to clipboard", "error");
    }
  } catch (err) {
    showNotification("Failed to copy to clipboard", "error");
  }
}

function showCopySuccess(button) {
  const originalText = button.textContent;
  const originalBg = button.style.backgroundColor;

  button.textContent = "Copied!";
  button.style.backgroundColor = "#28a745";

  setTimeout(() => {
    button.textContent = originalText;
    button.style.backgroundColor = originalBg;
  }, 2000);
}
