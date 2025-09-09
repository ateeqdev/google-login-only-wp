document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  // Global state
  let userIndex = wpsl_admin.initial_user_count || 0;

  // Initializers
  const init = () => {
    initUserManagement();
    initCopyButtons();
    initTestConnection();
    initFormSubmissions();
    initConditionalFields();
    initSecurityCards();

    if (new URLSearchParams(window.location.search).has("settings-updated")) {
      showNotification(wpsl_admin.strings.saved, "success");
    }
  };

  const initUserManagement = () => {
    const userList = document.getElementById("user-list");
    const addUserBtn = document.getElementById("wpsl-add-user-btn");
    if (!userList || !addUserBtn) return;

    addUserBtn.addEventListener("click", addUser);

    userList.addEventListener("click", (e) => {
      const removeBtn = e.target.closest(".wpsl-remove-user");
      if (removeBtn) {
        removeUser(removeBtn);
      }
    });
  };

  const initCopyButtons = () => {
    document.querySelectorAll(".wpsl-copy-btn").forEach((button) => {
      button.addEventListener("click", () => copyToClipboard(button));
    });
  };

  const initTestConnection = () => {
    const testBtn = document.getElementById("wpsl-test-connection-btn");
    if (testBtn) {
      testBtn.addEventListener("click", testGoogleConnection);
    }
  };

  const initFormSubmissions = () => {
    document.querySelectorAll(".wpsl-form").forEach((form) => {
      form.addEventListener("submit", (e) => {
        const submitBtn = e.submitter;
        if (submitBtn) {
          submitBtn.classList.add("loading");
          submitBtn.disabled = true;
        }
      });
    });
  };

  const initConditionalFields = () => {
    const allowSignupsToggle = document.getElementById("wpsl-allow-signups");
    const roleSection = document.getElementById("wpsl-signup-role-section");

    if (allowSignupsToggle && roleSection) {
      allowSignupsToggle.addEventListener("change", () => {
        roleSection.style.display = allowSignupsToggle.checked
          ? "block"
          : "none";
      });
    }
  };

  const initSecurityCards = () => {
    document
      .querySelectorAll(".wpsl-security-card .wpsl-toggle-input")
      .forEach((checkbox) => {
        checkbox.addEventListener("change", (e) => {
          e.target
            .closest(".wpsl-security-card")
            .classList.toggle("enabled", e.target.checked);
        });
      });
  };

  // Functions
  const addUser = () => {
    const userList = document.getElementById("user-list");
    const template = wpsl_admin.user_template.replace(
      /__INDEX__/g,
      userIndex++
    );
    userList.insertAdjacentHTML("beforeend", template);
    userList.lastElementChild.querySelector('input[type="email"]').focus();
  };

  const removeUser = (button) => {
    if (confirm(wpsl_admin.strings.confirm_remove_user)) {
      const userItem = button.closest(".wpsl-user-item");
      userItem.remove();
      const userList = document.getElementById("user-list");
      if (userList.children.length === 0) {
        addUser();
      }
    }
  };

  const testGoogleConnection = async (e) => {
    const button = e.target;
    const originalText = button.textContent;
    const clientId = document.getElementById("client_id")?.value;
    const clientSecret = document.getElementById("client_secret")?.value;

    if (!clientId || !clientSecret) {
      return showNotification(wpsl_admin.strings.fill_both_fields, "error");
    }

    button.innerHTML = `<span class="wpsl-loading"></span> ${wpsl_admin.strings.testing}`;
    button.disabled = true;

    try {
      const response = await fetch(wpsl_admin.ajax_url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "wpsl_test_connection",
          nonce: wpsl_admin.nonce,
          client_id: clientId,
          client_secret: clientSecret,
        }),
      });
      const data = await response.json();
      if (data.success) {
        showNotification(data.data.message, "success");
      } else {
        throw new Error(data.data);
      }
    } catch (error) {
      showNotification(
        `${wpsl_admin.strings.connection_failed}: ${error.message}`,
        "error"
      );
    } finally {
      button.innerHTML = originalText;
      button.disabled = false;
    }
  };

  const copyToClipboard = async (button) => {
    const input = button.previousElementSibling;
    const originalText = button.textContent;
    try {
      await navigator.clipboard.writeText(input.value);
      button.textContent = wpsl_admin.strings.copied;
      button.classList.add("success");
    } catch (err) {
      showNotification(wpsl_admin.strings.copy_failed, "error");
    } finally {
      setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove("success");
      }, 2000);
    }
  };

  const showNotification = (message, type = "info") => {
    document.querySelectorAll(".wpsl-notification").forEach((n) => n.remove());

    const notification = document.createElement("div");
    notification.className = `wpsl-notification wpsl-notification-${type}`;
    const iconMap = { success: "✓", error: "✗", warning: "!", info: "ℹ" };

    notification.innerHTML = `
      <span class="wpsl-notification-icon">${iconMap[type]}</span>
      <span class="wpsl-notification-message">${message}</span>
      <button class="wpsl-notification-close">&times;</button>
    `;

    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add("show"), 10);

    const removeNotif = () => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 400);
    };

    const timer = setTimeout(removeNotif, 5000);
    notification
      .querySelector(".wpsl-notification-close")
      .addEventListener("click", () => {
        clearTimeout(timer);
        removeNotif();
      });
  };

  // Run it
  init();
});
