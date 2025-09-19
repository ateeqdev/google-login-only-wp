document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  // Global state
  let userIndex = esl_admin.initial_user_count || 0;

  // Initializers
  const init = () => {
    initUserManagement();
    initCopyButtons();
    initTestConnection();
    initFormSubmissions();
    initConditionalFields();
    initSecurityCards();

    if (new URLSearchParams(window.location.search).has("settings-updated")) {
      showNotification(esl_admin.strings.saved, "success");
    }
  };

  const initUserManagement = () => {
    const userList = document.getElementById("user-list");
    const addUserBtn = document.getElementById("esl-add-user-btn");
    if (!userList || !addUserBtn) return;

    addUserBtn.addEventListener("click", addUser);

    userList.addEventListener("click", (e) => {
      const removeBtn = e.target.closest(".esl-remove-user");
      if (removeBtn) {
        removeUser(removeBtn);
      }
    });
  };

  const initCopyButtons = () => {
    document.querySelectorAll(".esl-copy-btn").forEach((button) => {
      button.addEventListener("click", () => copyToClipboard(button));
    });
  };

  const initTestConnection = () => {
    const testBtn = document.getElementById("esl-test-connection-btn");
    if (testBtn) {
      testBtn.addEventListener("click", testGoogleConnection);
    }
  };

  const initFormSubmissions = () => {
    document.querySelectorAll(".esl-form").forEach((form) => {
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
    const allowSignupsToggle = document.getElementById("esl-allow-signups");
    const roleSection = document.getElementById("esl-signup-role-section");

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
      .querySelectorAll(".esl-security-card .esl-toggle-input")
      .forEach((checkbox) => {
        checkbox.addEventListener("change", (e) => {
          e.target
            .closest(".esl-security-card")
            .classList.toggle("enabled", e.target.checked);
        });
      });
  };

  // Functions
  const addUser = () => {
    const userList = document.getElementById("user-list");
    const template = esl_admin.user_template.replace(
      /__INDEX__/g,
      userIndex++
    );
    userList.insertAdjacentHTML("beforeend", template);
    userList.lastElementChild.querySelector('input[type="email"]').focus();
  };

  const removeUser = (button) => {
    if (confirm(esl_admin.strings.confirm_remove_user)) {
      const userItem = button.closest(".esl-user-item");
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
      return showNotification(esl_admin.strings.fill_both_fields, "error");
    }

    button.innerHTML = `<span class="esl-loading"></span> ${esl_admin.strings.testing}`;
    button.disabled = true;

    try {
      const response = await fetch(esl_admin.ajax_url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "esl_test_connection",
          nonce: esl_admin.nonce,
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
        `${esl_admin.strings.connection_failed}: ${error.message}`,
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
      button.textContent = esl_admin.strings.copied;
      button.classList.add("success");
    } catch (err) {
      showNotification(esl_admin.strings.copy_failed, "error");
    } finally {
      setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove("success");
      }, 2000);
    }
  };

  const showNotification = (message, type = "info") => {
    document.querySelectorAll(".esl-notification").forEach((n) => n.remove());

    const notification = document.createElement("div");
    notification.className = `esl-notification esl-notification-${type}`;
    const iconMap = { success: "✓", error: "✗", warning: "!", info: "ℹ" };

    notification.innerHTML = `
      <span class="esl-notification-icon">${iconMap[type]}</span>
      <span class="esl-notification-message">${message}</span>
      <button class="esl-notification-close">&times;</button>
    `;

    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add("show"), 10);

    const removeNotif = () => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 400);
    };

    const timer = setTimeout(removeNotif, 5000);
    notification
      .querySelector(".esl-notification-close")
      .addEventListener("click", () => {
        clearTimeout(timer);
        removeNotif();
      });
  };

  // Run it
  init();
});
