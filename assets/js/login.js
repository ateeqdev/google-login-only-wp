document.addEventListener("DOMContentLoaded", function () {
  const loginBtn = document.getElementById("google-login-btn");
  if (loginBtn) {
    loginBtn.addEventListener("click", function () {
      this.classList.add("loading");
      this.style.pointerEvents = "none";
      // Re-enable after a timeout in case redirection fails
      setTimeout(() => {
        this.classList.remove("loading");
        this.style.pointerEvents = "auto";
      }, 5000);
    });
  }
});

/**
 * Handles the credential response from Google and submits it to the backend.
 * This function must be global as it's called by the Google GSI client library.
 * @param {object} response - The credential response object from Google.
 */
function handleCredentialResponse(response) {
  // Show loading indicator
  showAuthenticationLoading();

  const form = document.createElement("form");
  form.method = "POST";
  form.action = glo_login_params.callback_url;

  const credentialInput = document.createElement("input");
  credentialInput.type = "hidden";
  credentialInput.name = "credential";
  credentialInput.value = response.credential;

  const csrfInput = document.createElement("input");
  csrfInput.type = "hidden";
  csrfInput.name = "glo_csrf_token";
  csrfInput.value = glo_login_params.csrf_token;

  const nonceInput = document.createElement("input");
  nonceInput.type = "hidden";
  nonceInput.name = "nonce";
  nonceInput.value = glo_login_params.nonce;

  form.appendChild(credentialInput);
  form.appendChild(csrfInput);
  form.appendChild(nonceInput);
  document.body.appendChild(form);
  form.submit();
}

/**
 * Show loading indicator during authentication
 */
function showAuthenticationLoading() {
  const overlay = document.createElement("div");
  overlay.id = "glo-auth-loading";

  overlay.innerHTML = `
    <div class="loading-content">
      <div class="spinner"></div>
      <p>${glo_login_params.authenticating}</p>
    </div>
  `;

  document.body.appendChild(overlay);

  setTimeout(() => {
    const loadingElement = document.getElementById("glo-auth-loading");
    if (loadingElement) {
      loadingElement.remove();
    }
  }, 10000);
}
window.addEventListener("load", function () {
  // Check if Google's library and our localized parameters are available.
  if (
    typeof google !== "undefined" &&
    google.accounts &&
    typeof glo_login_params !== "undefined"
  ) {
    google.accounts.id.initialize({
      client_id: glo_login_params.client_id,
      callback: handleCredentialResponse,
      auto_select: false,
      cancel_on_tap_outside: true,
      context: glo_login_params.context, // 'signin' or 'use'
    });

    // Only show One Tap prompt if there are no login errors on the page
    const hasLoginError =
      window.location.search.includes("login_error") ||
      document.getElementById("login_error") !== null;

    if (glo_login_params.show_prompt && !hasLoginError) {
      // Delay prompt for better user experience
      const promptDelay = glo_login_params.context === "signin" ? 1000 : 2000;
      setTimeout(() => {
        google.accounts.id.prompt((notification) => {
          if (notification.isNotDisplayed()) {
            console.log(
              glo_login_params.one_tap_not_displayed,
              notification.getNotDisplayedReason()
            );
          } else if (notification.isSkippedMoment()) {
            console.log(
              glo_login_params.one_tap_skipped,
              notification.getSkippedReason()
            );
          }
        });
      }, promptDelay);
    }
  }
});
