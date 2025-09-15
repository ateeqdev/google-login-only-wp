document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const loginBtn = document.getElementById("google-login-btn");
  if (loginBtn) {
    loginBtn.addEventListener("click", function (e) {
      this.classList.add("loading");
      this.style.pointerEvents = "none";
      setTimeout(() => {
        this.classList.remove("loading");
        this.style.pointerEvents = "auto";
      }, 5000); // Failsafe
    });
  }
});

/**
 * Handles the credential response from Google and submits it to the backend.
 * This function must be global as it's called by the Google GSI client library.
 * @param {object} response - The credential response object from Google.
 */
function handleCredentialResponse(response) {
  showAuthenticationLoading();

  const form = document.createElement("form");
  form.method = "POST";
  form.action = otl_login_params.callback_url;
  form.style.display = "none";

  const createInput = (name, value) => {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    return input;
  };

  form.appendChild(createInput("credential", response.credential));
  form.appendChild(
    createInput("otl_csrf_token", otl_login_params.csrf_token)
  );
  form.appendChild(createInput("nonce", otl_login_params.nonce));

  document.body.appendChild(form);
  form.submit();
}

function showAuthenticationLoading() {
  let overlay = document.getElementById("otl-auth-loading");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "otl-auth-loading";
    overlay.innerHTML = `
      <div class="otl-auth-content">
        <div class="otl-spinner"></div>
        <p>${otl_login_params.authenticating}</p>
      </div>
    `;
    document.body.appendChild(overlay);
  }
  overlay.classList.add("show");
}

window.onload = function () {
  if (
    typeof google === "undefined" ||
    !google.accounts ||
    typeof otl_login_params === "undefined"
  ) {
    return;
  }

  google.accounts.id.initialize({
    client_id: otl_login_params.client_id,
    callback: handleCredentialResponse,
    auto_select: false,
    cancel_on_tap_outside: true,
    context: otl_login_params.context,
  });

  const hasLoginError =
    document.getElementById("login_error") !== null ||
    window.location.search.includes("otl_error_key");

  if (otl_login_params.show_prompt && !hasLoginError) {
    google.accounts.id.prompt((notification) => {
      if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
        console.log(
          "OTL: Google One Tap prompt was not displayed or was skipped."
        );
      }
    });
  }
};
